<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EppDeliveryPdf;
use App\Services\HseAccess;
use App\Services\HseData;
use App\Services\WorkerAssignments;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EppDeliveryReportController extends Controller
{
    public function __construct(private HseAccess $access, private HseData $data) {}

    private function context(Request $request): object
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'epp', 'ver');
        $this->access->authorize($context, 'epp', 'descargar');

        return $context;
    }

    public function options(Request $request): JsonResponse
    {
        $context = $this->context($request);

        return response()->json([
            'planillas' => DB::table('epp_planillas')->where('empresa_id', $context->empresa_id)->orderByDesc('numero')->get(['numero', 'fecha', 'zona', 'responsable', 'trabajadores']),
            'sedes' => $this->data->query('sedes', $context)->orderBy('nombre')->get(['id', 'nombre', 'pais']),
            'zonas' => $this->data->query('zonas', $context)->orderBy('nombre')->get(['id', 'nombre', 'sede_id']),
            'almacenes' => $this->data->query('almacenes', $context)->orderBy('nombre')->get(['id', 'nombre']),
            'fecha' => CarbonImmutable::now($context->zona_horaria)->toDateString(), 'zona_horaria' => $context->zona_horaria,
        ]);
    }

    public function pdf(Request $request, EppDeliveryPdf $pdf): Response
    {
        $context = $this->context($request);
        $input = $request->validate([
            'planilla_numero' => 'nullable|integer|min:1',
            'fecha' => 'required_without:planilla_numero|date_format:Y-m-d',
            'sede_id' => ['nullable', 'integer', Rule::exists('sedes', 'id')->where('empresa_id', $context->empresa_id)],
            'zona_id' => ['nullable', 'integer', Rule::exists('zonas', 'id')->where('empresa_id', $context->empresa_id)->when($request->filled('sede_id'), fn ($rule) => $rule->where('sede_id', $request->input('sede_id')))],
            'almacen_id' => ['nullable', 'integer', Rule::exists('almacenes', 'id')->where('empresa_id', $context->empresa_id)],
        ]);
        $planilla = null;
        if (! empty($input['planilla_numero'])) {
            $planilla = DB::table('epp_planillas')->where('empresa_id', $context->empresa_id)->where('numero', $input['planilla_numero'])->first();
            abort_unless($planilla, 404, 'Planilla no encontrada.');
            $input = ['fecha' => $planilla->fecha];
        }
        $start = CarbonImmutable::parse($input['fecha'], $context->zona_horaria)->startOfDay();
        $query = DB::table('epp_entregas as d')
            ->join('epp_entrega_detalles as l', fn ($join) => $join->on('l.entrega_id', '=', 'd.id')->on('l.empresa_id', '=', 'd.empresa_id'))
            ->join('trabajadores as w', fn ($join) => $join->on('w.id', '=', 'd.trabajador_id')->on('w.empresa_id', '=', 'd.empresa_id'))
            ->join('epp_catalogo as e', fn ($join) => $join->on('e.id', '=', 'l.epp_id')->on('e.empresa_id', '=', 'l.empresa_id'))
            ->where('d.empresa_id', $context->empresa_id)->where('d.estado', 'confirmada');
        if ($planilla) {
            $query->whereIn('d.id', json_decode($planilla->entregas, true, flags: JSON_THROW_ON_ERROR));
        } else {
            $query->where('d.fecha', '>=', $start->utc()->format('Y-m-d H:i:s'))->where('d.fecha', '<', $start->addDay()->utc()->format('Y-m-d H:i:s'));
        }
        if (! empty($input['almacen_id'])) {
            $query->where('l.almacen_id', $input['almacen_id']);
        }
        if (! empty($input['sede_id']) || ! empty($input['zona_id'])) {
            $location = DB::table('trabajador_asignaciones as h')->selectRaw('1')->whereColumn('h.trabajador_id', 'd.trabajador_id')->whereColumn('h.empresa_id', 'd.empresa_id');
            app(WorkerAssignments::class)->atDate($location, $input['fecha'], 'h.');
            foreach (['sede_id', 'zona_id'] as $field) {
                if (! empty($input[$field])) {
                    $location->where('h.'.$field, $input[$field]);
                }
            }
            $query->whereExists($location);
        }
        $rows = $query->orderBy('w.apellidos')->orderBy('w.nombres')->orderBy('d.fecha')->orderBy('l.id')->limit(5001)
            ->get(['w.numero_documento as dni', 'w.nombres', 'w.apellidos', 'e.nombre as epp', 'e.unidad', 'e.talla', 'l.cantidad', 'w.firma_almacenada', 'w.firma_ruta']);
        abort_if($rows->count() > 5000, 422, 'El informe supera 5000 filas. Selecciona sede, zona o almacén para reducirlo.');
        foreach ($rows as $row) {
            $row->trabajador = trim($row->nombres.' '.$row->apellidos);
            $row->epp .= ' ('.$row->talla.')';
            $row->cantidad = rtrim(rtrim(number_format((float) $row->cantidad, 3, '.', ''), '0'), '.').' '.$row->unidad;
            $row->firma_ruta = $row->firma_almacenada ? $row->firma_ruta : null;
        }
        $filters = ['Fecha' => $start->format('d/m/Y').' ('.$context->zona_horaria.')'];
        foreach (['sede_id' => ['Sede', 'sedes'], 'zona_id' => ['Zona', 'zonas'], 'almacen_id' => ['Almacén', 'almacenes']] as $key => [$label, $table]) {
            $filters[$label] = ! empty($input[$key]) ? $this->data->find($table, $context, $input[$key])->nombre : 'Todos';
        }
        if ($planilla) {
            $filters = ['Planilla' => (string) $planilla->numero, 'Fecha de entrega' => $start->format('d/m/Y'), 'Zona' => $planilla->zona, 'Responsable del registro' => $planilla->responsable, 'Trabajadores en la planilla' => (string) $planilla->trabajadores];
        }
        $filename = $planilla ? 'planilla-entrega-'.$planilla->numero : 'informe-entregas-'.$input['fecha'];
        $company = DB::table('empresas')->where('id', $context->empresa_id)->value('razon_social');

        return response($pdf->render($company, $filters, $rows->all()), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
