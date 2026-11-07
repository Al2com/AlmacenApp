<?php

namespace App\Http\Controllers;

use App\Enums\EstadoOrden;
use App\Enums\Rol;
use App\Exceptions\ReglaDeDominioException;
use App\Http\Requests\CrearOrdenRequest;
use App\Http\Resources\OrdenResource;
use App\Models\Articulo;
use App\Models\OrdenProduccion;
use App\Models\User;
use App\Services\BorrarOrden;
use App\Services\CancelarOrden;
use App\Services\LanzarOrden;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Órdenes de producción. Producción gestiona las suyas (filtradas en la
 * consulta); el admin, todas. Permisos en OrdenProduccionPolicy.
 */
class OrdenController extends Controller
{
    public function __construct(
        private readonly LanzarOrden $lanzar,
        private readonly CancelarOrden $cancelar,
        private readonly BorrarOrden $borrar,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', OrdenProduccion::class);

        $query = OrdenProduccion::query()->with(['lineas.articulo', 'user'])->latest();

        $usuario = $request->user();
        if ($usuario instanceof User && $usuario->rol === Rol::OperadorProduccion) {
            $query->where('user_id', $usuario->id);
        }

        return OrdenResource::collection($query->get());
    }

    public function store(CrearOrdenRequest $request): JsonResponse
    {
        Gate::authorize('create', OrdenProduccion::class);

        $usuario = $request->user();
        if (! $usuario instanceof User) {
            abort(401);
        }

        $lineas = [];
        foreach ($request->array('lineas') as $linea) {
            $lineas[] = [
                'articulo' => Articulo::findOrFail(data_get($linea, 'articulo_id')),
                'cantidad' => (float) data_get($linea, 'cantidad'),
            ];
        }

        $orden = $this->lanzar->lanzar($usuario, (string) $request->string('codigo'), $lineas);

        return OrdenResource::make($orden->load(['lineas.articulo', 'user']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(OrdenProduccion $orden): OrdenResource
    {
        Gate::authorize('view', $orden);

        return new OrdenResource($orden->load(['lineas.articulo', 'user']));
    }

    public function cancelar(OrdenProduccion $orden): OrdenResource
    {
        Gate::authorize('cancelar', $orden);

        $this->cancelar->cancelar($orden);

        return new OrdenResource($orden->load(['lineas.articulo', 'user']));
    }

    public function destroy(OrdenProduccion $orden): JsonResponse
    {
        Gate::authorize('borrar', $orden);

        $this->borrar->borrar($orden);

        return response()->json(['message' => 'Orden borrada.']);
    }

    public function entregar(OrdenProduccion $orden): OrdenResource
    {
        Gate::authorize('entregar', $orden);

        if ($orden->estado !== EstadoOrden::Preparada) {
            throw new ReglaDeDominioException('Solo se puede dar por entregada una orden preparada.');
        }

        $orden->forceFill(['estado' => EstadoOrden::Entregada])->save();

        return new OrdenResource($orden->load(['lineas.articulo', 'user']));
    }
}
