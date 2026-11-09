<?php

namespace App\Models;

use Database\Factories\LoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $articulo_id
 * @property string $codigo_lote
 * @property Carbon $fecha_recepcion
 * @property Carbon|null $caducidad
 */
class Lote extends Model
{
    /** @use HasFactory<LoteFactory> */
    use HasFactory;

    protected $table = 'lotes';

    protected $fillable = [
        'articulo_id',
        'codigo_lote',
        'fecha_recepcion',
        'caducidad',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_recepcion' => 'date',
            'caducidad' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Articulo, $this>
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }
}
