<?php

namespace App\Services;

use App\Enums\TipoMovimiento;
use App\Exceptions\ReglaDeDominioException;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\Stock;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mueve cantidad de un lote de una ubicación a otra con un movimiento de
 * traslado. Solo se traslada lo disponible (nunca lo reservado) y nunca un lote
 * caducado. La fecha de entrada del lote no cambia: un traslado no rejuvenece el
 * material. Las comprobaciones de destino (activa, capacidad y máximo de lotes)
 * y el cambio de saldo los hace RegistroMovimiento.
 */
class TrasladarStock
{
    public function trasladar(Lote $lote, Ubicacion $origen, Ubicacion $destino, float $cantidad, User $usuario): Movimiento
    {
        if ($cantidad <= 0) {
            throw new ReglaDeDominioException('La cantidad debe ser positiva.');
        }

        return DB::transaction(function () use ($lote, $origen, $destino, $cantidad, $usuario) {
            if (! $destino->activa) {
                throw new ReglaDeDominioException('La ubicación de destino no está activa.');
            }

            $saldo = Stock::query()
                ->where('lote_id', $lote->id)
                ->where('ubicacion_id', $origen->id)
                ->lockForUpdate()
                ->first();

            $disponible = $saldo === null
                ? 0.0
                : (float) $saldo->cantidad - (float) $saldo->cantidad_reservada;

            if ($cantidad > $disponible) {
                throw new ReglaDeDominioException('Solo se puede trasladar la cantidad disponible.');
            }

            // RegistroMovimiento rechaza el lote caducado y comprueba capacidad y
            // máximo de lotes en el destino.
            return (new RegistroMovimiento)->registrar(
                TipoMovimiento::Traslado,
                $lote,
                $cantidad,
                $usuario,
                origen: $origen,
                destino: $destino,
            );
        });
    }
}
