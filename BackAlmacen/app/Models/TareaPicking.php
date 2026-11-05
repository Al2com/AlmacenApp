<?php

namespace App\Models;

use App\Enums\EstadoTarea;
use Database\Factories\TareaPickingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tarea de recogida generada al lanzar una orden. Solo registra confirmada_at,
 * no lleva created_at/updated_at.
 *
 * @property int $id
 * @property int $orden_linea_id
 * @property int $lote_id
 * @property int $ubicacion_id
 * @property string $cantidad
 * @property int $secuencia
 * @property EstadoTarea $estado
 * @property int|null $asignada_a
 * @property Carbon|null $confirmada_at
 */
class TareaPicking extends Model
{
    /** @use HasFactory<TareaPickingFactory> */
    use HasFactory;

    protected $table = 'tareas_picking';

    public $timestamps = false;

    protected $fillable = [
        'orden_linea_id',
        'lote_id',
        'ubicacion_id',
        'cantidad',
        'secuencia',
        'estado',
        'asignada_a',
        'confirmada_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoTarea::class,
            'cantidad' => 'decimal:3',
            'confirmada_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OrdenLinea, $this>
     */
    public function ordenLinea(): BelongsTo
    {
        return $this->belongsTo(OrdenLinea::class, 'orden_linea_id');
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function asignada(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignada_a');
    }
}
