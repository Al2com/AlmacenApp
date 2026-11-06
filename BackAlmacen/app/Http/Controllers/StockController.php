<?php

namespace App\Http\Controllers;

use App\Services\ConsultaStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Consulta de stock y lotes. Lectura para cualquier rol autenticado (gate
 * «ver-stock»); admite filtrar por artículo y por estado de frescura.
 */
class StockController extends Controller
{
    public function __construct(private readonly ConsultaStock $consulta) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('ver-stock');

        $articuloId = $request->integer('articulo_id') ?: null;
        $estado = $request->string('estado')->toString() ?: null;

        return response()->json($this->consulta->listar($articuloId, $estado));
    }
}
