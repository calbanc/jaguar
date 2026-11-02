<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HseAccess;
use App\Services\HseData;
use App\Services\TrainingReport;
use App\Services\TrainingReportPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TrainingReportController extends Controller
{
    public function __construct(private HseAccess $access, private HseData $data, private TrainingReport $report) {}

    private function context(Request $request, string $action): object
    {
        $input = $request->validate(['empresa_id' => 'required|integer|min:1']);
        $context = $request->attributes->get('hse');
        abort_unless((int) $input['empresa_id'] === (int) $context->empresa_id, 403);
        $this->access->authorize($context, 'capacitaciones', $action);

        return $context;
    }

    private function find(object $context, int $id): object
    {
        $row = DB::table('capacitacion_formatos')->where('empresa_id', $context->empresa_id)->where('id', $id)->where('activo', true)->first();
        abort_unless($row, 404, 'Formato no encontrado.');

        return $row;
    }

    private function present(object $row): object
    {
        $row->diseno = json_decode($row->diseno, true);

        return $row;
    }

    private function design(Request $request): array
    {
        $input = $request->validate([
            'diseno' => 'required|array:html,orientacion,margen,fuente,columnas,detalle',
            'diseno.detalle' => ['sometimes', Rule::in(['tabla', 'listado'])],
            'diseno.html' => 'required|string|max:150000',
            'diseno.orientacion' => ['required', Rule::in(['vertical', 'horizontal'])],
            'diseno.margen' => 'required|integer|min:10|max:30',
            'diseno.fuente' => 'required|integer|min:8|max:18',
            'diseno.columnas' => 'required|array|min:1|max:7',
            'diseno.columnas.*' => ['required', 'string', 'distinct', Rule::in(array_keys(TrainingReport::COLUMNS))],
        ]);
        $design = $input['diseno'];
        $design['detalle'] ??= 'tabla';
        $design['html'] = $this->report->clean($design['html']);
        abort_if(trim(strip_tags($design['html'])) === '', 422, 'Escribe el contenido del informe.');

        return $design;
    }

    public function fields(Request $request): JsonResponse
    {
        $this->context($request, 'ver');

        return response()->json(['campos' => $this->report->fields(), 'columnas' => TrainingReport::COLUMNS]);
    }

    public function index(Request $request): JsonResponse
    {
        $context = $this->context($request, 'ver');
        $rows = DB::table('capacitacion_formatos')->where('empresa_id', $context->empresa_id)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'version', 'updated_at']);

        return response()->json(['data' => $rows]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->present($this->find($this->context($request, 'ver'), $id))]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->write($request);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->write($request, $id);
    }

    private function write(Request $request, ?int $id = null): JsonResponse
    {
        $context = $this->context($request, $id ? 'editar' : 'crear');
        $input = $request->validate(['nombre' => 'required|string|max:200', 'version' => $id ? 'required|integer|min:1' : 'sometimes|integer|min:1']);
        $design = $this->design($request);

        return DB::transaction(function () use ($request, $id, $context, $input, $design): JsonResponse {
            $this->data->lock($context);
            $before = $id ? $this->find($context, $id) : null;
            abort_if($before && $before->version !== $input['version'], 409, 'Otro usuario modificó este formato. Vuelve a abrirlo antes de guardar.');
            $values = ['nombre' => trim($input['nombre']), 'diseno' => json_encode($design, JSON_UNESCAPED_UNICODE), 'version' => ($before->version ?? 0) + 1, 'updated_at' => now()];
            if ($id) {
                DB::table('capacitacion_formatos')->where('empresa_id', $context->empresa_id)->where('id', $id)->update($values);
            } else {
                $id = DB::table('capacitacion_formatos')->insertGetId($values + ['empresa_id' => $context->empresa_id, 'created_at' => now()]);
            }
            $after = $this->find($context, $id);
            $this->data->audit($request, 'capacitacion_formatos', $before, $after, $before ? 'editar' : 'crear');

            return response()->json(['data' => $this->present($after)], $before ? 200 : 201);
        });
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $context = $this->context($request, 'eliminar');

        return DB::transaction(function () use ($request, $context, $id): JsonResponse {
            $this->data->lock($context);
            $before = $this->find($context, $id);
            DB::table('capacitacion_formatos')->where('empresa_id', $context->empresa_id)->where('id', $id)->update(['activo' => false, 'updated_at' => now()]);
            $after = clone $before;
            $after->activo = false;
            $this->data->audit($request, 'capacitacion_formatos', $before, $after, 'eliminar');

            return response()->json(['message' => 'Formato desactivado.']);
        });
    }

    public function download(Request $request, int $id, TrainingReportPdf $pdf): Response
    {
        $context = $this->context($request, 'descargar');
        $input = $request->validate(['numero_planilla' => 'required|integer|min:1']);
        $format = $this->find($context, $id);
        $design = json_decode($format->diseno, true);
        $html = $this->report->render($context, $design, $input['numero_planilla']);
        $content = $pdf->generate($html);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="capacitacion-'.$input['numero_planilla'].'-formato-'.$id.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $context = $this->context($request, 'ver');
        $input = $request->validate(['numero_planilla' => 'required|integer|min:1']);
        $design = $this->design($request);

        return response()->json(['html' => $this->report->render($context, $design, $input['numero_planilla'])]);
    }
}
