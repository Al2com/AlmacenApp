<?php

namespace App\Services;

use App\Enums\EstadoOrden;
use App\Enums\EstadoTarea;
use App\Models\OrdenProduccion;
use App\Models\TareaPicking;
use Illuminate\Support\Collection;

/**
 * Datos para recrear la ruta de picking en el modo demostración: la última
 * orden activa y sus tareas pendientes en orden de ruta. Solo lee; la
 * confirmación de cada línea la hace el DemostracionController con ConfirmarTarea.
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
     * Las tareas de picking pendientes de una orden, en orden de ruta. Solo las
     * pendientes: la demostración confirma de verdad, así que no debe recorrer
     * (ni reconfirmar) las que ya estén hechas.
     *
     * @return Collection<int, TareaPicking>
     */
    public function tareas(OrdenProduccion $orden): Collection
    {
        return TareaPicking::query()
            ->select('tareas_picking.*')
            ->join('orden_lineas', 'orden_lineas.id', '=', 'tareas_picking.orden_linea_id')
            ->where('orden_lineas.orden_id', $orden->id)
            ->where('tareas_picking.estado', EstadoTarea::Pendiente->value)
            ->orderBy('tareas_picking.secuencia')
            ->with(['ordenLinea.orden', 'lote.articulo', 'ubicacion'])
            ->get();
    }
}
