<?php

namespace App\Services;

use App\Models\TareaPicking;
use App\Models\User;

/**
 * Confirma una tarea de picking: descuenta el material reservado con un
 * movimiento de consumo, actualiza lo preparado de la línea y, cuando la orden
 * queda completa, la pasa a preparada.
 */
class ConfirmarTarea
{
    public function confirmar(TareaPicking $tarea, User $usuario): TareaPicking
    {
        throw new \RuntimeException('Pendiente: se implementa en el paso 3 de la fase 2.');
    }
}
