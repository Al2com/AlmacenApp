<?php

namespace App\Http\Controllers;

use App\Http\Resources\MovimientoResource;
use App\Services\Panel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * API de lectura del panel general. El acceso (admin y gestor) lo controla el
 * gate «ver-panel» en la ruta.
 */
class PanelController extends Controller
{
    public function __construct(private readonly Panel $panel) {}

    public function cifras(): JsonResponse
    {
        return response()->json($this->panel->cifras());
    }

    public function estanteria(): JsonResponse
    {
        return response()->json($this->panel->estanteria());
    }

    public function fefo(): JsonResponse
    {
        return response()->json($this->panel->fefo());
    }

    public function movimientos(): AnonymousResourceCollection
    {
        return MovimientoResource::collection($this->panel->ultimosMovimientos());
    }

    public function flujo(): JsonResponse
    {
        return response()->json($this->panel->flujoHorario());
    }
}
