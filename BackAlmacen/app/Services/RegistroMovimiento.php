<?php

namespace App\Services;

use App\Enums\TipoMovimiento;
use App\Exceptions\ReglaDeDominioException;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\Stock;
use App\Models\Ubicacion;
use App\Models\User;
use App\Support\FechaSimulada;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
        ?int $tareaPickingId = null,
    ): Movimiento {
        if ($origen === null && $destino === null) {
            throw new ReglaDeDominioException('Un movimiento necesita ubicación de origen o de destino.');
        }

        return DB::transaction(function () use ($tipo, $lote, $cantidad, $usuario, $origen, $destino, $motivo, $tareaPickingId) {
            if ($origen !== null) {
                $this->retirarDeOrigen($tipo, $lote, $cantidad, $origen);
            }

            if ($destino !== null) {
                $this->anadirAlDestino($lote, $cantidad, $destino);
            }

            return Movimiento::create([
                'tipo' => $tipo,
                'lote_id' => $lote->id,
                'ubicacion_origen_id' => $origen?->id,
                'ubicacion_destino_id' => $destino?->id,
                'cantidad' => $cantidad,
                'user_id' => $usuario->id,
                'motivo' => $motivo,
                'tarea_picking_id' => $tareaPickingId,
            ]);
        });
    }

    private function retirarDeOrigen(TipoMovimiento $tipo, Lote $lote, float $cantidad, Ubicacion $origen): void
    {
        if ($tipo === TipoMovimiento::Consumo && $this->estaCaducado($lote)) {
            throw new ReglaDeDominioException('Un lote caducado no se consume: debe darse de baja.');
        }

        $stock = Stock::query()
            ->where('lote_id', $lote->id)
            ->where('ubicacion_id', $origen->id)
            ->lockForUpdate()
            ->first();

        if ($stock === null || (float) $stock->cantidad < $cantidad) {
            throw new ReglaDeDominioException('No hay saldo suficiente en la ubicación de origen.');
        }

        $this->guardarSaldo($lote, $origen, (float) $stock->cantidad - $cantidad);
    }

    private function anadirAlDestino(Lote $lote, float $cantidad, Ubicacion $destino): void
    {
        $stock = Stock::query()
            ->where('lote_id', $lote->id)
            ->where('ubicacion_id', $destino->id)
            ->lockForUpdate()
            ->first();

        $cantidadActual = (float) Stock::query()
            ->where('ubicacion_id', $destino->id)
            ->sum('cantidad');

        if ($cantidadActual + $cantidad > (float) $destino->capacidad) {
            throw new ReglaDeDominioException('La ubicación no tiene capacidad suficiente.');
        }

        if ($stock === null) {
            $lotesDistintos = Stock::query()
                ->where('ubicacion_id', $destino->id)
                ->distinct()
                ->count('lote_id');

            if ($lotesDistintos >= $destino->max_lotes) {
                throw new ReglaDeDominioException('La ubicación no admite más lotes que su máximo.');
            }

            Stock::create([
                'lote_id' => $lote->id,
                'ubicacion_id' => $destino->id,
                'cantidad' => $cantidad,
                'cantidad_reservada' => 0,
            ]);

            return;
        }

        $this->guardarSaldo($lote, $destino, (float) $stock->cantidad + $cantidad);
    }

    /**
     * El saldo tiene clave primaria compuesta (lote + ubicación), que Eloquent
     * no sabe actualizar con save(); por eso se actualiza por esa pareja.
     */
    private function guardarSaldo(Lote $lote, Ubicacion $ubicacion, float $cantidad): void
    {
        Stock::query()
            ->where('lote_id', $lote->id)
            ->where('ubicacion_id', $ubicacion->id)
            ->update(['cantidad' => $cantidad]);
    }

    private function estaCaducado(Lote $lote): bool
    {
        $caducidad = $lote->caducidad;

        if (! $caducidad instanceof Carbon) {
            return false;
        }

        return $caducidad->lessThan(FechaSimulada::hoy());
    }
}
