<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Impuesto;
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

    public function test_exportar_precios_incluye_unidad_medida_y_presentaciones()
    {
        $this->actingAs($this->admin);
        $producto = Producto::factory()->create([
            'nombre' => 'Queso',
            'unidad_medida' => 'kg',
            'costo_usd' => 4.00,
        ]);
        ProductoPresentacion::factory()->create([
            'producto_id' => $producto->id,
            'nombre' => 'Kilogramo',
            'factor_conversion' => 1,
            'margen' => 30,
            'precio_usd' => 5.20,
            'fuente_tasa' => 'promedio',
            'activa' => true,
        ]);

        $response = $this->get('/herramientas/exportar?tipos[]=precios');
        $data = json_decode($response->getContent(), true);
        $item = collect($data['precios'])->firstWhere('nombre', 'Queso');

        $this->assertNotNull($item);
        $this->assertEquals('kg', $item['unidad_medida']);
        $this->assertFalse($item['controla_inventario']);
        $this->assertEquals('Kilogramo', $item['presentaciones'][0]['nombre']);
        $this->assertEquals(1, $item['presentaciones'][0]['factor_conversion']);
        $this->assertEquals(30, $item['presentaciones'][0]['margen']);
        $this->assertEquals('promedio', $item['presentaciones'][0]['fuente_tasa']);
    }

    public function test_guardar_venta_sin_stock_persiste_la_configuracion()
    {
        $this->actingAs($this->admin);

        $this->from('/herramientas/configuracion')
            ->post('/herramientas/venta-sin-stock', ['permitir_venta_sin_stock' => '1'])
            ->assertRedirect('/herramientas/configuracion');
        $this->assertEquals('1', Configuracion::obtener('permitir_venta_sin_stock'));

        $this->from('/herramientas/configuracion')
            ->post('/herramientas/venta-sin-stock', [])
            ->assertRedirect('/herramientas/configuracion');
        $this->assertEquals('0', Configuracion::obtener('permitir_venta_sin_stock'));
    }

    public function test_importar_precios_respeta_unidad_medida_al_crear()
    {
        $this->actingAs($this->admin);

        $json = json_encode(['precios' => [[
            'nombre' => 'Queso Importado',
            'costo_usd' => 4.00,
            'unidad_medida' => 'kg',
            'estado' => 'disponible',
            'presentaciones' => [
                ['nombre' => 'Kilogramo', 'factor_conversion' => 5, 'margen' => 30, 'fuente_tasa' => 'promedio', 'activa' => true],
                ['nombre' => 'Media', 'factor_conversion' => 0.5, 'margen' => 25, 'fuente_tasa' => 'promedio', 'activa' => true],
            ],
        ]]]);
        $archivo = UploadedFile::fake()->createWithContent('precios.json', $json);

        $this->from('/herramientas/datos')->post('/herramientas/importar', [
            'archivo' => $archivo,
            'tipos' => ['precios'],
        ])->assertRedirect('/herramientas/datos');

        $producto = Producto::where('nombre', 'Queso Importado')->first();

        $this->assertNotNull($producto);
        $this->assertEquals('kg', $producto->unidad_medida);
        $this->assertEquals(0, (float) $producto->stock_actual);
        $this->assertCount(2, $producto->presentaciones);
        $this->assertEquals([1.0, 1.0], $producto->presentaciones->pluck('factor_conversion')->all());
    }

    public function test_importar_precios_actualiza_unidad_de_producto_existente()
    {
        $producto = Producto::factory()->create([
            'nombre' => 'Queso',
            'unidad_medida' => 'unidad',
            'stock_actual' => 10,
        ]);
        ProductoPresentacion::factory()->create([
            'producto_id' => $producto->id,
            'nombre' => 'Unidad',
            'factor_conversion' => 1,
            'margen' => 10,
            'precio_usd' => 4.40,
            'fuente_tasa' => 'promedio',
            'activa' => true,
        ]);
        $this->actingAs($this->admin);

        $json = json_encode(['precios' => [[
            'nombre' => 'Queso',
            'costo_usd' => 4.00,
            'unidad_medida' => 'kg',
            'estado' => 'disponible',
            'presentaciones' => [
                ['nombre' => 'Kilogramo', 'factor_conversion' => 1, 'margen' => 30, 'fuente_tasa' => 'promedio', 'activa' => true],
            ],
        ]]]);
        $archivo = UploadedFile::fake()->createWithContent('precios.json', $json);

        $this->from('/herramientas/datos')->post('/herramientas/importar', [
            'archivo' => $archivo,
            'tipos' => ['precios'],
        ])->assertRedirect('/herramientas/datos');

        $producto->refresh();
        $this->assertEquals('kg', $producto->unidad_medida);
        $this->assertEquals(0, (float) $producto->stock_actual);
        $this->assertEquals(1, $producto->presentaciones->first()->factor_conversion);
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

    public function test_importar_precios_guarda_lote_y_estado()
    {
        $this->actingAs($this->admin);
        $producto = Producto::factory()->create(['nombre' => 'Coamo 900ml', 'costo_usd' => 1.70, 'estado' => 'disponible']);

        $json = json_encode(['precios' => [[
            'nombre' => 'Coamo 900ml',
            'costo_usd' => 2.23,
            'unidades_lote' => 20,
            'precio_lote' => 44.50,
            'estado' => 'no_disponible',
            'presentaciones' => [
                ['nombre' => 'Unidad', 'factor_conversion' => 1, 'margen' => 7, 'fuente_tasa' => 'usdt', 'activa' => true],
            ],
        ]]]);
        $archivo = UploadedFile::fake()->createWithContent('datos.json', $json);

        $response = $this->from('/herramientas/datos')->post('/herramientas/importar', ['archivo' => $archivo]);

        $response->assertSessionHas('success');
        $producto->refresh();
        $this->assertEquals(2.23, $producto->costo_usd);
        $this->assertEquals(20, $producto->unidades_lote);
        $this->assertEquals(44.50, $producto->precio_lote);
        $this->assertEquals('no_disponible', $producto->estado);
    }

    public function test_importar_precios_sin_lote_conserva_el_guardado()
    {
        $this->actingAs($this->admin);
        $producto = Producto::factory()->create(['nombre' => 'Coamo 900ml', 'unidades_lote' => 20, 'precio_lote' => 44.50]);

        $json = json_encode(['precios' => [[
            'nombre' => 'Coamo 900ml',
            'costo_usd' => 2.23,
            'presentaciones' => [
                ['nombre' => 'Unidad', 'factor_conversion' => 1, 'margen' => 7, 'fuente_tasa' => 'usdt', 'activa' => true],
            ],
        ]]]);
        $archivo = UploadedFile::fake()->createWithContent('datos.json', $json);

        $this->from('/herramientas/datos')->post('/herramientas/importar', ['archivo' => $archivo]);

        $producto->refresh();
        $this->assertEquals(20, $producto->unidades_lote);
        $this->assertEquals(44.50, $producto->precio_lote);
    }

    public function test_exportar_precios_incluye_lote_y_estado()
    {
        $this->actingAs($this->admin);
        Producto::factory()->create([
            'nombre' => 'Coamo 900ml',
            'unidades_lote' => 20,
            'precio_lote' => 44.50,
            'estado' => 'no_disponible',
        ]);

        $response = $this->get('/herramientas/exportar?tipos[]=precios');
        $data = json_decode($response->getContent(), true);
        $precio = collect($data['precios'])->firstWhere('nombre', 'Coamo 900ml');

        $this->assertSame(20, $precio['unidades_lote']);
        $this->assertEquals(44.50, $precio['precio_lote']);
        $this->assertSame('no_disponible', $precio['estado']);
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

    // ===== DIAGNÓSTICO export/import =====

    public function test_round_trip_precios_conserva_datos()
    {
        $this->actingAs($this->admin);
        $impuesto = Impuesto::factory()->create(['nombre' => 'IVA', 'porcentaje' => 16]);
        $producto = Producto::factory()->create([
            'nombre' => 'Harina',
            'costo_usd' => 2.00,
            'unidades_lote' => 12,
            'precio_lote' => 24.00,
            'unidad_medida' => 'unidad',
            'impuesto_id' => $impuesto->id,
            'estado' => 'disponible',
        ]);
        ProductoPresentacion::factory()->create([
            'producto_id' => $producto->id,
            'nombre' => 'Unidad',
            'factor_conversion' => 1,
            'margen' => 20,
            'precio_usd' => 2.40,
            'fuente_tasa' => 'promedio',
            'activa' => true,
        ]);

        $resp = $this->get('/herramientas/exportar?tipos[]=precios');
        $export = json_decode($resp->getContent(), true);

        $producto->forceDelete();

        $archivo = UploadedFile::fake()->createWithContent('p.json', json_encode(['precios' => $export['precios']]));
        $this->from('/herramientas/datos')->post('/herramientas/importar', [
            'archivo' => $archivo,
            'tipos' => ['precios'],
        ])->assertSessionHas('success');

        $nuevo = Producto::where('nombre', 'Harina')->first();
        $this->assertNotNull($nuevo);
        $this->assertEquals(2.00, (float) $nuevo->costo_usd);
        $this->assertEquals(12, $nuevo->unidades_lote);
        $this->assertEquals(24.00, (float) $nuevo->precio_lote);
        $this->assertEquals('unidad', $nuevo->unidad_medida);
        $this->assertEquals($impuesto->id, $nuevo->impuesto_id);
        $this->assertCount(1, $nuevo->presentaciones);
        $this->assertEquals('Unidad', $nuevo->presentaciones->first()->nombre);
        $this->assertEquals(20, $nuevo->presentaciones->first()->margen);
    }

    public function test_importar_precios_dos_veces_no_duplica()
    {
        $this->actingAs($this->admin);
        $json = json_encode(['precios' => [[
            'nombre' => 'Harina',
            'costo_usd' => 1.00,
            'unidad_medida' => 'unidad',
            'estado' => 'disponible',
            'presentaciones' => [['nombre' => 'Unidad', 'factor_conversion' => 1, 'margen' => 10, 'fuente_tasa' => 'promedio', 'activa' => true]],
        ]]]);
        $archivo = fn () => UploadedFile::fake()->createWithContent('p.json', $json);

        $this->from('/herramientas/datos')->post('/herramientas/importar', ['archivo' => $archivo(), 'tipos' => ['precios']]);
        $this->from('/herramientas/datos')->post('/herramientas/importar', ['archivo' => $archivo(), 'tipos' => ['precios']]);

        $this->assertEquals(1, Producto::where('nombre', 'Harina')->count());
    }

    public function test_importar_tasas_dos_veces_no_duplica_historial()
    {
        $this->actingAs($this->admin);
        $json = json_encode(['tasas_cambio' => [
            ['tipo' => 'bcv', 'monto' => 45, 'fecha' => '2026-01-01', 'activo' => true],
        ]]);
        $archivo = fn () => UploadedFile::fake()->createWithContent('t.json', $json);

        $this->from('/herramientas/datos')->post('/herramientas/importar', ['archivo' => $archivo(), 'tipos' => ['tasas_cambio']]);
        $this->from('/herramientas/datos')->post('/herramientas/importar', ['archivo' => $archivo(), 'tipos' => ['tasas_cambio']]);

        $this->assertEquals(1, TasaCambio::where('tipo', 'bcv')->count());
    }

    public function test_importar_formato_antiguo_dos_veces_no_duplica()
    {
        $this->actingAs($this->admin);
        $json = json_encode(['productos' => [[
            'nombre' => 'Harina',
            'costo_usd' => 1.00,
            'unidad_medida' => 'unidad',
            'precio_unitario_usd' => 1.10,
            'fuente_tasa' => 'promedio',
        ]]]);
        $archivo = fn () => UploadedFile::fake()->createWithContent('p.json', $json);

        $this->from('/herramientas/datos')->post('/herramientas/importar', ['archivo' => $archivo(), 'tipos' => ['productos']]);
        $this->from('/herramientas/datos')->post('/herramientas/importar', ['archivo' => $archivo(), 'tipos' => ['productos']]);

        $this->assertEquals(1, Producto::where('nombre', 'Harina')->count());
    }

    public function test_importar_archivo_con_bom()
    {
        $this->actingAs($this->admin);
        $json = json_encode(['precios' => [[
            'nombre' => 'Harina',
            'costo_usd' => 1.00,
            'unidad_medida' => 'unidad',
            'estado' => 'disponible',
            'presentaciones' => [['nombre' => 'Unidad', 'factor_conversion' => 1, 'margen' => 10, 'fuente_tasa' => 'promedio', 'activa' => true]],
        ]]]);
        // BOM UTF-8 al inicio (como lo guarda Notepad en Windows).
        $archivo = UploadedFile::fake()->createWithContent('p.json', "\xEF\xBB\xBF".$json);

        $this->from('/herramientas/datos')->post('/herramientas/importar', [
            'archivo' => $archivo,
            'tipos' => ['precios'],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('productos', ['nombre' => 'Harina']);
    }

    public function test_importar_json_invalido_devuelve_error()
    {
        $this->actingAs($this->admin);
        $archivo = UploadedFile::fake()->createWithContent('p.json', '{esto no es json');

        $this->from('/herramientas/datos')->post('/herramientas/importar', [
            'archivo' => $archivo,
            'tipos' => ['precios'],
        ])->assertSessionHasErrors('archivo');
    }

    public function test_exportar_no_incluye_productos_desactivados()
    {
        $this->actingAs($this->admin);
        $activo = Producto::factory()->create(['nombre' => 'Activo']);
        $desactivado = Producto::factory()->create(['nombre' => 'Inactivo']);
        $desactivado->delete();

        $resp = $this->get('/herramientas/exportar?tipos[]=precios');
        $nombres = collect(json_decode($resp->getContent(), true)['precios'])->pluck('nombre');

        $this->assertContains('Activo', $nombres);
        $this->assertNotContains('Inactivo', $nombres);
    }

    public function test_importar_inventario_resuelve_categoria_por_nombre()
    {
        $this->actingAs($this->admin);
        $cat = Categoria::factory()->create(['nombre' => 'Víveres']);
        Producto::factory()->create(['nombre' => 'Harina', 'categoria_id' => $cat->id, 'unidad_medida' => 'unidad']);

        $resp = $this->get('/herramientas/exportar?tipos[]=inventario');
        $inv = json_decode($resp->getContent(), true)['inventario'];

        // El export incluye el nombre de la categoría.
        $this->assertEquals('Víveres', $inv[0]['categoria_nombre']);

        // Destino con la misma categoría pero con otro id.
        Producto::where('nombre', 'Harina')->forceDelete();
        $cat->forceDelete();
        $catDestino = Categoria::factory()->create(['nombre' => 'Víveres']);

        $archivo = UploadedFile::fake()->createWithContent('d.json', json_encode(['inventario' => $inv]));
        $this->from('/herramientas/datos')->post('/herramientas/importar', [
            'archivo' => $archivo,
            'tipos' => ['inventario'],
        ])->assertSessionHas('success');

        $nuevo = Producto::where('nombre', 'Harina')->first();
        $this->assertEquals($catDestino->id, $nuevo->categoria_id);
    }
}
