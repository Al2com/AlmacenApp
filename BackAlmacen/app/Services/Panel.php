<?php

namespace App\Services;

use App\Enums\EstadoOrden;
use App\Enums\EstadoTarea;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\OrdenProduccion;
use App\Models\Stock;
use App\Models\TareaPicking;
use App\Models\Ubicacion;
use App\Support\FechaSimulada;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Datos de solo lectura del panel general. Cada método elige explícitamente los
 * campos que expone, así que no se filtran columnas sin querer.
 */
class Panel
{
    /**
     * Cuatro cifras del día. Las de órdenes, picking e incidencias llegan en la
     * fase 2; hasta entonces son cero.
     *
     * @return array<string, int>
     */
    public function cifras(): array
    {
        $hoy = FechaSimulada::hoy();
        $limite = $hoy->copy()->addDays($this->umbralCaducidad());

        $caducanPronto = DB::table('lotes')
            ->join('stock', 'stock.lote_id', '=', 'lotes.id')
            ->whereNotNull('lotes.caducidad')
            ->whereBetween('lotes.caducidad', [$hoy->toDateString(), $limite->toDateString()])
            ->where('stock.cantidad', '>', 0)
            ->distinct()
            ->count('lotes.id');

        $ordenesEnCurso = OrdenProduccion::query()
            ->whereIn('estado', [
                EstadoOrden::Lanzada->value,
                EstadoOrden::EnPicking->value,
                EstadoOrden::Preparada->value,
            ])
            ->count();

        $tareasPendientes = TareaPicking::query()
            ->where('estado', EstadoTarea::Pendiente->value)
            ->count();

        return [
            'lotes_caducan_pronto' => $caducanPronto,
            'ordenes_en_curso' => $ordenesEnCurso,
            'tareas_pendientes' => $tareasPendientes,
            // Las incidencias llegan con la PDA (fase 3).
            'incidencias_abiertas' => 0,
        ];
    }

