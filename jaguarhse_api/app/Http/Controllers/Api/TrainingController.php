<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HseAccess;
use App\Services\HseData;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TrainingController extends Controller
{
    private const CATALOGS = ['tema_capacitacion', 'lugar_capacitacion', 'capacitador', 'empresa_capacitacion'];

    public function __construct(private HseAccess $access, private HseData $data) {}

    private function context(Request $request, string $action): object
    {
        $input = $request->validate(['empresa_id' => 'required|integer|min:1']);
        $context = $request->attributes->get('hse');
        abort_unless((int) $input['empresa_id'] === (int) $context->empresa_id, 403, 'La empresa no corresponde a la sesión.');
        $this->access->authorize($context, 'capacitaciones', $action);

        return $context;
    }

    private function query(string $resource, object $context): Builder
    {
        return DB::table($resource)->where('empresa_id', $context->empresa_id);
    }

    private function find(string $resource, object $context, int $id): object
    {
        $row = $this->query($resource, $context)->where('id', $id)->first();
        abort_unless($row, 404, 'Registro no encontrado.');

        return $row;
    }

    private function present(string $resource, object $row): object
    {
        if ($resource === 'capacitacion') {
            $row->trabajadores = DB::table('capacitacion_participantes')->where('empresa_id', $row->empresa_id)
                ->where('capacitacion_id', $row->id)->where('activo', true)->pluck('trabajador_id')->all();
        }

        return $row;
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        $context = $this->context($request, 'ver');
        $input = $request->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'page' => 'sometimes|integer|min:1', 'activo' => 'sometimes|boolean']);
        $query = $this->query($resource, $context);
        if (array_key_exists('activo', $input)) {
            $query->where('activo', $input['activo']);
        }
        $page = $query->orderByDesc('id')->paginate($input['per_page'] ?? 25)->withQueryString();
        $page->through(fn ($row) => $this->present($resource, $row));

        return response()->json($page);
    }

    public function show(Request $request, string $resource, int $id): JsonResponse
    {
        $context = $this->context($request, 'ver');

        return response()->json(['data' => $this->present($resource, $this->find($resource, $context, $id))]);
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
        $context = $this->context($request, $id ? 'editar' : 'crear');
        if ($resource === 'capacitacion') {
            $request->validate(['numero_planilla' => 'missing']);
        }

        return DB::transaction(function () use ($request, $resource, $id, $context): JsonResponse {
            $this->data->lock($context);
            $before = $id ? $this->find($resource, $context, $id) : null;
            abort_if($before && ! $before->activo, 422, 'El registro está inactivo.');
            $input = array_replace($before ? (array) $before : [], $request->all());
            $exists = fn (string $table, string $column = 'id') => Rule::exists($table, $column)->where('empresa_id', $context->empresa_id)->where('activo', true);
            if ($resource === 'capacitacion') {
                if ($id && ! $request->exists('trabajadores')) {
                    $input['trabajadores'] = $this->present($resource, clone $before)->trabajadores;
                }
                $rules = [
                    'fecha' => 'required|date_format:Y-m-d',
                    'hora_inicio' => 'required|date_format:H:i',
                    'hora_termino' => 'required|date_format:H:i|after:hora_inicio',
                    'responsable_registro_id' => ['required', 'integer', $exists('miembros_empresa', 'usuario_id'), Rule::exists('usuarios', 'id')->where('activo', true)],
                    'observaciones' => 'nullable|string|max:10000',
                    'trabajadores' => 'sometimes|array|max:1000',
                    'trabajadores.*' => ['required', 'integer', 'distinct', $exists('trabajadores')],
                ];
                foreach (self::CATALOGS as $catalog) {
                    $rules[$catalog.'_id'] = ['required', 'integer', $exists($catalog)];
                }
                foreach (['hora_inicio', 'hora_termino'] as $field) {
                    if (! $request->exists($field) && $before) {
                        $input[$field] = substr($before->$field, 0, 5);
                    }
                }
            } else {
                $rules = ['nombre' => 'required|string|max:200', 'descripcion' => 'nullable|string|max:10000'];
                if ($resource === 'lugar_capacitacion') {
                    $rules['direccion'] = 'nullable|string|max:255';
                }
                if (in_array($resource, ['capacitador', 'empresa_capacitacion'], true)) {
                    $rules += ['numero_documento' => 'nullable|string|max:30', 'email' => 'nullable|email|max:200', 'telefono' => 'nullable|string|max:30'];
                }
            }
            $values = Validator::make($input, $rules)->validate();
            $workers = $values['trabajadores'] ?? [];
            unset($values['trabajadores']);
            $values['empresa_id'] = $context->empresa_id;
            $values['updated_at'] = now();
            if ($id) {
                $this->query($resource, $context)->where('id', $id)->update($values);
            } else {
                if ($resource === 'capacitacion') {
                    $values['numero_planilla'] = (int) $this->query($resource, $context)->max('numero_planilla') + 1;
                }
                $id = DB::table($resource)->insertGetId($values);
            }
            if ($resource === 'capacitacion') {
                DB::table('capacitacion_participantes')->where('empresa_id', $context->empresa_id)->where('capacitacion_id', $id)->update(['activo' => false, 'updated_at' => now()]);
                foreach ($workers as $worker) {
                    DB::table('capacitacion_participantes')->updateOrInsert(
                        ['empresa_id' => $context->empresa_id, 'capacitacion_id' => $id, 'trabajador_id' => $worker],
                        ['activo' => true, 'updated_at' => now()]
                    );
                }
            }
            $after = $this->present($resource, $this->find($resource, $context, $id));
            $this->data->audit($request, $resource, $before, $after, $before ? 'editar' : 'crear');

            return response()->json(['data' => $after], $before ? 200 : 201);
        });
    }

    public function destroy(Request $request, string $resource, int $id): JsonResponse
    {
        $context = $this->context($request, 'eliminar');

        return DB::transaction(function () use ($request, $resource, $id, $context): JsonResponse {
            $this->data->lock($context);
            $before = $this->find($resource, $context, $id);
            if (in_array($resource, self::CATALOGS, true)) {
                abort_if($this->query('capacitacion', $context)->where($resource.'_id', $id)->where('activo', true)->exists(), 422, 'El registro está en uso en una capacitación activa.');
            }
            $this->query($resource, $context)->where('id', $id)->update(['activo' => false, 'updated_at' => now()]);
            if ($resource === 'capacitacion') {
                DB::table('capacitacion_participantes')->where('empresa_id', $context->empresa_id)->where('capacitacion_id', $id)->update(['activo' => false, 'updated_at' => now()]);
            }
            $after = $this->find($resource, $context, $id);
            $this->data->audit($request, $resource, $before, $after, 'eliminar');

            return response()->json(['data' => $after]);
        });
    }
}
