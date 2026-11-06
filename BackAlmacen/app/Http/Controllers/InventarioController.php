<?php

namespace App\Http\Controllers;

use App\Http\Requests\BajaRequest;
use App\Http\Requests\RegularizarRequest;
use App\Http\Requests\ReservarRequest;
use App\Http\Requests\TrasladarRequest;
use App\Http\Resources\MovimientoResource;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\DarDeBaja;
use App\Services\RegularizarStock;
use App\Services\ReservaDeStock;
use App\Services\TrasladarStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Operaciones de inventario del gestor (y del admin). El acceso lo controla el
 * gate «gestionar-inventario» en la ruta; la lógica vive en los servicios.
 */
class InventarioController extends Controller
{
    public function __construct(
        private readonly RegularizarStock $regularizar,
        private readonly TrasladarStock $trasladar,
        private readonly DarDeBaja $baja,
        private readonly ReservaDeStock $reservas,
    ) {}

    public function regularizarStock(RegularizarRequest $request): MovimientoResource
    {
        $movimiento = $this->regularizar->regularizar(
            Lote::findOrFail($request->integer('lote_id')),
            Ubicacion::findOrFail($request->integer('ubicacion_id')),
            $request->float('cantidad'),
            $this->usuario($request),
            (string) $request->string('motivo'),
        );

        return $this->resource($movimiento);
    }

    public function trasladarStock(TrasladarRequest $request): MovimientoResource
    {
        $movimiento = $this->trasladar->trasladar(
            Lote::findOrFail($request->integer('lote_id')),
            Ubicacion::findOrFail($request->integer('origen_id')),
            Ubicacion::findOrFail($request->integer('destino_id')),
            $request->float('cantidad'),
            $this->usuario($request),
        );

        return $this->resource($movimiento);
    }

    public function darDeBaja(BajaRequest $request): MovimientoResource
    {
        $movimiento = $this->baja->baja(
            Lote::findOrFail($request->integer('lote_id')),
            Ubicacion::findOrFail($request->integer('ubicacion_id')),
            $request->float('cantidad'),
            $this->usuario($request),
            (string) $request->string('motivo'),
        );

        return $this->resource($movimiento);
    }

    public function reservar(ReservarRequest $request): JsonResponse
    {
        $resultado = $this->reservas->reservar(
            Articulo::findOrFail($request->integer('articulo_id')),
            $request->float('cantidad'),
        );

        return response()->json([
            'reservado' => $resultado['reservado'],
            'faltante' => $resultado['faltante'],
        ]);
    }

    public function liberar(ReservarRequest $request): JsonResponse
    {
        $liberado = $this->reservas->liberar(
            Articulo::findOrFail($request->integer('articulo_id')),
            $request->float('cantidad'),
        );

        return response()->json(['liberado' => $liberado]);
    }

    private function resource(Movimiento $movimiento): MovimientoResource
    {
        return new MovimientoResource(
            $movimiento->load(['lote.articulo', 'user', 'ubicacionOrigen', 'ubicacionDestino'])
        );
    }

    private function usuario(Request $request): User
    {
        $usuario = $request->user();
        if (! $usuario instanceof User) {
            abort(401);
        }

        return $usuario;
    }
}
