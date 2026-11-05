<?php

use App\Http\Middleware\AsegurarUsuarioActivo;
use App\Http\Middleware\ForzarCambioDePassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        //
    })->create();
