<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HseAccess;
use App\Services\HseData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

class TrainingWorkflowController extends Controller
{
    public function __construct(private HseAccess $access, private HseData $data) {}

    private function context(Request $request, string $action): object
    {
        $input = $request->validate(['empresa_id' => 'required|integer|min:1']);
        $context = $request->attributes->get('hse');
        abort_unless((int) $input['empresa_id'] === (int) $context->empresa_id, 403);
        $this->access->authorize($context, 'capacitaciones', $action);

        return $context;
    }

    private function planilla(object $context, int $id): object
    {
        $row = DB::table('capacitacion')->where('empresa_id', $context->empresa_id)->where('id', $id)->first();
        abort_unless($row, 404, 'Planilla no encontrada.');

        return $row;
    }

    public function options(Request $request): JsonResponse
    {
        $context = $this->context($request, 'ver');
        $result = [];
        foreach (['tema_capacitacion', 'lugar_capacitacion', 'capacitador', 'empresa_capacitacion'] as $table) {
            $result[$table] = DB::table($table)->where('empresa_id', $context->empresa_id)->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
        }
        $result['responsables'] = DB::table('miembros_empresa as m')->join('usuarios as u', 'u.id', '=', 'm.usuario_id')
            ->where('m.empresa_id', $context->empresa_id)->where('m.activo', true)->where('u.activo', true)->orderBy('u.nombre')->get(['u.id', 'u.nombre']);
        $result['trabajadores'] = DB::table('trabajadores')->where('empresa_id', $context->empresa_id)->where('activo', true)->orderBy('apellidos')->get(['id', 'numero_documento', 'nombres', 'apellidos', 'puesto']);
        $result['siguiente_numero'] = (int) DB::table('capacitacion')->where('empresa_id', $context->empresa_id)->max('numero_planilla') + 1;
        $result['responsable_actual'] = $context->usuario_id;
        $result['fecha'] = now($context->zona_horaria)->toDateString();

        return response()->json($result);
    }

    public function byNumber(Request $request, int $numero): JsonResponse
    {
        $context = $this->context($request, 'ver');
        $row = DB::table('capacitacion')->where('empresa_id', $context->empresa_id)->where('numero_planilla', $numero)->first();
        abort_unless($row, 404, 'No existe esta planilla en la empresa.');

        return response()->json(['data' => $row]);
    }

    public function participants(Request $request, int $id): JsonResponse
    {
        $context = $this->context($request, 'ver');
        $this->planilla($context, $id);
        $rows = DB::table('capacitacion_participantes as p')->join('trabajadores as t', function ($join) {
            $join->on('t.id', '=', 'p.trabajador_id')->on('t.empresa_id', '=', 'p.empresa_id');
        })->where('p.empresa_id', $context->empresa_id)->where('p.capacitacion_id', $id)->where('p.activo', true)
            ->orderBy('t.apellidos')->get(['p.id', 'p.trabajador_id', 't.numero_documento', 't.nombres', 't.apellidos', 't.puesto', 'p.firma_fecha', 't.firma_almacenada as firma_disponible']);

        return response()->json(['data' => $rows]);
    }

    public function addParticipant(Request $request, int $id): JsonResponse
    {
        $context = $this->context($request, 'editar');
        $input = $request->validate(['trabajador_id' => ['required', 'integer', Rule::exists('trabajadores', 'id')->where('empresa_id', $context->empresa_id)->where('activo', true)]]);

        return DB::transaction(function () use ($context, $id, $input, $request): JsonResponse {
            $this->data->lock($context);
            abort_unless($this->planilla($context, $id)->activo, 422, 'La planilla está anulada.');
            $key = ['empresa_id' => $context->empresa_id, 'capacitacion_id' => $id, 'trabajador_id' => $input['trabajador_id']];
            $before = DB::table('capacitacion_participantes')->where($key)->first();
            abort_if($before && $before->activo, 422, 'El trabajador ya está registrado.');
            DB::table('capacitacion_participantes')->updateOrInsert($key, ['activo' => true, 'updated_at' => now()]);
            $after = DB::table('capacitacion_participantes')->where($key)->first();
            $this->data->audit($request, 'capacitacion_participantes', $before, $after, 'crear');

            return response()->json(['data' => ['id' => $after->id]], 201);
        });
    }

    private function participant(object $context, int $id, int $participant): object
    {
        $row = DB::table('capacitacion_participantes')->where('empresa_id', $context->empresa_id)->where('capacitacion_id', $id)->where('id', $participant)->where('activo', true)->first();
        abort_unless($row, 404);

        return $row;
    }

