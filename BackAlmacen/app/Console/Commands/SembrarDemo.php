<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * Deja el almacén lleno (85-90 %) y un pedido `OP-DEMO-GRANDE` lanzado para
 * simular su ruta en el plano; el usuario puede crear y borrar más pedidos.
 * Solo para entorno local: se niega a tocar nada en producción. Se ejecuta
 * sobre una base ya migrada; con `--fresh` la recrea antes (borra todo y
 * vuelve a migrar).
 */
class SembrarDemo extends Command
{
    protected $signature = 'almacen:demo {--fresh : Recrea la base (migrate:fresh) antes de sembrar}';

    protected $description = 'Llena el almacén con datos de demostración (solo en local)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Este comando no se ejecuta en producción.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->info('Recreando la base de datos…');
            $this->call('migrate:fresh', ['--force' => true]);
        }

        $this->info('Sembrando datos de demostración…');
        (new DemoSeeder)->run();
        $this->info('Listo: almacén lleno y pedido OP-DEMO-GRANDE lanzado para simular en el plano.');

        return self::SUCCESS;
    }
}
