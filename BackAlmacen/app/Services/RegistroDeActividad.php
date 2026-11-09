<?php

namespace App\Services;

use App\Models\EventoAuditoria;
use App\Models\User;

/**
 * Anota eventos de actividad que no son movimientos (reservas, accesos fallidos,
 * cambios de configuración) para el feed del admin. Nunca guarda datos sensibles
 * (contraseñas, tokens ni correos completos).
 */
class RegistroDeActividad
{
    public function registrar(string $tipo, ?User $usuario = null, ?float $cantidad = null, ?string $detalle = null): void
    {
        EventoAuditoria::create([
            'tipo' => $tipo,
            'user_id' => $usuario?->id,
            'cantidad' => $cantidad,
            'detalle' => $detalle,
        ]);
    }

    /**
     * Enmascara un correo para no guardarlo entero: ana@almacen.local → a***@almacen.local.
     */
    public function enmascararCorreo(string $email): string
    {
        $arroba = strpos($email, '@');
        if ($arroba === false || $arroba === 0) {
            return '***';
        }

        return $email[0].'***'.substr($email, $arroba);
    }
}
