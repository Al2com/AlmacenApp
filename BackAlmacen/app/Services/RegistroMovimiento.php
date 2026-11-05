<?php

namespace App\Services;

use App\Enums\TipoMovimiento;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\Ubicacion;
use App\Models\User;

/**
 * Registra movimientos y actualiza el saldo de stock de forma atómica.
 */
class RegistroMovimiento
{
    /**
     * Registra un movimiento y actualiza el saldo de la tabla stock en una
     * única transacción, bloqueando las filas implicadas. Rechaza, con
     * ReglaDeDominioException, las operaciones que dejarían el stock
     * incoherente o que violan las reglas de dominio (lote caducado, límites
     * de la ubicación, etc.).
     */
    public function registrar(
        TipoMovimiento $tipo,
        Lote $lote,
        float $cantidad,
        User $usuario,
        ?Ubicacion $origen = null,
        ?Ubicacion $destino = null,
        ?string $motivo = null,
    ): Movimiento {
        throw new \RuntimeException('Pendiente: se implementa en el paso 4.');
    }
}
