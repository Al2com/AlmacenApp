<?php

namespace App\Services;

use App\Enums\EstadoOrden;
use App\Exceptions\ReglaDeDominioException;
use App\Models\OrdenProduccion;
use App\Models\Stock;
use App\Models\TareaPicking;
use Illuminate\Support\Facades\DB;

/**
 * Borra una orden de verdad (orden, líneas y tareas). Solo se permite cuando la
 * orden no ha movido stock de verdad: estando lanzada (se liberan sus reservas
 * antes) o ya cancelada. En picking, preparada o entregada no se borra, porque
 * tiene movimientos inmutables; para esas está el flujo normal.
 */
class BorrarOrden
{
    public function borrar(OrdenProduccion $orden): void
    {
        if (! in_array($orden->estado, [EstadoOrden::Lanzada, EstadoOrden::Cancelada], true)) {
            throw new ReglaDeDominioException('Solo se puede borrar una orden lanzada o cancelada, antes de empezar el picking.');
        }

        DB::transaction(function () use ($orden) {
            $tareas = TareaPicking::query()
                ->whereHas('ordenLinea', fn ($q) => $q->where('orden_id', $orden->id))
                ->lockForUpdate()
                ->get();

            foreach ($tareas as $tarea) {
                // Una orden lanzada tiene reservas vivas; liberarlas antes de
                // borrar la tarea (una cancelada ya no tiene ni tareas).
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

            $orden->lineas()->delete();
            $orden->delete();
        });
    }
}
