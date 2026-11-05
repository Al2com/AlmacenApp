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
        $ubicaciones = $this->crearUbicaciones();
        $this->poblarAlmacen($usuarios, $ubicaciones);
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
     * La estantería del prototipo: 4 pasillos de 10 estanterías por 3 niveles.
     *
     * @return list<Ubicacion>
     */
    private function crearUbicaciones(): array
    {
        $ubicaciones = [];

        foreach (range(1, 4) as $pasillo) {
            foreach (range(1, 10) as $estanteria) {
                foreach (range(1, 3) as $nivel) {
                    $ubicaciones[] = Ubicacion::create([
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

        return $ubicaciones;
    }

    /**
     * Artículos con y sin caducidad, lotes en distintos estados (caducado,
     * caduca pronto, válido y sin caducidad) y una docena de movimientos que
     * dejan el stock coherente. Las fechas se eligen respecto a «hoy»
     * (2026-11-05) para que los estados se vean en el panel.
     *
     * @param  array<string, User>  $usuarios
     * @param  list<Ubicacion>  $ubicaciones
     */
    private function poblarAlmacen(array $usuarios, array $ubicaciones): void
    {
        $servicio = new RegistroMovimiento;
        $recepcion = $usuarios[Rol::OperadorRecepcion->value];
        $almacen = $usuarios[Rol::OperadorAlmacen->value];
        $gestor = $usuarios[Rol::GestorInventario->value];

        $siguienteLibre = 0;
        $recibir = function (Lote $lote, float $cantidad) use ($servicio, $recepcion, $ubicaciones, &$siguienteLibre): Ubicacion {
            $ubicacion = $ubicaciones[$siguienteLibre++];
            $servicio->registrar(TipoMovimiento::Recepcion, $lote, $cantidad, $recepcion, destino: $ubicacion);

            return $ubicacion;
        };

        // Harina: un lote caducado (queda a la espera de baja), uno que caduca
        // pronto y uno válido; sirve para ver el FEFO dentro de un artículo.
        $harina = Articulo::factory()->create(['codigo' => 'HAR-001', 'nombre' => 'Harina de trigo', 'unidad' => 'kg']);
        $recibir($this->lote($harina, 'HAR-2609', '2026-09-20', '2026-10-25'), 50);
        $recibir($this->lote($harina, 'HAR-2610', '2026-10-28', '2026-11-09'), 80);
        $harinaValida = $this->lote($harina, 'HAR-2611', '2026-11-02', '2027-02-01');
        $ubHarinaValida = $recibir($harinaValida, 100);

        // Azúcar: dos lotes válidos.
        $azucar = Articulo::factory()->create(['codigo' => 'AZU-001', 'nombre' => 'Azúcar blanca', 'unidad' => 'kg']);
        $azucarPrimero = $this->lote($azucar, 'AZU-2610', '2026-10-15', '2026-12-15');
        $ubAzucarPrimero = $recibir($azucarPrimero, 60);
        $recibir($this->lote($azucar, 'AZU-2611', '2026-11-01', '2027-01-20'), 90);

        // Leche en polvo: uno que caduca pronto y otro ya caducado que se da de baja.
        $leche = Articulo::factory()->create(['codigo' => 'LEC-001', 'nombre' => 'Leche en polvo', 'unidad' => 'kg']);
        $recibir($this->lote($leche, 'LEC-2610', '2026-10-25', '2026-11-10'), 40);
        $lecheVencida = $this->lote($leche, 'LEC-2609', '2026-09-01', '2026-10-30');
        $ubLecheVencida = $recibir($lecheVencida, 25);

        // Aceite: un lote válido que luego se traslada a otra ubicación.
        $aceite = Articulo::factory()->create(['codigo' => 'ACE-001', 'nombre' => 'Aceite de girasol', 'unidad' => 'l']);
        $aceiteLote = $this->lote($aceite, 'ACE-2610', '2026-10-20', '2027-03-01');
        $ubAceite = $recibir($aceiteLote, 70);

        // Artículos sin caducidad.
        $tornillos = Articulo::factory()->sinCaducidad()->create(['codigo' => 'TOR-001', 'nombre' => 'Tornillo M6', 'unidad' => 'ud']);
        $recibir(Lote::factory()->for($tornillos)->sinCaducidad()->create(['codigo_lote' => 'TOR-0001', 'fecha_recepcion' => '2026-10-10']), 100);

        $etiquetas = Articulo::factory()->sinCaducidad()->create(['codigo' => 'ETI-001', 'nombre' => 'Etiqueta adhesiva', 'unidad' => 'ud']);
        $recibir(Lote::factory()->for($etiquetas)->sinCaducidad()->create(['codigo_lote' => 'ETI-0001', 'fecha_recepcion' => '2026-10-12']), 100);

        // Salidas: dos consumos, un traslado interno y una baja por caducidad.
        $servicio->registrar(TipoMovimiento::Consumo, $harinaValida, 30, $almacen, origen: $ubHarinaValida);
        $servicio->registrar(TipoMovimiento::Consumo, $azucarPrimero, 20, $almacen, origen: $ubAzucarPrimero);
        $servicio->registrar(TipoMovimiento::Traslado, $aceiteLote, 70, $almacen, origen: $ubAceite, destino: $ubicaciones[$siguienteLibre++]);
        $servicio->registrar(TipoMovimiento::Baja, $lecheVencida, 25, $gestor, origen: $ubLecheVencida, motivo: 'caducado');
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