    public function removeParticipant(Request $request, int $id, int $participant): JsonResponse
    {
        $context = $this->context($request, 'editar');

        return DB::transaction(function () use ($request, $context, $id, $participant): JsonResponse {
            $this->data->lock($context);
            abort_unless($this->planilla($context, $id)->activo, 422);
            $before = $this->participant($context, $id, $participant);
            DB::table('capacitacion_participantes')->where('id', $before->id)->update(['activo' => false, 'updated_at' => now()]);
            $after = clone $before;
            $after->activo = false;
            $this->data->audit($request, 'capacitacion_participantes', $before, $after, 'eliminar');

            return response()->json(['message' => 'Participante retirado.']);
        });
    }

    public function sign(Request $request, int $id, int $participant): JsonResponse
    {
        $context = $this->context($request, 'editar');
        $this->planilla($context, $id);
        $this->participant($context, $id, $participant);
        $request->validate([
            'usar_guardada' => 'sometimes|boolean',
            'reemplazar' => 'sometimes|boolean',
            'firma' => [$request->boolean('usar_guardada') ? 'prohibited' : 'required', 'file', 'image', 'mimetypes:image/png', 'max:2048', 'dimensions:min_width=10,min_height=10,max_width=4096,max_height=4096'],
        ]);
        $file = $request->file('firma');
        $scanner = config('hse.clamscan_path');
        abort_if($file && app()->environment('production') && ! $scanner, 503, 'El análisis antimalware no está configurado.');
        if ($file && $scanner) {
            $scan = new Process([$scanner, '--no-summary', $file->getRealPath()]);
            $scan->setTimeout(60);
            $scan->run();
            abort_if($scan->getExitCode() === 1, 422, 'Archivo no válido.');
            abort_unless($scan->isSuccessful(), 503, 'El análisis antimalware no está disponible.');
        }
        $path = null;
        try {
            return DB::transaction(function () use ($request, $context, $id, $participant, $file, &$path): JsonResponse {
                $this->data->lock($context);
                $planilla = $this->planilla($context, $id);
                abort_unless($planilla->activo, 422, 'La planilla está anulada.');
                $before = $this->participant($context, $id, $participant);
                abort_if($before->firma_ruta && ! $request->boolean('reemplazar'), 422, 'Confirma que deseas reemplazar la firma de esta planilla.');
                $worker = DB::table('trabajadores')->where('empresa_id', $context->empresa_id)->where('id', $before->trabajador_id)->first();
                $extension = 'png';
                $content = null;
                if ($request->boolean('usar_guardada')) {
                    abort_unless($worker->firma_almacenada && $worker->firma_ruta && Storage::disk('firmas')->exists($worker->firma_ruta), 422, 'El trabajador no tiene una firma guardada disponible.');
                    $content = Storage::disk('firmas')->get($worker->firma_ruta);
                    $extension = strtolower(pathinfo($worker->firma_ruta, PATHINFO_EXTENSION));
                    abort_unless(in_array($extension, ['png', 'jpg', 'jpeg'], true), 422, 'Formato de firma no válido.');
                }
                $document = str_replace('.', '%2E', rawurlencode($worker->numero_documento));
                $path = $context->empresa_id.'/'.$planilla->numero_planilla.'/'.$worker->id.'/'.Str::uuid().'/'.$document.'.'.$extension;
                $stored = $content !== null ? Storage::disk('firmas_capacitaciones')->put($path, $content) : Storage::disk('firmas_capacitaciones')->putFileAs(dirname($path), $file, basename($path));
                abort_unless($stored, 500, 'No se pudo guardar la firma.');
                DB::table('capacitacion_participantes')->where('id', $participant)->update(['firma_ruta' => $path, 'firma_bytes' => $content !== null ? strlen($content) : $file->getSize(), 'firma_fecha' => now(), 'updated_at' => now()]);
                $after = $this->participant($context, $id, $participant);
                $this->data->audit($request, 'capacitacion_participantes', $before, $after, $request->boolean('usar_guardada') ? 'reutilizar_firma' : 'guardar_firma');

                return response()->json(['message' => 'Firma guardada.'], 201);
            });
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('firmas_capacitaciones')->delete($path);
            }
            throw $error;
        }
    }

    public function signature(Request $request, int $id, int $participant): StreamedResponse
    {
        $context = $this->context($request, 'ver');
        $this->planilla($context, $id);
        $row = $this->participant($context, $id, $participant);
        $request->validate(['fuente' => 'sometimes|in:trabajador']);
        if ($request->input('fuente') === 'trabajador') {
            $worker = DB::table('trabajadores')->where('empresa_id', $context->empresa_id)->where('id', $row->trabajador_id)->first();
            abort_unless($worker->firma_almacenada && $worker->firma_ruta && Storage::disk('firmas')->exists($worker->firma_ruta), 404);

            return Storage::disk('firmas')->response($worker->firma_ruta, basename($worker->firma_ruta), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }
        abort_unless($row->firma_ruta && Storage::disk('firmas_capacitaciones')->exists($row->firma_ruta), 404);

        return Storage::disk('firmas_capacitaciones')->response($row->firma_ruta, basename($row->firma_ruta), ['Content-Type' => str_ends_with($row->firma_ruta, '.png') ? 'image/png' : 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
