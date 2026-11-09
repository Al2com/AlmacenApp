<?php

namespace App\Http\Resources;

use App\Models\OrdenLinea;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrdenLinea
 */
class OrdenLineaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $solicitada = (float) $this->cantidad_solicitada;
        $preparada = (float) $this->cantidad_preparada;

        return [
            'articulo' => $this->articulo->nombre,
            'codigo_articulo' => $this->articulo->codigo,
            'solicitada' => $solicitada,
            'preparada' => $preparada,
            'faltante' => max(0, $solicitada - $preparada),
        ];
    }
}
