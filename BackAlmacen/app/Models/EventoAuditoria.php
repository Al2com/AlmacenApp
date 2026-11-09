<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Evento de actividad que no es un movimiento (reserva, acceso fallido, cambio
 * de configuración). Registro inmutable: solo created_at.
 *
 * @property int $id
 * @property string $tipo
 * @property int|null $user_id
 * @property string|null $cantidad
 * @property string|null $detalle
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class EventoAuditoria extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'eventos_auditoria';

    protected $fillable = [
        'tipo',
        'user_id',
        'cantidad',
        'detalle',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
