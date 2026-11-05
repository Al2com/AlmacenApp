<?php

namespace App\Http\Resources;

use App\Models\Movimiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Movimiento
 */
class MovimientoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo->value,
            'cantidad' => (float) $this->cantidad,
            'articulo' => $this->lote->articulo->nombre,
            'lote' => $this->lote->codigo_lote,
            'origen' => $this->ubicacionOrigen?->codigo,
            'destino' => $this->ubicacionDestino?->codigo,
            'usuario' => $this->user->nombre,
            'motivo' => $this->motivo,
            'fecha' => $this->created_at->toIso8601String(),
        ];
    }
}
