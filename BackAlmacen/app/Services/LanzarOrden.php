<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\OrdenProduccion;
use App\Models\User;

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
        throw new \RuntimeException('Pendiente: se implementa en el paso 3 de la fase 2.');
    }
}