    /**
     * Cada posición de la estantería con su estado y su contenido. El estado
     * distingue si lo reservado es la siguiente tarea de su orden (en_picking),
     * otra tarea en cola (reservada) o se acaba de recoger (recogida).
     *
     * @return list<array<string, mixed>>
     */
    public function estanteria(): array
    {
        $hoy = FechaSimulada::hoy();
        $limite = $hoy->copy()->addDays($this->umbralCaducidad());
        ['pendientes' => $pendientes, 'recogidas' => $recogidas] = $this->datosDeTareas();

        $ubicaciones = Ubicacion::query()
            ->with(['stock' => fn ($q) => $q->where('cantidad', '>', 0), 'stock.lote.articulo'])
            ->orderBy('pasillo')
            ->orderBy('estanteria')
            ->orderBy('nivel')
            ->get();

        return $ubicaciones->map(function (Ubicacion $ubicacion) use ($hoy, $limite, $pendientes, $recogidas) {
            $pendientesAqui = $pendientes[$ubicacion->id] ?? [];
            $recogidasAqui = $recogidas[$ubicacion->id] ?? [];

            return [
                'codigo' => $ubicacion->codigo,
                'pasillo' => $ubicacion->pasillo,
                'estanteria' => $ubicacion->estanteria,
                'nivel' => $ubicacion->nivel,
                'refrigerado' => $ubicacion->tipo === 'refrigerado',
                'estado' => $this->estado($ubicacion->stock, $hoy, $limite, $pendientesAqui, $recogidasAqui),
                'contenido' => $this->contenido($ubicacion->stock, $pendientesAqui, $recogidasAqui),
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Stock>  $stock
     * @param  list<array{lote_id: int, orden: string, cantidad: float, es_siguiente: bool}>  $pendientes
     * @param  list<array{articulo: string, codigo_articulo: string, lote: string, cantidad: float, orden: string}>  $recogidas
     * @return list<array<string, mixed>>
     */
    private function contenido(Collection $stock, array $pendientes, array $recogidas): array
    {
        $contenido = $stock->map(fn (Stock $s) => [
            'articulo' => $s->lote->articulo->nombre,
            'codigo_articulo' => $s->lote->articulo->codigo,
            'lote' => $s->lote->codigo_lote,
            'caducidad' => $s->lote->caducidad?->toDateString(),
            'disponible' => (float) $s->cantidad - (float) $s->cantidad_reservada,
            'reservada' => (float) $s->cantidad_reservada,
            'total' => (float) $s->cantidad,
            'reservas' => collect($pendientes)
                ->where('lote_id', $s->lote_id)
                ->map(fn (array $p) => ['orden' => $p['orden'], 'cantidad' => $p['cantidad']])
                ->values()->all(),
        ])->values()->all();

        // Sin stock (se consumió todo) pero recién recogida: se muestra igual,
        // para que la caja no desaparezca antes de que se vea el cambio.
        if ($contenido === [] && $recogidas !== []) {
            return collect($recogidas)->map(fn (array $r) => [
                'articulo' => $r['articulo'],
                'codigo_articulo' => $r['codigo_articulo'],
                'lote' => $r['lote'],
                'caducidad' => null,
                'disponible' => 0.0,
                'reservada' => 0.0,
                'total' => 0.0,
                'reservas' => [],
                'recogida' => $r['cantidad'],
                'recogida_orden' => $r['orden'],
            ])->values()->all();
        }

        return $contenido;
    }

    /**
     * Tareas pendientes (agrupadas por orden, para saber cuál es la siguiente)
     * y tareas recién confirmadas, ambas indexadas por ubicación.
     *
     * @return array{pendientes: array<int, list<array{lote_id: int, orden: string, cantidad: float, es_siguiente: bool}>>, recogidas: array<int, list<array{articulo: string, codigo_articulo: string, lote: string, cantidad: float, orden: string}>>}
     */
    private function datosDeTareas(): array
    {
        $pendientes = [];
        $porOrden = TareaPicking::query()
            ->where('estado', EstadoTarea::Pendiente->value)
            ->whereHas('ordenLinea.orden', fn ($q) => $q->whereIn('estado', [
                EstadoOrden::Lanzada->value,
                EstadoOrden::EnPicking->value,
            ]))
            ->with('ordenLinea.orden')
            ->orderBy('secuencia')
            ->get()
            ->groupBy(fn (TareaPicking $t) => $t->ordenLinea->orden_id);

        foreach ($porOrden as $tareasDeLaOrden) {
            $siguienteId = $tareasDeLaOrden->first()->id;
            foreach ($tareasDeLaOrden as $tarea) {
                $pendientes[$tarea->ubicacion_id][] = [
                    'lote_id' => $tarea->lote_id,
                    'orden' => $tarea->ordenLinea->orden->codigo,
                    'cantidad' => (float) $tarea->cantidad,
                    'es_siguiente' => $tarea->id === $siguienteId,
                ];
            }
        }

        $recogidas = [];
        $ventana = (int) config('almacen.segundos_recogida', 20);
        $confirmadasRecientes = TareaPicking::query()
            ->where('estado', EstadoTarea::Confirmada->value)
            ->where('confirmada_at', '>=', now()->subSeconds($ventana))
            ->with(['lote.articulo', 'ordenLinea.orden'])
            ->get();

        foreach ($confirmadasRecientes as $tarea) {
            $recogidas[$tarea->ubicacion_id][] = [
                'articulo' => $tarea->lote->articulo->nombre,
                'codigo_articulo' => $tarea->lote->articulo->codigo,
                'lote' => $tarea->lote->codigo_lote,
                'cantidad' => (float) $tarea->cantidad,
                'orden' => $tarea->ordenLinea->orden->codigo,
            ];
        }

        return ['pendientes' => $pendientes, 'recogidas' => $recogidas];
    }

    /**
     * «Salen primero»: lotes con saldo en orden FEFO. El caducado va el primero,
     * marcado para baja (no para consumo).
     *
     * @return list<array<string, mixed>>
     */
    public function fefo(): array
    {
        $hoy = FechaSimulada::hoy();

        $lotes = Lote::query()
            ->select('lotes.*')
            ->join('stock', 'stock.lote_id', '=', 'lotes.id')
            ->selectRaw('SUM(stock.cantidad - stock.cantidad_reservada) as disponible')
            ->with('articulo')
            ->groupBy('lotes.id')
            ->having('disponible', '>', 0)
            ->orderByRaw('lotes.caducidad is null')
            ->orderBy('lotes.caducidad')
            ->orderBy('lotes.fecha_recepcion')
            ->get();

        return $lotes->map(fn (Lote $lote) => [
            'articulo' => $lote->articulo->nombre,
            'codigo_articulo' => $lote->articulo->codigo,
            'lote' => $lote->codigo_lote,
            'caducidad' => $lote->caducidad?->toDateString(),
            'disponible' => (float) $lote->getAttribute('disponible'),
            'para_baja' => $lote->caducidad !== null && $lote->caducidad->lessThan($hoy),
        ])->all();
    }

    /**
     * @return Collection<int, Movimiento>
     */
    public function ultimosMovimientos(int $cuantos = 15): Collection
    {
        return Movimiento::query()
            ->with(['lote.articulo', 'user', 'ubicacionOrigen', 'ubicacionDestino'])
            ->latest()
            ->limit($cuantos)
            ->get();
    }

    /**
     * Entradas (recepción) y salidas (consumo y baja) por hora del día de hoy.
     *
     * @return list<array{hora: int, entradas: float, salidas: float}>
     */
    public function flujoHorario(): array
    {
        $filas = DB::table('movimientos')
            ->whereDate('created_at', Carbon::today()->toDateString())
            ->selectRaw('HOUR(created_at) as hora, tipo, SUM(cantidad) as total')
            ->groupBy('hora', 'tipo')
            ->get();

        $horas = [];
        for ($hora = 0; $hora < 24; $hora++) {
            $horas[$hora] = ['hora' => $hora, 'entradas' => 0.0, 'salidas' => 0.0];
        }

        foreach ($filas as $fila) {
            $hora = (int) $fila->hora;
            if ($fila->tipo === 'recepcion') {
                $horas[$hora]['entradas'] += (float) $fila->total;
            } elseif (in_array($fila->tipo, ['consumo', 'baja'], true)) {
                $horas[$hora]['salidas'] += (float) $fila->total;
            }
        }

        return array_values($horas);
    }

    private function umbralCaducidad(): int
    {
        return (int) config('almacen.dias_aviso_caducidad', 7);
    }

    /**
     * Prioridad: una caducidad manda siempre; si no, se acaba de recoger; si
     * no, es la siguiente tarea de su orden (en_picking) o espera su turno
     * (reservada); si no, ocupada o libre.
     *
     * @param  Collection<int, Stock>  $stock
     * @param  list<array{lote_id: int, orden: string, cantidad: float, es_siguiente: bool}>  $pendientes
     * @param  list<array{articulo: string, codigo_articulo: string, lote: string, cantidad: float, orden: string}>  $recogidas
     */
    private function estado(Collection $stock, Carbon $hoy, Carbon $limite, array $pendientes, array $recogidas): string
    {
        if ($stock->contains(fn (Stock $s) => $s->lote->caducidad !== null && $s->lote->caducidad->lessThan($hoy))) {
            return 'caducada';
        }

        if ($stock->contains(fn (Stock $s) => $s->lote->caducidad !== null && $s->lote->caducidad->between($hoy, $limite))) {
            return 'caduca_pronto';
        }

        if ($recogidas !== []) {
            return 'recogida';
        }

        if (collect($pendientes)->contains('es_siguiente', true)) {
            return 'en_picking';
        }

        if ($pendientes !== [] || $stock->contains(fn (Stock $s) => (float) $s->cantidad_reservada > 0)) {
            return 'reservada';
        }

        if ($stock->isEmpty()) {
            return 'libre';
        }

        return 'ocupada';
    }
}
