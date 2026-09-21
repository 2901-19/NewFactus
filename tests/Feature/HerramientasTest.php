<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\TasaCambio;
use App\Models\User;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class HerramientasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisoSeeder::class);
        $this->admin = User::factory()->create(['rol' => 'admin']);
    }

    public function test_configuracion_muestra_formulario()
    {
        $this->actingAs($this->admin);

        $response = $this->get('/herramientas/configuracion');

        $response->assertStatus(200);
    }

    public function test_configuracion_guarda_datos()
    {
        $this->actingAs($this->admin);

        $response = $this->from('/herramientas/configuracion')->post('/herramientas/configuracion', [
            'nombre_negocio' => 'Mi Abasto',
            'rif' => 'J-12345678-9',
            'direccion' => 'Calle Principal',
            'telefono' => '0412-1111111',
        ]);

        $response->assertRedirect('/herramientas/configuracion');
        $response->assertSessionHas('success');

        $this->assertEquals('Mi Abasto', Configuracion::obtener('nombre_negocio'));
        $this->assertEquals('J-12345678-9', Configuracion::obtener('rif'));
        $this->assertEquals('Calle Principal', Configuracion::obtener('direccion'));
        $this->assertEquals('0412-1111111', Configuracion::obtener('telefono'));
    }

    public function test_exportar_genera_archivo()
    {
        $this->actingAs($this->admin);

        $response = $this->get('/herramientas/exportar');

        $response->assertStatus(200);
    }

    public function test_exportar_inventario_no_incluye_stock()
    {
        $this->actingAs($this->admin);
        Producto::factory()->create(['nombre' => 'Azúcar', 'stock_actual' => 99.5]);

        $response = $this->get('/herramientas/exportar?tipos[]=inventario');
        $data = json_decode($response->getContent(), true);

        $producto = collect($data['inventario'])->firstWhere('nombre', 'Azúcar');
        $this->assertNotNull($producto);
        $this->assertArrayNotHasKey('stock_actual', $producto);
    }

    public function test_importar_con_archivo_valido()
    {
        $this->actingAs($this->admin);

        $json = json_encode(['productos' => [], 'clientes' => [], 'impuestos' => [], 'tasas_cambio' => []]);
        $archivo = UploadedFile::fake()->createWithContent('datos.json', $json);

        $response = $this->from('/herramientas/datos')->post('/herramientas/importar', [
            'archivo' => $archivo,
        ]);

        $response->assertSessionHas('success');
    }

    public function test_impresora_muestra_configuracion()
    {
        $this->actingAs($this->admin);

        $response = $this->get('/herramientas/impresora');

        $response->assertStatus(200);
    }

    public function test_impresora_guarda_configuracion()
    {
        $this->actingAs($this->admin);

        $response = $this->from('/herramientas/impresora')->post('/herramientas/impresora', [
            'tipo' => 'network',
            'host' => '192.168.1.100',
            'port' => 9100,
        ]);

        $response->assertRedirect('/herramientas/impresora');
        $response->assertSessionHas('success');
    }

    public function test_datos_muestra_estadisticas()
    {
        $this->actingAs($this->admin);

        $response = $this->get('/herramientas/datos');

        $response->assertStatus(200);
    }

    public function test_precios_muestra_lista()
    {
        $this->actingAs($this->admin);

        $response = $this->get('/herramientas/precios');

        $response->assertStatus(200);
    }

    public function test_precios_filtra_por_categoria()
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Harinas']);
        $harina = Producto::factory()->create(['nombre' => 'Harina Pan', 'categoria_id' => $categoria->id]);
        ProductoPresentacion::factory()->create([
            'producto_id' => $harina->id,
            'nombre' => 'Unidad',
            'precio_usd' => 3.00,
            'activa' => true,
        ]);
        Producto::factory()->create(['nombre' => 'Azúcar', 'categoria_id' => null]);
        $this->actingAs($this->admin);

        $response = $this->get('/herramientas/precios?categoria_id='.$categoria->id);

        $response->assertStatus(200);
        $response->assertSee('Harina Pan');
        $response->assertDontSee('Azúcar');
    }

    public function test_precios_filtra_por_presentacion()
    {
        $harina = Producto::factory()->create(['nombre' => 'Harina Pan']);
        ProductoPresentacion::factory()->create([
            'producto_id' => $harina->id,
            'nombre' => 'Unidad',
            'precio_usd' => 3.00,
            'activa' => true,
        ]);
        ProductoPresentacion::factory()->create([
            'producto_id' => $harina->id,
            'nombre' => 'Mayor',
            'precio_usd' => 24.00,
            'activa' => true,
        ]);
        $azucar = Producto::factory()->create(['nombre' => 'Azúcar']);
        ProductoPresentacion::factory()->create([
            'producto_id' => $azucar->id,
            'nombre' => 'Unidad',
            'precio_usd' => 1.00,
            'activa' => true,
        ]);
        $this->actingAs($this->admin);

        $response = $this->get('/herramientas/precios?presentacion[]=Mayor');

        $response->assertStatus(200);
        $response->assertSee('Harina Pan');
        $response->assertDontSee('Azúcar');
    }

    public function test_precios_sin_filtro_incluye_todas_las_presentaciones()
    {
        $harina = Producto::factory()->create(['nombre' => 'Harina Pan']);
        ProductoPresentacion::factory()->create([
            'producto_id' => $harina->id,
            'nombre' => 'Unidad',
            'precio_usd' => 3.00,
            'activa' => true,
        ]);
        ProductoPresentacion::factory()->create([
            'producto_id' => $harina->id,
            'nombre' => 'Mayor',
            'precio_usd' => 24.00,
            'activa' => true,
        ]);
        $this->actingAs($this->admin);

        $response = $this->get('/herramientas/precios');

        $response->assertStatus(200);
        $response->assertSee('Harina Pan');
        $response->assertSee('Unidad');
        $response->assertSee('Mayor');
    }

    public function test_precios_json_respeta_filtro_presentacion()
    {
        $harina = Producto::factory()->create(['nombre' => 'Harina Pan']);
        ProductoPresentacion::factory()->create([
            'producto_id' => $harina->id,
            'nombre' => 'Unidad',
            'precio_usd' => 3.00,
            'activa' => true,
        ]);
        ProductoPresentacion::factory()->create([
            'producto_id' => $harina->id,
            'nombre' => 'Mayor',
            'precio_usd' => 24.00,
            'activa' => true,
        ]);
        $azucar = Producto::factory()->create(['nombre' => 'Azúcar']);
        ProductoPresentacion::factory()->create([
            'producto_id' => $azucar->id,
            'nombre' => 'Unidad',
            'precio_usd' => 1.00,
            'activa' => true,
        ]);
        $this->actingAs($this->admin);

        $response = $this->get('/herramientas/precios?export=json&presentacion[]=Mayor');
        $response->assertStatus(200)->assertJsonCount(1);

        $json = $response->json();
        $this->assertEquals('Harina Pan', $json[0]['nombre']);
        $this->assertEquals(['Mayor'], array_column($json[0]['presentaciones'], 'nombre'));

        $combinado = $this->get('/herramientas/precios?export=json&presentacion[]=Mayor&presentacion[]=Unidad');
        $combinado->assertStatus(200)->assertJsonCount(2);
    }

    public function test_precios_pdf_aplica_filtro_presentacion()
    {
        $harina = Producto::factory()->create(['nombre' => 'Harina Pan']);
        ProductoPresentacion::factory()->create([
            'producto_id' => $harina->id,
            'nombre' => 'Unidad',
            'precio_usd' => 3.00,
            'activa' => true,
        ]);
        ProductoPresentacion::factory()->create([
            'producto_id' => $harina->id,
            'nombre' => 'Mayor',
            'precio_usd' => 24.00,
            'activa' => true,
        ]);
        $this->actingAs($this->admin);

        $response = $this->get('/herramientas/precios/pdf?presentacion[]=Mayor');

        $response->assertOk();
        $this->assertStringContainsString('mayor', (string) $response->headers->get('content-disposition'));
        $this->assertStringNotContainsString('unidad', (string) $response->headers->get('content-disposition'));
    }

    public function test_precios_ignora_presentaciones_inexistentes()
    {
        Producto::factory()->create(['nombre' => 'Azúcar']);
        $this->actingAs($this->admin);

        $response = $this->get('/herramientas/precios?export=json&presentacion[]=Inexistente');

        $response->assertStatus(200)->assertJsonCount(1);
    }

    public function test_imprimir_precio_falla_sin_presentacion_id()
    {
        $producto = Producto::factory()->create();
        $this->actingAs($this->admin);

        $response = $this->from('/herramientas/precios')
            ->get('/herramientas/precios/imprimir?producto_id='.$producto->id);

        $response->assertRedirect('/herramientas/precios');
        $response->assertSessionHasErrors('presentacion_id');
    }

    public function test_imprimir_precio_rechaza_presentacion_de_otro_producto()
    {
        $producto = Producto::factory()->create(['nombre' => 'Aceite']);
        $otro = Producto::factory()->create(['nombre' => 'Arroz']);
        $presentacion = ProductoPresentacion::factory()->create([
            'producto_id' => $otro->id,
            'nombre' => 'Bolsa 1kg',
        ]);
        $this->actingAs($this->admin);

        $response = $this->from('/herramientas/precios')
            ->get('/herramientas/precios/imprimir?producto_id='.$producto->id.'&presentacion_id='.$presentacion->id);

        $response->assertRedirect('/herramientas/precios');
        $response->assertSessionHasErrors('error');
    }

    public function test_imprimir_precio_avisa_si_no_hay_tasa()
    {
        $producto = Producto::factory()->create(['nombre' => 'Harina']);
        $presentacion = ProductoPresentacion::factory()->create([
            'producto_id' => $producto->id,
            'nombre' => 'Unidad',
            'precio_usd' => 3.00,
            'fuente_tasa' => 'usdt',
        ]);
        TasaCambio::factory()->create(['tipo' => 'promedio', 'monto' => 60.00]);
        $this->actingAs($this->admin);

        $response = $this->from('/herramientas/precios')
            ->get('/herramientas/precios/imprimir?producto_id='.$producto->id.'&presentacion_id='.$presentacion->id);

        $response->assertRedirect('/herramientas/precios');
        $response->assertSessionHasErrors('error');
    }

    public function test_importar_tasas_repetidas_conserva_historial()
    {
        $this->actingAs($this->admin);

        $json = json_encode(['tasas_cambio' => [
            ['tipo' => 'promedio', 'moneda' => 'USD', 'monto' => 50.00, 'fecha' => '2026-07-04'],
            ['tipo' => 'promedio', 'moneda' => 'USD', 'monto' => 52.00, 'fecha' => '2026-07-05'],
        ]]);
        $archivo = UploadedFile::fake()->createWithContent('datos.json', $json);

        $response = $this->from('/herramientas/datos')->post('/herramientas/importar', [
            'archivo' => $archivo,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseCount('tasa_cambios', 2);
        $this->assertDatabaseHas('tasa_cambios', ['tipo' => 'promedio', 'monto' => 50.00, 'origen' => 'importado']);
        $this->assertDatabaseHas('tasa_cambios', ['tipo' => 'promedio', 'monto' => 52.00, 'origen' => 'importado']);
    }

    public function test_importar_inventario_crea_producto_con_stock_actual()
    {
        $this->actingAs($this->admin);

        $json = json_encode(['inventario' => [
            ['nombre' => 'Azúcar', 'unidad_medida' => 'unidad', 'stock_actual' => 25.5],
        ]]);
        $archivo = UploadedFile::fake()->createWithContent('datos.json', $json);

        $response = $this->from('/herramientas/datos')->post('/herramientas/importar', [
            'archivo' => $archivo,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('productos', [
            'nombre' => 'Azúcar',
            'unidad_medida' => 'unidad',
            'stock_actual' => 25.5,
        ]);
    }

    public function test_importar_inventario_kilo_se_normaliza_a_pesable()
    {
        $this->actingAs($this->admin);

        $json = json_encode(['inventario' => [
            ['nombre' => 'Harina Maíz', 'unidad_medida' => 'kilo', 'stock_actual' => 25.5],
        ]]);
        $archivo = UploadedFile::fake()->createWithContent('datos.json', $json);

        $this->from('/herramientas/datos')->post('/herramientas/importar', [
            'archivo' => $archivo,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('productos', [
            'nombre' => 'Harina Maíz',
            'unidad_medida' => 'kg',
            'stock_actual' => 0,
        ]);
        $producto = Producto::where('nombre', 'Harina Maíz')->first();
        $this->assertDatabaseHas('producto_presentaciones', [
            'producto_id' => $producto->id,
            'nombre' => 'Kilogramo',
            'factor_conversion' => 1,
        ]);
    }

    public function test_importar_precios_cero_marca_producto_no_disponible()
    {
        $this->actingAs($this->admin);

        $json = json_encode(['precios' => [
            ['nombre' => 'Harina', 'costo_usd' => 0, 'tiene_iva' => true, 'fuente_tasa' => 'promedio', 'presentaciones' => [
                ['nombre' => 'Unidad', 'factor_conversion' => 1, 'margen' => 0, 'activa' => true],
            ]],
        ]]);
        $archivo = UploadedFile::fake()->createWithContent('datos.json', $json);

        $response = $this->from('/herramientas/datos')->post('/herramientas/importar', [
            'archivo' => $archivo,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('productos', ['nombre' => 'Harina', 'estado' => 'no_disponible']);
    }

    public function test_importar_productos_formato_antiguo_crea_presentaciones()
    {
        $this->actingAs($this->admin);

        $json = json_encode(['productos' => [
            [
                'nombre' => 'Leche',
                'costo_usd' => 2.00,
                'margen_detal' => 25,
                'precio_unitario_usd' => 2.50,
                'margen_mayor' => 12.5,
                'precio_mayor_usd' => 4.50,
                'unidades_por_paquete' => 2,
            ],
        ]]);
        $archivo = UploadedFile::fake()->createWithContent('datos.json', $json);

        $response = $this->from('/herramientas/datos')->post('/herramientas/importar', [
            'archivo' => $archivo,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('productos', ['nombre' => 'Leche', 'estado' => 'disponible', 'costo_usd' => 2.00]);
        $this->assertDatabaseHas('producto_presentaciones', [
            'nombre' => 'Unidad',
            'factor_conversion' => 1,
            'margen' => 25.00,
            'precio_usd' => 2.50,
            'activa' => true,
        ]);
        $this->assertDatabaseHas('producto_presentaciones', [
            'nombre' => 'Mayor',
            'factor_conversion' => 1,
            'precio_usd' => 4.50,
            'activa' => true,
        ]);
    }

    public function test_importar_inventario_crea_presentacion_unidad()
    {
        $this->actingAs($this->admin);

        $json = json_encode(['inventario' => [
            ['nombre' => 'Aceite', 'unidad_medida' => 'botella', 'stock_actual' => 12],
        ]]);
        $archivo = UploadedFile::fake()->createWithContent('datos.json', $json);

        $this->from('/herramientas/datos')->post('/herramientas/importar', [
            'archivo' => $archivo,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('productos', [
            'nombre' => 'Aceite',
            'unidad_medida' => 'unidad',
            'stock_actual' => 12,
        ]);
        $this->assertDatabaseHas('producto_presentaciones', [
            'nombre' => 'Unidad',
            'factor_conversion' => 1,
            'precio_usd' => 0,
            'activa' => true,
        ]);
    }

    public function test_importar_precios_formato_antiguo_conserva_presentacion_mayor()
    {
        $this->actingAs($this->admin);

        $json = json_encode(['precios' => [
            [
                'nombre' => 'Arroz',
                'costo_usd' => 3.00,
                'margen_detal' => 20,
                'precio_unitario_usd' => 3.60,
                'margen_mayor' => 26.67,
                'precio_mayor_usd' => 3.80,
            ],
        ]]);
        $archivo = UploadedFile::fake()->createWithContent('datos.json', $json);

        $this->from('/herramientas/datos')->post('/herramientas/importar', [
            'archivo' => $archivo,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('producto_presentaciones', ['nombre' => 'Unidad', 'margen' => 20.00, 'precio_usd' => 3.60]);
        $this->assertDatabaseHas('producto_presentaciones', ['nombre' => 'Mayor', 'precio_usd' => 3.80]);
    }

    public function test_importar_tasas_respeta_activo_del_archivo()
    {
        $this->actingAs($this->admin);

        $json = json_encode(['tasas_cambio' => [
            ['tipo' => 'promedio', 'monto' => 50.00, 'fecha' => '2026-08-01', 'activo' => false],
        ]]);
        $archivo = UploadedFile::fake()->createWithContent('datos.json', $json);

        $this->from('/herramientas/datos')->post('/herramientas/importar', [
            'archivo' => $archivo,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('tasa_cambios', [
            'tipo' => 'promedio',
            'monto' => 50.00,
            'activo' => false,
            'origen' => 'importado',
        ]);
    }
}
