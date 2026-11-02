<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HseAccess;
use App\Services\HseData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

class FileController extends Controller
{
    public function __construct(private HseData $data, private HseAccess $access) {}

    public function upload(Request $request): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'documentos', 'crear');

        return $this->storeFile($request, $context);
    }

    public function uploadEquipmentSheet(Request $request, int $id): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'almacenes', 'editar');
        $this->data->find('epp_catalogo', $context, $id);
        $request->merge(['clasificacion' => 'interno']);

        return $this->storeFile($request, $context, $id);
    }

    public function uploadImplementPhoto(Request $request, int $id): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'implementos', 'editar');
        $request->validate(['empresa_id' => 'required|integer', 'archivo' => 'required|image|mimes:jpeg,png|max:5120', 'inspeccion_id' => 'nullable|integer']);
        abort_unless((int) $request->input('empresa_id') === (int) $context->empresa_id, 403);
        $this->data->find('implementos', $context, $id);
        if ($request->filled('inspeccion_id')) {
            $inspection = $this->data->find('implemento_inspecciones', $context, $request->integer('inspeccion_id'));
            abort_unless((int) $inspection->implemento_id === $id, 422, 'La inspección no corresponde a este implemento.');
        }
        $request->merge(['clasificacion' => 'interno']);

        return $this->storeFile($request, $context, safetyId: $id);
    }

    public function implementPhoto(Request $request, int $id): StreamedResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'implementos', 'ver');
        $photo = $this->data->find('implemento_archivos', $context, $id);

        return $this->downloadFile($context, $photo->archivo_id);
    }

    private function storeFile(Request $request, object $context, ?int $equipmentId = null, ?int $safetyId = null): JsonResponse
    {
        $request->validate(['archivo' => 'required|file|mimetypes:application/pdf,image/jpeg,image/png|max:'.config('hse.upload_max_kb'),
            'clasificacion' => 'sometimes|in:interno,confidencial,restringido']);
        $file = $request->file('archivo');
        $scanner = config('hse.clamscan_path');
        abort_if(app()->environment('production') && ! $scanner, 503, 'El análisis antimalware no está configurado.');
        if ($scanner) {
            $scan = new Process([$scanner, '--no-summary', $file->getRealPath()]);
            $scan->setTimeout(60);
            $scan->run();
            abort_if($scan->getExitCode() === 1, 422, 'El archivo no superó el análisis antimalware.');
            abort_unless($scan->isSuccessful(), 503, 'El servicio antimalware no está disponible.');
        }
        $path = null;
        try {
            return DB::transaction(function () use ($request, $context, $file, $equipmentId, $safetyId, &$path) {
                $this->data->lock($context);
                $used = $this->data->query('archivos', $context)->whereNull('eliminado_at')->sum('bytes') + $this->data->query('trabajadores', $context)->sum('firma_bytes');
                $limit = $context->contrato->limite_almacenamiento_mb;
                abort_if($limit !== null && $used + $file->getSize() > $limit * 1024 * 1024, 422, 'Almacenamiento contratado excedido.');
                $path = $file->store('empresas/'.$context->empresa_id, 'hse');
                abort_unless($path, 500, 'No se pudo almacenar el archivo.');
                $record = $this->data->insert('archivos', $context, ['subido_por' => $context->miembro_id,
                    'nombre_original' => basename($file->getClientOriginalName()), 'disco' => 'hse', 'ruta' => $path,
                    'mime_type' => $file->getMimeType(), 'bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()),
                    'clasificacion' => $request->input('clasificacion', 'interno')]);
                $this->data->audit($request, 'archivos', null, $record, 'subir');
                if ($equipmentId !== null) {
                    $before = $this->data->find('epp_catalogo', $context, $equipmentId);
                    $after = $this->data->update('epp_catalogo', $context, $equipmentId, ['ficha_archivo_id' => $record->id]);
                    $this->data->audit($request, 'epp_catalogo', $before, $after, 'ficha_tecnica');
                }

                if ($safetyId !== null) {
                    $photoId = DB::table('implemento_archivos')->insertGetId(['empresa_id' => $context->empresa_id,
                        'implemento_id' => $safetyId, 'inspeccion_id' => $request->input('inspeccion_id'), 'archivo_id' => $record->id,
                        'created_at' => now(), 'updated_at' => now()]);
                    $this->data->audit($request, 'implemento_archivos', null, DB::table('implemento_archivos')->find($photoId), 'crear');
                }

                return response()->json(['data' => $this->data->present('archivos', $record)], 201);
            });
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('hse')->delete($path);
            }
            throw $error;
        }
    }

    public function download(Request $request, int $id): StreamedResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'documentos', 'ver');
        $this->access->authorize($context, 'documentos', 'descargar');

        return $this->downloadFile($context, $id);
    }

    public function downloadEquipmentSheet(Request $request, int $id): StreamedResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'almacenes', 'ver');
        $this->access->authorize($context, 'almacenes', 'descargar');
        $equipment = $this->data->find('epp_catalogo', $context, $id);
        abort_unless($equipment->ficha_archivo_id, 404, 'Este EPP no tiene ficha técnica.');

        return $this->downloadFile($context, $equipment->ficha_archivo_id);
    }

    private function downloadFile(object $context, int $id): StreamedResponse
    {
        $file = $this->data->find('archivos', $context, $id);
        abort_if($file->eliminado_at !== null, 404);
        if ($file->clasificacion !== 'interno') {
            $this->access->authorize($context, 'documentos', 'confidenciales');
        }
        abort_unless($file->disco === 'hse' && Storage::disk('hse')->exists($file->ruta), 404);

        return Storage::disk('hse')->download($file->ruta, $file->nombre_original, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
