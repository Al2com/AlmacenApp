<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrdenController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\RecepcionController;
use App\Http\Controllers\TareaController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// Público: solo el login (con límite de intentos) y la cookie CSRF (/sanctum/csrf-cookie).
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Todo lo demás exige sesión y cuenta activa.
Route::middleware(['auth:sanctum', 'activo'])->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/password', [AuthController::class, 'cambiarPassword']);

    // Hasta cambiar la contraseña del primer acceso, no se llega al resto.
    Route::middleware('password.cambiada')->group(function (): void {
        Route::apiResource('usuarios', UsuarioController::class)->only(['index', 'store', 'update']);

        // Panel general: solo admin y gestor.
        Route::middleware('can:ver-panel')->prefix('panel')->group(function (): void {
            Route::get('/cifras', [PanelController::class, 'cifras']);
            Route::get('/estanteria', [PanelController::class, 'estanteria']);
            Route::get('/fefo', [PanelController::class, 'fefo']);
            Route::get('/movimientos', [PanelController::class, 'movimientos']);
            Route::get('/flujo', [PanelController::class, 'flujo']);
        });

        // Recepciones: solo admin y operador de recepción.
        Route::middleware('can:registrar-recepcion')->group(function (): void {
            Route::get('/recepciones/ubicacion-propuesta', [RecepcionController::class, 'ubicacionPropuesta']);
            Route::post('/recepciones', [RecepcionController::class, 'store']);
        });

        // Órdenes de producción (permisos por OrdenProduccionPolicy).
        Route::get('/ordenes', [OrdenController::class, 'index']);
        Route::post('/ordenes', [OrdenController::class, 'store']);
        Route::get('/ordenes/{orden}', [OrdenController::class, 'show']);
        Route::post('/ordenes/{orden}/cancelar', [OrdenController::class, 'cancelar']);
        Route::post('/ordenes/{orden}/entregar', [OrdenController::class, 'entregar']);

        // Tareas de picking (permisos por TareaPickingPolicy).
        Route::get('/tareas', [TareaController::class, 'index']);
        Route::post('/tareas/{tarea}/confirmar', [TareaController::class, 'confirmar']);
    });
});
