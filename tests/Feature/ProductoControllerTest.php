<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\TasaCambio;
use App\Models\User;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductoControllerTest extends TestCase
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

    public function test_index_muestra_productos()
    {
        Producto::factory()->count(3)->create();
        $this->actingAs($this->user);

        $response = $this->get('/productos');

        $response->assertStatus(200);
    }

    public function test_data_devuelve_productos_para_datatables()
    {
        $producto = Producto::factory()->create(['nombre' => 'Harina Pan']);
        Categoria::factory()->create(['nombre' => 'Harinas']);
        $this->actingAs($this->user);

        $response = $this->getJson('/productos/data?draw=1&start=0&length=10&order[0][column]=0&order[0][dir]=asc');

        $response->assertStatus(200);
        $response->assertJson(['draw' => 1]);
        $response->assertJsonStructure(['recordsTotal', 'recordsFiltered', 'data']);
        $response->assertJsonFragment(['nombre' => 'Harina Pan']);
    }

    public function test_data_permite_buscar_por_nombre()
    {
        Producto::factory()->create(['nombre' => 'Harina Pan']);
        Producto::factory()->create(['nombre' => 'Azúcar Morena']);
        $this->actingAs($this->user);

        $response = $this->getJson('/productos/data?draw=1&start=0&length=10&search[value]=Harina');

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Harina Pan', $data[0]['nombre']);
    }

    public function test_data_ordena_por_categoria()
    {
        $z = Categoria::factory()->create(['nombre' => 'Zeta']);
        $a = Categoria::factory()->create(['nombre' => 'Alfa']);
        Producto::factory()->create(['nombre' => 'Prod Z', 'categoria_id' => $z->id]);
        Producto::factory()->create(['nombre' => 'Prod A', 'categoria_id' => $a->id]);
        $this->actingAs($this->user);

        $response = $this->getJson('/productos/data?draw=1&start=0&length=10&order[0][column]=2&order[0][dir]=asc');

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals('Prod A', $data[0]['nombre']);
        $this->assertEquals('Prod Z', $data[1]['nombre']);
    }

    public function test_create_muestra_formulario()
    {
        $this->actingAs($this->user);

        $response = $this->get('/productos/create');

        $response->assertStatus(200);
    }

    public function test_store_crea_producto()
    {
        $categoria = Categoria::factory()->create();
        $this->actingAs($this->user);

        $response = $this->post('/productos', [
            'nombre' => 'Nuevo Producto',
            'categoria_id' => $categoria->id,
            'costo_usd' => 10.00,
            'unidad_medida' => 'unidad',
            'stock_actual' => 5,
            'impuesto_id' => null,
            'estado' => 'disponible',
            'presentaciones' => [
                ['nombre' => 'Unidad', 'factor_conversion' => 1, 'margen' => 25, 'fuente_tasa' => 'promedio', 'activa' => true],
            ],
        ]);

        $response->assertRedirect('/productos');
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('productos', ['nombre' => 'Nuevo Producto', 'unidad_medida' => 'unidad']);
        $this->assertDatabaseHas('producto_presentaciones', ['nombre' => 'Unidad', 'precio_usd' => 12.50, 'fuente_tasa' => 'promedio']);
    }

    public function test_store_crea_producto_pesable_sin_inventario()
    {
        $categoria = Categoria::factory()->create();
        $this->actingAs($this->user);

        $response = $this->post('/productos', [
            'nombre' => 'Queso Fresco',
            'categoria_id' => $categoria->id,
            'costo_usd' => 4.00,
            'unidad_medida' => 'kg',
            'stock_actual' => 999,
            'impuesto_id' => null,
            'estado' => 'disponible',
            'presentaciones' => [
                ['nombre' => 'Kilogramo', 'factor_conversion' => 1, 'margen' => 30, 'fuente_tasa' => 'promedio', 'activa' => true],
            ],
        ]);

        $response->assertRedirect('/productos');
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('productos', [
            'nombre' => 'Queso Fresco',
            'unidad_medida' => 'kg',
            'stock_actual' => 0,
        ]);

        $producto = Producto::where('nombre', 'Queso Fresco')->first();
        $this->assertFalse($producto->controla_inventario);
        $this->assertCount(1, $producto->presentaciones);
        $this->assertDatabaseHas('producto_presentaciones', [
            'producto_id' => $producto->id,
            'nombre' => 'Kilogramo',
            'factor_conversion' => 1,
            'margen' => 30,
            'precio_usd' => 5.20,
        ]);
    }

    public function test_store_rechaza_unidad_de_medida_no_valida()
    {
        $this->actingAs($this->user);

        $response = $this->post('/productos', [
            'nombre' => 'Invalido',
            'costo_usd' => 5,
            'unidad_medida' => 'litro',
            'estado' => 'disponible',
            'presentaciones' => [
                ['nombre' => 'Unidad', 'factor_conversion' => 1, 'margen' => 0, 'fuente_tasa' => 'promedio', 'activa' => true],
            ],
        ]);

        $response->assertSessionHasErrors('unidad_medida');
    }

    public function test_store_valida_campos_requeridos()
    {
        $this->actingAs($this->user);

        $response = $this->post('/productos', []);

        $response->assertSessionHasErrors(['nombre', 'costo_usd', 'unidad_medida', 'presentaciones', 'estado']);
    }

    public function test_edit_muestra_formulario()
    {
        $producto = Producto::factory()->create();
        $this->actingAs($this->user);

        $response = $this->get("/productos/{$producto->id}/edit");

        $response->assertStatus(200);
        $response->assertViewHas('producto');
    }

    public function test_update_actualiza_producto()
    {
        $producto = Producto::factory()->create(['nombre' => 'Original']);
        ProductoPresentacion::factory()->create([
            'producto_id' => $producto->id,
            'nombre' => 'Unidad',
            'factor_conversion' => 1,
            'margen' => 25,
            'precio_usd' => 12.50,
            'activa' => true,
        ]);
        $this->actingAs($this->user);

        $response = $this->put("/productos/{$producto->id}", [
            'nombre' => 'Actualizado',
            'categoria_id' => $producto->categoria_id,
            'costo_usd' => $producto->costo_usd,
            'unidad_medida' => 'unidad',
            'stock_actual' => $producto->stock_actual,
            'impuesto_id' => $producto->impuesto_id,
            'estado' => $producto->estado,
            'presentaciones' => [
                ['nombre' => 'Unidad', 'factor_conversion' => 1, 'margen' => 25, 'fuente_tasa' => 'promedio', 'activa' => true],
            ],
        ]);

        $response->assertRedirect('/productos');
        $this->assertDatabaseHas('productos', ['nombre' => 'Actualizado']);
    }

    public function test_update_convertir_a_kg_deja_una_sola_presentacion()
    {
        $producto = Producto::factory()->create(['nombre' => 'Granos', 'stock_actual' => 10]);
        $unidad = ProductoPresentacion::factory()->create([
            'producto_id' => $producto->id,
            'nombre' => 'Unidad',
            'factor_conversion' => 1,
            'margen' => 20,
            'precio_usd' => 12.00,
            'activa' => true,
        ]);
        ProductoPresentacion::factory()->create([
            'producto_id' => $producto->id,
            'nombre' => 'Mayor',
            'factor_conversion' => 5,
            'margen' => 10,
            'precio_usd' => 55.00,
            'activa' => true,
        ]);
        $this->actingAs($this->user);

        $response = $this->put("/productos/{$producto->id}", [
            'nombre' => $producto->nombre,
            'categoria_id' => $producto->categoria_id,
            'costo_usd' => $producto->costo_usd,
            'unidad_medida' => 'kg',
            'stock_actual' => 999,
            'impuesto_id' => $producto->impuesto_id,
            'estado' => $producto->estado,
            'presentaciones' => [
                ['id' => $unidad->id, 'nombre' => 'Kilogramo', 'factor_conversion' => 1, 'margen' => 20, 'fuente_tasa' => 'promedio', 'activa' => true],
            ],
        ]);

        $response->assertRedirect('/productos');
        $producto->refresh();

        $this->assertEquals('kg', $producto->unidad_medida);
        $this->assertEquals(0, $producto->stock_actual);
        $this->assertFalse($producto->controla_inventario);
        $this->assertCount(1, $producto->presentaciones);
        $this->assertEquals('Kilogramo', $producto->presentaciones->first()->nombre);
        $this->assertEquals(1, $producto->presentaciones->first()->factor_conversion);
    }

    public function test_destroy_desactiva_producto()
    {
        $producto = Producto::factory()->create();
        $this->actingAs($this->user);

        $response = $this->delete("/productos/{$producto->id}");

        $response->assertRedirect('/productos');
        $this->assertSoftDeleted($producto);
    }

    public function test_restore_reactiva_producto()
    {
        $producto = Producto::factory()->create();
        $producto->delete();
        $this->actingAs($this->user);

        $response = $this->post("/productos/{$producto->id}/restore");

        $response->assertRedirect('/productos');
        $this->assertNotSoftDeleted($producto);
    }

    public function test_edit_de_producto_desactivado_redirige_con_error()
    {
        $producto = Producto::factory()->create();
        $producto->delete();
        $this->actingAs($this->user);

        $response = $this->get("/productos/{$producto->id}/edit");

        $response->assertRedirect('/productos');
        $response->assertSessionHasErrors('error');
    }

    public function test_update_de_producto_desactivado_redirige_con_error()
    {
        $producto = Producto::factory()->create();
        $producto->delete();
        $this->actingAs($this->user);

        $response = $this->put("/productos/{$producto->id}", [
            'nombre' => 'Intento de edicion',
            'costo_usd' => 1,
            'unidad_medida' => 'unidad',
            'estado' => 'disponible',
            'presentaciones' => [
                ['nombre' => 'Unidad', 'factor_conversion' => 1, 'margen' => 10, 'fuente_tasa' => 'promedio', 'activa' => true],
            ],
        ]);

        $response->assertRedirect('/productos');
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('productos', ['id' => $producto->id, 'nombre' => $producto->nombre]);
    }
}
