<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChecklistService;
use App\Services\HseAccess;
use App\Services\HseData;
use App\Services\InventoryService;
use App\Services\TableFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    public function __construct(private HseData $data, private HseAccess $access) {}

    public function warehouseSites(Request $request): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'almacenes', 'ver');

        return response()->json(['data' => $this->data->query('sedes', $context)->orderBy('nombre')->get(['id', 'nombre'])]);
    }

    public function equipmentOptions(Request $request): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'epp', 'ver');

        return response()->json(['data' => $this->data->query('epp_catalogo', $context)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])]);
    }

    public function warehouses(Request $request): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'epp', 'ver');

        return response()->json(['data' => $this->data->query('almacenes', $context)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])]);
    }

    public function action(Request $request, string $resource, int $id, string $action): JsonResponse
    {
        $actions = [
            'documento_versiones' => ['revisar'], 'checklist_plantillas' => ['publicar', 'versionar', 'retirar'],
            'checklist_registros' => ['respuestas', 'enviar', 'revisar', 'anular'],
            'epp_entregas' => ['confirmar', 'anular'], 'hallazgos' => ['transicion'], 'acciones_correctivas' => ['transicion'],
        ];
        abort_unless(in_array($action, $actions[$resource] ?? [], true), 404);
        $context = $request->attributes->get('hse');
        $permission = match ($action) {
            'anular', 'retirar' => 'eliminar', 'versionar' => 'crear',
            'transicion' => in_array($request->input('estado'), ['anulado', 'anulada']) ? 'eliminar' : 'editar',
            default => 'editar',
        };
        $this->access->authorize($context, config('hse.resources.'.$resource), $permission);

        return DB::transaction(function () use ($request, $resource, $id, $action, $context) {
            $this->data->lock($context);
            $before = $this->data->find($resource, $context, $id);
            $checklists = app(ChecklistService::class);
            if ($resource === 'documento_versiones') {
                $input = $request->validate(['resultado' => 'required|in:aprobado,observado,rechazado', 'comentario' => 'nullable|string|max:10000']);
                abort_unless($before->estado === 'pendiente', 409, 'La versión ya fue revisada.');
                $this->data->insert('documento_revisiones', $context, ['documento_version_id' => $id, 'revisor_id' => $context->miembro_id,
                    'resultado' => $input['resultado'], 'comentario' => $input['comentario'] ?? null]);
                $after = $this->data->update($resource, $context, $id, ['estado' => $input['resultado'], 'revisado_por' => $context->miembro_id,
                    'revisado_at' => now(), 'comentario_revision' => $input['comentario'] ?? null]);
            } elseif ($resource === 'checklist_plantillas') {
                if ($action === 'retirar') {
                    abort_unless($before->estado === 'publicado', 409, 'Solo se retiran plantillas publicadas.');
                }
                $after = match ($action) {
                    'publicar' => $checklists->publish($context, $before),
                    'versionar' => $checklists->version($context, $before),
                    'retirar' => $this->data->update($resource, $context, $id, ['estado' => 'retirado']),
                };
            } elseif ($resource === 'checklist_registros') {
                if ($action === 'respuestas') {
                    $input = $request->validate(['respuestas' => 'required|array']);
                    $before = $checklists->snapshot($context, $before);
                    $after = $checklists->saveAnswers($context, $before, $input['respuestas']);
                    $after = $checklists->snapshot($context, $after);
                } elseif ($action === 'enviar') {
                    $after = $checklists->submit($context, $before);
                } else {
                    abort_unless($action === 'revisar' ? $before->estado === 'enviado' : in_array($before->estado, ['borrador', 'enviado']), 409, 'Transición inválida.');
                    $after = $this->data->update($resource, $context, $id, ['estado' => $action === 'revisar' ? 'revisado' : 'anulado']);
                }
            } elseif ($resource === 'epp_entregas') {
                $after = app(InventoryService::class)->transition($context, $before, $action === 'anular');
            } else {
                $input = $request->validate(['estado' => 'required|string', 'comentario' => 'required|string|max:10000']);
                $transitions = $resource === 'hallazgos' ? [
                    'abierto' => ['en_proceso', 'anulado'], 'en_proceso' => ['por_validar', 'anulado'], 'por_validar' => ['en_proceso', 'cerrado'],
                ] : ['pendiente' => ['en_proceso', 'anulada'], 'en_proceso' => ['por_validar', 'anulada'], 'por_validar' => ['en_proceso', 'completada']];
                abort_unless(in_array($input['estado'], $transitions[$before->estado] ?? [], true), 409, 'Transición inválida.');
                $values = ['estado' => $input['estado']];
                if (in_array($input['estado'], ['cerrado', 'completada'])) {
                    $this->access->authorize($context, 'hallazgos', 'editar');
                }
                if ($input['estado'] === 'cerrado') {
                    abort_if($this->data->query('acciones_correctivas', $context)->where('hallazgo_id', $id)->whereNotIn('estado', ['completada', 'anulada'])->exists(), 422, 'Hay acciones pendientes.');
                    $values += ['cerrado_por' => $context->miembro_id, 'cerrado_at' => now()];
                }
                if ($resource === 'acciones_correctivas') {
                    abort_if(in_array($this->data->find('hallazgos', $context, $before->hallazgo_id)->estado, ['cerrado', 'anulado']), 409, 'Hallazgo finalizado.');
                    $this->data->insert('hallazgo_seguimientos', $context, ['hallazgo_id' => $before->hallazgo_id, 'autor_id' => $context->miembro_id,
                        'comentario' => 'Acción #'.$id.' → '.$input['estado'].': '.$input['comentario']]);
                    if ($input['estado'] === 'completada') {
                        $values['completada_at'] = now();
                    }
                } else {
                    $this->data->insert('hallazgo_seguimientos', $context, ['hallazgo_id' => $id, 'autor_id' => $context->miembro_id,
                        'comentario' => $input['comentario'], 'estado_resultante' => $input['estado']]);
                }
                $after = $this->data->update($resource, $context, $id, $values);
            }
            $this->data->audit($request, $resource, $before, $after, $action);

            return response()->json(['data' => $after]);
        });
    }

    public function movement(Request $request): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'almacenes', 'crear');
        $input = $request->validate(['epp_id' => 'required|integer|min:1', 'almacen_id' => 'required|integer|min:1',
            'cantidad' => 'required|numeric|decimal:0,3|not_in:0', 'tipo' => 'required|in:entrada,ajuste',
            'clave_operacion' => 'required|string|max:100', 'observacion' => 'nullable|string|max:10000',
            'costo_unitario' => [Rule::requiredIf((float) $request->input('cantidad') > 0), 'nullable', 'numeric', 'decimal:0,6', 'gt:0', 'max:1000000000']]);
        abort_if($input['tipo'] === 'entrada' && $input['cantidad'] <= 0, 422, 'Entrada debe ser positiva.');

        return DB::transaction(function () use ($request, $input, $context) {
            $this->data->lock($context);
            $after = app(InventoryService::class)->movement($context, $input);
            $this->data->audit($request, 'epp_movimientos', null, $after, 'movimiento');

            return response()->json(['data' => $after], 201);
        });
    }

    public function returnOptions(Request $request): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'epp', 'editar');
        $rows = DB::table('v_epp_trabajador_detalle as d')->join('trabajadores as w', function ($join) {
            $join->on('w.id', '=', 'd.trabajador_id')->on('w.empresa_id', '=', 'd.empresa_id');
        })->where('d.empresa_id', $context->empresa_id)->where('d.cantidad_neta', '>', 0)
            ->orderByDesc('d.fecha')->select('d.*', 'w.nombres', 'w.apellidos', 'w.numero_documento')->get();

        return response()->json(['data' => $rows]);
    }

    public function returnItems(Request $request): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'epp', 'editar');
        $input = $request->validate(['entrega_detalle_id' => 'required|integer|min:1', 'cantidad' => 'required|numeric|decimal:0,3|gt:0',
            'clave_operacion' => 'required|string|max:100', 'observacion' => 'nullable|string|max:10000']);

        return DB::transaction(function () use ($request, $input, $context) {
            $this->data->lock($context);
            $after = app(InventoryService::class)->returnItems($context, $input);
            $this->data->audit($request, 'epp_movimientos', null, $after, 'devolucion');

            return response()->json(['data' => $after], 201);
        });
    }

    public function score(Request $request, int $id): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'inspecciones', 'ver');
        $record = $this->data->find('checklist_registros', $context, $id);

        return response()->json(['data' => ['registro_id' => $id, 'puntaje' => app(ChecklistService::class)->score($context, $record)]]);
    }

    public function workerEquipmentCosts(Request $request, int $id): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'epp', 'ver');
        $worker = $this->data->find('trabajadores', $context, $id);
        $summary = $this->data->query('v_epp_costo_trabajador', $context)->where('id', $id)->first();
        $details = $this->data->query('v_epp_trabajador_detalle', $context)->where('trabajador_id', $worker->id)->orderByDesc('fecha')->orderByDesc('id')->get();

        return response()->json(['data' => $summary, 'detalles' => $details]);
    }

    public function report(Request $request, string $report): JsonResponse
    {
        $views = ['requisitos' => ['v_estado_requisitos', 'documentos'], 'temas' => ['v_estado_temas', 'gestion_sst'],
            'epp-trabajadores' => ['v_epp_costo_trabajador', 'epp'], 'stock' => ['v_epp_stock', 'almacenes'], 'indicadores' => ['v_indicador_resultados', 'indicadores']];
        abort_unless(isset($views[$report]), 404);
        [$view, $module] = $views[$report];
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, $module, 'ver');
        $input = $request->validate(['per_page' => 'sometimes|integer|min:1|max:100']);
        if (DB::getDriverName() === 'mysql') {
            $offset = now($context->zona_horaria)->format('P');
            DB::statement('SET time_zone = ?', [$offset]);
        }
        try {
            $query = $this->data->query($view, $context);
            app(TableFilters::class)->apply($query, $view, $request);
            $result = $query->paginate($input['per_page'] ?? 25);
        } finally {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("SET time_zone = '+00:00'");
            }
        }

        return response()->json($result->toArray() + ['columns' => app(TableFilters::class)->columns($view)]);
    }

    public function template(Request $request, int $id): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'inspecciones', 'ver');
        $template = $this->data->find('checklist_plantillas', $context, $id);
        $sections = $this->data->query('checklist_secciones', $context)->where('plantilla_id', $id)->orderBy('orden')->orderBy('id')->get();
        $questions = $this->data->query('checklist_preguntas', $context)->where('plantilla_id', $id)->orderBy('orden')->orderBy('id')->get();
        $options = $this->data->query('checklist_pregunta_opciones', $context)->whereIn('pregunta_id', $questions->pluck('id'))
            ->orderBy('orden')->orderBy('id')->get()->groupBy('pregunta_id');
        $questions = $questions->map(function ($question) use ($options) {
            $question = $this->data->present('checklist_preguntas', $question);
            $question->opciones = ($options[$question->id] ?? collect())->values()->all();

            return $question;
        })->groupBy('seccion_id');
        $template->secciones = $sections->map(function ($section) use ($questions) {
            $section->preguntas = ($questions[$section->id] ?? collect())->values()->all();

            return $section;
        })->all();

        return response()->json(['data' => $template]);
    }

    public function checklistDetail(Request $request, int $id): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'inspecciones', 'ver');
        $record = $this->data->find('checklist_registros', $context, $id);
        $detail = app(ChecklistService::class)->snapshot($context, $record);
        $detail->puntaje = app(ChecklistService::class)->score($context, $record);

        return response()->json(['data' => $detail]);
    }

    public function catalogs(Request $request): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $permissions = DB::table('rol_permisos as rp')->join('permisos as p', 'p.id', '=', 'rp.permiso_id')
            ->where('rp.empresa_id', $context->empresa_id)->where('rp.rol_id', $context->rol_id)->select('p.*')->get();

        return response()->json(['data' => ['plataformas' => DB::table('plataformas')->get(), 'modulos' => DB::table('modulos')->get(),
            'paises' => config('hse.country_codes'), 'permisos' => $permissions, 'empresa' => DB::table('empresas')->where('id', $context->empresa_id)->first()]]);
    }
}
