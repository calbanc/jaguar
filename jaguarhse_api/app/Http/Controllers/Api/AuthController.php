<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HseAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request, HseAccess $access): JsonResponse
    {
        $input = $request->validate(['email' => 'required|email|max:190', 'password' => 'required|string|max:255',
            'empresa_codigo' => 'required|string|max:40', 'plataforma' => 'required|in:web,movil', 'dispositivo' => 'nullable|string|max:200']);
        $user = DB::table('usuarios')->where('email', $input['email'])->where('activo', true)->first();
        abort_unless($user && Hash::check($input['password'], $user->password_hash), 401, 'Credenciales inválidas.');
        $id = DB::table('accesos_aplicacion as a')->join('miembros_empresa as m', 'm.id', '=', 'a.miembro_id')
            ->join('empresas as e', 'e.id', '=', 'a.empresa_id')->join('plataformas as p', 'p.id', '=', 'a.plataforma_id')
            ->where('m.usuario_id', $user->id)->where('e.codigo', $input['empresa_codigo'])->where('p.codigo', $input['plataforma'])->value('a.id');
        abort_unless($id, 401, 'Credenciales inválidas.');
        $context = $access->context($id);
        if (! $user->email_verified_at) {
            return response()->json(['message' => 'Debes verificar tu correo electrónico antes de iniciar sesión.',
                'code' => 'EMAIL_NOT_VERIFIED'], 403);
        }
        $token = bin2hex(random_bytes(32));
        $expires = now()->addHours(config('hse.token_hours'));
        DB::table('sesiones')->insert(['empresa_id' => $context->empresa_id, 'acceso_id' => $id,
            'token_hash' => hash('sha256', $token), 'expira_at' => $expires, 'dispositivo' => $input['dispositivo'] ?? null,
            'ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['token' => $token, 'token_type' => 'Bearer', 'expira_at' => $expires->toISOString(), 'data' => $context]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->attributes->get('hse')]);
    }

    public function logout(Request $request): JsonResponse
    {
        DB::table('sesiones')->where('id', $request->attributes->get('hse')->sesion_id)->update(['revocado_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function password(Request $request): JsonResponse
    {
        $input = $request->validate(['password_actual' => 'required|string|max:255', 'password' => 'required|string|min:12|max:255|confirmed']);
        $context = $request->attributes->get('hse');
        DB::transaction(function () use ($input, $context) {
            $user = DB::table('usuarios')->where('id', $context->usuario_id)->lockForUpdate()->first();
            abort_unless(Hash::check($input['password_actual'], $user->password_hash), 422, 'Contraseña actual incorrecta.');
            DB::table('usuarios')->where('id', $user->id)->update(['password_hash' => Hash::make($input['password']), 'updated_at' => now()]);
            $members = DB::table('miembros_empresa')->where('usuario_id', $user->id)->pluck('id');
            $accesses = DB::table('accesos_aplicacion')->whereIn('miembro_id', $members)->pluck('id');
            DB::table('sesiones')->whereIn('acceso_id', $accesses)->whereNull('revocado_at')->update(['revocado_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['message' => 'Contraseña actualizada. Inicie sesión nuevamente en sus dispositivos.']);
    }

    public function sessions(Request $request): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $sessions = DB::table('sesiones as s')->join('accesos_aplicacion as a', 'a.id', '=', 's.acceso_id')
            ->where('s.empresa_id', $context->empresa_id)->where('a.miembro_id', $context->miembro_id)
            ->whereNull('s.revocado_at')->where('s.expira_at', '>', now())
            ->select('s.id', 's.dispositivo', 's.ip', 's.expira_at', 's.ultimo_uso_at', 's.created_at')
            ->orderByDesc('s.id')->paginate(25);

        return response()->json($sessions);
    }

    public function revokeSession(Request $request, int $id): JsonResponse
    {
        $context = $request->attributes->get('hse');
        $accesses = DB::table('accesos_aplicacion')->where('empresa_id', $context->empresa_id)->where('miembro_id', $context->miembro_id)->pluck('id');
        $session = DB::table('sesiones')->where('empresa_id', $context->empresa_id)->whereIn('acceso_id', $accesses)->where('id', $id);
        abort_unless($session->exists(), 404);
        $session->update(['revocado_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Sesión revocada.']);
    }
}
