<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verificación de correo · Jaguar HSE</title><style>body{margin:0;background:#f5f7f5;color:#263b30;font:16px/1.7 Arial,sans-serif;min-height:100vh;display:grid;place-items:center}main{max-width:440px;padding:40px;margin:24px;background:white;border:1px solid #e5eae6;border-radius:12px}small{letter-spacing:2px}h1{font-size:28px;line-height:1.3}p{color:#637168}a,button{display:inline-block;background:#344f3d;color:white;padding:14px 24px;border:0;border-radius:6px;text-decoration:none;font:inherit;cursor:pointer}</style></head><body><main><small>JAGUAR HSE</small>
@if($status === 'confirm')
<h1>Confirma tu correo.</h1><p>Un último paso para activar el acceso a tu cuenta.</p><form method="POST" action="{{ $confirmationUrl }}"><button type="submit">Confirmar mi correo</button></form>
@elseif($status === 'verified')
<h1>Correo verificado.</h1><p>Tu dirección ha sido confirmada. Ya puedes iniciar sesión con tu correo y contraseña.</p><a href="{{ $loginUrl }}">Ir al inicio de sesión</a>
@else
<h1>El enlace no es válido.</h1><p>Puede haber vencido o tu correo haber cambiado. Vuelve al inicio de sesión para solicitar un nuevo enlace.</p><a href="{{ $loginUrl }}">Ir al inicio de sesión</a>
@endif
</main></body></html>
