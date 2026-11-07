<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * Deja el almacén lleno (85-90 %) para una demostración, sin ningún pedido: los
 * pedidos los crea el usuario y el plano recrea su ruta. Solo para entorno
 * local: se niega a tocar nada en producción. Se ejecuta sobre una base ya
 * migrada; con `--fresh` la recrea antes (borra todo y vuelve a migrar).
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
        $this->info('Listo: almacén lleno (sin pedidos; créalos tú y simúlalos en el plano).');

        return self::SUCCESS;
    }
}
