<?php

namespace App\Models;

use Database\Factories\StockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saldo actual por lote y ubicación. No tiene id propio: su clave es la
 * pareja (lote_id, ubicacion_id).
 */
class Stock extends Model
{
    /** @use HasFactory<StockFactory> */
    use HasFactory;

    protected $table = 'stock';

    public $incrementing = false;

    protected $fillable = [
        'lote_id',
        'ubicacion_id',
        'cantidad',
        'cantidad_reservada',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'cantidad_reservada' => 'decimal:3',
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
     * @return BelongsTo<Ubicacion, $this>
     */
    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }
}
