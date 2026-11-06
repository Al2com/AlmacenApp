<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Feed de actividad del admin: mezcla los movimientos y los eventos de auditoría
 * más recientes (o lo nuevo desde una marca de tiempo), en un formato común y
 * sin datos sensibles.
 */
class Actividad
{
    /**
     * @return list<array<string, mixed>>
     */
    public function desde(?string $desde = null, int $limite = 30): array
    {
        $movimientos = DB::table('movimientos')
            ->leftJoin('lotes', 'lotes.id', '=', 'movimientos.lote_id')
            ->leftJoin('users', 'users.id', '=', 'movimientos.user_id')
            ->leftJoin('ubicaciones as destino', 'destino.id', '=', 'movimientos.ubicacion_destino_id')
            ->leftJoin('ubicaciones as origen', 'origen.id', '=', 'movimientos.ubicacion_origen_id')
            ->when($desde, fn ($q) => $q->where('movimientos.created_at', '>', $desde))
            ->orderByDesc('movimientos.created_at')
            ->limit($limite)
            ->get([
                'movimientos.id',
                'movimientos.tipo',
                'movimientos.cantidad',
                'movimientos.motivo',
                'movimientos.created_at',
                'lotes.codigo_lote',
                'users.nombre as usuario',
                DB::raw('coalesce(destino.codigo, origen.codigo) as ubicacion'),
            ])
            ->map(fn ($fila) => [
                'id' => 'mov-'.$fila->id,
                'fecha' => Carbon::parse($fila->created_at)->toIso8601String(),
                'usuario' => $fila->usuario,
                'tipo' => $fila->tipo,
                'lote' => $fila->codigo_lote,
                'ubicacion' => $fila->ubicacion,
                'cantidad' => (float) $fila->cantidad,
                'detalle' => $fila->motivo,
            ]);

        $eventos = DB::table('eventos_auditoria')
            ->leftJoin('users', 'users.id', '=', 'eventos_auditoria.user_id')
            ->when($desde, fn ($q) => $q->where('eventos_auditoria.created_at', '>', $desde))
            ->orderByDesc('eventos_auditoria.created_at')
            ->limit($limite)
            ->get([
                'eventos_auditoria.id',
                'eventos_auditoria.tipo',
                'eventos_auditoria.cantidad',
                'eventos_auditoria.detalle',
                'eventos_auditoria.created_at',
                'users.nombre as usuario',
            ])
            ->map(fn ($fila) => [
                'id' => 'evt-'.$fila->id,
                'fecha' => Carbon::parse($fila->created_at)->toIso8601String(),
                'usuario' => $fila->usuario ?? '—',
                'tipo' => $fila->tipo,
                'lote' => null,
                'ubicacion' => null,
                'cantidad' => $fila->cantidad !== null ? (float) $fila->cantidad : null,
                'detalle' => $fila->detalle,
            ]);

        return $movimientos
            ->concat($eventos)
            ->sortByDesc('fecha')
            ->take($limite)
            ->values()
            ->all();
    }
}
