<?php

namespace App\Services;

use App\Enums\EstadoOrden;
use App\Enums\EstadoTarea;
use App\Enums\Rol;
use App\Exceptions\ReglaDeDominioException;
use App\Models\TareaPicking;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Pone en marcha un operario de demostración que pica la cola real de tareas
 * pendientes (la orden más antigua primero, en orden de ruta), confirmando con
 * el mismo servicio que usa la PDA. Cada confirmación es un movimiento real,
 * atribuido a este usuario y no al admin que puso en marcha la demostración.
 * Solo existe en local: `docs/seguridad.md`.
 */
class OperarioVirtual
{
    private const CORREO = 'demo.operario@almacen.local';

    public function __construct(private readonly ConfirmarTarea $confirmarTarea = new ConfirmarTarea) {}

    /**
     * El usuario de demostración, creado la primera vez que se necesita.
     */
    public function operario(): User
    {
        if (app()->environment('production')) {
            throw new ReglaDeDominioException('El modo demostración no está disponible en producción.');
        }

        $existente = User::where('email', self::CORREO)->first();
        if ($existente !== null) {
            return $existente;
        }

        return User::create([
            'nombre' => 'Operario de demostración',
            'email' => self::CORREO,
            'rol' => Rol::OperadorAlmacen,
            'password' => Hash::make(Str::random(40)),
            'activo' => true,
            'debe_cambiar_password' => false,
        ]);
    }

    /**
     * La próxima tarea a picar: de entre las órdenes activas, la más antigua
     * primero y, dentro de ella, su orden de ruta.
     */
    public function siguienteTarea(): ?TareaPicking
    {
        return TareaPicking::query()
            ->select('tareas_picking.*')
            ->join('orden_lineas', 'orden_lineas.id', '=', 'tareas_picking.orden_linea_id')
            ->join('ordenes_produccion', 'ordenes_produccion.id', '=', 'orden_lineas.orden_id')
            ->where('tareas_picking.estado', EstadoTarea::Pendiente->value)
            ->whereIn('ordenes_produccion.estado', [EstadoOrden::Lanzada->value, EstadoOrden::EnPicking->value])
            ->orderBy('ordenes_produccion.created_at')
            ->orderBy('tareas_picking.secuencia')
            ->with(['ordenLinea.orden', 'lote.articulo', 'ubicacion'])
            ->first();
    }

    /**
     * Cuántas tareas quedan por picar en las órdenes activas.
     */
    public function pendientes(): int
    {
        return TareaPicking::query()
            ->join('orden_lineas', 'orden_lineas.id', '=', 'tareas_picking.orden_linea_id')
            ->join('ordenes_produccion', 'ordenes_produccion.id', '=', 'orden_lineas.orden_id')
            ->where('tareas_picking.estado', EstadoTarea::Pendiente->value)
            ->whereIn('ordenes_produccion.estado', [EstadoOrden::Lanzada->value, EstadoOrden::EnPicking->value])
            ->count();
    }

    /**
     * Confirma la siguiente tarea de la cola (o no hace nada si no hay
     * ninguna) y devuelve la tarea confirmada.
     */
    public function confirmarSiguiente(): ?TareaPicking
    {
        $tarea = $this->siguienteTarea();
        if ($tarea === null) {
            return null;
        }

        return $this->confirmarTarea->confirmar($tarea, $this->operario());
    }
}
