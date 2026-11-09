<?php

namespace App\Enums;

/**
 * Estados de una orden de producción (docs/diseno.md).
 */
enum EstadoOrden: string
{
    case Lanzada = 'lanzada';
    case EnPicking = 'en_picking';
    case Preparada = 'preparada';
    case Entregada = 'entregada';
    case Cancelada = 'cancelada';
}
