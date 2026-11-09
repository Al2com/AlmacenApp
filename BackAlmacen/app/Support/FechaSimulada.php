<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Única fuente de la fecha de «hoy» para la lógica de caducidad. El resto del
 * dominio pregunta aquí en vez de usar now() o Carbon::today() directamente.
 *
 * El admin puede fijar un día concreto para simular el paso del tiempo; si no
 * hay ninguno fijado, «hoy» es el día real. La fecha se guarda en el almacén
 * de caché: el modelo de datos (docs/er.md) deja esta configuración fuera del
 * borrador, así que no se crea ninguna tabla para ella.
 */
class FechaSimulada
{
    private const CLAVE = 'fecha_simulada';

    public static function hoy(): Carbon
    {
        $fijada = Cache::get(self::CLAVE);

        return is_string($fijada)
            ? Carbon::parse($fijada)->startOfDay()
            : Carbon::today();
    }

    public static function fijar(Carbon $fecha): void
    {
        Cache::forever(self::CLAVE, $fecha->toDateString());
    }

    public static function limpiar(): void
    {
        Cache::forget(self::CLAVE);
    }

    public static function estaSimulada(): bool
    {
        return Cache::has(self::CLAVE);
    }
}
