<?php

namespace App\Services;

use App\Enums\EstadoOrden;
use App\Enums\EstadoTarea;
use App\Enums\TipoMovimiento;
use App\Exceptions\ReglaDeDominioException;
use App\Models\OrdenLinea;
use App\Models\Stock;
use App\Models\TareaPicking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Confirma una tarea de picking: libera la reserva, descuenta el material con un
 * movimiento de consumo ligado a la tarea, actualiza lo preparado de la línea y
 * ajusta el estado de la orden.
 */
class ConfirmarTarea
{
    public function confirmar(TareaPicking $tarea, User $usuario): TareaPicking
    {
        if ($tarea->estado === EstadoTarea::Confirmada) {
            throw new ReglaDeDominioException('La tarea ya está confirmada.');
        }

        return DB::transaction(function () use ($tarea, $usuario) {
            $cantidad = (float) $tarea->cantidad;

            // Se libera la reserva antes de consumir: así la cantidad reservada
            // nunca queda por encima de la cantidad (restricción CHECK).
            $saldo = Stock::query()
                ->where('lote_id', $tarea->lote_id)
                ->where('ubicacion_id', $tarea->ubicacion_id)
                ->lockForUpdate()
                ->firstOrFail();

            Stock::query()
                ->where('lote_id', $tarea->lote_id)
                ->where('ubicacion_id', $tarea->ubicacion_id)
                ->update(['cantidad_reservada' => (float) $saldo->cantidad_reservada - $cantidad]);

            (new RegistroMovimiento)->registrar(
                TipoMovimiento::Consumo,
                $tarea->lote,
                $cantidad,
                $usuario,
                origen: $tarea->ubicacion,
                tareaPickingId: $tarea->id,
            );

            $tarea->forceFill([
                'estado' => EstadoTarea::Confirmada,
                'asignada_a' => $usuario->id,
                'confirmada_at' => now(),
            ])->save();

            $linea = $tarea->ordenLinea;
            $linea->forceFill([
                'cantidad_preparada' => (float) $linea->cantidad_preparada + $cantidad,
            ])->save();

            $this->actualizarEstadoOrden($tarea);

            return $tarea;
        });
    }

    private function actualizarEstadoOrden(TareaPicking $tarea): void
    {
        $orden = $tarea->ordenLinea->orden;

        $quedanTareas = TareaPicking::query()
            ->whereHas('ordenLinea', fn ($q) => $q->where('orden_id', $orden->id))
            ->where('estado', '!=', EstadoTarea::Confirmada->value)
            ->exists();

        $faltaMaterial = OrdenLinea::query()
            ->where('orden_id', $orden->id)
            ->whereColumn('cantidad_preparada', '<', 'cantidad_solicitada')
            ->exists();

        $orden->forceFill([
            'estado' => (! $quedanTareas && ! $faltaMaterial)
                ? EstadoOrden::Preparada
                : EstadoOrden::EnPicking,
        ])->save();
    }
}
