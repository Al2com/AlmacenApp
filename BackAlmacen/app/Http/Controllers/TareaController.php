<?php

namespace App\Http\Controllers;

use App\Enums\EstadoTarea;
use App\Http\Resources\TareaResource;
use App\Models\TareaPicking;
use App\Models\User;
use App\Services\ConfirmarTarea;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Tareas de picking (operador de almacén y admin). La cola pendiente se sirve
 * en orden de ruta. Permisos en TareaPickingPolicy.
 */
class TareaController extends Controller
{
    public function __construct(private readonly ConfirmarTarea $confirmar) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', TareaPicking::class);

        $tareas = TareaPicking::query()
            ->where('estado', EstadoTarea::Pendiente->value)
            ->with(['ordenLinea.orden', 'lote.articulo', 'ubicacion'])
            ->orderBy('secuencia')
            ->get();

        return TareaResource::collection($tareas);
    }

    public function confirmar(Request $request, TareaPicking $tarea): TareaResource
    {
        Gate::authorize('confirmar', $tarea);

        $usuario = $request->user();
        if (! $usuario instanceof User) {
            abort(401);
        }

        $this->confirmar->confirmar($tarea, $usuario);

        return new TareaResource($tarea->load(['ordenLinea.orden', 'lote.articulo', 'ubicacion']));
    }
}
