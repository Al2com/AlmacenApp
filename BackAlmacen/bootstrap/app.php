<?php

use App\Exceptions\ReglaDeDominioException;
use App\Http\Middleware\AsegurarUsuarioActivo;
use App\Http\Middleware\ForzarCambioDePassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum en modo SPA: la sesión viaja por cookie en las rutas de la API.
        $middleware->statefulApi();

        $middleware->alias([
            'activo' => AsegurarUsuarioActivo::class,
            'password.cambiada' => ForzarCambioDePassword::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Una regla de dominio violada es un 422, no un error del servidor.
        $exceptions->render(fn (ReglaDeDominioException $e, Request $request) => response()->json(['message' => $e->getMessage()], 422));
    })->create();
