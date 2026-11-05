<?php

namespace App\Models;

use App\Enums\EstadoOrden;
use Database\Factories\OrdenProduccionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo
 * @property EstadoOrden $estado
 * @property int $user_id
 * @property Carbon $created_at
 */
class OrdenProduccion extends Model
{
    /** @use HasFactory<OrdenProduccionFactory> */
    use HasFactory;

    protected $table = 'ordenes_produccion';

    protected $fillable = [
        'codigo',
        'estado',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoOrden::class,
        ];
    }

    /**
     * @return HasMany<OrdenLinea, $this>
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(OrdenLinea::class, 'orden_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
