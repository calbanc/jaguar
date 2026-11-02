<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EppPlanillas;
use App\Services\ExcelExport;
use App\Services\HseAccess;
use App\Services\HseData;
use App\Services\InventoryService;
use App\Services\WorkerSpreadsheet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EppDeliveryController extends Controller
{
    public function __construct(private HseData $data, private HseAccess $access, private InventoryService $inventory) {}

    private function responsibleUsers(object $context): Builder
    {
        return DB::table('miembros_empresa as m')->join('usuarios as u', 'u.id', '=', 'm.usuario_id')
            ->where('m.empresa_id', $context->empresa_id)->where('m.activo', true)->where('u.activo', true);
    }

    public function options(Request $request): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'epp', 'crear');
        $now = CarbonImmutable::now($context->zona_horaria);

        return response()->json([
            'proxima_planilla' => app(EppPlanillas::class)->nextNumber($context),
            'trabajadores' => $this->data->query('trabajadores', $context)->where('activo', true)->orderBy('apellidos')->get(['id', 'tipo_documento', 'numero_documento', 'nombres', 'apellidos']),
            'epps' => $this->data->query('epp_catalogo', $context)->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'talla', 'unidad']),
            'almacenes' => $this->data->query('almacenes', $context)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'responsables' => $this->responsibleUsers($context)->orderBy('u.nombre')->get(['m.id', 'u.nombre']),
            'responsable_actual' => $context->miembro_id, 'fecha' => $now->toDateString(), 'hora' => $now->format('H:i'), 'zona_horaria' => $context->zona_horaria,
            'stock' => $this->data->query('epp_movimientos', $context)->select('epp_id', 'almacen_id')->selectRaw('SUM(cantidad) as disponible')->groupBy('epp_id', 'almacen_id')->get(),
        ]);
    }

    public function template(Request $request, ExcelExport $excel): BinaryFileResponse
    {
        $this->access->authorize($request->attributes->get('hse'), 'epp', 'crear');
        $path = $excel->create('DNI trabajadores', ['DNI'], [['']], textColumns: [1]);

        return response()->download($path, 'plantilla-entrega-masiva.xlsx', ['Cache-Control' => 'private, no-store'])->deleteFileAfterSend(true);
    }

    public function bulk(Request $request, WorkerSpreadsheet $spreadsheet): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'epp', 'crear');
        $rules = $this->rules();
        unset($rules['trabajador_id']);
        $input = $request->validate($rules + ['archivo' => 'required|file|max:5120|extensions:xlsx', 'confirmar' => 'sometimes|boolean', 'validacion' => 'nullable|string|size:64']);
        $rows = $spreadsheet->read($request->file('archivo')->getRealPath(), ['DNI']);
        $shared = array_intersect_key($input, $rules);
        $shared['cantidad'] = bcadd((string) $shared['cantidad'], '0', 3);
        $documents = array_column(array_column($rows, 'values'), 0);
        sort($documents, SORT_STRING);
        $hash = hash('sha256', json_encode([$shared, $documents], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($request, $context, $rows, $shared, $hash) {
            $this->data->lock($context);
            $prior = DB::table('epp_entrega_lotes')->where('empresa_id', $context->empresa_id)->where('uuid_cliente', $shared['uuid_cliente'])->first();
            if ($prior) {
                abort_unless(hash_equals($prior->huella, $hash), 409, 'Este lote ya se registró con otros datos. Inicia una nueva entrega masiva.');

                $planilla = app(EppPlanillas::class)->register($context, $shared['uuid_cliente'], json_decode($prior->entregas, true));

                return response()->json(['planilla_numero' => $planilla->numero, 'registrados' => $prior->total, 'entregas' => json_decode($prior->entregas), 'message' => 'El lote ya estaba registrado. No se descontó stock nuevamente.']);
            }
            foreach (['epp_id' => 'epp_catalogo', 'almacen_id' => 'almacenes'] as $key => $table) {
                abort_unless($this->data->query($table, $context)->where('id', $shared[$key])->where('activo', true)->exists(), 422, 'Selecciona un EPP y almacén activos de esta empresa.');
            }
            abort_unless($this->responsibleUsers($context)->where('m.id', $shared['entregado_por'])->exists(), 422, 'Selecciona un usuario responsable activo de esta empresa.');
            $duplicates = array_count_values(array_column(array_column($rows, 'values'), 0));
            $workers = $this->data->query('trabajadores', $context)->whereRaw('UPPER(tipo_documento) = ?', ['DNI'])->get()->keyBy('numero_documento');
            $results = [];
            foreach ($rows as $row) {
                $dni = $row['values'][0];
                $worker = $workers->get($dni);
                $errors = $row['errores'];
                if (! preg_match('/^[0-9]{8}$/', $dni)) {
                    $errors[] = 'El DNI debe tener 8 dígitos. Conserva los ceros iniciales usando formato texto.';
                }
                if (($duplicates[$dni] ?? 0) > 1) {
                    $errors[] = 'DNI repetido en el archivo.';
                }
                if (! $worker || ! $worker->activo) {
                    $errors[] = 'No existe un trabajador activo con este DNI en la empresa.';
                }
                $results[] = ['fila' => $row['fila'], 'dni' => $dni, 'trabajador_id' => $worker?->id, 'trabajador' => $worker ? trim($worker->nombres.' '.$worker->apellidos) : null, 'errores' => $errors];
            }
            $invalid = count(array_filter($results, fn ($row) => $row['errores'] !== []));
            $required = bcmul($shared['cantidad'], (string) count($results), 3);
            $stock = (string) $this->data->query('epp_movimientos', $context)->where('epp_id', $shared['epp_id'])->where('almacen_id', $shared['almacen_id'])->sum('cantidad');
            $sufficient = bccomp($stock, $required, 3) >= 0;
            if ($request->boolean('confirmar') && $invalid === 0 && $sufficient) {
                abort_unless(hash_equals($hash, (string) $request->input('validacion')), 422, 'Valida nuevamente el archivo y los datos de la entrega antes de confirmar.');
                $ids = [];
                foreach ($results as $row) {
                    $individual = $shared;
                    $individual['uuid_cliente'] = (string) Str::uuid();
                    $individual['trabajador_id'] = $row['trabajador_id'];
                    $ids[] = $this->deliver($request, $context, $individual)->getData()->data->id;
                }
                DB::table('epp_entrega_lotes')->insert(['empresa_id' => $context->empresa_id, 'uuid_cliente' => $shared['uuid_cliente'], 'huella' => $hash, 'total' => count($ids), 'entregas' => json_encode($ids, JSON_THROW_ON_ERROR), 'created_at' => now()]);

                $planilla = app(EppPlanillas::class)->register($context, $shared['uuid_cliente'], $ids);

                return response()->json(['planilla_numero' => $planilla->numero, 'registrados' => count($ids), 'entregas' => $ids, 'message' => count($ids).' entregas registradas correctamente.'], 201);
            }

            return response()->json(['total' => count($results), 'invalidos' => $invalid, 'filas' => $results, 'cantidad_total' => $required, 'stock_disponible' => $stock, 'stock_suficiente' => $sufficient, 'validacion' => $invalid === 0 && $sufficient ? $hash : null,
                'message' => $invalid ? 'Corrige los DNI indicados. No se registró ninguna entrega.' : (! $sufficient ? 'Stock insuficiente para todos los trabajadores. No se registró ninguna entrega.' : 'Validación correcta. Confirma para registrar todas las entregas.')]);
        });
    }

    public function store(Request $request): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'epp', 'crear');
        $input = $request->validate($this->rules());

        return DB::transaction(function () use ($request, $context, $input) {
            $response = $this->deliver($request, $context, $input);
            $body = $response->getData(true);
            $planilla = app(EppPlanillas::class)->register($context, $input['uuid_cliente'], [$body['data']['id']]);
            $body['planilla_numero'] = $planilla->numero;

            return $response->setData($body);
        });
    }

    private function rules(): array
    {
        return [
            'uuid_cliente' => 'required|uuid', 'trabajador_id' => 'required|integer|min:1',
            'epp_id' => 'required|integer|min:1', 'almacen_id' => 'required|integer|min:1',
            'cantidad' => 'required|numeric|decimal:0,3|gt:0|max:1000000',
            'motivo' => 'required|in:nuevo,reposicion', 'fecha' => 'required|date_format:Y-m-d', 'hora' => 'required|date_format:H:i',
            'entregado_por' => 'required|integer|min:1', 'observacion' => 'nullable|string|max:10000',
        ];
    }

    private function deliver(Request $request, object $context, array $input): JsonResponse
    {
        $header = [
            'uuid_cliente' => $input['uuid_cliente'], 'trabajador_id' => $input['trabajador_id'], 'entregado_por' => $input['entregado_por'],
            'motivo' => $input['motivo'], 'fecha' => CarbonImmutable::createFromFormat('!Y-m-d H:i', $input['fecha'].' '.$input['hora'], $context->zona_horaria)->utc()->format('Y-m-d H:i:s'),
            'observacion' => $input['observacion'] ?? null,
        ];

        return DB::transaction(function () use ($request, $context, $input, $header) {
            $this->data->lock($context);
            $prior = $this->data->query('epp_entregas', $context)->where('uuid_cliente', $input['uuid_cliente'])->first();
            if ($prior) {
                foreach ($header as $key => $value) {
                    abort_unless($value == $prior->$key, 409, 'La operación ya se registró con datos distintos.');
                }
                $details = $this->data->query('epp_entrega_detalles', $context)->where('entrega_id', $prior->id)->get();
                abort_unless($details->count() === 1 && $details[0]->epp_id == $input['epp_id'] && $details[0]->almacen_id == $input['almacen_id'] && bccomp((string) $details[0]->cantidad, (string) $input['cantidad'], 3) === 0, 409, 'La operación ya se registró con otros artículos.');

                return response()->json(['data' => $this->data->present('epp_entregas', $prior)]);
            }
            foreach (['trabajador_id' => 'trabajadores', 'epp_id' => 'epp_catalogo', 'almacen_id' => 'almacenes'] as $key => $table) {
                abort_unless($this->data->query($table, $context)->where('id', $input[$key])->where('activo', true)->exists(), 422, 'Selecciona un trabajador, EPP y almacén activos de esta empresa.');
            }
            abort_unless($this->responsibleUsers($context)->where('m.id', $input['entregado_por'])->exists(), 422, 'El responsable debe ser un usuario activo de esta empresa.');
            $delivery = $this->data->insert('epp_entregas', $context, $header);
            $detail = $this->data->insert('epp_entrega_detalles', $context, ['entrega_id' => $delivery->id, 'epp_id' => $input['epp_id'], 'almacen_id' => $input['almacen_id'], 'cantidad' => $input['cantidad']]);
            $after = $this->inventory->transition($context, $delivery, false);
            $this->data->audit($request, 'epp_entregas', null, $after, 'entregar');
            $this->data->audit($request, 'epp_entrega_detalles', null, $this->data->find('epp_entrega_detalles', $context, $detail->id), 'crear');

            return response()->json(['data' => $this->data->present('epp_entregas', $after)], 201);
        });
    }
}
