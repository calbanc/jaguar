<?php

namespace App\Http\Middleware;

use App\Services\HseAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateHse
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        abort_unless($token && strlen($token) === 64, 401, 'Token requerido.');
        $session = DB::table('sesiones')->where('token_hash', hash('sha256', $token))
            ->whereNull('revocado_at')->where('expira_at', '>', now())->first();
        abort_unless($session, 401, 'Sesión vencida o revocada.');
        $context = app(HseAccess::class)->context($session->acceso_id);
        abort_unless($context->empresa_id === $session->empresa_id, 401);
        if (! DB::table('usuarios')->where('id', $context->usuario_id)->value('email_verified_at')) {
            return response()->json(['message' => 'Debes verificar tu correo electrónico antes de iniciar sesión.', 'code' => 'EMAIL_NOT_VERIFIED'], 403);
        }
        $context->sesion_id = $session->id;
        $request->attributes->set('hse', $context);
        DB::table('sesiones')->where('id', $session->id)->update(['ultimo_uso_at' => now(), 'updated_at' => now()]);

        return $next($request);
    }
}
