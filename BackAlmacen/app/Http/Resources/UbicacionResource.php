<?php

namespace App\Http\Resources;

use App\Models\Ubicacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ubicacion
 */
class UbicacionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'pasillo' => $this->pasillo,
            'estanteria' => $this->estanteria,
            'nivel' => $this->nivel,
            'refrigerado' => $this->tipo === 'refrigerado',
            'capacidad' => $this->capacidad,
            'max_lotes' => $this->max_lotes,
        ];
    }
}
