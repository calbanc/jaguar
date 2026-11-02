<?php

use App\Http\Middleware\AuthenticateHse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToPriorityList(ThrottleRequests::class, AuthenticateHse::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $error, Request $request) {
            if ($request->is('api/*') && in_array((int) ($error->errorInfo[1] ?? 0), [3819, 1406, 1264, 1292])) {
                return response()->json(['message' => 'Los valores no cumplen las restricciones del esquema.'], 422);
            }
            if ($request->is('api/*') && in_array((string) $error->getCode(), ['23000', '23505', '23503'])) {
                return response()->json(['message' => 'La operación viola una restricción de integridad.'], 409);
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
