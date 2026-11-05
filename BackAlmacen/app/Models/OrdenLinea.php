<?php

namespace App\Models;

use Database\Factories\OrdenLineaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Línea de una orden. Forma parte del agregado de la orden, así que no lleva
 * timestamps propios.
 *
 * @property int $id
 * @property int $orden_id
 * @property int $articulo_id
 * @property string $cantidad_solicitada
 * @property string $cantidad_preparada
 */
class OrdenLinea extends Model
{
    /** @use HasFactory<OrdenLineaFactory> */
    use HasFactory;

    protected $table = 'orden_lineas';

    public $timestamps = false;

    protected $fillable = [
        'orden_id',
        'articulo_id',
        'cantidad_solicitada',
        'cantidad_preparada',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_solicitada' => 'decimal:3',
            'cantidad_preparada' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<OrdenProduccion, $this>
     */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenProduccion::class, 'orden_id');
    }

    /**
     * @return BelongsTo<Articulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    /**
     * @return HasMany<TareaPicking, $this>
     */
    public function tareas(): HasMany
    {
        return $this->hasMany(TareaPicking::class, 'orden_linea_id');
    }
}
