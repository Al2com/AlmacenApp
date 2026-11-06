<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\Ubicacion;
use App\Support\FechaSimulada;
use Illuminate\Support\Carbon;

/**
 * Consulta de solo lectura del stock por lote y ubicación, con el estado de
 * frescura de cada lote (válido, caduca pronto, caducado o sin caducidad).
 */
class ConsultaStock
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listar(?int $articuloId = null, ?string $estado = null, ?int $ubicacionId = null, ?string $lote = null): array
    {
        $hoy = FechaSimulada::hoy();
        $limite = $hoy->copy()->addDays((int) config('almacen.dias_aviso_caducidad', 7));

        $query = Stock::query()
            ->select('stock.*')
            ->join('lotes', 'lotes.id', '=', 'stock.lote_id')
            ->with(['lote.articulo', 'ubicacion'])
            ->where('stock.cantidad', '>', 0)
            ->orderBy('lotes.articulo_id')
            ->orderByRaw('lotes.caducidad is null')
            ->orderBy('lotes.caducidad');

        if ($articuloId !== null) {
            $query->where('lotes.articulo_id', $articuloId);
        }

        if ($ubicacionId !== null) {
            $query->where('stock.ubicacion_id', $ubicacionId);
        }

        if ($lote !== null && $lote !== '') {
            $query->where('lotes.codigo_lote', 'like', '%'.$lote.'%');
        }

        $filas = $query->get()->map(fn (Stock $s) => [
            'articulo' => $s->lote->articulo->nombre,
            'codigo_articulo' => $s->lote->articulo->codigo,
            'lote' => $s->lote->codigo_lote,
            'caducidad' => $s->lote->caducidad?->toDateString(),
            'ubicacion' => $s->ubicacion->codigo,
            'disponible' => (float) $s->cantidad - (float) $s->cantidad_reservada,
            'reservada' => (float) $s->cantidad_reservada,
            'total' => (float) $s->cantidad,
            'estado' => $this->estado($s->lote->caducidad, $hoy, $limite),
        ]);

        if ($estado !== null) {
            $filas = $filas->filter(fn (array $fila) => $fila['estado'] === $estado)->values();
        }

        return $filas->all();
    }

    /**
     * Detalle de una posición: sus datos y los lotes que contiene.
     *
     * @return array<string, mixed>
     */
    public function posicion(Ubicacion $ubicacion): array
    {
        $hoy = FechaSimulada::hoy();
        $limite = $hoy->copy()->addDays((int) config('almacen.dias_aviso_caducidad', 7));

        $ubicacion->loadMissing([
            'stock' => fn ($q) => $q->where('cantidad', '>', 0),
            'stock.lote.articulo',
        ]);

        return [
            'codigo' => $ubicacion->codigo,
            'pasillo' => $ubicacion->pasillo,
            'estanteria' => $ubicacion->estanteria,
            'nivel' => $ubicacion->nivel,
            'refrigerado' => $ubicacion->tipo === 'refrigerado',
            'capacidad' => $ubicacion->capacidad,
            'max_lotes' => $ubicacion->max_lotes,
            'contenido' => $ubicacion->stock->map(fn (Stock $s) => [
                'articulo' => $s->lote->articulo->nombre,
                'codigo_articulo' => $s->lote->articulo->codigo,
                'lote' => $s->lote->codigo_lote,
                'caducidad' => $s->lote->caducidad?->toDateString(),
                'disponible' => (float) $s->cantidad - (float) $s->cantidad_reservada,
                'reservada' => (float) $s->cantidad_reservada,
                'total' => (float) $s->cantidad,
                'estado' => $this->estado($s->lote->caducidad, $hoy, $limite),
            ])->values()->all(),
        ];
    }

    private function estado(?Carbon $caducidad, Carbon $hoy, Carbon $limite): string
    {
        if ($caducidad === null) {
            return 'sin_caducidad';
        }
        if ($caducidad->lessThan($hoy)) {
            return 'caducado';
        }
        if ($caducidad->lessThanOrEqualTo($limite)) {
            return 'caduca_pronto';
        }

        return 'ok';
    }
}
