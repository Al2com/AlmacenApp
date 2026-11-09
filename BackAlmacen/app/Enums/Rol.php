<?php

namespace App\Enums;

/**
 * Los cinco roles fijos del sistema (docs/diseno.md). Las reglas de qué puede
 * hacer cada uno viven en las policies (paso 7); aquí solo está la identidad.
 */
enum Rol: string
{
    case Admin = 'admin';
    case GestorInventario = 'gestor_inventario';
    case OperadorProduccion = 'operador_produccion';
    case OperadorAlmacen = 'operador_almacen';
    case OperadorRecepcion = 'operador_recepcion';
}
