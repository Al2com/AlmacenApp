<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Enums\TipoMovimiento;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\User;
use App\Services\LanzarOrden;
use App\Services\RegistroMovimiento;
use Database\Seeders\Concerns\CreaEstructuraBase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Llena el almacén para una demostración: 25 artículos, el 85-90 % de las
 * posiciones ocupadas y un pedido `OP-DEMO-GRANDE` ya lanzado, listo para
 * simular su ruta en el plano. El usuario puede crear más pedidos y borrarlos;
 * la demostración recrea la ruta del pedido que esté lanzado (ver
 * RutaDemostracion). Todo escrito a mano (sin Faker ni azar), así que siempre
 * da el mismo resultado. Se invoca con `php artisan almacen:demo` (nunca en
 * producción); ver SembrarDemo.
 */
class DemoSeeder extends Seeder
{
    use CreaEstructuraBase;

    /** «Hoy» de referencia para las fechas del catálogo (ver FechaSimulada). */
    private const HOY = '2026-11-05';

    /** Cantidad de cada lote: simplifica el cálculo de cuánto pedir después. */
    private const CANTIDAD_POR_LOTE = 20.0;

    public function run(): void
    {
        $usuarios = $this->crearUsuarios();
        ['normales' => $normales, 'frias' => $frias] = $this->crearUbicaciones();

        $servicio = new RegistroMovimiento;
        $recepcion = $usuarios[Rol::OperadorRecepcion->value];
        $hoy = Carbon::parse(self::HOY);

        $siguienteNormal = 0;
        $siguienteFrio = 0;
        $totalesPorArticulo = [];

        foreach ($this->catalogo() as $config) {
            $fabrica = $config['caducidad'] ? Articulo::factory() : Articulo::factory()->sinCaducidad();
            $articulo = $fabrica->create([
                'codigo' => $config['codigo'],
                'nombre' => $config['nombre'],
                'unidad' => $config['unidad'],
            ]);

            $total = 0.0;
            for ($i = 0; $i < $config['lotes']; $i++) {
                $fechaRecepcion = $hoy->copy()->subDays(40 - $i * 3);

                if ($config['caducidad']) {
                    $caducidad = $this->caducidadDelLote($config, $i, $hoy);
                    $lote = Lote::factory()->for($articulo)->create([
                        'codigo_lote' => sprintf('%s-L%02d', $config['codigo'], $i + 1),
                        'fecha_recepcion' => $fechaRecepcion->toDateString(),
                        'caducidad' => $caducidad->toDateString(),
                    ]);
                } else {
                    $lote = Lote::factory()->for($articulo)->sinCaducidad()->create([
                        'codigo_lote' => sprintf('%s-L%02d', $config['codigo'], $i + 1),
                        'fecha_recepcion' => $fechaRecepcion->toDateString(),
                    ]);
                }

                if ($config['frio']) {
                    $ubicacion = $frias[$siguienteFrio++];
                } else {
                    $ubicacion = $normales[$siguienteNormal++];
                }

                $servicio->registrar(TipoMovimiento::Recepcion, $lote, self::CANTIDAD_POR_LOTE, $recepcion, destino: $ubicacion);
                $total += self::CANTIDAD_POR_LOTE;
            }

            $totalesPorArticulo[$config['codigo']] = $total;
        }

        $this->lanzarPedidoLargo($usuarios[Rol::OperadorProduccion->value], $totalesPorArticulo);
    }

    /**
     * El pedido de demostración `OP-DEMO-GRANDE`: una línea por artículo (25),
     * pidiendo casi todo el stock de cada uno para que el FEFO reparta entre
     * varios lotes y ubicaciones, salvo en dos artículos donde pide más de lo
     * que hay para que se vea la preparación parcial.
     *
     * @param  array<string, float>  $totalesPorArticulo
     */
    private function lanzarPedidoLargo(User $produccion, array $totalesPorArticulo): void
    {
        $sinStockSuficiente = ['DEMO-017', 'DEMO-020'];

        $lineas = [];
        foreach ($totalesPorArticulo as $codigo => $total) {
            $lineas[] = [
                'articulo' => Articulo::where('codigo', $codigo)->firstOrFail(),
                // Deja siempre un lote intacto, para que el resto se reparta
                // entre varios lotes y ubicaciones por FEFO.
                'cantidad' => in_array($codigo, $sinStockSuficiente, true) ? $total + 500 : $total - self::CANTIDAD_POR_LOTE,
            ];
        }

        (new LanzarOrden)->lanzar($produccion, 'OP-DEMO-GRANDE', $lineas, 'Pedido de demostración');
    }

