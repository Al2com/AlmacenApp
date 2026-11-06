<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * Deja el almacén listo para una demostración: lleno al 85-90 %, con un pedido
 * largo ya lanzado para picar. Solo para entorno local: se niega a tocar nada
 * si la aplicación está en producción. Se ejecuta sobre una base ya migrada
 * (recomendable, recién creada con `php artisan migrate:fresh`).
 */
class SembrarDemo extends Command
{
    protected $signature = 'almacen:demo';

    protected $description = 'Llena el almacén con datos de demostración (solo en local)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Este comando no se ejecuta en producción.');

            return self::FAILURE;
        }

        $this->info('Sembrando datos de demostración…');
        (new DemoSeeder)->run();
        $this->info('Listo: almacén lleno y pedido de demostración lanzado.');

        return self::SUCCESS;
    }
}
