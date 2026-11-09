<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\Stock;
use App\Support\FechaSimulada;
use Illuminate\Support\Facades\DB;

/**
 * Reserva y libera stock de un artículo sumando o restando a cantidad_reservada.
 * Una reserva no es un movimiento: solo compromete saldo disponible. Reparte por
 * FEFO (saltando caducados) y bloquea las filas para que dos reservas seguidas
 * no cuenten dos veces el mismo saldo.
 */
class ReservaDeStock
{
    /**
     * @return array{reservado: float, faltante: float, tramos: list<array{lote_id: int, ubicacion_id: int, cantidad: float}>}
     */
    public function reservar(Articulo $articulo, float $cantidad): array
    {
        return DB::transaction(function () use ($articulo, $cantidad) {
            $hoy = FechaSimulada::hoy()->toDateString();

            $disponibles = Stock::query()
                ->select('stock.*')
                ->join('lotes', 'lotes.id', '=', 'stock.lote_id')
                ->where('lotes.articulo_id', $articulo->id)
                ->whereRaw('stock.cantidad - stock.cantidad_reservada > 0')
                ->where(function ($q) use ($hoy) {
                    $q->whereNull('lotes.caducidad')->orWhere('lotes.caducidad', '>=', $hoy);
                })
                ->orderByRaw('lotes.caducidad is null')
                ->orderBy('lotes.caducidad')
                ->orderBy('lotes.fecha_recepcion')
                ->lockForUpdate()
                ->get();

            $pendiente = $cantidad;
            $tramos = [];

            foreach ($disponibles as $fila) {
                if ($pendiente <= 0) {
                    break;
                }

                $libre = (float) $fila->cantidad - (float) $fila->cantidad_reservada;
                $reservar = min($pendiente, $libre);

                Stock::query()
                    ->where('lote_id', $fila->lote_id)
                    ->where('ubicacion_id', $fila->ubicacion_id)
                    ->update(['cantidad_reservada' => (float) $fila->cantidad_reservada + $reservar]);

                $tramos[] = [
                    'lote_id' => $fila->lote_id,
                    'ubicacion_id' => $fila->ubicacion_id,
                    'cantidad' => $reservar,
                ];
                $pendiente -= $reservar;
            }

            return [
                'reservado' => $cantidad - $pendiente,
                'faltante' => $pendiente,
                'tramos' => $tramos,
            ];
        });
    }

    /**
     * Libera hasta $cantidad de lo reservado del artículo y devuelve lo liberado.
     */
    public function liberar(Articulo $articulo, float $cantidad): float
    {
        return DB::transaction(function () use ($articulo, $cantidad) {
            $reservadas = Stock::query()
                ->select('stock.*')
                ->join('lotes', 'lotes.id', '=', 'stock.lote_id')
                ->where('lotes.articulo_id', $articulo->id)
                ->where('stock.cantidad_reservada', '>', 0)
                ->orderByRaw('lotes.caducidad is null')
                ->orderByDesc('lotes.caducidad')
                ->lockForUpdate()
                ->get();

            $pendiente = $cantidad;

            foreach ($reservadas as $fila) {
                if ($pendiente <= 0) {
                    break;
                }

                $quita = min($pendiente, (float) $fila->cantidad_reservada);

                Stock::query()
                    ->where('lote_id', $fila->lote_id)
                    ->where('ubicacion_id', $fila->ubicacion_id)
                    ->update(['cantidad_reservada' => (float) $fila->cantidad_reservada - $quita]);

                $pendiente -= $quita;
            }

            return $cantidad - $pendiente;
        });
    }
}
