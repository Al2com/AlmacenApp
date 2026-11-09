<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Enums\TipoMovimiento;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\LanzarOrden;
use App\Services\RegistroMovimiento;
use Database\Seeders\Concerns\CreaEstructuraBase;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use CreaEstructuraBase;

    public function run(): void
    {
        $usuarios = $this->crearUsuarios();
        ['normales' => $normales, 'frias' => $frias] = $this->crearUbicaciones();
        $this->poblarAlmacen($usuarios, $normales, $frias);
    }

    /**
     * Artículos con y sin caducidad, lotes en distintos estados (caducado,
     * caduca pronto, válido y sin caducidad) y una docena de movimientos que
     * dejan el stock coherente. Las fechas se eligen respecto a «hoy»
     * (2026-11-05) para que los estados se vean en el panel.
     *
     * @param  array<string, User>  $usuarios
     * @param  list<Ubicacion>  $normales
     * @param  list<Ubicacion>  $frias
     */
    private function poblarAlmacen(array $usuarios, array $normales, array $frias): void
    {
        $servicio = new RegistroMovimiento;
        $recepcion = $usuarios[Rol::OperadorRecepcion->value];
        $almacen = $usuarios[Rol::OperadorAlmacen->value];
        $gestor = $usuarios[Rol::GestorInventario->value];

        // Se reparte el llenado intercalando los cuatro pasillos, en vez de
        // llenar uno entero, para que el plano se vea poblado por todas partes.
        $porPasillo = [];
        foreach ($normales as $ubicacion) {
            $porPasillo[$ubicacion->pasillo][] = $ubicacion;
        }
        $intercaladas = [];
        $maximo = max(array_map('count', $porPasillo));
        for ($i = 0; $i < $maximo; $i++) {
            foreach ($porPasillo as $lista) {
                if (isset($lista[$i])) {
                    $intercaladas[] = $lista[$i];
                }
            }
        }

        $siguienteLibre = 0;
        $recibir = function (Lote $lote, float $cantidad) use ($servicio, $recepcion, $intercaladas, &$siguienteLibre): Ubicacion {
            $ubicacion = $intercaladas[$siguienteLibre++];
            $servicio->registrar(TipoMovimiento::Recepcion, $lote, $cantidad, $recepcion, destino: $ubicacion);

            return $ubicacion;
        };

        $siguienteFrio = 0;
        $recibirFrio = function (Lote $lote, float $cantidad) use ($servicio, $recepcion, $frias, &$siguienteFrio): Ubicacion {
            $ubicacion = $frias[$siguienteFrio++];
            $servicio->registrar(TipoMovimiento::Recepcion, $lote, $cantidad, $recepcion, destino: $ubicacion);

            return $ubicacion;
        };

        // Vacuna (inyectable, cadena de frío) → cámara refrigerada. Un lote caducado
        // (a la espera de baja), uno que caduca pronto y uno válido: FEFO dentro de un artículo.
        $vacuna = Articulo::factory()->create(['codigo' => 'VAC-001', 'nombre' => 'Vacuna polivalente ovino', 'unidad' => 'dosis']);
        $recibirFrio($this->lote($vacuna, 'VAC-2609', '2026-09-20', '2026-10-25'), 50);
        $recibirFrio($this->lote($vacuna, 'VAC-2610', '2026-10-28', '2026-11-09'), 80);
        $vacunaValida = $this->lote($vacuna, 'VAC-2611', '2026-11-02', '2027-02-01');
        $ubVacunaValida = $recibirFrio($vacunaValida, 100);

        // Amoxicilina inyectable: dos lotes válidos.
        $amoxicilina = Articulo::factory()->create(['codigo' => 'AMX-001', 'nombre' => 'Amoxicilina 150 mg/ml inyectable', 'unidad' => 'vial']);
        $amoxicilinaPrimero = $this->lote($amoxicilina, 'AMX-2610', '2026-10-15', '2026-12-15');
        $ubAmoxicilinaPrimero = $recibir($amoxicilinaPrimero, 60);
        $recibir($this->lote($amoxicilina, 'AMX-2611', '2026-11-01', '2027-01-20'), 90);

        // Ivermectina: uno que caduca pronto y otro ya caducado que se da de baja.
        $ivermectina = Articulo::factory()->create(['codigo' => 'IVE-001', 'nombre' => 'Ivermectina 1% inyectable', 'unidad' => 'vial']);
        $recibir($this->lote($ivermectina, 'IVE-2610', '2026-10-25', '2026-11-10'), 40);
        $ivermectinaVencida = $this->lote($ivermectina, 'IVE-2609', '2026-09-01', '2026-10-30');
        $ubIvermectinaVencida = $recibir($ivermectinaVencida, 25);

        // Meloxicam: un lote válido que luego se traslada a otra ubicación.
        $meloxicam = Articulo::factory()->create(['codigo' => 'MEL-001', 'nombre' => 'Meloxicam 20 mg/ml', 'unidad' => 'vial']);
        $meloxicamLote = $this->lote($meloxicam, 'MEL-2610', '2026-10-20', '2027-03-01');
        $ubMeloxicam = $recibir($meloxicamLote, 70);

        // Fungibles sin caducidad.
        $jeringas = Articulo::factory()->sinCaducidad()->create(['codigo' => 'JER-001', 'nombre' => 'Jeringa desechable 5 ml', 'unidad' => 'ud']);
        $recibir(Lote::factory()->for($jeringas)->sinCaducidad()->create(['codigo_lote' => 'JER-0001', 'fecha_recepcion' => '2026-10-10']), 100);

        $guantes = Articulo::factory()->sinCaducidad()->create(['codigo' => 'GUA-001', 'nombre' => 'Guantes de nitrilo talla M', 'unidad' => 'ud']);
        $recibir(Lote::factory()->for($guantes)->sinCaducidad()->create(['codigo_lote' => 'GUA-0001', 'fecha_recepcion' => '2026-10-12']), 100);

        // Catálogo amplio de material veterinario repartido por los pasillos,
        // con estados variados (caducado, caduca pronto, válido y sin caducidad).
        $crearYRecibir = function (array $item, callable $recibe): void {
            [$codigo, $nombre, $unidad, $caducidad, $cantidad] = $item;
            if ($caducidad === null) {
                $articulo = Articulo::factory()->sinCaducidad()->create(['codigo' => $codigo, 'nombre' => $nombre, 'unidad' => $unidad]);
                $lote = Lote::factory()->for($articulo)->sinCaducidad()->create(['codigo_lote' => $codigo.'-01', 'fecha_recepcion' => '2026-10-20']);
            } else {
                $articulo = Articulo::factory()->create(['codigo' => $codigo, 'nombre' => $nombre, 'unidad' => $unidad]);
                $lote = $this->lote($articulo, $codigo.'-01', '2026-10-20', $caducidad);
            }
            $recibe($lote, $cantidad);
        };

        $catalogo = [
            ['DESP-001', 'Desparasitante oral comprimidos', 'caja', '2027-04-01', 45],
            ['POUR-001', 'Antiparasitario pour-on 1 L', 'frasco', '2027-02-15', 30],
            ['FLUN-001', 'Flunixino meglumina inyectable', 'vial', '2026-11-10', 24],
            ['DEXA-001', 'Dexametasona inyectable', 'vial', '2027-01-20', 36],
            ['ADE-001', 'Vitaminas AD3E inyectable', 'vial', '2027-05-01', 40],
            ['OXIT-001', 'Oxitetraciclina inyectable', 'vial', '2026-10-28', 18],
            ['CALC-001', 'Calcio inyectable', 'frasco', '2027-03-10', 28],
            ['SUERO-001', 'Suero fisiológico 500 ml', 'bolsa', '2027-08-01', 60],
            ['CLOR-001', 'Clorhexidina 1 L', 'bote', '2027-06-01', 22],
            ['POVI-001', 'Povidona yodada 1 L', 'bote', '2027-06-01', 20],
            ['GEL-001', 'Gel ecográfico 5 L', 'garrafa', '2028-01-01', 15],
            ['MAMI-001', 'Antibiótico intramamario', 'jeringa', '2026-11-08', 30],
            ['SUTU-001', 'Hilo de sutura reabsorbible', 'sobre', '2027-09-01', 50],
            ['GUAP-001', 'Guantes de palpación', 'caja', null, 40],
            ['AGUJ-001', 'Agujas hipodérmicas 18G', 'caja', null, 55],
            ['VEND-001', 'Venda cohesiva', 'caja', null, 35],
        ];
        foreach ($catalogo as $item) {
            $crearYRecibir($item, $recibir);
        }

        // Más productos de cadena de frío que acaban de llenar la cámara.
        $catalogoFrio = [
            ['OXIP-001', 'Oxitocina inyectable', 'vial', '2027-02-01', 20],
            ['RABIA-001', 'Vacuna antirrábica', 'dosis', '2027-07-01', 40],
            ['INSU-001', 'Insulina veterinaria', 'vial', '2026-11-11', 12],
        ];
        foreach ($catalogoFrio as $item) {
            $crearYRecibir($item, $recibirFrio);
        }

        // Salidas: dos consumos, un traslado interno y una baja por caducidad.
        $servicio->registrar(TipoMovimiento::Consumo, $vacunaValida, 30, $almacen, origen: $ubVacunaValida);
        $servicio->registrar(TipoMovimiento::Consumo, $amoxicilinaPrimero, 20, $almacen, origen: $ubAmoxicilinaPrimero);
        $servicio->registrar(TipoMovimiento::Traslado, $meloxicamLote, 70, $almacen, origen: $ubMeloxicam, destino: $intercaladas[$siguienteLibre++]);
        $servicio->registrar(TipoMovimiento::Baja, $ivermectinaVencida, 25, $gestor, origen: $ubIvermectinaVencida, motivo: 'caducado');

        // Orden ya lanzada, lista para picar en la PDA: reserva por FEFO y genera
        // tareas que recorren varios pasillos. Así la demo arranca sin crearla a mano.
        $porCodigo = fn (string $codigo) => Articulo::where('codigo', $codigo)->firstOrFail();
        (new LanzarOrden)->lanzar($usuarios[Rol::OperadorProduccion->value], 'OP-DEMO-1', [
            ['articulo' => $porCodigo('AMX-001'), 'cantidad' => 30],
            ['articulo' => $porCodigo('SUERO-001'), 'cantidad' => 40],
            ['articulo' => $porCodigo('CALC-001'), 'cantidad' => 20],
            ['articulo' => $porCodigo('DESP-001'), 'cantidad' => 25],
            ['articulo' => $porCodigo('ADE-001'), 'cantidad' => 20],
        ]);
    }

    private function lote(Articulo $articulo, string $codigo, string $recepcion, string $caducidad): Lote
    {
        return Lote::factory()->for($articulo)->create([
            'codigo_lote' => $codigo,
            'fecha_recepcion' => $recepcion,
            'caducidad' => $caducidad,
        ]);
    }
}
