<?php

namespace App\Http\Controllers;

use App\Http\Resources\TareaResource;
use App\Services\OperarioVirtual;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Modo demostración: un operario virtual pica la cola real de tareas con la
 * API real (nada de tocar el stock desde el front). Solo admin, nunca en
 * producción (gate «usar-demostracion»).
 */
class DemostracionController extends Controller
{
    public function __construct(private readonly OperarioVirtual $operario) {}

    public function estado(): JsonResponse
    {
        Gate::authorize('usar-demostracion');

        $tarea = $this->operario->siguienteTarea();

        return response()->json([
            'tarea' => $tarea ? new TareaResource($tarea) : null,
            'pendientes' => $this->operario->pendientes(),
        ]);
    }

    public function confirmar(): JsonResponse
    {
        Gate::authorize('usar-demostracion');

        $confirmada = $this->operario->confirmarSiguiente();
        $siguiente = $this->operario->siguienteTarea();

        return response()->json([
            'confirmada' => $confirmada ? new TareaResource($confirmada) : null,
            'siguiente' => $siguiente ? new TareaResource($siguiente) : null,
            'pendientes' => $this->operario->pendientes(),
        ]);
    }
}
