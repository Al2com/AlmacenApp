<?php

namespace App\Models;

use Database\Factories\UbicacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ubicacion extends Model
{
    /** @use HasFactory<UbicacionFactory> */
    use HasFactory;

    protected $table = 'ubicaciones';

    protected $fillable = [
        'codigo',
        'tipo',
        'pasillo',
        'estanteria',
        'nivel',
        'capacidad',
        'max_lotes',
        'activa',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pasillo' => 'integer',
            'estanteria' => 'integer',
            'nivel' => 'integer',
            'capacidad' => 'integer',
            'max_lotes' => 'integer',
            'activa' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Stock, $this>
     */
    public function stock(): HasMany
    {
        return $this->hasMany(Stock::class);
    }
}
