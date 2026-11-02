<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HseAccess;
use App\Services\HseData;
use App\Services\WorkerSignatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

class WorkerSignatureController extends Controller
{
    public function __construct(private HseAccess $access, private HseData $data, private WorkerSignatures $signatures) {}

    public function store(Request $request, int $worker): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'organizacion', 'editar');
        $this->data->find('trabajadores', $context, $worker);
        $request->validate(['firma' => 'required|file|image|mimetypes:image/png,image/jpeg|max:2048|dimensions:max_width=4096,max_height=4096']);
        $file = $request->file('firma');
        $scanner = config('hse.clamscan_path');
        abort_if(app()->environment('production') && ! $scanner, 503, 'El análisis antimalware no está configurado.');
        if ($scanner) {
            $scan = new Process([$scanner, '--no-summary', $file->getRealPath()]);
            $scan->setTimeout(60);
            $scan->run();
            abort_if($scan->getExitCode() === 1, 422, 'El archivo no superó el análisis antimalware.');
            abort_unless($scan->isSuccessful(), 503, 'El servicio antimalware no está disponible.');
        }

        return $this->signatures->transaction(function () use ($request, $context, $worker, $file) {
            $this->data->lock($context);
            $before = $this->data->find('trabajadores', $context, $worker);
            $used = $this->data->query('archivos', $context)->whereNull('eliminado_at')->sum('bytes') + $this->data->query('trabajadores', $context)->sum('firma_bytes');
            $limit = $context->contrato->limite_almacenamiento_mb;
            abort_if($limit !== null && $used - $before->firma_bytes + $file->getSize() > $limit * 1024 * 1024, 422, 'Almacenamiento contratado excedido.');
            $path = $this->signatures->path($before, $file->getMimeType() === 'image/png' ? 'png' : 'jpg');
            $this->signatures->track($path, $before->firma_ruta);
            $stored = Storage::disk('firmas')->putFileAs(dirname($path), $file, basename($path));
            abort_unless($stored, 500, 'No se pudo guardar la firma.');
            $after = $this->data->update('trabajadores', $context, $worker, [
                'firma_almacenada' => true, 'firma_ruta' => $path, 'firma_bytes' => $file->getSize(), 'firma_actualizada_at' => now(),
            ]);
            $this->data->audit($request, 'trabajadores', $before, $after, 'guardar_firma');

            return response()->json(['data' => $this->data->present('trabajadores', $after)], 201);
        });
    }

    public function show(Request $request, int $worker): StreamedResponse
    {
        $context = $request->attributes->get('hse');
        $this->access->authorize($context, 'organizacion', 'ver');
        $this->access->authorize($context, 'organizacion', 'descargar');
        $record = $this->data->find('trabajadores', $context, $worker);
        abort_unless($record->firma_almacenada && $record->firma_ruta && Storage::disk('firmas')->exists($record->firma_ruta), 404, 'El trabajador no tiene firma almacenada.');

        return Storage::disk('firmas')->download($record->firma_ruta, 'firma-'.rawurlencode($record->numero_documento).'.'.pathinfo($record->firma_ruta, PATHINFO_EXTENSION), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
