<?php

namespace App\Http\Controllers;

use App\Http\Resources\ArticuloResource;
use App\Models\Articulo;
use App\Models\Stock;
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

        $articulos = Articulo::query()->where('activo', true)->orderBy('nombre')->get();

        // Stock disponible por artículo (cantidad menos reservado, sumando sus
        // lotes), en una sola consulta.
        $disponibles = Stock::query()
            ->join('lotes', 'lotes.id', '=', 'stock.lote_id')
            ->groupBy('lotes.articulo_id')
            ->selectRaw('lotes.articulo_id as articulo_id, SUM(stock.cantidad - stock.cantidad_reservada) as disponible')
            ->pluck('disponible', 'articulo_id');

        $articulos->each(function (Articulo $articulo) use ($disponibles): void {
            $articulo->disponible = (float) ($disponibles[$articulo->id] ?? 0);
        });

        return ArticuloResource::collection($articulos);
    }
}
