<?php

namespace App\Http\Resources;

use App\Models\Articulo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Articulo
 */
class ArticuloResource extends JsonResource
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
            'unidad' => $this->unidad,
            'tiene_caducidad' => $this->tiene_caducidad,
        ];
    }
}
