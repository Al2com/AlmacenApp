<?php

namespace App\Services;

use App\Enums\EstadoOrden;
use App\Enums\EstadoTarea;
use App\Models\Articulo;
use App\Models\OrdenLinea;
use App\Models\OrdenProduccion;
use App\Models\TareaPicking;
use App\Models\Ubicacion;
use App\Models\User;
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
    public function lanzar(User $usuario, string $codigo, array $lineas, ?string $nombre = null): OrdenProduccion
    {
        return DB::transaction(function () use ($usuario, $codigo, $lineas, $nombre) {
            $orden = OrdenProduccion::create([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'estado' => EstadoOrden::Lanzada,
                'user_id' => $usuario->id,
            ]);

            $reserva = new ReservaDeStock;
            $reservas = [];
            foreach ($lineas as $linea) {
                $ordenLinea = OrdenLinea::create([
                    'orden_id' => $orden->id,
                    'articulo_id' => $linea['articulo']->id,
                    'cantidad_solicitada' => $linea['cantidad'],
                    'cantidad_preparada' => 0,
                ]);

                // Reserva por FEFO los lotes del artículo; cada tramo reservado
                // se convierte en una tarea de picking.
                foreach ($reserva->reservar($linea['articulo'], $linea['cantidad'])['tramos'] as $tramo) {
                    $reservas[] = ['orden_linea_id' => $ordenLinea->id] + $tramo;
                }
            }

            $this->generarTareasPorRuta($reservas);

            return $orden;
        });
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
