<?php

namespace Database\Factories;

use App\Enums\TipoMovimiento;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movimiento>
 */
class MovimientoFactory extends Factory
{
    protected $model = Movimiento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => TipoMovimiento::Recepcion,
            'lote_id' => Lote::factory(),
            'ubicacion_origen_id' => null,
            'ubicacion_destino_id' => Ubicacion::factory(),
            'cantidad' => 10,
            'user_id' => User::factory(),
            'tarea_picking_id' => null,
            'motivo' => null,
        ];
    }
}
