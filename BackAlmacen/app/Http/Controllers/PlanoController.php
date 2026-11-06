<?php

namespace App\Http\Controllers;

use App\Services\Panel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Plano del almacén (lo usan la vista 2D del panel y la vista 3D). Mismos datos
 * y mismos estados de posición; el acceso (admin, gestor, producción) lo
 * controla el gate «ver-plano».
 */
class PlanoController extends Controller
{
    public function __construct(private readonly Panel $panel) {}

    public function estanteria(): JsonResponse
    {
        Gate::authorize('ver-plano');

        return response()->json($this->panel->estanteria());
    }
}
