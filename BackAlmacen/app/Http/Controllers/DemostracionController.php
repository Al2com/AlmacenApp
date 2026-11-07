<?php

namespace App\Http\Controllers;

use App\Http\Resources\TareaResource;
use App\Services\RutaDemostracion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Modo demostración: devuelve la ruta de picking del último pedido lanzado para
 * recrearla en el plano 3D. Solo lee (no confirma ni consume nada). Solo admin,
 * nunca en producción (gate «usar-demostracion»).
 */
class DemostracionController extends Controller
{
    public function __construct(private readonly RutaDemostracion $ruta) {}

    public function ruta(): JsonResponse
    {
        Gate::authorize('usar-demostracion');

        $orden = $this->ruta->ultimaOrden();

        if ($orden === null) {
            return response()->json(['orden' => null, 'tareas' => []]);
        }

        return response()->json([
            'orden' => $orden->codigo,
            'tareas' => TareaResource::collection($this->ruta->tareas($orden)),
        ]);
    }
}
