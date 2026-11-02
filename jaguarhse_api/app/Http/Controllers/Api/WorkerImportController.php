<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExcelExport;
use App\Services\HseAccess;
use App\Services\HseData;
use App\Services\WorkerAssignments;
use App\Services\WorkerSpreadsheet;
use App\Services\WorkerValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WorkerImportController extends Controller
{
    public function __construct(private HseAccess $access, private HseData $data) {}

    private function context(Request $request, string $action): object
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'organizacion', $action);

        return $context;
    }

    public function options(Request $request): JsonResponse
    {
        $context = $this->context($request, 'ver');
        $areas = DB::table('areas as a')->join('zonas as z', function ($join) {
            $join->on('z.id', '=', 'a.zona_id')->on('z.empresa_id', '=', 'a.empresa_id');
        })->join('sedes as s', function ($join) {
            $join->on('s.id', '=', 'z.sede_id')->on('s.empresa_id', '=', 'z.empresa_id');
        })->where('a.empresa_id', $context->empresa_id)->select('a.id', 'a.nombre', 'a.activo', 'a.zona_id', 'z.sede_id', 's.nombre as sede', 'z.nombre as zona', 's.pais')->orderBy('s.nombre')->orderBy('z.nombre')->orderBy('a.nombre')->get();

        $zones = $this->data->query('zonas', $context)->orderBy('nombre')->get()->map(fn ($zone) => $this->data->present('zonas', $zone));
        $sites = $this->data->query('sedes', $context)->orderBy('nombre')->get(['id', 'nombre', 'pais', 'activo']);

        return response()->json(['data' => $areas, 'zonas' => $zones, 'sedes' => $sites]);
    }

    public function template(Request $request, ExcelExport $excel): BinaryFileResponse
    {
        $this->context($request, 'crear');
        $path = $excel->create('Trabajadores', WorkerSpreadsheet::HEADERS, [array_fill(0, 5, '')], textColumns: [1]);

        return response()->download($path, 'plantilla-trabajadores.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store'])->deleteFileAfterSend(true);
    }

    public function import(Request $request, WorkerSpreadsheet $spreadsheet, WorkerValidation $validation): JsonResponse
    {
        $context = $this->context($request, 'crear');
        $request->validate(['archivo' => 'required|file|max:5120|extensions:xlsx', 'confirmar' => 'sometimes|boolean']);
        $rows = $spreadsheet->read($request->file('archivo')->getRealPath());

        return DB::transaction(function () use ($request, $context, $rows, $validation) {
            $this->data->lock($context);
            $location = $request->validate([
                'zona_id' => ['required', 'integer', Rule::exists('zonas', 'id')->where('empresa_id', $context->empresa_id)],
                'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('empresa_id', $context->empresa_id)->where('zona_id', $request->input('zona_id'))],
            ]);
            $existing = $this->data->query('trabajadores', $context)->whereRaw('UPPER(tipo_documento) = ?', ['DNI'])->pluck('numero_documento')->all();
            $duplicates = array_count_values(array_column(array_column($rows, 'values'), 0));
            $result = [];
            foreach ($rows as $row) {
                [$dni, $names, $lastNames, $position, $date] = $row['values'];
                $values = $location + ['tipo_documento' => 'DNI', 'numero_documento' => $dni, 'nombres' => $names, 'apellidos' => $lastNames, 'puesto' => $position, 'fecha_ingreso' => $date];
                $errors = array_merge($row['errores'], $validation->validator($values)->errors()->all());
                if (($duplicates[$dni] ?? 0) > 1) {
                    $errors[] = 'El DNI está repetido en este archivo.';
                }
                if (in_array($dni, $existing, true)) {
                    $errors[] = 'El DNI ya está registrado en esta empresa, incluso si el trabajador está inactivo.';
                }
                $result[] = ['fila' => $row['fila'], 'datos' => $values, 'errores' => $errors];
            }
            $invalid = count(array_filter($result, fn ($row) => $row['errores'] !== []));
            if ($request->boolean('confirmar') && $invalid === 0) {
                foreach ($result as $row) {
                    $worker = $this->data->insert('trabajadores', $context, $row['datos']);
                    app(WorkerAssignments::class)->initial($request, $worker);
                    $this->data->audit($request, 'trabajadores', null, $worker, 'importar_excel');
                }

                return response()->json(['importados' => count($result), 'message' => count($result).' trabajadores importados correctamente.'], 201);
            }

            return response()->json(['total' => count($result), 'validos' => count($result) - $invalid, 'invalidos' => $invalid,
                'filas' => $result, 'message' => $invalid ? 'Corrige las filas indicadas. No se ha guardado ningún trabajador.' : 'Archivo validado. Confirma para registrar los trabajadores.']);
        });
    }
}
