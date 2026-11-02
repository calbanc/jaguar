<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EppMovementReport;
use App\Services\ExcelExport;
use App\Services\HseAccess;
use App\Services\HseData;
use App\Services\TableFilters;
use App\Services\WorkerAssignments;
use App\Services\WorkerSignatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ResourceController extends Controller
{
    public function __construct(private HseData $data, private HseAccess $access) {}

    private function authorize(Request $request, string $resource, string $action): object
    {
        $module = config('hse.resources.'.$resource);
        abort_unless($module, 404);
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, $module, $action);

        return $context;
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        $context = $this->authorize($request, $resource, 'ver');
        $input = $request->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'page' => 'sometimes|integer|min:1', 'filter' => 'sometimes|array']);
        $table = $resource === 'epp_movimientos' ? 'v_epp_movimiento_detalle' : $resource;
        $query = $resource === 'epp_movimientos' ? app(EppMovementReport::class)->query($context, $request) : $this->data->query($resource, $context);
        foreach ($input['filter'] ?? [] as $field => $value) {
            abort_unless(isset($this->data->schema($resource)['fields'][$field]) && is_scalar($value), 422, 'Filtro inválido.');
            if ($resource === 'trabajadores' && app(WorkerAssignments::class)->locationFilter($query, $field, (string) $value, exact: true)) {
                continue;
            }
            $query->where($field, $value);
        }
        if ($resource === 'trabajadores') {
            app(WorkerAssignments::class)->filterSelection($query, $request);
        }
        app(TableFilters::class)->apply($query, $table, $request);
        $page = $query->orderByDesc('id')->paginate($input['per_page'] ?? 25);
        $page->through(fn ($row) => $resource === 'epp_movimientos' ? app(EppMovementReport::class)->present($row, $context) : $this->data->present($resource, $row));

        return response()->json($page->toArray() + ['columns' => app(TableFilters::class)->columns($table)]);
    }

    public function show(Request $request, string $resource, int $id): JsonResponse
    {
        $context = $this->authorize($request, $resource, 'ver');

        if ($resource === 'epp_movimientos') {
            $row = app(EppMovementReport::class)->query($context, $request)->where('id', $id)->first();
            abort_unless($row, 404);

            return response()->json(['data' => app(EppMovementReport::class)->present($row, $context)]);
        }

        return response()->json(['data' => $this->data->present($resource, $this->data->find($resource, $context, $id))]);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        return $this->write($request, $resource);
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        return $this->write($request, $resource, $id);
    }

    private function write(Request $request, string $resource, ?int $id = null): JsonResponse
    {
        $context = $this->authorize($request, $resource, $id ? 'editar' : 'crear');
        abort_if(in_array($resource, config('hse.read_only'), true), 405, 'Use el flujo específico de este recurso.');

        $signatures = app(WorkerSignatures::class);

        return $signatures->transaction(function () use ($request, $resource, $id, $context, $signatures) {
            $this->data->lock($context);
            $before = $id ? $this->data->find($resource, $context, $id) : null;
            $input = $request->all();
            $initialPeriod = null;
            if ($resource === 'trabajadores' && ! $id && array_key_exists('asignacion_inicial', $input)) {
                $validated = $request->validate([
                    'asignacion_inicial' => 'required|array:fecha_inicio,fecha_fin',
                    'asignacion_inicial.fecha_inicio' => 'required|date_format:Y-m-d',
                    'asignacion_inicial.fecha_fin' => 'nullable|date_format:Y-m-d|after_or_equal:asignacion_inicial.fecha_inicio',
                ]);
                $initialPeriod = $validated['asignacion_inicial'];
                unset($input['asignacion_inicial']);
            }
            if ($id && array_key_exists('activo', $input) && ! $request->boolean('activo')) {
                $this->access->authorize($context, config('hse.resources.'.$resource), 'eliminar');
            }
            if (! $id && isset($input['uuid_cliente'])) {
                $prior = $this->data->query($resource, $context)->where('uuid_cliente', $input['uuid_cliente'])->first();
                if ($prior) {
                    foreach ($input as $field => $value) {
                        abort_unless(property_exists($prior, $field) && $value == $prior->$field, 409, 'UUID reutilizado con datos diferentes.');
                    }

                    return response()->json(['data' => $prior]);
                }
            }
            $server = [];
            if ($resource === 'documento_versiones') {
                $request->validate(['requisito_id' => 'required|integer']);
                $server['version'] = 1 + $this->data->query($resource, $context)->where('requisito_id', $input['requisito_id'])->max('version');
            }
            $values = $this->data->validate($resource, $input, $context, $before, $server);
            if ($resource === 'trabajadores' && $before) {
                $values = $signatures->relocate($before, $values);
            }
            $after = $id ? $this->data->update($resource, $context, $id, $values) : $this->data->insert($resource, $context, $values);
            if ($resource === 'trabajadores' && ! $id) {
                app(WorkerAssignments::class)->initial($request, $after, $initialPeriod);
            }
            $this->data->audit($request, $resource, $before, $after, $id ? 'editar' : 'crear');

            return response()->json(['data' => $this->data->present($resource, $after)], $id ? 200 : 201);
        });
    }

    public function export(Request $request, string $resource, ExcelExport $excel): BinaryFileResponse
    {
        $reports = ['reportes-temas' => ['v_estado_temas', 'gestion_sst'],
            'reportes-requisitos' => ['v_estado_requisitos', 'documentos'],
            'reportes-epp-trabajadores' => ['v_epp_costo_trabajador', 'epp'], 'reportes-stock' => ['v_epp_stock', 'almacenes'], 'reportes-indicadores' => ['v_indicador_resultados', 'indicadores']];
        [$table, $module] = $reports[$resource] ?? [$resource, config('hse.resources.'.$resource)];
        abort_unless($module, 404);
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, $module, 'ver');
        $this->access->authorize($context, $module, 'descargar');
        $input = $request->validate(['q' => 'nullable|string|max:150', 'estado' => 'nullable|string|max:40']);
        if ($resource === 'epp_movimientos') {
            $table = 'v_epp_movimiento_detalle';
        }
        $query = $resource === 'epp_movimientos' ? app(EppMovementReport::class)->query($context, $request) : $this->data->query($table, $context);
        if ($resource === 'trabajadores') {
            app(WorkerAssignments::class)->filterSelection($query, $request);
        }
        app(TableFilters::class)->apply($query, $table, $request);
        if (! empty($input['estado']) && isset($this->data->schema($resource)['fields']['estado'])) {
            $query->where('estado', $input['estado']);
        }
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET time_zone = ?', [now($context->zona_horaria)->format('P')]);
        }
        try {
            $rows = $query->get()->map(fn ($row) => $resource === 'epp_movimientos' ? app(EppMovementReport::class)->present($row, $context) : (isset($reports[$resource]) ? $row : $this->data->present($resource, $row)));
        } finally {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("SET time_zone = '+00:00'");
            }
        }
        $keys = $rows->isNotEmpty() ? array_keys((array) $rows->first()) : ['resultado'];
        $keys = match ($resource) {
            'almacenes' => ['nombre', 'sede_nombre', 'activo'],
            'trabajadores' => ['tipo_documento', 'numero_documento', 'nombres', 'apellidos', 'puesto', 'fecha_ingreso', 'fecha_cese', 'sede_nombre', 'zona_nombre', 'area_nombre', 'pais', 'asignacion_inicio', 'asignacion_fin', 'fecha_ubicacion', 'firma_almacenada', 'activo'],
            'sedes' => ['codigo', 'nombre', 'pais', 'direccion', 'activo'],
            'zonas' => ['nombre', 'sede_nombre', 'pais', 'activo'],
            'areas' => ['nombre', 'zona_nombre', 'sede_nombre', 'pais', 'activo'],
            default => array_values(array_diff($keys, ['empresa_id', 'uuid_cliente'])),
        };
        $headers = array_map(fn ($key) => ['costo_unitario_usd' => 'Costo unitario USD referencial', 'costo_total_usd' => 'Costo total USD referencial', 'tipo_cambio_consulta' => 'Tipo de cambio de consulta (PEN por USD)', 'costo_entregado' => 'Costo entregado (PEN)', 'costo_devuelto' => 'Costo devuelto (PEN)', 'costo_neto' => 'Costo neto (PEN)', 'costo_total' => 'Costo total (PEN)', 'costo_unitario' => 'Costo unitario (PEN)', 'costo_total_original' => 'Total en moneda de compra', 'costo_unitario_original' => 'Costo unitario en moneda de compra', 'tipo_cambio' => 'Tipo de cambio a PEN'][$key] ?? ucfirst(str_replace('_', ' ', $key)), $keys);
        $values = $rows->map(fn ($row) => array_map(fn ($key) => is_scalar($row->$key ?? null) ? $row->$key : (($row->$key ?? null) === null ? '' : json_encode($row->$key, JSON_UNESCAPED_UNICODE)), $keys));
        $path = $excel->create(ucfirst(str_replace('_', ' ', $resource)), $headers, $values);

        return response()->download($path, $resource.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store'])->deleteFileAfterSend(true);
    }

    public function deactivate(Request $request, string $resource, int $id): JsonResponse
    {
        $context = $this->authorize($request, $resource, 'eliminar');
        abort_if(in_array($resource, config('hse.read_only'), true), 405);
        abort_unless(isset($this->data->schema($resource)['fields']['activo']), 405, 'Este recurso conserva su historial y no admite eliminación.');

        return DB::transaction(function () use ($request, $resource, $id, $context) {
            $this->data->lock($context);
            $before = $this->data->find($resource, $context, $id);
            $after = $this->data->update($resource, $context, $id, ['activo' => false]);
            $this->data->audit($request, $resource, $before, $after, 'desactivar');

            return response()->json(['data' => $after]);
        });
    }
}
