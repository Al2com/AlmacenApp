<?php

namespace App\Http\Resources;

use App\Models\Lote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lote
 */
class LoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_lote' => $this->codigo_lote,
            'articulo' => $this->articulo->nombre,
            'codigo_articulo' => $this->articulo->codigo,
            'fecha_recepcion' => $this->fecha_recepcion->toDateString(),
            'caducidad' => $this->caducidad?->toDateString(),
        ];
    }
}
