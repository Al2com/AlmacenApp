<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarRecepcionRequest;
use App\Http\Resources\LoteResource;
use App\Models\Articulo;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\Recepcion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Recepciones (admin y operador de recepción, vía gate «registrar-recepcion»).
 */
class RecepcionController extends Controller
{
    public function __construct(private readonly Recepcion $recepcion) {}

    public function ubicacionPropuesta(Request $request): JsonResponse
    {
        Gate::authorize('registrar-recepcion');

        $ubicacion = $this->recepcion->proponerUbicacion($request->float('cantidad'));

        return response()->json(['ubicacion' => $ubicacion?->codigo]);
    }

    public function store(RegistrarRecepcionRequest $request): JsonResponse
    {
        Gate::authorize('registrar-recepcion');

        $usuario = $request->user();
        if (! $usuario instanceof User) {
            abort(401);
        }

        $articulo = Articulo::findOrFail($request->integer('articulo_id'));
        $ubicacion = $request->filled('ubicacion_id')
            ? Ubicacion::findOrFail($request->integer('ubicacion_id'))
            : null;

        $resultado = $this->recepcion->registrar(
            $articulo,
            (string) $request->string('codigo_lote'),
            $request->float('cantidad'),
            $usuario,
            Carbon::parse((string) $request->string('fecha_recepcion')),
            $request->filled('caducidad') ? Carbon::parse((string) $request->string('caducidad')) : null,
            $ubicacion,
        );

        return response()->json([
            'lote' => new LoteResource($resultado['lote']->load('articulo')),
            'ubicacion' => $resultado['ubicacion']->codigo,
            'cantidad' => $request->float('cantidad'),
        ], 201);
    }
}
