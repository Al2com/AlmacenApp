<?php

namespace App\Http\Controllers;

use App\Http\Resources\UbicacionResource;
use App\Models\Ubicacion;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Catálogo de ubicaciones activas para consulta (p. ej. elegir el destino de un
 * traslado). Lectura para cualquier rol autenticado (gate «ver-stock»).
 */
class UbicacionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('ver-stock');

        return UbicacionResource::collection(
            Ubicacion::query()
                ->where('activa', true)
                ->orderBy('pasillo')
                ->orderBy('estanteria')
                ->orderBy('nivel')
                ->get()
        );
    }
}
