<?php

namespace App\Services;

use App\Enums\EstadoOrden;
use App\Exceptions\ReglaDeDominioException;
use App\Models\OrdenProduccion;
use App\Models\Stock;
use App\Models\TareaPicking;
use Illuminate\Support\Facades\DB;

/**
 * Cancela una orden recién lanzada: libera las reservas de sus tareas, las
 * elimina (nunca llegaron a ejecutarse) y marca la orden como cancelada.
 */
class CancelarOrden
{
    public function cancelar(OrdenProduccion $orden): OrdenProduccion
    {
        if ($orden->estado !== EstadoOrden::Lanzada) {
            throw new ReglaDeDominioException('Solo se puede cancelar una orden recién lanzada, antes de empezar el picking.');
        }

        return DB::transaction(function () use ($orden) {
            $tareas = TareaPicking::query()
                ->whereHas('ordenLinea', fn ($q) => $q->where('orden_id', $orden->id))
                ->lockForUpdate()
                ->get();

            foreach ($tareas as $tarea) {
                $saldo = Stock::query()
                    ->where('lote_id', $tarea->lote_id)
                    ->where('ubicacion_id', $tarea->ubicacion_id)
                    ->lockForUpdate()
                    ->first();

                if ($saldo !== null) {
                    Stock::query()
                        ->where('lote_id', $tarea->lote_id)
                        ->where('ubicacion_id', $tarea->ubicacion_id)
                        ->update(['cantidad_reservada' => (float) $saldo->cantidad_reservada - (float) $tarea->cantidad]);
                }

                $tarea->delete();
            }

            $orden->forceFill(['estado' => EstadoOrden::Cancelada])->save();

            return $orden;
        });
    }
}
