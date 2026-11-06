<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Enums\TipoMovimiento;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\Ubicacion;
use App\Models\User;
use App\Services\RegistroMovimiento;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = $this->crearUsuarios();
        ['normales' => $normales, 'frias' => $frias] = $this->crearUbicaciones();
        $this->poblarAlmacen($usuarios, $normales, $frias);
    }

    /**
     * Un usuario por rol, con contraseña conocida para el entorno local.
     *
     * @return array<string, User>
     */
    private function crearUsuarios(): array
    {
        $usuarios = [];

        foreach (Rol::cases() as $rol) {
            $usuarios[$rol->value] = User::factory()->create([
                'nombre' => ucfirst(str_replace('_', ' ', $rol->value)),
                'email' => $rol->value.'@almacen.local',
                'rol' => $rol,
            ]);
        }

        return $usuarios;
    }

    /**
     * La estantería del prototipo: 4 pasillos de 10 estanterías por 3 niveles,
     * más una cámara refrigerada con una estantería pequeña (3 por 2) para los
     * productos que necesitan frío.
     *
     * @return array{normales: list<Ubicacion>, frias: list<Ubicacion>}
     */
    private function crearUbicaciones(): array
    {
        $normales = [];
        foreach (range(1, 4) as $pasillo) {
            foreach (range(1, 10) as $estanteria) {
                foreach (range(1, 3) as $nivel) {
                    $normales[] = Ubicacion::create([
                        'codigo' => sprintf('P%d-E%02d-N%d', $pasillo, $estanteria, $nivel),
                        'tipo' => 'estante',
                        'pasillo' => $pasillo,
                        'estanteria' => $estanteria,
                        'nivel' => $nivel,
                        'capacidad' => 100,
                        'max_lotes' => 1,
                        'activa' => true,
                    ]);
                }
            }
        }

        $frias = [];
        foreach (range(1, 3) as $estanteria) {
            foreach (range(1, 2) as $nivel) {
                $frias[] = Ubicacion::create([
                    'codigo' => sprintf('FRIO-E%02d-N%d', $estanteria, $nivel),
                    'tipo' => 'refrigerado',
                    'pasillo' => 5,
                    'estanteria' => $estanteria,
                    'nivel' => $nivel,
                    'capacidad' => 100,
                    'max_lotes' => 1,
                    'activa' => true,
                ]);
            }
        }

        return ['normales' => $normales, 'frias' => $frias];
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

        $siguienteLibre = 0;
        $recibir = function (Lote $lote, float $cantidad) use ($servicio, $recepcion, $normales, &$siguienteLibre): Ubicacion {
            $ubicacion = $normales[$siguienteLibre++];
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

        // Salidas: dos consumos, un traslado interno y una baja por caducidad.
        $servicio->registrar(TipoMovimiento::Consumo, $vacunaValida, 30, $almacen, origen: $ubVacunaValida);
        $servicio->registrar(TipoMovimiento::Consumo, $amoxicilinaPrimero, 20, $almacen, origen: $ubAmoxicilinaPrimero);
        $servicio->registrar(TipoMovimiento::Traslado, $meloxicamLote, 70, $almacen, origen: $ubMeloxicam, destino: $normales[$siguienteLibre++]);
        $servicio->registrar(TipoMovimiento::Baja, $ivermectinaVencida, 25, $gestor, origen: $ubIvermectinaVencida, motivo: 'caducado');
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
