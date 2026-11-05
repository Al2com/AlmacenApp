<?php

namespace App\Http\Controllers;

use App\Http\Resources\ArticuloResource;
use App\Models\Articulo;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Catálogo de artículos para consulta (lo usan, por ejemplo, el formulario de
 * órdenes y el de recepciones). Cualquier rol autenticado puede leerlo.
 */
class ArticuloController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('ver-catalogo');

        return ArticuloResource::collection(
            Articulo::query()->where('activo', true)->orderBy('nombre')->get()
        );
    }
}
