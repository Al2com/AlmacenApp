<?php

namespace App\Http\Controllers;

use App\Http\Resources\TareaResource;
use App\Models\TareaPicking;
use App\Models\User;
use App\Services\ConfirmarTarea;
use App\Services\RutaDemostracion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Modo demostración: da la ruta de picking del último pedido activo para
 * recorrerla en el plano 3D, y confirma cada línea de verdad por la API (un
 * movimiento real, a nombre del usuario que lanza la demo). Solo admin, nunca
 * en producción (gate «usar-demostracion»).
 */
class DemostracionController extends Controller
{
    public function __construct(
        private readonly RutaDemostracion $ruta,
        private readonly ConfirmarTarea $confirmarTarea,
    ) {}

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

    public function confirmar(Request $request, TareaPicking $tarea): TareaResource
    {
        Gate::authorize('usar-demostracion');

        $usuario = $request->user();
        if (! $usuario instanceof User) {
            abort(401);
        }

        $this->confirmarTarea->confirmar($tarea, $usuario);

        return new TareaResource($tarea->load(['ordenLinea.orden', 'lote.articulo', 'ubicacion']));
    }
}
