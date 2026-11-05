<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Única fuente de la fecha de «hoy» para la lógica de caducidad. El resto del
 * dominio pregunta aquí en vez de usar now() o Carbon::today() directamente.
 *
 * De momento devuelve el día actual del sistema (respetando el reloj de los
 * tests). En el paso 5 pasará a leer la fecha simulada que configura el admin.
 */
class FechaSimulada
{
    public static function hoy(): Carbon
    {
        return Carbon::today();
    }
}
