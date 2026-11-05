<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\OrdenProduccion;
use App\Models\User;

/**
 * Producción gestiona solo sus propias órdenes; el admin, todas. El filtrado de
 * «lo suyo» se hace además en la consulta del índice.
 */
class OrdenProduccionPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->rol, [Rol::Admin, Rol::OperadorProduccion], true);
    }

    public function view(User $user, OrdenProduccion $orden): bool
    {
        return $this->gestiona($user, $orden);
    }

    public function create(User $user): bool
    {
        return in_array($user->rol, [Rol::Admin, Rol::OperadorProduccion], true);
    }

    public function cancelar(User $user, OrdenProduccion $orden): bool
    {
        return $this->gestiona($user, $orden);
    }

    public function entregar(User $user, OrdenProduccion $orden): bool
    {
        return $this->gestiona($user, $orden);
    }

    private function gestiona(User $user, OrdenProduccion $orden): bool
    {
        return $user->rol === Rol::Admin
            || ($user->rol === Rol::OperadorProduccion && $orden->user_id === $user->id);
    }
}
