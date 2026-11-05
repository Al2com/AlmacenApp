<?php

namespace Database\Factories;

use App\Enums\EstadoTarea;
use App\Models\Lote;
use App\Models\OrdenLinea;
use App\Models\TareaPicking;
use App\Models\Ubicacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TareaPicking>
 */
class TareaPickingFactory extends Factory
{
    protected $model = TareaPicking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'orden_linea_id' => OrdenLinea::factory(),
            'lote_id' => Lote::factory(),
            'ubicacion_id' => Ubicacion::factory(),
            'cantidad' => 5,
            'secuencia' => 1,
            'estado' => EstadoTarea::Pendiente,
            'asignada_a' => null,
            'confirmada_at' => null,
        ];
    }
}
