<?php

namespace App\Http\Controllers;

use App\Services\Actividad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feed de actividad en vivo. Solo admin (gate «ver-actividad» en la ruta) y con
 * su propio límite de peticiones, porque el front lo consulta cada pocos
 * segundos. Devuelve lo nuevo desde la marca de tiempo «desde».
 */
class ActividadController extends Controller
{
    public function __construct(private readonly Actividad $actividad) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->actividad->desde($request->string('desde')->toString() ?: null)
        );
    }
}
