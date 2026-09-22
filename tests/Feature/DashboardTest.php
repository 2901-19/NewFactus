<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Producto;
use App\Models\TasaCambio;
use App\Models\User;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private TasaCambio $tasa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisoSeeder::class);
        $this->user = User::factory()->create(['rol' => 'admin']);
        $this->tasa = TasaCambio::factory()->create([
            'tipo' => 'promedio',
            'monto' => 50.00,
            'fecha' => now()->format('Y-m-d'),
        ]);
    }

    public function test_dashboard_muestra_datos()
    {
        Cliente::factory()->count(5)->create();
        $categoria = Categoria::factory()->create();
        Producto::factory()->count(10)->create(['categoria_id' => $categoria->id]);

        $this->actingAs($this->user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewHas([
            'totalProductos', 'totalClientes', 'ventasHoy', 'totalHoyBs', 'totalHoyUsd',
            'creditosPendientes', 'variacionHoy', 'variacionMes', 'porDia7', 'metodosHoy',
            'ultimasFacturas', 'creditosPendientesLista', 'tasasVigentes', 'masVendidos',
        ]);
    }

    public function test_dashboard_muestra_ceros_sin_datos()
    {
        $this->actingAs($this->user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewHas('totalProductos', function ($value) {
            return $value == 0;
        });
    }

    public function test_dashboard_muestra_facturas_tasas_y_metodos()
    {
        $cliente = Cliente::factory()->create();
        Factura::factory()->count(2)->create([
            'cliente_id' => $cliente->id,
            'user_id' => $this->user->id,
            'fecha_venta' => now()->format('Y-m-d'),
            'metodo_pago' => 'efectivo',
            'total_bs' => 5000,
            'estado' => 'contado',
        ]);
        Factura::factory()->credito()->create([
            'cliente_id' => $cliente->id,
            'user_id' => $this->user->id,
            'fecha_venta' => now()->subDays(2)->format('Y-m-d'),
            'total_usd' => 100,
            'total_bs' => 5000,
        ]);

        $this->actingAs($this->user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewHas('ventasHoy', 2);
        $response->assertViewHas('totalHoyBs', 10000);
        $response->assertViewHas('creditosPendientes', 1);
        $response->assertViewHas('ultimasFacturas', fn ($c) => $c->count() === 3);
        $response->assertViewHas('creditosPendientesLista', fn ($c) => $c->count() === 1);
        $response->assertViewHas('tasasVigentes', fn ($t) => $t->has('promedio'));
        $response->assertViewHas('metodosHoy', fn ($m) => $m['efectivo'] == 10000);
        $response->assertSee('Promedio:');
        $response->assertSee('$100,00');
    }

    public function test_credito_pendiente_hoy_no_cuenta_como_ingreso()
    {
        $cliente = Cliente::factory()->create();
        Factura::factory()->create([
            'cliente_id' => $cliente->id,
            'user_id' => $this->user->id,
            'fecha_venta' => now()->format('Y-m-d'),
            'metodo_pago' => 'efectivo',
            'total_bs' => 1000,
            'estado' => 'contado',
        ]);
        Factura::factory()->credito()->create([
            'cliente_id' => $cliente->id,
            'user_id' => $this->user->id,
            'fecha_venta' => now()->format('Y-m-d'),
            'total_bs' => 5000,
            'total_usd' => 100,
        ]);

        $this->actingAs($this->user);

        $response = $this->get('/dashboard');

        $response->assertViewHas('ventasHoy', 1);
        $response->assertViewHas('totalHoyBs', 1000);
        $response->assertViewHas('creditosPendientes', 1);
    }

    public function test_credito_cobrado_cuenta_en_fecha_del_cobro()
    {
        $cliente = Cliente::factory()->create();
        Factura::factory()->credito()->create([
            'cliente_id' => $cliente->id,
            'user_id' => $this->user->id,
            'fecha_venta' => now()->subDays(3)->format('Y-m-d'),
            'fecha_pago' => now()->format('Y-m-d'),
            'estado_credito' => 'cancelado',
            'metodo_pago' => 'punto',
            'total_bs' => 4000,
            'total_usd' => 80,
            'pago_bs' => 6000,
        ]);

        $this->actingAs($this->user);

        $response = $this->get('/dashboard');

        $response->assertViewHas('ventasHoy', 1);
        $response->assertViewHas('totalHoyBs', 6000);
        $response->assertViewHas('totalHoyUsd', 80);
        $response->assertViewHas('metodosHoy', fn ($m) => $m['punto'] == 6000);
    }

    public function test_factura_anulada_no_cuenta_como_ingreso()
    {
        $cliente = Cliente::factory()->create();
        Factura::factory()->anulada()->create([
            'cliente_id' => $cliente->id,
            'user_id' => $this->user->id,
            'fecha_venta' => now()->format('Y-m-d'),
            'metodo_pago' => 'efectivo',
            'total_bs' => 9999,
        ]);

        $this->actingAs($this->user);

        $response = $this->get('/dashboard');

        $response->assertViewHas('ventasHoy', 0);
        $response->assertViewHas('totalHoyBs', 0);
    }
}
