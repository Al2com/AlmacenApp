<?php

namespace App\Services;

use App\Enums\TipoMovimiento;
use App\Exceptions\ReglaDeDominioException;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registra una entrada: crea el lote (o reusa el que ya existe con ese código),
 * elige una ubicación libre con capacidad si no se indica una, y registra el
 * movimiento de recepción, todo en una transacción.
 */
class Recepcion
{
    public function __construct(private readonly RegistroMovimiento $movimientos) {}

    /**
     * Primera ubicación libre (en orden de ruta) con hueco de lote y capacidad.
     */
    public function proponerUbicacion(float $cantidad): ?Ubicacion
    {
        return Ubicacion::query()
            ->where('activa', true)
            ->where('tipo', 'estante')
            ->orderBy('pasillo')
            ->orderBy('estanteria')
            ->orderBy('nivel')
            ->with('stock')
            ->get()
            ->first(function (Ubicacion $ubicacion) use ($cantidad) {
                $usada = (float) $ubicacion->stock->sum('cantidad');
                $lotes = $ubicacion->stock->pluck('lote_id')->unique()->count();

                return $usada + $cantidad <= (float) $ubicacion->capacidad && $lotes < $ubicacion->max_lotes;
            });
    }

    /**
     * @return array{lote: Lote, ubicacion: Ubicacion, movimiento: Movimiento}
     */
    public function registrar(
        Articulo $articulo,
        string $codigoLote,
        float $cantidad,
        User $usuario,
        Carbon $fechaRecepcion,
        ?Carbon $caducidad,
        ?Ubicacion $ubicacion = null,
    ): array {
        return DB::transaction(function () use ($articulo, $codigoLote, $cantidad, $usuario, $fechaRecepcion, $caducidad, $ubicacion) {
            $lote = Lote::firstOrCreate(
                ['articulo_id' => $articulo->id, 'codigo_lote' => $codigoLote],
                ['fecha_recepcion' => $fechaRecepcion->toDateString(), 'caducidad' => $caducidad?->toDateString()],
            );

            $destino = $ubicacion ?? $this->proponerUbicacion($cantidad);

            if ($destino === null) {
                throw new ReglaDeDominioException('No hay ninguna ubicación libre con capacidad suficiente.');
            }

            $movimiento = $this->movimientos->registrar(TipoMovimiento::Recepcion, $lote, $cantidad, $usuario, destino: $destino);

            return ['lote' => $lote, 'ubicacion' => $destino, 'movimiento' => $movimiento];
        });
    }
}
