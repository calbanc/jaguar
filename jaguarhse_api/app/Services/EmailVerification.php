<?php

namespace App\Services;

use App\Mail\VerifyHseEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Throwable;

class EmailVerification
{
    public function url(object $user): string
    {
        $signed = URL::temporarySignedRoute('api.v1.verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ], absolute: false);

        return rtrim(config('app.url'), '/').$signed;
    }

    public function send(int $userId): string
    {
        $user = DB::table('usuarios')->where('id', $userId)->where('activo', true)->first();
        if (! $user || $user->email_verified_at) {
            return 'verified';
        }
        $key = 'email-verification:'.$userId.':'.sha1($user->email);
        if (! RateLimiter::attempt($key, 1, fn () => true, 60)) {
            return 'throttled';
        }
        try {
            Mail::to($user->email)->send(new VerifyHseEmail($user->nombre, $this->url($user)));

            return 'sent';
        } catch (Throwable $error) {
            RateLimiter::clear($key);
            Log::warning('No se pudo enviar el correo de verificación.', ['usuario_id' => $userId, 'exception' => get_class($error)]);

            return 'failed';
        }
    }

    public function registrationMessage(string $status): string
    {
        return match ($status) {
            'sent' => 'Usuario guardado. Enviamos un correo para verificar su dirección antes de iniciar sesión.',
            'throttled' => 'Usuario guardado. Ya se envió un correo de verificación recientemente.',
            default => 'Usuario guardado, pero no pudimos enviar el correo de verificación. Puede reintentarlo desde el inicio de sesión.',
        };
    }
}
