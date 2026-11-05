<?php

namespace App\Enums;

enum TipoMovimiento: string
{
    case Recepcion = 'recepcion';
    case Consumo = 'consumo';
    case Traslado = 'traslado';
    case Ajuste = 'ajuste';
    case Baja = 'baja';
}
