<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmailVerification;
use App\Services\HseAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

class EmailVerificationController extends Controller
{
    public function resend(Request $request, HseAccess $access, EmailVerification $verification): JsonResponse
    {
        $input = $request->validate(['email' => 'required|email|max:190', 'password' => 'required|string|max:255',
            'empresa_codigo' => 'required|string|max:40', 'plataforma' => 'required|in:web,movil']);
        $user = DB::table('usuarios')->where('email', $input['email'])->where('activo', true)->first();
        abort_unless($user && Hash::check($input['password'], $user->password_hash), 401, 'Credenciales inválidas.');
        $id = DB::table('accesos_aplicacion as a')->join('miembros_empresa as m', 'm.id', '=', 'a.miembro_id')
            ->join('empresas as e', 'e.id', '=', 'a.empresa_id')->join('plataformas as p', 'p.id', '=', 'a.plataforma_id')
            ->where('m.usuario_id', $user->id)->where('e.codigo', $input['empresa_codigo'])->where('p.codigo', $input['plataforma'])->value('a.id');
        abort_unless($id, 401, 'Credenciales inválidas.');
        $access->context($id);
        $status = $verification->send($user->id);

        return match ($status) {
            'sent' => response()->json(['message' => 'Te enviamos el enlace de verificación. Revisa tu bandeja de entrada y la carpeta de spam.']),
            'verified' => response()->json(['message' => 'Tu correo ya está verificado. Puedes iniciar sesión.']),
            'throttled' => response()->json(['message' => 'Espera un minuto antes de volver a enviar el correo.'], 429)->header('Retry-After', '60'),
            default => response()->json(['message' => 'No pudimos enviar el correo. Inténtalo nuevamente en unos minutos.', 'code' => 'VERIFICATION_DELIVERY_FAILED'], 503),
        };
    }

    public function verify(Request $request, int $id, string $hash): Response
    {
        $status = 'invalid';
        if (URL::hasValidSignature($request, absolute: false)) {
            $status = DB::transaction(function () use ($request, $id, $hash) {
                $user = DB::table('usuarios')->where('id', $id)->where('activo', true)->lockForUpdate()->first();
                if (! $user || ! hash_equals(sha1($user->email), $hash)) {
                    return 'invalid';
                }
                if ($user->email_verified_at) {
                    return 'verified';
                }
                if ($request->isMethod('post')) {
                    DB::table('usuarios')->where('id', $id)->update(['email_verified_at' => now(), 'updated_at' => now()]);

                    return 'verified';
                }

                return 'confirm';
            });
        }

        return response()->view('auth.verify-email', ['status' => $status, 'confirmationUrl' => $request->getRequestUri(),
            'loginUrl' => config('hse.frontend_url')], $status === 'invalid' ? 403 : 200)
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    }
}
