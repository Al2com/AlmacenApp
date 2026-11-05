<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\Lote;

/**
 * Propone el orden en que deben consumirse los lotes de un artículo según el
 * criterio FEFO.
 */
class SeleccionFefo
{
    /**
     * Devuelve los lotes del artículo ordenados por criterio FEFO: primero el
     * que caduca antes; si empatan, el que entró antes; los lotes sin caducidad
     * van al final, también por fecha de entrada.
     *
     * @return list<Lote>
     */
    public function ordenar(Articulo $articulo): array
    {
        throw new \RuntimeException('Pendiente: se implementa en el paso 4.');
    }
}
