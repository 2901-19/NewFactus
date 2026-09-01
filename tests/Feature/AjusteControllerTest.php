<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\TasaCambio;
use App\Models\User;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AjusteControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisoSeeder::class);
        foreach (['promedio', 'dolar', 'bcv'] as $tipo) {
            TasaCambio::create([
                'tipo' => $tipo,
                'nombre' => ucfirst($tipo),
                'monto' => 50.00,
                'fecha' => now()->toDateString(),
            ]);
        }
        $this->user = User::factory()->create(['rol' => 'admin']);
    }

    public function test_editar_precios_muestra_tabla()
    {
        $this->actingAs($this->user);

        $response = $this->get('/productos/ajustar-precios');

        $response->assertStatus(200);
        $response->assertSee('Actualizar Precios');
    }

    public function test_precios_data_devuelve_una_fila_por_producto()
    {
        $producto = Producto::factory()->create(['nombre' => 'Harina', 'costo_usd' => 3.00]);
        ProductoPresentacion::factory()->create([
            'producto_id' => $producto->id,
            'nombre' => 'Unidad',
            'factor_conversion' => 1,
            'margen' => 30,
            'precio_usd' => 3.90,
            'fuente_tasa' => 'promedio',
            'activa' => true,
        ]);
        $this->actingAs($this->user);

        $response = $this->getJson('/productos/ajustar-precios/data?draw=1&start=0&length=10');

        $response->assertStatus(200);
        $response->assertJson(['draw' => 1]);
        $response->assertJsonStructure(['recordsTotal', 'recordsFiltered', 'data']);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Harina', $data[0]['nombre']);
        $this->assertEquals(3.0, $data[0]['costo_usd']);
        $this->assertEquals('Unidad', $data[0]['presentaciones'][0]['nombre']);
    }

    public function test_precios_data_busca_por_nombre()
    {
        Producto::factory()->create(['nombre' => 'Harina Pan']);
        Producto::factory()->create(['nombre' => 'Azúcar']);
        $this->actingAs($this->user);

        $response = $this->getJson('/productos/ajustar-precios/data?draw=1&start=0&length=10&search[value]=Harina');

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Harina Pan', $data[0]['nombre']);
    }

    public function test_precios_data_excluye_productos_desactivados()
    {
        $activo = Producto::factory()->create(['nombre' => 'Activo']);
        $desactivado = Producto::factory()->create(['nombre' => 'Inactivo']);
        $desactivado->delete();
        $this->actingAs($this->user);

        $response = $this->getJson('/productos/ajustar-precios/data?draw=1&start=0&length=10');

        $data = $response->json('data');
        $ids = collect($data)->pluck('producto_id');
        $this->assertContains($activo->id, $ids);
        $this->assertNotContains($desactivado->id, $ids);
    }

    public function test_guardar_precio_actualiza_margen_y_precio_usd()
    {
        $producto = Producto::factory()->create(['nombre' => 'Queso', 'costo_usd' => 4.00]);
        $pres = ProductoPresentacion::factory()->create([
            'producto_id' => $producto->id,
            'nombre' => 'Unidad',
            'factor_conversion' => 1,
            'margen' => 10,
            'precio_usd' => 4.40,
            'fuente_tasa' => 'promedio',
            'activa' => true,
        ]);
        $this->actingAs($this->user);

        $response = $this->postJson("/productos/{$producto->id}/ajustar-precio", [
            'costo_usd' => 5.00,
            'presentaciones' => [
                ['id' => $pres->id, 'margen' => 20],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'costo_usd' => 5.00]);
        $this->assertDatabaseHas('producto_presentaciones', ['id' => $pres->id, 'margen' => 20, 'precio_usd' => 6.00]);
    }
}
