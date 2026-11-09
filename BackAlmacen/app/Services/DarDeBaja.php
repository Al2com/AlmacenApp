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
 * Da de baja una cantidad de un lote en una ubicación (movimiento de baja), por
 * ejemplo un lote caducado. Solo se da de baja lo disponible, nunca lo
 * reservado. Motivo obligatorio.
 */
class DarDeBaja
{
    public function baja(Lote $lote, Ubicacion $ubicacion, float $cantidad, User $usuario, string $motivo): Movimiento
    {
        if ($cantidad <= 0) {
            throw new ReglaDeDominioException('La cantidad debe ser positiva.');
        }

        return DB::transaction(function () use ($lote, $ubicacion, $cantidad, $usuario, $motivo) {
            $saldo = Stock::query()
                ->where('lote_id', $lote->id)
                ->where('ubicacion_id', $ubicacion->id)
                ->lockForUpdate()
                ->first();

            $disponible = $saldo === null
                ? 0.0
                : (float) $saldo->cantidad - (float) $saldo->cantidad_reservada;

            if ($cantidad > $disponible) {
                throw new ReglaDeDominioException('No se puede dar de baja más de lo disponible.');
            }

            return (new RegistroMovimiento)->registrar(
                TipoMovimiento::Baja,
                $lote,
                $cantidad,
                $usuario,
                origen: $ubicacion,
                motivo: $motivo,
            );
        });
    }
}
