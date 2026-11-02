<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HseAccess;
use App\Services\HseData;
use App\Services\SafetyImplements;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SafetyImplementController extends Controller
{
    public function __construct(private HseAccess $access, private HseData $data, private SafetyImplements $service) {}

    private function context(Request $request, string $action): object
    {
        $context = $request->attributes->get('hse');
        $input = $request->validate(['empresa_id' => 'required|integer|min:1']);
        abort_unless((int) $input['empresa_id'] === (int) $context->empresa_id, 403);
        $this->access->authorize($context, 'implementos', $action);

        return $context;
    }

    private function find(string $table, object $context, int $id): object
    {
        return $this->data->find($table, $context, $id);
    }

    private function reference(string $table, object $context): mixed
    {
        return Rule::exists($table, 'id')->where('empresa_id', $context->empresa_id);
    }

    private function write(Request $request, object $context, string $table, array $values, ?int $id = null): object
    {
        $before = $id ? $this->find($table, $context, $id) : null;
        if ($id) {
            DB::table($table)->where('empresa_id', $context->empresa_id)->where('id', $id)->update($values + ['updated_at' => now()]);
        } else {
            $id = DB::table($table)->insertGetId($values + ['empresa_id' => $context->empresa_id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $after = $this->find($table, $context, $id);
        $this->data->audit($request, $table, $before, $after, $before ? 'editar' : 'crear');

        return $after;
    }

    public function index(Request $request): JsonResponse
    {
        $context = $this->context($request, 'ver');
        $result = [];
        foreach (['categorias' => 'implementos_categorias', 'catalogo' => 'implementos_catalogo', 'proveedores' => 'implementos_proveedores', 'implementos' => 'implementos', 'instalaciones' => 'implemento_instalaciones', 'inspecciones' => 'implemento_inspecciones', 'costos' => 'implemento_costos'] as $key => $table) {
            $result[$key] = $this->data->query($table, $context)->orderByDesc('id')->get();
        }
        $result['areas'] = DB::table('areas as a')->join('zonas as z', 'z.id', '=', 'a.zona_id')->join('sedes as s', 's.id', '=', 'z.sede_id')
            ->where('a.empresa_id', $context->empresa_id)->where('z.empresa_id', $context->empresa_id)->where('s.empresa_id', $context->empresa_id)
            ->select('a.*', 'z.nombre as zona', 's.nombre as sede')->get()->map(function ($area) {
                $area->coordenadas = $area->coordenadas ? json_decode($area->coordenadas, true) : null;

                return $area;
            });
        $result['responsables'] = DB::table('miembros_empresa as m')->join('usuarios as u', 'u.id', '=', 'm.usuario_id')->where('m.empresa_id', $context->empresa_id)->where('m.activo', true)->where('u.activo', true)->get(['m.id', 'u.nombre']);
        $result['fotos'] = $this->data->query('implemento_archivos', $context)->get(['id', 'implemento_id', 'inspeccion_id', 'created_at']);
        $result['hoy'] = CarbonImmutable::now($context->zona_horaria)->toDateString();

        return response()->json($result);
    }

    public function category(Request $request, ?int $id = null): JsonResponse
    {
        $context = $this->context($request, $id ? 'editar' : 'crear');
        $values = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('implementos_categorias')->where('empresa_id', $context->empresa_id)->ignore($id)],
            'descripcion' => 'nullable|string|max:10000',
            'activo' => 'sometimes|boolean',
        ]);

        return DB::transaction(function () use ($request, $context, $values, $id): JsonResponse {
            $this->data->lock($context);
            $category = $this->write($request, $context, 'implementos_categorias', $values, $id);
            $this->data->query('implementos_catalogo', $context)->where('categoria_id', $category->id)->update(['categoria' => $category->nombre]);

            return response()->json(['data' => $category], $id ? 200 : 201);
        });
    }

    public function catalog(Request $request, ?int $id = null): JsonResponse
    {
        $context = $this->context($request, $id ? 'editar' : 'crear');
        $values = $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::unique('implementos_catalogo')->where('empresa_id', $context->empresa_id)->ignore($id)],
            'nombre' => 'required|string|max:180', 'categoria_id' => ['required', 'integer', $this->reference('implementos_categorias', $context)], 'descripcion' => 'nullable|string|max:10000',
            'periodicidad_cantidad' => 'required|integer|min:1|max:3650', 'periodicidad_unidad' => 'required|in:dias,meses,anios', 'activo' => 'sometimes|boolean',
        ]);

        return DB::transaction(function () use ($request, $context, $values, $id): JsonResponse {
            $this->data->lock($context);
            $category = $this->find('implementos_categorias', $context, $values['categoria_id']);
            $existing = $id ? $this->find('implementos_catalogo', $context, $id) : null;
            abort_unless($category->activo || ($existing && (int) $existing->categoria_id === (int) $category->id), 422, 'La categoría está inactiva. Selecciona una categoría activa.');
            $values['categoria'] = $category->nombre;

            return response()->json(['data' => $this->write($request, $context, 'implementos_catalogo', $values, $id)], $id ? 200 : 201);
        });
    }

    public function provider(Request $request, ?int $id = null): JsonResponse
    {
        $context = $this->context($request, $id ? 'editar' : 'crear');
        $values = $request->validate(['nombre' => 'required|string|max:180', 'documento' => 'nullable|string|max:30', 'contacto' => 'nullable|string|max:180', 'email' => 'nullable|email|max:200', 'telefono' => 'nullable|string|max:40', 'activo' => 'sometimes|boolean']);

        return DB::transaction(function () use ($request, $context, $values, $id): JsonResponse {
            $this->data->lock($context);

            return response()->json(['data' => $this->write($request, $context, 'implementos_proveedores', $values, $id)], $id ? 200 : 201);
        });
    }

    public function stockEntry(Request $request): JsonResponse
    {
        $context = $this->context($request, 'crear');
        $values = $request->validate([
            'categoria_id' => ['required', 'integer', $this->reference('implementos_categorias', $context)],
            'catalogo_id' => ['required', 'integer', $this->reference('implementos_catalogo', $context)],
            'cantidad' => 'required|integer|min:1|max:200',
            'proveedor_id' => ['required', 'integer', $this->reference('implementos_proveedores', $context)],
            'marca' => 'nullable|string|max:100', 'modelo' => 'nullable|string|max:100',
            'fecha_adquisicion' => 'nullable|date_format:Y-m-d', 'observaciones' => 'nullable|string|max:10000',
        ]);

        return DB::transaction(function () use ($request, $context, $values): JsonResponse {
            $this->data->lock($context);
            $provider = $this->find('implementos_proveedores', $context, $values['proveedor_id']);
            abort_unless($provider->activo, 422, 'Selecciona un proveedor activo.');
            $category = $this->find('implementos_categorias', $context, $values['categoria_id']);
            $catalog = $this->find('implementos_catalogo', $context, $values['catalogo_id']);
            abort_unless($category->activo && $catalog->activo && (int) $catalog->categoria_id === (int) $category->id, 422, 'Selecciona una categoría y un tipo activos que correspondan entre sí.');
            $quantity = $values['cantidad'];
            unset($values['cantidad'], $values['categoria_id']);
            $sequence = (int) $this->data->query('implementos', $context)->max('id');
            $prefix = mb_substr($catalog->codigo, 0, 35);
            $items = [];
            for ($index = 0; $index < $quantity; $index++) {
                do {
                    $code = $prefix.'-'.str_pad((string) ++$sequence, 6, '0', STR_PAD_LEFT);
                } while ($this->data->query('implementos', $context)->where('codigo', $code)->exists());
                $items[] = $this->write($request, $context, 'implementos', $values + ['codigo' => $code, 'estado' => 'disponible']);
            }

            return response()->json(['data' => $items, 'cantidad' => count($items)], 201);
        });
    }

    public function item(Request $request, ?int $id = null): JsonResponse
    {
        $context = $this->context($request, $id ? 'editar' : 'crear');
        $values = $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::unique('implementos')->where('empresa_id', $context->empresa_id)->ignore($id)],
            'catalogo_id' => ['required', 'integer', $this->reference('implementos_catalogo', $context)],
            'marca' => 'nullable|string|max:100', 'modelo' => 'nullable|string|max:100', 'serie' => 'nullable|string|max:100',
            'fecha_adquisicion' => 'nullable|date_format:Y-m-d', 'observaciones' => 'nullable|string|max:10000',
        ]);

        return DB::transaction(function () use ($request, $context, $values, $id): JsonResponse {
            $this->data->lock($context);
            $existing = $id ? $this->find('implementos', $context, $id) : null;
            abort_if($existing && $existing->catalogo_id !== $values['catalogo_id'], 422, 'El tipo de una unidad registrada no se cambia; crea otra unidad para conservar su historial.');
            abort_unless($existing || $this->find('implementos_catalogo', $context, $values['catalogo_id'])->activo, 422, 'El tipo de implemento está inactivo.');

            return response()->json(['data' => $this->write($request, $context, 'implementos', $values, $id)], $id ? 200 : 201);
        });
    }

    public function area(Request $request, int $id): JsonResponse
    {
        $context = $this->context($request, 'editar');
        $values = $request->validate(['coordenadas' => 'present|nullable|array|max:500', 'coordenadas.*' => 'required|array|size:2', 'coordenadas.*.0' => 'required|numeric|between:-180,180', 'coordenadas.*.1' => 'required|numeric|between:-90,90']);
        $polygon = $this->service->polygon($values['coordenadas']);

        return DB::transaction(function () use ($request, $context, $polygon, $id): JsonResponse {
            $this->data->lock($context);

            return response()->json(['data' => $this->write($request, $context, 'areas', ['coordenadas' => $polygon ? json_encode($polygon, JSON_THROW_ON_ERROR) : null], $id)]);
        });
    }

    public function install(Request $request, int $id): JsonResponse
    {
        $context = $this->context($request, 'editar');
        $today = CarbonImmutable::now($context->zona_horaria)->toDateString();
        $values = $request->validate([
            'area_id' => ['required', 'integer', $this->reference('areas', $context)], 'ubicacion' => 'required|string|max:255', 'piso' => 'nullable|string|max:50',
            'latitud' => 'nullable|required_with:longitud|numeric|between:-90,90', 'longitud' => 'nullable|required_with:latitud|numeric|between:-180,180',
            'fecha_instalacion' => 'required|date_format:Y-m-d|before_or_equal:'.$today, 'fecha_renovacion' => 'nullable|date_format:Y-m-d|after_or_equal:fecha_instalacion',
            'responsable_id' => ['nullable', 'integer', $this->reference('miembros_empresa', $context)],
        ]);

        return DB::transaction(function () use ($request, $context, $values, $id): JsonResponse {
            $this->data->lock($context);
            $item = $this->find('implementos', $context, $id);
            abort_if($item->estado === 'baja', 422, 'Este implemento está dado de baja.');
            abort_unless($this->find('areas', $context, $values['area_id'])->activo, 422, 'El área está inactiva.');
            abort_if($item->fecha_adquisicion && $values['fecha_instalacion'] < $item->fecha_adquisicion, 422, 'La instalación no puede preceder a la adquisición.');
            $latest = $this->data->query('implemento_inspecciones', $context)->where('implemento_id', $id)->max('fecha_realizada');
            abort_if($latest && $values['fecha_instalacion'] < $latest, 422, 'El traslado no puede preceder a la última inspección.');
            $this->closeInstallation($request, $context, $id, $values['fecha_instalacion']);
            $responsible = $values['responsable_id'] ?? null;
            unset($values['responsable_id']);
            $installation = $this->write($request, $context, 'implemento_instalaciones', $values + ['implemento_id' => $id]);
            $this->write($request, $context, 'implementos', ['estado' => 'instalado'], $id);
            $this->service->schedule($item, $installation, $values['fecha_instalacion'], $responsible);

            return response()->json(['data' => $installation], 201);
        });
    }

    private function closeInstallation(Request $request, object $context, int $id, string $date): void
    {
        $last = $this->data->query('implemento_instalaciones', $context)->where('implemento_id', $id)->orderByDesc('id')->first();
        abort_if($last && $date < ($last->fecha_retiro ?? $last->fecha_instalacion), 422, 'La fecha debe ser posterior o igual a la última instalación o retiro.');
        foreach ($this->data->query('implemento_instalaciones', $context)->where('implemento_id', $id)->whereNull('fecha_retiro')->get() as $row) {
            $this->write($request, $context, 'implemento_instalaciones', ['fecha_retiro' => $date], $row->id);
        }
        foreach ($this->data->query('implemento_inspecciones', $context)->where('implemento_id', $id)->where('estado', 'pendiente')->get() as $row) {
            $this->write($request, $context, 'implemento_inspecciones', ['estado' => 'cancelada'], $row->id);
        }
    }

    public function retire(Request $request, int $id): JsonResponse
    {
        $context = $this->context($request, 'eliminar');
        $values = $request->validate(['estado' => 'required|in:disponible,mantenimiento,baja']);

        return DB::transaction(function () use ($request, $context, $values, $id): JsonResponse {
            $this->data->lock($context);
            $item = $this->find('implementos', $context, $id);
            abort_if($item->estado === 'baja', 409, 'La unidad ya está dada de baja.');
            $this->closeInstallation($request, $context, $id, CarbonImmutable::now($context->zona_horaria)->toDateString());

            return response()->json(['data' => $this->write($request, $context, 'implementos', $values, $id)]);
        });
    }

    public function inspect(Request $request, int $id): JsonResponse
    {
        $context = $this->context($request, 'editar');
        $today = CarbonImmutable::now($context->zona_horaria)->toDateString();
        $values = $request->validate(['fecha_realizada' => 'required|date_format:Y-m-d|before_or_equal:'.$today, 'resultado' => 'required|in:operativo,requiere_mantenimiento,no_operativo', 'observaciones' => 'required|string|max:10000']);

        return DB::transaction(function () use ($request, $context, $values, $id): JsonResponse {
            $this->data->lock($context);
            $inspection = $this->find('implemento_inspecciones', $context, $id);
            abort_unless($inspection->estado === 'pendiente', 409, 'La inspección ya fue realizada o cancelada.');
            $installation = $this->find('implemento_instalaciones', $context, $inspection->instalacion_id);
            $lastDate = $this->data->query('implemento_inspecciones', $context)->where('implemento_id', $inspection->implemento_id)->max('fecha_realizada');
            abort_if($values['fecha_realizada'] < max($installation->fecha_instalacion, $lastDate ?? ''), 422, 'La fecha no puede preceder a la instalación o a la última inspección.');
            abort_if($installation->fecha_retiro !== null, 409, 'Esta instalación ya fue retirada.');
            $result = $this->write($request, $context, 'implemento_inspecciones', $values + ['estado' => 'realizada', 'realizado_por' => $context->miembro_id], $id);
            $item = $this->write($request, $context, 'implementos', ['estado' => $values['resultado'] === 'operativo' ? 'instalado' : 'mantenimiento'], $inspection->implemento_id);
            $this->service->schedule($item, $installation, $values['fecha_realizada'], $inspection->responsable_id);

            return response()->json(['data' => $result]);
        });
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $context = $this->context($request, 'editar');
        $values = $request->validate(['responsable_id' => ['required', 'integer', $this->reference('miembros_empresa', $context)]]);

        return DB::transaction(function () use ($request, $context, $values, $id): JsonResponse {
            $this->data->lock($context);
            abort_unless($this->find('implemento_inspecciones', $context, $id)->estado === 'pendiente', 409, 'Solo se asignan inspecciones pendientes.');

            return response()->json(['data' => $this->write($request, $context, 'implemento_inspecciones', $values, $id)]);
        });
    }

    public function cost(Request $request, int $id): JsonResponse
    {
        $context = $this->context($request, 'crear');
        $values = $request->validate([
            'proveedor_id' => ['required', 'integer', $this->reference('implementos_proveedores', $context)],
            'tipo' => 'required|in:compra,instalacion,recarga,mantenimiento,renovacion', 'fecha' => 'required|date_format:Y-m-d',
            'comprobante' => 'nullable|string|max:100', 'moneda' => 'required|in:PEN,USD', 'importe' => 'required|numeric|decimal:0,2|min:0|max:999999999999.99', 'observaciones' => 'nullable|string|max:10000',
        ]);

        return DB::transaction(function () use ($request, $context, $values, $id): JsonResponse {
            $this->data->lock($context);
            $this->find('implementos', $context, $id);
            abort_unless($this->find('implementos_proveedores', $context, $values['proveedor_id'])->activo, 422, 'El proveedor está inactivo.');

            return response()->json(['data' => $this->write($request, $context, 'implemento_costos', $values + ['implemento_id' => $id])], 201);
        });
    }
}
