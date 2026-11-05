<?php

namespace App\Services;

use App\Enums\EstadoOrden;
use App\Enums\EstadoTarea;
use App\Models\Articulo;
use App\Models\OrdenLinea;
use App\Models\OrdenProduccion;
use App\Models\Stock;
use App\Models\TareaPicking;
use App\Models\Ubicacion;
use App\Models\User;
use App\Support\FechaSimulada;
use Illuminate\Support\Facades\DB;

/**
 * Lanza una orden de producción: crea la orden con sus líneas, reserva los
 * lotes por FEFO (saltando los caducados) y genera las tareas de picking
 * ordenadas por ruta. Si falta stock, reserva lo que hay y la línea queda con
 * su faltante.
 */
class LanzarOrden
{
    /**
     * @param  list<array{articulo: Articulo, cantidad: float}>  $lineas
     */
    public function lanzar(User $usuario, string $codigo, array $lineas): OrdenProduccion
    {
        return DB::transaction(function () use ($usuario, $codigo, $lineas) {
            $orden = OrdenProduccion::create([
                'codigo' => $codigo,
                'estado' => EstadoOrden::Lanzada,
                'user_id' => $usuario->id,
            ]);

            $reservas = [];
            foreach ($lineas as $linea) {
                $ordenLinea = OrdenLinea::create([
                    'orden_id' => $orden->id,
                    'articulo_id' => $linea['articulo']->id,
                    'cantidad_solicitada' => $linea['cantidad'],
                    'cantidad_preparada' => 0,
                ]);

                foreach ($this->reservarFefo($ordenLinea, $linea['articulo'], $linea['cantidad']) as $reserva) {
                    $reservas[] = $reserva;
                }
            }

            $this->generarTareasPorRuta($reservas);

            return $orden;
        });
    }

    /**
     * Reserva por FEFO el material disponible del artículo y devuelve los
     * tramos reservados (lote + ubicación + cantidad). No reserva caducados ni
     * más de lo disponible; si falta, devuelve menos de lo pedido.
     *
     * @return list<array{orden_linea_id: int, lote_id: int, ubicacion_id: int, cantidad: float}>
     */
    private function reservarFefo(OrdenLinea $ordenLinea, Articulo $articulo, float $cantidad): array
    {
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

        $reservas = [];
        $pendiente = $cantidad;

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

            $reservas[] = [
                'orden_linea_id' => $ordenLinea->id,
                'lote_id' => $fila->lote_id,
                'ubicacion_id' => $fila->ubicacion_id,
                'cantidad' => $reservar,
            ];

            $pendiente -= $reservar;
        }

        return $reservas;
    }

    /**
     * Crea las tareas en orden de ruta (pasillo, estantería, nivel): el
     * operador recorre el almacén una sola vez.
     *
     * @param  list<array{orden_linea_id: int, lote_id: int, ubicacion_id: int, cantidad: float}>  $reservas
     */
    private function generarTareasPorRuta(array $reservas): void
    {
        $ubicaciones = Ubicacion::query()
            ->whereIn('id', array_column($reservas, 'ubicacion_id'))
            ->get()
            ->keyBy('id');

        usort($reservas, function (array $a, array $b) use ($ubicaciones) {
            $ua = $ubicaciones[$a['ubicacion_id']];
            $ub = $ubicaciones[$b['ubicacion_id']];

            return [$ua->pasillo, $ua->estanteria, $ua->nivel]
                <=> [$ub->pasillo, $ub->estanteria, $ub->nivel];
        });

        $secuencia = 1;
        foreach ($reservas as $reserva) {
            TareaPicking::create([
                'orden_linea_id' => $reserva['orden_linea_id'],
                'lote_id' => $reserva['lote_id'],
                'ubicacion_id' => $reserva['ubicacion_id'],
                'cantidad' => $reserva['cantidad'],
                'secuencia' => $secuencia++,
                'estado' => EstadoTarea::Pendiente,
            ]);
        }
    }
}
