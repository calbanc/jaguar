<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verifica tu correo</title></head>
<body style="margin:0;background:#f5f7f5;color:#263b30;font-family:Arial,Helvetica,sans-serif">
<div style="display:none;max-height:0;overflow:hidden">Confirma tu dirección para acceder a Jaguar HSE. El enlace vence en 60 minutos.</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:48px 20px">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;background:#fff;border:1px solid #e5eae6;border-radius:12px"><tr><td style="padding:40px 32px">
<p style="margin:0 0 36px;font-size:17px;font-weight:bold;letter-spacing:2px">JAGUAR<span style="color:#879477"> HSE</span></p>
<h1 style="font-size:26px;line-height:1.25;letter-spacing:-.6px;margin:0 0 20px">Verifica tu correo.</h1>
<p style="font-size:15px;line-height:1.7;margin:0 0 12px">Hola, {{ $name }}.</p>
<p style="font-size:15px;line-height:1.7;color:#637168;margin:0 0 28px">Confirma tu dirección de correo para acceder a tu cuenta de Jaguar HSE.</p>
<a href="{{ $verificationUrl }}" style="display:inline-block;padding:15px 24px;background:#344f3d;border-radius:6px;color:#fff;text-decoration:none;font-weight:bold;font-size:14px">Verificar mi correo</a>
<p style="font-size:13px;line-height:1.6;color:#788179;margin:26px 0">Este enlace vence en 60 minutos. Si no reconoces esta cuenta, puedes ignorar este mensaje.</p>
<div style="border-top:1px solid #edf0ed;padding-top:22px"><p style="font-size:12px;color:#788179;line-height:1.6;margin:0 0 8px">Si el botón no funciona, copia este enlace en tu navegador:</p><a href="{{ $verificationUrl }}" style="font-size:12px;line-height:1.6;color:#48604d;word-break:break-all">{{ $verificationUrl }}</a></div>
</td></tr></table><p style="font-size:12px;color:#8a938c;margin:24px 0 0">Jaguar HSE · Gestión que protege.</p>
</td></tr></table></body></html>
