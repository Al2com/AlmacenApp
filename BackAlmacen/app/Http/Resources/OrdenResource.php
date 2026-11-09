<?php

namespace App\Http\Resources;

use App\Models\OrdenProduccion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrdenProduccion
 */
class OrdenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'estado' => $this->estado->value,
            'solicitante' => $this->whenLoaded('user', fn () => $this->user->nombre),
            'creada' => $this->created_at->toIso8601String(),
            'lineas' => OrdenLineaResource::collection($this->whenLoaded('lineas')),
        ];
    }
}
