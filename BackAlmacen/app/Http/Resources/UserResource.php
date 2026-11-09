<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'email' => $this->email,
            'rol' => $this->rol->value,
            'activo' => $this->activo,
            'debe_cambiar_password' => $this->debe_cambiar_password,
            'ultimo_acceso' => $this->ultimo_acceso?->toIso8601String(),
        ];
    }
}
