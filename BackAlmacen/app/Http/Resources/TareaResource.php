<?php

namespace App\Http\Resources;

use App\Models\TareaPicking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TareaPicking
 */
class TareaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orden' => $this->ordenLinea->orden->codigo,
            'articulo' => $this->lote->articulo->nombre,
            'lote' => $this->lote->codigo_lote,
            'ubicacion' => $this->ubicacion->codigo,
            'pasillo' => $this->ubicacion->pasillo,
            'estanteria' => $this->ubicacion->estanteria,
            'nivel' => $this->ubicacion->nivel,
            'cantidad' => (float) $this->cantidad,
            'secuencia' => $this->secuencia,
            'estado' => $this->estado->value,
        ];
    }
}
