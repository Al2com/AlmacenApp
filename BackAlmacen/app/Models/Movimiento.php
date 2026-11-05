<?php

namespace App\Models;

use App\Enums\TipoMovimiento;
use Database\Factories\MovimientoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro inmutable: un movimiento nunca se edita ni se borra. Por eso solo
 * guarda created_at, no updated_at.
 */
class Movimiento extends Model
{
    /** @use HasFactory<MovimientoFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'movimientos';

    protected $fillable = [
        'tipo',
        'lote_id',
        'ubicacion_origen_id',
        'ubicacion_destino_id',
        'cantidad',
        'user_id',
        'tarea_picking_id',
        'motivo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimiento::class,
            'cantidad' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Lote, $this>
     */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
