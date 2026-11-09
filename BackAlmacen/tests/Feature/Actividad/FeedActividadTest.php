<?php

namespace Tests\Feature\Actividad;

use App\Enums\Rol;
use App\Models\Articulo;
use App\Models\Lote;
use App\Models\Stock;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedActividadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-05 09:00:00');
    }

    private function admin(): User
    {
        return User::factory()->create(['rol' => Rol::Admin]);
    }

    private function gestor(): User
    {
        return User::factory()->create(['rol' => Rol::GestorInventario]);
    }

    private function saldoRegularizable(): array
    {
        $lote = Lote::factory()->create(['caducidad' => '2027-02-01']);
        $ubicacion = Ubicacion::factory()->create();
        Stock::create(['lote_id' => $lote->id, 'ubicacion_id' => $ubicacion->id, 'cantidad' => 10, 'cantidad_reservada' => 0]);

        return [$lote, $ubicacion];
    }

    public function test_un_movimiento_aparece_en_el_feed(): void
    {
        [$lote, $ubicacion] = $this->saldoRegularizable();
        $this->actingAs($this->gestor())->postJson('/api/inventario/ajustes', [
            'lote_id' => $lote->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad' => 7,
            'motivo' => 'recuento',
        ])->assertCreated();

        $feed = $this->actingAs($this->admin())->getJson('/api/panel/actividad')->assertOk()->json();

        $this->assertSame('ajuste', $feed[0]['tipo']);
        $this->assertSame($ubicacion->codigo, $feed[0]['ubicacion']);
    }

    public function test_una_reserva_aparece_en_el_feed(): void
    {
        $articulo = Articulo::factory()->create();
        $lote = Lote::factory()->for($articulo)->create(['caducidad' => '2027-02-01']);
        Stock::create(['lote_id' => $lote->id, 'ubicacion_id' => Ubicacion::factory()->create()->id, 'cantidad' => 10, 'cantidad_reservada' => 0]);

        $this->actingAs($this->gestor())->postJson('/api/inventario/reservas', ['articulo_id' => $articulo->id, 'cantidad' => 4])->assertOk();

        $feed = $this->actingAs($this->admin())->getJson('/api/panel/actividad')->assertOk()->json();

        $this->assertSame('reserva', $feed[0]['tipo']);
    }

    public function test_un_login_fallido_aparece_sin_el_correo_completo(): void
    {
        User::factory()->create(['email' => 'ana@almacen.local']);
        config(['sanctum.stateful' => ['localhost']]);

        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/login', ['email' => 'ana@almacen.local', 'password' => 'incorrecta'])
            ->assertStatus(422);

        $feed = $this->actingAs($this->admin())->getJson('/api/panel/actividad')->assertOk()->json();

        $evento = collect($feed)->firstWhere('tipo', 'login_fallido');
        $this->assertNotNull($evento);
        $this->assertStringNotContainsString('ana@almacen.local', $evento['detalle']);
    }

    public function test_el_feed_solo_devuelve_lo_nuevo_desde_una_marca(): void
    {
        [$lote, $ubicacion] = $this->saldoRegularizable();
        $this->actingAs($this->gestor())->postJson('/api/inventario/ajustes', [
            'lote_id' => $lote->id, 'ubicacion_id' => $ubicacion->id, 'cantidad' => 9, 'motivo' => 'uno',
        ])->assertCreated();

        $primero = $this->actingAs($this->admin())->getJson('/api/panel/actividad')->json();
        $marca = $primero[0]['fecha'];

        $this->travelTo('2026-11-05 10:00:00');
        $this->actingAs($this->gestor())->postJson('/api/inventario/ajustes', [
            'lote_id' => $lote->id, 'ubicacion_id' => $ubicacion->id, 'cantidad' => 5, 'motivo' => 'dos',
        ])->assertCreated();

        $nuevo = $this->actingAs($this->admin())
            ->getJson('/api/panel/actividad?desde='.urlencode($marca))
            ->assertOk()
            ->json();

        $this->assertCount(1, $nuevo);
        $this->assertSame('dos', $nuevo[0]['detalle']);
    }
}
