<?php

namespace App\Models;

use Database\Factories\ArticuloFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property float|null $disponible Stock disponible, calculado al listar el catálogo (no es columna).
 */
class Articulo extends Model
{
    /** @use HasFactory<ArticuloFactory> */
    use HasFactory;

    protected $table = 'articulos';

    protected $fillable = [
        'codigo',
        'nombre',
        'unidad',
        'tiene_caducidad',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tiene_caducidad' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Lote, $this>
     */
    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }
}