    /**
     * La fecha de caducidad de un lote: por defecto, una fecha lejana que se
     * escalona con el índice del lote (para que el FEFO tenga sentido dentro
     * de un mismo artículo); los lotes señalados en el catálogo fuerzan un
     * estado concreto (caducado o caduca pronto).
     */
    private function caducidadDelLote(array $config, int $indiceLote, Carbon $hoy): Carbon
    {
        if ($indiceLote === 0 && $config['forzar'] === 'caducado') {
            return $hoy->copy()->subDays(3);
        }

        if ($indiceLote === 0 && $config['forzar'] === 'caduca_pronto') {
            return $hoy->copy()->addDays(4);
        }

        return $hoy->copy()->addMonths($config['validezMeses'])->addDays($indiceLote * 20);
    }

    /**
     * 25 artículos de material veterinario. 22 de ellos van a las posiciones
     * normales (104 lotes) y 3 a la cámara refrigerada (6 lotes, la llenan
     * entera): 110 lotes en total sobre 126 posiciones, el 87 % ocupado.
     *
     * @return list<array<string, mixed>>
     */
    private function catalogo(): array
    {
        return [
            // Con caducidad, posiciones normales: 16 con 5 lotes + 3 con 4 lotes.
            $this->item('DEMO-001', 'Amoxicilina 150 mg/ml inyectable', 'vial', lotes: 5, validezMeses: 8, forzar: 'caducado'),
            $this->item('DEMO-002', 'Enrofloxacino 10% inyectable', 'vial', lotes: 5, validezMeses: 7, forzar: 'caducado'),
            $this->item('DEMO-003', 'Flunixino meglumina inyectable', 'vial', lotes: 5, validezMeses: 6, forzar: 'caduca_pronto'),
            $this->item('DEMO-004', 'Dexametasona inyectable', 'vial', lotes: 5, validezMeses: 9, forzar: 'caduca_pronto'),
            $this->item('DEMO-005', 'Vitaminas AD3E inyectable', 'vial', lotes: 5, validezMeses: 10, forzar: 'caduca_pronto'),
            $this->item('DEMO-006', 'Oxitetraciclina inyectable', 'vial', lotes: 5, validezMeses: 12),
            $this->item('DEMO-007', 'Meloxicam 20 mg/ml', 'vial', lotes: 5, validezMeses: 11),
            $this->item('DEMO-008', 'Calcio inyectable', 'frasco', lotes: 5, validezMeses: 13),
            $this->item('DEMO-009', 'Desparasitante oral comprimidos', 'caja', lotes: 5, validezMeses: 14),
            $this->item('DEMO-010', 'Antiparasitario pour-on 1 L', 'frasco', lotes: 5, validezMeses: 10),
            $this->item('DEMO-011', 'Suero fisiológico 500 ml', 'bolsa', lotes: 5, validezMeses: 20),
            $this->item('DEMO-012', 'Clorhexidina 1 L', 'bote', lotes: 5, validezMeses: 18),
            $this->item('DEMO-013', 'Povidona yodada 1 L', 'bote', lotes: 5, validezMeses: 18),
            $this->item('DEMO-014', 'Gel ecográfico 5 L', 'garrafa', lotes: 5, validezMeses: 24),
            $this->item('DEMO-015', 'Antibiótico intramamario', 'jeringa', lotes: 5, validezMeses: 8),
            $this->item('DEMO-016', 'Hilo de sutura reabsorbible', 'sobre', lotes: 5, validezMeses: 22),
            $this->item('DEMO-017', 'Ivermectina 1% inyectable', 'vial', lotes: 4, validezMeses: 9),
            $this->item('DEMO-018', 'Antiinflamatorio tópico 500 g', 'bote', lotes: 4, validezMeses: 12),
            $this->item('DEMO-019', 'Complejo vitamínico B oral', 'frasco', lotes: 4, validezMeses: 15),

            // Fungibles sin caducidad, posiciones normales: 3 con 4 lotes.
            $this->item('DEMO-020', 'Guantes de palpación', 'caja', lotes: 4, caducidad: false),
            $this->item('DEMO-021', 'Agujas hipodérmicas 18G', 'caja', lotes: 4, caducidad: false),
            $this->item('DEMO-022', 'Venda cohesiva', 'caja', lotes: 4, caducidad: false),

            // Cadena de frío, cámara refrigerada: 3 con 2 lotes (la llenan entera).
            $this->item('DEMO-023', 'Vacuna polivalente ovino', 'dosis', lotes: 2, validezMeses: 9, frio: true),
            $this->item('DEMO-024', 'Vacuna antirrábica', 'dosis', lotes: 2, validezMeses: 12, frio: true),
            $this->item('DEMO-025', 'Insulina veterinaria', 'vial', lotes: 2, validezMeses: 6, frio: true, forzar: 'caduca_pronto'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(
        string $codigo,
        string $nombre,
        string $unidad,
        int $lotes,
        ?int $validezMeses = null,
        bool $caducidad = true,
        bool $frio = false,
        ?string $forzar = null,
    ): array {
        return [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'unidad' => $unidad,
            'lotes' => $lotes,
            'validezMeses' => $validezMeses,
            'caducidad' => $caducidad,
            'frio' => $frio,
            'forzar' => $forzar,
        ];
    }
}
