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
     * La última orden lanzada (aún sin empezar a picar), o null si no hay. La
     * demostración sirve para ver el recorrido ANTES de picar, así que solo
     * mira las lanzadas, no las que ya están en picking.
     */
    public function ultimaOrden(): ?OrdenProduccion
    {
        return OrdenProduccion::query()
            ->where('estado', EstadoOrden::Lanzada->value)
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
