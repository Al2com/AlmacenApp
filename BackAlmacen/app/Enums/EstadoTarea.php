<?php

namespace App\Enums;

/**
 * Estados de una tarea de picking (docs/diseno.md).
 */
enum EstadoTarea: string
{
    case Pendiente = 'pendiente';
    case Confirmada = 'confirmada';
    case Incidencia = 'incidencia';
}
