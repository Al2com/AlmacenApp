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
 * Regulariza (ajuste) la cantidad contada de un lote en una ubicación. Corrige
 * un saldo que ya existe; no cambia de sitio el material ni lo coloca nuevo, así
 * que no pasa por la capacidad de la ubicación. Genera un movimiento de ajuste y
 * actualiza el saldo en la misma transacción.
 */
class RegularizarStock
{
    public function regularizar(Lote $lote, Ubicacion $ubicacion, float $cantidadNueva, User $usuario, string $motivo): Movimiento
    {
        if ($cantidadNueva < 0) {
            throw new ReglaDeDominioException('La cantidad no puede ser negativa.');
        }

        return DB::transaction(function () use ($lote, $ubicacion, $cantidadNueva, $usuario, $motivo) {
            $saldo = Stock::query()
                ->where('lote_id', $lote->id)
                ->where('ubicacion_id', $ubicacion->id)
                ->lockForUpdate()
                ->first();

            if ($saldo === null) {
                throw new ReglaDeDominioException('No hay stock de ese lote en esa ubicación.');
            }

            if ($cantidadNueva < (float) $saldo->cantidad_reservada) {
                throw new ReglaDeDominioException('La cantidad no puede quedar por debajo de lo reservado.');
            }

            $diferencia = $cantidadNueva - (float) $saldo->cantidad;
            if ($diferencia === 0.0) {
                throw new ReglaDeDominioException('No hay diferencia que regularizar.');
            }

            Stock::query()
                ->where('lote_id', $lote->id)
                ->where('ubicacion_id', $ubicacion->id)
                ->update(['cantidad' => $cantidadNueva]);

            return Movimiento::create([
                'tipo' => TipoMovimiento::Ajuste,
                'lote_id' => $lote->id,
                'ubicacion_origen_id' => $diferencia < 0 ? $ubicacion->id : null,
                'ubicacion_destino_id' => $diferencia > 0 ? $ubicacion->id : null,
                'cantidad' => abs($diferencia),
                'user_id' => $usuario->id,
                'motivo' => $motivo,
            ]);
        });
    }
}
