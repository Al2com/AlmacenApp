<?php

namespace App\Services;

use App\Enums\EstadoTarea;
use App\Exceptions\ReglaDeDominioException;
use App\Models\Stock;
use App\Models\TareaPicking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Abre una incidencia sobre una tarea de picking (no hay material o no coincide):
 * libera la reserva y marca la tarea como incidencia. No consume nada.
 */
class MarcarIncidencia
{
    public function marcar(TareaPicking $tarea, User $usuario): TareaPicking
    {
        if ($tarea->estado !== EstadoTarea::Pendiente) {
            throw new ReglaDeDominioException('Solo se puede abrir una incidencia sobre una tarea pendiente.');
        }

        return DB::transaction(function () use ($tarea, $usuario) {
            $saldo = Stock::query()
                ->where('lote_id', $tarea->lote_id)
                ->where('ubicacion_id', $tarea->ubicacion_id)
                ->lockForUpdate()
                ->first();

            if ($saldo !== null) {
                Stock::query()
                    ->where('lote_id', $tarea->lote_id)
                    ->where('ubicacion_id', $tarea->ubicacion_id)
                    ->update(['cantidad_reservada' => max(0, (float) $saldo->cantidad_reservada - (float) $tarea->cantidad)]);
            }

            $tarea->forceFill([
                'estado' => EstadoTarea::Incidencia,
                'asignada_a' => $usuario->id,
            ])->save();

            return $tarea;
        });
    }
}
