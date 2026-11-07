<?php

namespace App\Services;

use App\Enums\EstadoOrden;
use App\Models\OrdenProduccion;
use App\Models\TareaPicking;
use Illuminate\Support\Collection;

/**
 * Datos para recrear la ruta de picking en el modo demostración: la última
 * orden lanzada y sus tareas en orden de ruta. Solo lee; no confirma ni consume
 * nada (la demostración es una recreación visual, no un picking real).
 */
class RutaDemostracion
{
    /**
     * La última orden activa (lanzada o ya en picking), o null si no hay. La
     * demostración es solo visual, así que vale igual para una orden recién
     * lanzada que para una que ya se está preparando; las entregadas y
     * canceladas quedan fuera.
     */
    public function ultimaOrden(): ?OrdenProduccion
    {
        return OrdenProduccion::query()
            ->whereIn('estado', [EstadoOrden::Lanzada->value, EstadoOrden::EnPicking->value])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Las tareas de picking de una orden, en orden de ruta.
     *
     * @return Collection<int, TareaPicking>
     */
    public function tareas(OrdenProduccion $orden): Collection
    {
        return TareaPicking::query()
            ->select('tareas_picking.*')
            ->join('orden_lineas', 'orden_lineas.id', '=', 'tareas_picking.orden_linea_id')
            ->where('orden_lineas.orden_id', $orden->id)
            ->orderBy('tareas_picking.secuencia')
            ->with(['ordenLinea.orden', 'lote.articulo', 'ubicacion'])
            ->get();
    }
}
