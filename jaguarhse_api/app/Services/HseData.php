<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HseData
{
    private ?array $metadata = null;

    public function schema(string $table): array
    {
        $this->metadata ??= json_decode(file_get_contents(database_path('schema/metadata.json')), true, flags: JSON_THROW_ON_ERROR);
        abort_unless(isset($this->metadata[$table]), 404);

        return $this->metadata[$table];
    }

    public function query(string $table, object $context): Builder
    {
        return DB::table($table)->where('empresa_id', $context->empresa_id);
    }

    public function find(string $table, object $context, int $id): object
    {
        $record = $this->query($table, $context)->where('id', $id)->first();
        abort_unless($record, 404, 'Registro no encontrado.');

        return $record;
    }

    public function lock(object $context): void
    {
        DB::table('empresas')->where('id', $context->empresa_id)->lockForUpdate()->first();
    }

    public function validate(string $table, array $input, object $context, ?object $existing = null, array $server = []): array
    {
        $schema = $this->schema($table);
        $protected = ['id', 'empresa_id', 'created_at', 'updated_at', 'revisado_por', 'revisado_at',
            'comentario_revision', 'cerrado_por', 'cerrado_at', 'completada_at', 'recibido_at'];
        $actors = ['reportado_por', 'realizado_por', 'entregado_por', 'registrado_por', 'autor_id'];
        $protected = array_merge($protected, $actors, array_keys($server));
        if ($table === 'trabajadores') {
            $protected = array_merge($protected, ['firma_almacenada', 'firma_ruta', 'firma_bytes', 'firma_actualizada_at']);
        }
        if (array_key_exists('estado', $schema['fields'])) {
            $protected[] = 'estado';
        }
        if ($table === 'trabajadores' && $existing && (array_key_exists('zona_id', $input) || array_key_exists('area_id', $input))) {
            throw ValidationException::withMessages(['zona_id' => 'Usa el historial de sedes para registrar o corregir una asignación por fechas.']);
        }
        foreach (array_keys($input) as $field) {
            if (! isset($schema['fields'][$field]) || in_array($field, $protected, true)) {
                throw ValidationException::withMessages([$field => 'Campo desconocido o administrado por el servidor.']);
            }
        }
        $rules = [];
        foreach ($schema['fields'] as $field => $definition) {
            if (in_array($field, $protected, true)) {
                continue;
            }
            $rules[$field] = [$existing ? 'sometimes' : ($definition['nullable'] || $definition['default'] ? 'sometimes' : 'required')];
            if ($definition['nullable']) {
                $rules[$field][] = 'nullable';
            } else {
                $rules[$field][] = 'required';
            }
            $rules[$field][] = match ($definition['type']) {
                'BIGINT', 'INT' => 'integer', 'BOOLEAN' => 'boolean', 'DECIMAL' => 'numeric',
                'DATE' => 'date_format:Y-m-d', 'DATETIME' => 'date_format:Y-m-d H:i:s',
                'JSON' => 'array', default => 'string',
            };
            if ($definition['type'] === 'DECIMAL' && preg_match('/DECIMAL\(\d+,(\d+)\)/', $definition['definition'], $precision)) {
                $rules[$field][] = 'decimal:0,'.$precision[1];
            }
            if (isset($definition['max'])) {
                $rules[$field][] = 'max:'.$definition['max'];
            }
            if (isset($definition['values'])) {
                $rules[$field][] = Rule::in($definition['values']);
            }
            if (str_contains($definition['definition'], 'UNSIGNED')) {
                $rules[$field][] = 'min:0';
            }
            if ($field === 'uuid_cliente') {
                $rules[$field][] = 'uuid';
            }
        }
        if ($table === 'sedes') {
            $rules['pais'][] = Rule::in(config('hse.country_codes'));
        }
        if ($table === 'areas' && isset($input['coordenadas'])) {
            $rules['coordenadas'] = ['array:type,coordinates'];
            $rules['coordenadas.type'] = ['required', Rule::in(['Polygon'])];
            $rules['coordenadas.coordinates'] = 'required|array|size:1';
            $rules['coordenadas.coordinates.0'] = 'required|array|min:3|max:500';
            $rules['coordenadas.coordinates.0.*'] = 'required|array|size:2';
            $rules['coordenadas.coordinates.0.*.0'] = 'required|numeric|between:-180,180';
            $rules['coordenadas.coordinates.0.*.1'] = 'required|numeric|between:-90,90';
        }
        if ($table === 'epp_catalogo') {
            $rules['unidad'][] = Rule::in(config('hse.equipment_units'));
        }
        $data = Validator::make($input, $rules, ['unidad.in' => 'Selecciona una unidad de medida del listado permitido.'])->validate();
        if ($table === 'areas' && isset($data['coordenadas'])) {
            $data['coordenadas'] = app(SafetyImplements::class)->polygon($data['coordenadas']['coordinates'][0]);
        }
        foreach ($actors as $field) {
            if (isset($schema['fields'][$field]) && ! $existing) {
                $data[$field] = $context->miembro_id;
            }
        }
        $data = array_merge($data, $server);
        $merged = array_merge((array) $existing, $data, ['empresa_id' => $context->empresa_id]);
        if ($table === 'trabajadores') {
            app(WorkerValidation::class)->validator($merged, requireZone: ! $existing)->validate();
            if ($existing) {
                app(WorkerAssignments::class)->validateEmployment($merged);
            }
        }
        foreach ($schema['foreign_keys'] as $fk) {
            if ($fk['columns'] === ['empresa_id']) {
                continue;
            }
            $query = DB::table($fk['table']);
            foreach ($fk['columns'] as $i => $field) {
                if (! isset($merged[$field])) {
                    continue 2;
                }
                $query->where($fk['references'][$i], $merged[$field]);
            }
            if ($fk['table'] === 'archivos') {
                $query->whereNull('eliminado_at');
            }
            if (! $query->exists()) {
                throw ValidationException::withMessages([end($fk['columns']) => 'Referencia inválida para esta empresa o relación.']);
            }
        }
        foreach ($schema['unique'] as $columns) {
            $query = DB::table($table);
            foreach ($columns as $field) {
                if (! isset($merged[$field])) {
                    continue 2;
                }
                $query->where($field, $merged[$field]);
            }
            if ($existing) {
                $query->where('id', '!=', $existing->id);
            }
            if ($query->exists()) {
                throw ValidationException::withMessages([end($columns) => 'Ya existe un registro con estos valores.']);
            }
        }
        foreach ($schema['fields'] as $field => $definition) {
            if ($definition['type'] === 'JSON' && isset($merged[$field]) && is_string($merged[$field])) {
                $merged[$field] = json_decode($merged[$field], true, flags: JSON_THROW_ON_ERROR);
            }
        }
        $this->businessRules($table, $merged, $context, $existing);
        foreach ($data as $field => $value) {
            if ($schema['fields'][$field]['type'] === 'JSON' && $value !== null) {
                $data[$field] = json_encode($value, JSON_THROW_ON_ERROR);
            }
        }

        return $data;
    }

    private function businessRules(string $table, array $data, object $context, ?object $existing): void
    {
        $fail = static function (string $field, string $message): never {
            throw ValidationException::withMessages([$field => $message]);
        };
        if ($table === 'areas' && $existing && $existing->zona_id != $data['zona_id'] && ($this->query('trabajadores', $context)->where('area_id', $existing->id)->exists() || $this->query('trabajador_asignaciones', $context)->where('area_id', $existing->id)->exists())) {
            $fail('zona_id', 'Esta área tiene historial de trabajadores. Crea otra área en la zona de destino para conservarlo.');
        }
        if ($table === 'zonas' && $existing && $existing->sede_id != $data['sede_id'] && $this->query('trabajador_asignaciones', $context)->where('zona_id', $existing->id)->exists()) {
            $fail('sede_id', 'Esta zona tiene historial de trabajadores. Crea otra zona en la sede de destino para conservarlo.');
        }
        foreach ([['fecha_emision', 'fecha_vencimiento'], ['fecha_ingreso', 'fecha_cese'],
            ['longitud_min', 'longitud_max'], ['numero_min', 'numero_max'], ['fecha_min', 'fecha_max'],
            ['seleccion_min', 'seleccion_max'], ['archivos_min', 'archivos_max']] as [$min, $max]) {
            if (isset($data[$min], $data[$max]) && $data[$min] > $data[$max]) {
                $fail($max, 'Debe ser mayor o igual al mínimo o fecha inicial.');
            }
        }
        foreach (['version', 'orden', 'factor', 'cantidad', 'archivo_max_bytes'] as $field) {
            if (isset($data[$field]) && $data[$field] <= 0) {
                $fail($field, 'Debe ser mayor que cero.');
            }
        }
        if (isset($data['peso']) && ($data['peso'] < 0 || ($table === 'requisitos_documentales' && $data['peso'] == 0))) {
            $fail('peso', 'Peso inválido.');
        }
        if ($table === 'temas_hse' && isset($data['aplica']) && ! $data['aplica'] && empty(trim($data['motivo_no_aplica'] ?? ''))) {
            $fail('motivo_no_aplica', 'Indique el motivo.');
        }
        if (in_array($table, ['checklist_plantillas', 'checklist_secciones', 'checklist_preguntas', 'checklist_pregunta_opciones'], true)) {
            $templateId = $table === 'checklist_plantillas' ? ($existing?->id) : ($data['plantilla_id'] ?? null);
            if ($table === 'checklist_pregunta_opciones') {
                $question = $this->find('checklist_preguntas', $context, $data['pregunta_id']);
                $templateId = $question->plantilla_id;
                abort_unless(in_array($question->tipo, ['selector', 'seleccion_multiple']), 422, 'Este tipo no admite opciones.');
            }
            if ($templateId) {
                abort_unless($this->find('checklist_plantillas', $context, $templateId)->estado === 'borrador', 409, 'La plantilla publicada es inmutable.');
            }
            if ($existing && isset($existing->plantilla_id)) {
                abort_unless($existing->plantilla_id == ($data['plantilla_id'] ?? $existing->plantilla_id), 422, 'No se puede mover a otra plantilla.');
            }
            if ($table === 'checklist_pregunta_opciones' && $existing) {
                abort_unless($existing->pregunta_id == $data['pregunta_id'], 422, 'No se puede mover una opción.');
            }
            if ($table === 'checklist_preguntas') {
                app(ChecklistService::class)->validateQuestion($data);
            }
        }
        if ($table === 'checklist_registros') {
            abort_unless($this->find('checklist_plantillas', $context, $data['plantilla_id'])->estado === 'publicado', 409, 'La plantilla no está publicada.');
            if ($existing) {
                abort_unless($existing->estado === 'borrador' && $existing->plantilla_id == $data['plantilla_id'], 409, 'Registro inmutable.');
            }
            foreach (['latitud' => 90, 'longitud' => 180] as $field => $limit) {
                if (isset($data[$field]) && abs($data[$field]) > $limit) {
                    $fail($field, 'Coordenada inválida.');
                }
            }
        }
        if ($table === 'epp_entregas' && $existing) {
            abort_unless($existing->estado === 'borrador', 409, 'Entrega inmutable.');
        }
        if ($table === 'epp_entrega_detalles') {
            abort_unless($this->find('epp_entregas', $context, $data['entrega_id'])->estado === 'borrador', 409, 'Entrega inmutable.');
            if ($existing) {
                abort_unless($existing->entrega_id == $data['entrega_id'], 422, 'No se puede mover un detalle.');
            }
        }
        if ($table === 'documento_versiones' && $existing) {
            abort(409, 'Las versiones se conservan; cree una nueva versión.');
        }
        if ($table === 'hallazgos' && $existing) {
            abort_if(in_array($existing->estado, ['cerrado', 'anulado']), 409, 'Hallazgo finalizado.');
        }
        if (in_array($table, ['hallazgo_seguimientos', 'hallazgo_evidencias', 'acciones_correctivas'])) {
            abort_if(in_array($this->find('hallazgos', $context, $data['hallazgo_id'])->estado, ['cerrado', 'anulado']), 409, 'Hallazgo finalizado.');
            if ($existing && $existing->hallazgo_id != $data['hallazgo_id']) {
                $fail('hallazgo_id', 'No se puede mover el registro.');
            }
        }
        if ($table === 'acciones_correctivas' && $existing) {
            abort_if(in_array($existing->estado, ['completada', 'anulada']), 409, 'Acción finalizada.');
        }
        if ($table === 'hallazgo_seguimientos' && ! empty($data['estado_resultante'])) {
            $fail('estado_resultante', 'Use la acción de transición del hallazgo.');
        }
        if ($table === 'indicadores' && $existing && $existing->tipo_calculo !== $data['tipo_calculo'] && $this->query('indicador_mediciones', $context)->where('indicador_id', $existing->id)->exists()) {
            $fail('tipo_calculo', 'Un indicador con mediciones conserva su tipo.');
        }
        if ($table === 'indicador_mediciones') {
            $indicator = $this->find('indicadores', $context, $data['indicador_id']);
            if (substr($data['periodo'], -2) !== '01') {
                $fail('periodo', 'Use el primer día del mes.');
            }
            $manual = isset($data['valor_manual']) && ! isset($data['numerador']) && ! isset($data['denominador']);
            $ratio = ! isset($data['valor_manual']) && isset($data['numerador'], $data['denominador']) && $data['denominador'] >= 0;
            if (($indicator->tipo_calculo === 'manual' && ! $manual) || ($indicator->tipo_calculo === 'razon' && ! $ratio)) {
                $fail('valor_manual', 'La medición debe corresponder al tipo de cálculo del indicador.');
            }
        }
    }

    public function insert(string $table, object $context, array $data): object
    {
        $id = DB::table($table)->insertGetId(array_merge($data, ['empresa_id' => $context->empresa_id, 'created_at' => now(), 'updated_at' => now()]));

        return $this->find($table, $context, $id);
    }

    public function update(string $table, object $context, int $id, array $data): object
    {
        $this->query($table, $context)->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));

        return $this->find($table, $context, $id);
    }

    public function audit(Request $request, string $table, ?object $before, object $after, string $action): void
    {
        $context = $request->attributes->get('hse');
        $this->insert('auditoria_eventos', $context, ['actor_id' => $context->miembro_id, 'accion' => $action,
            'entidad' => $table, 'registro_id' => $after->id, 'datos_antes' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'datos_despues' => json_encode($after, JSON_THROW_ON_ERROR), 'ip' => $request->ip()]);
    }

    public function present(string $table, object $record): object
    {
        $record = clone $record;
        if ($table === 'trabajadores') {
            $record = app(WorkerAssignments::class)->locate($record);
            $record->firma_almacenada = (bool) ($record->firma_almacenada ?? false);
            unset($record->firma_ruta, $record->firma_bytes);
        }
        if ($table === 'areas') {
            $zone = DB::table('zonas')->where('empresa_id', $record->empresa_id)->where('id', $record->zona_id)->first();
            $record->zona_nombre = $zone->nombre;
            $record->sede_id = $zone->sede_id;
        }
        if (in_array($table, ['almacenes', 'zonas', 'areas'], true)) {
            $site = DB::table('sedes')->where('empresa_id', $record->empresa_id)->where('id', $record->sede_id)->first();
            $record->sede_nombre = $site->nombre;
            if ($table !== 'almacenes') {
                $record->pais = $site->pais;
            }
        }
        if ($table === 'epp_compras') {
            unset($record->huella, $record->proveedor_clave, $record->clave_operacion);
        }
        if ($table === 'epp_entregas') {
            $worker = DB::table('trabajadores')->where('empresa_id', $record->empresa_id)->where('id', $record->trabajador_id)->first();
            $record->trabajador = trim(($worker->nombres ?? '').' '.($worker->apellidos ?? ''));
            $record->documento = $worker->numero_documento ?? null;
            $record->responsable = DB::table('miembros_empresa as m')->join('usuarios as u', 'u.id', '=', 'm.usuario_id')->where('m.empresa_id', $record->empresa_id)->where('m.id', $record->entregado_por)->value('u.nombre');
            $details = DB::table('epp_entrega_detalles')->where('empresa_id', $record->empresa_id)->where('entrega_id', $record->id);
            $record->costo_total = (clone $details)->whereNull('costo_total')->exists() || $record->estado === 'borrador' ? null : $details->sum('costo_total');
            $record->moneda = 'PEN';
            $record->articulos_entregados = (clone $details)->get()->map(function ($detail) use ($record) {
                $epp = DB::table('epp_catalogo')->where('empresa_id', $record->empresa_id)->where('id', $detail->epp_id)->value('nombre');
                $warehouse = DB::table('almacenes')->where('empresa_id', $record->empresa_id)->where('id', $detail->almacen_id)->value('nombre');

                return $epp.' · '.$detail->cantidad.' · '.$warehouse;
            })->implode('; ');
        }
        if (in_array($table, ['epp_movimientos', 'epp_entrega_detalles', 'epp_compra_detalles'])) {
            $record->epp = DB::table('epp_catalogo')->where('empresa_id', $record->empresa_id)->where('id', $record->epp_id)->value('nombre');
            $record->almacen = DB::table('almacenes')->where('empresa_id', $record->empresa_id)->where('id', $record->almacen_id)->value('nombre');
            $record->moneda = $table === 'epp_compra_detalles' ? DB::table('epp_compras')->where('empresa_id', $record->empresa_id)->where('id', $record->compra_id)->value('moneda') : 'PEN';
        }
        if ($table === 'epp_catalogo') {
            $record->ficha_tecnica = DB::table('archivos')->where('empresa_id', $record->empresa_id)->where('id', $record->ficha_archivo_id)->whereNull('eliminado_at')->value('nombre_original');
        }
        if ($table === 'archivos') {
            unset($record->ruta, $record->disco);
        }
        foreach ($this->schema($table)['fields'] as $field => $definition) {
            if (isset($record->$field) && $definition['type'] === 'JSON') {
                $record->$field = json_decode($record->$field, true);
            }
        }

        return $record;
    }
}
