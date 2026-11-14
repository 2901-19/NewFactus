<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Factura;
use App\Models\Impuesto;
use App\Models\ItemFactura;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\Rol;
use App\Models\TasaCambio;
use App\Models\User;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;

    private Cliente $cliente;

    private Producto $producto;

    private ProductoPresentacion $presentacionUnidad;

    private ProductoPresentacion $presentacionMayor;

    private TasaCambio $tasa;

    private Impuesto $iva;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisoSeeder::class);
        Configuracion::updateOrCreate(['clave' => 'imprimir_al_facturar'], ['valor' => '0']);
        Configuracion::olvidar('imprimir_al_facturar');
        $rolCajero = Rol::create(['nombre' => 'Cajero', 'slug' => 'cajero']);
        $rolCajero->permisos()->sync(
            Permiso::whereIn('slug', [
                'ver-dashboard',
                'usar-pos',
                'gestionar-clientes',
                'ver-facturas',
                'crear-facturas',
                'anular-facturas',
                'gestionar-creditos',
                'actualizar-inventarios',
            ])->pluck('id')
        );
        $this->cajero = User::factory()->create(['rol' => 'cajero']);
        $this->cliente = Cliente::factory()->create();
        $categoria = Categoria::factory()->create();
        $this->iva = Impuesto::factory()->create([
            'porcentaje' => 16.00,
        ]);
        $this->producto = Producto::factory()->create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Producto Test',
            'costo_usd' => 10.00,
            'stock_actual' => 125,
            'unidad_medida' => 'unidad',
            'impuesto_id' => $this->iva->id,
            'estado' => 'disponible',
        ]);
        $this->presentacionUnidad = ProductoPresentacion::factory()->create([
            'producto_id' => $this->producto->id,
            'nombre' => 'Unidad',
            'factor_conversion' => 1,
            'margen' => 0,
            'precio_usd' => 10.00,
            'fuente_tasa' => 'promedio',
            'activa' => true,
        ]);
        $this->presentacionMayor = ProductoPresentacion::factory()->create([
            'producto_id' => $this->producto->id,
            'nombre' => 'Mayor',
            'factor_conversion' => 12,
            'margen' => 0,
            'precio_usd' => 96.00,
            'fuente_tasa' => 'promedio',
            'activa' => true,
        ]);
        $this->tasa = TasaCambio::factory()->create([
            'tipo' => 'promedio',
            'monto' => 50.00,
            'fecha' => '2026-07-04',
        ]);
        TasaCambio::factory()->create([
            'tipo' => 'bcv',
            'monto' => 45.00,
            'fecha' => '2026-07-04',
        ]);
    }

    private function crearItemData(array $datos = []): array
    {
        return array_merge([
            'producto_id' => $this->producto->id,
            'presentacion_id' => $this->presentacionUnidad->id,
            'cantidad' => 1,
        ], $datos);
    }

    public function test_index_muestra_facturas()
    {
        Factura::factory()->count(3)->create();
        $this->actingAs($this->cajero);

        $response = $this->get('/facturas');

        $response->assertStatus(200);
        $response->assertViewIs('facturas.index');
    }

    public function test_data_pagina_por_servidor_sin_limite_de_20()
    {
        Factura::factory()->count(25)->create();
        $this->actingAs($this->cajero);

        $primera = $this->getJson('/facturas/data?draw=1&start=0&length=10&order%5B0%5D%5Bcolumn%5D=5&order%5B0%5D%5Bdir%5D=desc');

        $primera->assertOk()
            ->assertJsonPath('recordsTotal', 25)
            ->assertJsonPath('recordsFiltered', 25)
            ->assertJsonCount(10, 'data');

        $ultima = $this->getJson('/facturas/data?draw=2&start=20&length=10');
        $ultima->assertOk()->assertJsonCount(5, 'data');

        // Orden por cliente (subconsulta) y por usuario no deben romper la consulta.
        $this->getJson('/facturas/data?draw=3&start=0&length=10&order%5B0%5D%5Bcolumn%5D=1&order%5B0%5D%5Bdir%5D=asc')->assertOk();
        $this->getJson('/facturas/data?draw=4&start=0&length=10&order%5B0%5D%5Bcolumn%5D=2&order%5B0%5D%5Bdir%5D=desc')->assertOk();
    }

    public function test_data_busca_por_correlativo_cliente_y_estado()
    {
        Factura::factory()->create(['correlativo' => 'FAC-BUSCA-01', 'estado' => 'anulada']);
        $cliente = Cliente::factory()->create(['nombre' => 'Distribuidora Zurita']);
        Factura::factory()->create(['cliente_id' => $cliente->id]);
        Factura::factory()->count(3)->create();
        $this->actingAs($this->cajero);

        $porCorrelativo = $this->getJson('/facturas/data?draw=1&start=0&length=10&search%5Bvalue%5D=fac-busca');
        $porCorrelativo->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonCount(1, 'data');

        $porCliente = $this->getJson('/facturas/data?draw=2&start=0&length=10&search%5Bvalue%5D=zurita');
        $porCliente->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonCount(1, 'data');

        $porEstado = $this->getJson('/facturas/data?draw=3&start=0&length=10&search%5Bvalue%5D=anulada');
        $porEstado->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonCount(1, 'data');
    }

    public function test_data_escapa_el_html_de_las_columnas_de_texto()
    {
        Factura::factory()->create(['correlativo' => '<script>x</script>']);
        $this->actingAs($this->cajero);

        $response = $this->getJson('/facturas/data?draw=1&start=0&length=10');

        $response->assertOk();
        $this->assertSame('&lt;script&gt;x&lt;/script&gt;', $response->json('data.0.correlativo'));
    }

    public function test_pos_muestra_formulario()
    {
        $this->actingAs($this->cajero);

        $response = $this->get('/pos');

        $response->assertStatus(200);
        $response->assertViewHas(['productos', 'clientes']);
    }

    public function test_pos_catalogo_json_reducido()
    {
        $this->actingAs($this->cajero);

        $response = $this->get('/pos');

        $response->assertStatus(200);
        $response->assertViewHas('productos');

        $productos = $response->viewData('productos');
        $this->assertIsIterable($productos);

        $producto = collect($productos)->first(fn ($p) => $p['id'] === $this->producto->id);
        $this->assertNotNull($producto);
        $this->assertEqualsCanonicalizing(
            ['id', 'nombre', 'controla_inventario', 'stock_actual', 'unidad_medida', 'impuesto', 'presentaciones'],
            array_keys($producto)
        );
    }

    public function test_pos_enpoint_lista_presentaciones()
    {
        $this->actingAs($this->cajero);

        $response = $this->getJson('/pos/productos?start=0&length=15&draw=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'draw', 'recordsTotal', 'recordsFiltered', 'data',
        ]);

        $data = $response->json('data');
        $ids = array_column($data, 'presentacion_id');
        $this->assertContains($this->presentacionUnidad->id, $ids);
        $this->assertContains($this->presentacionMayor->id, $ids);
    }

    public function test_pos_busca_por_combinacion_de_palabras_en_cualquier_orden()
    {
        $productoExtra = Producto::factory()->create([
            'nombre' => 'Harina PAN 1kg',
            'costo_usd' => 2.00,
            'stock_actual' => 10,
            'unidad_medida' => 'unidad',
            'estado' => 'disponible',
        ]);
        ProductoPresentacion::factory()->create([
            'producto_id' => $productoExtra->id,
            'nombre' => 'Unidad',
            'factor_conversion' => 1,
            'margen' => 0,
            'precio_usd' => 2.50,
            'fuente_tasa' => 'promedio',
            'activa' => true,
        ]);
        $this->actingAs($this->cajero);

        $response = $this->getJson('/pos/productos?start=0&length=15&draw=1&search[value]=pan%20harina');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Harina PAN 1kg', $data[0]['nombre']);
    }

    public function test_pos_marca_producto_sin_tasa_configurada()
    {
        $productoSinTasa = Producto::factory()->create([
            'nombre' => 'Producto Sin Tasa',
            'costo_usd' => 5.00,
            'stock_actual' => 10,
            'unidad_medida' => 'unidad',
            'estado' => 'disponible',
        ]);
        ProductoPresentacion::factory()->create([
            'producto_id' => $productoSinTasa->id,
            'nombre' => 'Unidad',
            'factor_conversion' => 1,
            'margen' => 0,
            'precio_usd' => 5.00,
            'fuente_tasa' => 'paralelo',
            'activa' => true,
        ]);
        $this->actingAs($this->cajero);

        $response = $this->get('/pos');
        $response->assertStatus(200);
        $response->assertSee('Producto Sin Tasa');
        $response->assertSee('"fuente_tasa":"paralelo"', false);

        $dataResponse = $this->getJson('/pos/productos?start=0&length=50&draw=1');
        $dataResponse->assertStatus(200);
        $data = $dataResponse->json('data');
        $sinTasa = collect($data)->first(fn ($f) => $f['nombre'] === 'Producto Sin Tasa');
        $this->assertNotNull($sinTasa);
        $this->assertFalse($sinTasa['fila_ok']);
    }

    public function test_store_crea_factura_contado()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [
                $this->crearItemData(['cantidad' => 3]),
            ],
        ]);

        $response->assertJson(['success' => true]);
        $response->assertStatus(200);

        $factura = Factura::first();
        $this->assertNotNull($factura);
        $this->assertEquals($this->cliente->id, $factura->cliente_id);
        $this->assertEquals('contado', $factura->estado);
        $this->assertNull($factura->estado_credito);
        $this->assertEquals($this->cajero->id, $factura->user_id);

        $this->assertDatabaseHas('items_factura', [
            'factura_id' => $factura->id,
            'producto_id' => $this->producto->id,
            'presentacion_id' => $this->presentacionUnidad->id,
            'cantidad' => 3,
        ]);
    }

    public function test_store_guarda_unidad_medida_del_item_pesable()
    {
        $this->actingAs($this->cajero);

        $productoPesable = Producto::factory()->create([
            'nombre' => 'Queso',
            'costo_usd' => 4.00,
            'stock_actual' => 0,
            'unidad_medida' => 'kg',
            'estado' => 'disponible',
        ]);
        $presentacionKg = ProductoPresentacion::factory()->create([
            'producto_id' => $productoPesable->id,
            'nombre' => 'Kilogramo',
            'factor_conversion' => 1,
            'margen' => 30,
            'precio_usd' => 5.20,
            'activa' => true,
        ]);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [
                [
                    'producto_id' => $productoPesable->id,
                    'presentacion_id' => $presentacionKg->id,
                    'cantidad' => 0.8,
                ],
            ],
        ]);

        $response->assertJson(['success' => true]);
        $factura = Factura::first();
        $this->assertDatabaseHas('items_factura', [
            'factura_id' => $factura->id,
            'producto_id' => $productoPesable->id,
            'cantidad' => 0.8,
            'unidad_medida' => 'kg',
        ]);
    }

    public function test_store_usa_precio_de_presentacion_seleccionada()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [
                $this->crearItemData([
                    'presentacion_id' => $this->presentacionMayor->id,
                    'cantidad' => 1,
                ]),
            ],
        ]);

        $response->assertJson(['success' => true]);

        $factura = Factura::first();
        $item = $factura->items->first();
        $this->assertEquals($this->presentacionMayor->id, $item->presentacion_id);
        $this->assertEquals('Mayor', $item->presentacion_nombre);
        $this->assertEquals(96.00, (float) $item->precio_unitario_usd);
    }

    public function test_store_usa_la_tasa_de_cada_presentacion()
    {
        TasaCambio::factory()->create(['tipo' => 'bcv', 'monto' => 40.00, 'fecha' => '2026-07-04']);
        Configuracion::updateOrCreate(['clave' => 'tasa_referencia'], ['valor' => 'promedio']);
        $this->presentacionUnidad->update(['fuente_tasa' => 'bcv']);
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [$this->crearItemData()],
        ]);

        $response->assertJson(['success' => true]);

        // El precio en Bs usa la tasa de la presentación (bcv = 40), pero el
        // total USD sigue calculándose con la tasa de referencia (promedio = 50).
        $factura = Factura::first();
        $item = $factura->items->first();
        $this->assertEquals(400.0, (float) $factura->subtotal_bs);
        $this->assertEquals(10.00, (float) $item->precio_unitario_usd);
        $this->assertEquals(9.28, (float) $factura->total_usd);
    }

    public function test_store_calcula_total_usd_y_tasa_a_tasa_bcv()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [
                $this->crearItemData(),
            ],
        ]);

        $response->assertJson(['success' => true]);

        $factura = Factura::first();
        $this->assertEquals(500.0, (float) $factura->subtotal_bs);
        $this->assertEquals(80.0, (float) $factura->iva_bs);
        $this->assertEquals(580.0, (float) $factura->total_bs);
        $this->assertEquals(12.89, (float) $factura->total_usd);
        $this->assertEquals(45.0, (float) $factura->tasa_cambio);
    }

    public function test_store_usa_tasa_de_referencia_configurada()
    {
        TasaCambio::factory()->create(['tipo' => 'dolar', 'monto' => 60.00, 'fecha' => '2026-07-04']);
        Configuracion::updateOrCreate(['clave' => 'tasa_referencia'], ['valor' => 'dolar']);
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [
                $this->crearItemData(),
            ],
        ]);

        $response->assertJson(['success' => true]);

        $factura = Factura::first();
        $this->assertEquals(580.0, (float) $factura->total_bs);
        $this->assertEquals(9.67, (float) $factura->total_usd);
        $this->assertEquals(60.0, (float) $factura->tasa_cambio);
    }

    public function test_store_pago_mixto_guarda_detalle_pago()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'mixto',
            'pagos' => [
                ['metodo' => 'efectivo', 'monto' => 400.00],
                ['metodo' => 'punto', 'monto' => 180.00],
            ],
            'estado' => 'contado',
            'items' => [
                $this->crearItemData(),
            ],
        ]);

        $response->assertJson(['success' => true]);
        $response->assertStatus(200);

        $factura = Factura::first();
        $this->assertSame('mixto', $factura->metodo_pago);
        $this->assertEquals([
            ['metodo' => 'efectivo', 'monto' => 400.0],
            ['metodo' => 'punto', 'monto' => 180.0],
        ], $factura->detalle_pago);
        $this->assertEquals(580.0, (float) $factura->total_bs);
    }

    public function test_store_pago_mixto_suma_incorrecta_es_rechazado()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'mixto',
            'pagos' => [
                ['metodo' => 'efectivo', 'monto' => 100.00],
                ['metodo' => 'punto', 'monto' => 100.00],
            ],
            'estado' => 'contado',
            'items' => [
                $this->crearItemData(),
            ],
        ]);

        $response->assertJson(['success' => false]);
        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_store_pago_mixto_sin_pagos_falla_validacion()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'mixto',
            'estado' => 'contado',
            'items' => [
                $this->crearItemData(),
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pagos']);
    }

    public function test_store_descuenta_stock_actual()
    {
        $this->actingAs($this->cajero);

        $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [
                $this->crearItemData(['cantidad' => 14]),
            ],
        ]);

        $this->producto->refresh();
        $this->assertEquals(111, (float) $this->producto->stock_actual);
    }

    public function test_store_descuenta_stock_segun_factor_de_presentacion()
    {
        $this->actingAs($this->cajero);

        $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [
                $this->crearItemData([
                    'presentacion_id' => $this->presentacionMayor->id,
                    'cantidad' => 2,
                ]),
            ],
        ]);

        $this->producto->refresh();
        $this->assertEquals(101, (float) $this->producto->stock_actual);
    }

    public function test_store_crea_factura_credito()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'credito',
            'estado' => 'credito',
            'items' => [
                $this->crearItemData(['cantidad' => 2]),
            ],
        ]);

        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('facturas', [
            'cliente_id' => $this->cliente->id,
            'estado' => 'credito',
            'estado_credito' => 'pendiente',
        ]);
    }

    public function test_anular_factura_restaura_stock()
    {
        $this->actingAs($this->cajero);

        $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [
                $this->crearItemData(['cantidad' => 12]),
            ],
        ]);

        $factura = Factura::first();

        $response = $this->post("/facturas/{$factura->id}/anular");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('facturas', [
            'id' => $factura->id,
            'estado' => 'anulada',
        ]);

        $this->producto->refresh();
        $this->assertEquals(125, (float) $this->producto->stock_actual);
    }

    public function test_pagar_credito_marca_como_cancelado()
    {
        $this->actingAs($this->cajero);

        $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'credito',
            'estado' => 'credito',
            'items' => [
                $this->crearItemData(['cantidad' => 5]),
            ],
        ]);

        $factura = Factura::first();

        $response = $this->post("/facturas/{$factura->id}/pagar-credito", ['metodo_pago' => 'efectivo']);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('facturas', [
            'id' => $factura->id,
            'estado_credito' => 'cancelado',
            'metodo_pago' => 'efectivo',
            'pago_bs' => round($factura->total_usd * 45.00, 2),
        ]);
        $factura->refresh();
        $this->assertNotNull($factura->fecha_pago);
    }

    public function test_pagar_credito_sin_tasa_referencia_redirige_con_error()
    {
        TasaCambio::query()->delete();
        $factura = Factura::factory()->create([
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'credito',
            'estado' => 'credito',
            'estado_credito' => 'pendiente',
        ]);
        $this->actingAs($this->cajero);

        $response = $this->from(route('facturas.creditos'))
            ->post("/facturas/{$factura->id}/pagar-credito", ['metodo_pago' => 'efectivo']);

        $response->assertRedirect(route('facturas.creditos'));
        $response->assertSessionHasErrors('error');

        $factura->refresh();
        $this->assertEquals('pendiente', $factura->estado_credito);
        $this->assertNull($factura->fecha_pago);
    }

    public function test_pagar_credito_usa_tasa_referencia_vigente_del_cobro()
    {
        $this->actingAs($this->cajero);

        $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'credito',
            'estado' => 'credito',
            'items' => [
                $this->crearItemData(['cantidad' => 5]),
            ],
        ]);

        $factura = Factura::first();
        $usdAlVender = round($factura->total_usd, 2);

        TasaCambio::factory()->create([
            'tipo' => 'bcv',
            'monto' => 60.00,
            'fecha' => '2026-08-07',
        ]);

        $response = $this->post("/facturas/{$factura->id}/pagar-credito", ['metodo_pago' => 'punto']);

        $response->assertRedirect();
        $this->assertDatabaseHas('facturas', [
            'id' => $factura->id,
            'estado_credito' => 'cancelado',
            'metodo_pago' => 'punto',
            'pago_bs' => round($usdAlVender * 60.00, 2),
        ]);
    }

    public function test_pagar_credito_requiere_metodo_pago_simple()
    {
        $this->actingAs($this->cajero);

        $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'credito',
            'estado' => 'credito',
            'items' => [
                $this->crearItemData(['cantidad' => 3]),
            ],
        ]);

        $factura = Factura::first();

        $response = $this->post("/facturas/{$factura->id}/pagar-credito");
        $response->assertSessionHasErrors('metodo_pago');

        $response = $this->post("/facturas/{$factura->id}/pagar-credito", ['metodo_pago' => 'mixto']);
        $response->assertSessionHasErrors('metodo_pago');

        $factura->refresh();
        $this->assertEquals('pendiente', $factura->estado_credito);
        $this->assertNull($factura->fecha_pago);
    }

    public function test_validacion_requiere_items()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items']);
    }

    public function test_validacion_stock_insuficiente()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [
                $this->crearItemData(['cantidad' => 9999]),
            ],
        ]);

        $response->assertJson(['success' => false]);
    }

    public function test_credito_requiere_cliente()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'metodo_pago' => 'credito',
            'estado' => 'credito',
            'items' => [
                $this->crearItemData(['cantidad' => 2]),
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['cliente_id']);
    }

    public function test_show_muestra_factura()
    {
        $factura = Factura::factory()->create();
        $this->actingAs($this->cajero);

        $response = $this->get("/facturas/{$factura->id}");

        $response->assertStatus(200);
        $response->assertViewHas('factura');
    }

    public function test_creditos_muestra_pendientes()
    {
        Factura::factory()->count(2)->create(['estado' => 'credito', 'estado_credito' => 'pendiente']);
        $this->actingAs($this->cajero);

        $response = $this->get('/creditos');

        $response->assertStatus(200);
        $response->assertViewHas('facturas');
    }

    public function test_creditos_muestra_todos_sin_limite_de_20()
    {
        Factura::factory()->count(25)->create(['estado' => 'credito', 'estado_credito' => 'pendiente']);
        $this->actingAs($this->cajero);

        $response = $this->get('/creditos');

        $response->assertStatus(200);
        $this->assertCount(25, $response->viewData('facturas'));
    }

    public function test_store_rechaza_venta_sin_tasa_configurada()
    {
        TasaCambio::query()->delete();
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [
                $this->crearItemData(),
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertDatabaseCount('facturas', 0);

        $this->producto->refresh();
        $this->assertEquals(125, (float) $this->producto->stock_actual);
    }

    public function test_store_rechaza_producto_sin_precio()
    {
        $this->presentacionUnidad->update(['precio_usd' => 0]);
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [
                $this->crearItemData(),
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_store_rechaza_presentacion_no_activa()
    {
        $this->presentacionUnidad->update(['activa' => false]);
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [
                $this->crearItemData(),
            ],
        ]);

        $response->assertJson(['success' => false]);
        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_anular_credito_cobrado_es_bloqueado()
    {
        $this->actingAs($this->cajero);

        $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'credito',
            'estado' => 'credito',
            'items' => [
                $this->crearItemData(['cantidad' => 2]),
            ],
        ]);

        $factura = Factura::first();
        $this->post("/facturas/{$factura->id}/pagar-credito", ['metodo_pago' => 'efectivo']);
        $factura->refresh();
        $this->assertEquals('cancelado', $factura->estado_credito);

        $response = $this->post("/facturas/{$factura->id}/anular");

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');

        $this->assertDatabaseHas('facturas', [
            'id' => $factura->id,
            'estado' => 'credito',
            'estado_credito' => 'cancelado',
        ]);
    }

    public function test_anular_restaura_stock_de_producto_desactivado()
    {
        $this->actingAs($this->cajero);

        $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [
                $this->crearItemData(['cantidad' => 10]),
            ],
        ]);

        $factura = Factura::first();
        $this->producto->delete();

        $response = $this->post("/facturas/{$factura->id}/anular");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->producto->refresh();
        $this->assertEquals(125, (float) $this->producto->stock_actual);
    }

    public function test_store_redondea_subtotal_iva_y_total()
    {
        $this->presentacionUnidad->update(['precio_usd' => 10.3333]);
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [
                $this->crearItemData(['cantidad' => 3]),
            ],
        ]);

        $response->assertJson(['success' => true]);

        $factura = Factura::first();
        $this->assertEquals(1550.00, (float) $factura->subtotal_bs);
        $this->assertEquals(248.00, (float) $factura->iva_bs);
        $this->assertEquals(1798.00, (float) $factura->total_bs);
    }

    public function test_pos_busca_ignorando_acentos()
    {
        $productoExtra = Producto::factory()->create([
            'nombre' => 'Azúcar Morena',
            'costo_usd' => 1.00,
            'stock_actual' => 10,
            'unidad_medida' => 'unidad',
            'estado' => 'disponible',
        ]);
        ProductoPresentacion::factory()->create([
            'producto_id' => $productoExtra->id,
            'nombre' => 'Unidad',
            'factor_conversion' => 1,
            'margen' => 0,
            'precio_usd' => 1.50,
            'fuente_tasa' => 'promedio',
            'activa' => true,
        ]);
        $this->actingAs($this->cajero);

        $response = $this->getJson('/pos/productos?start=0&length=15&draw=1&search[value]=azucar');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Azúcar Morena', $data[0]['nombre']);
    }

    public function test_store_pago_movil_requiere_referencia_y_telefono()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'pago_movil',
            'estado' => 'contado',
            'items' => [$this->crearItemData()],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['datos_pago.referencia', 'datos_pago.telefono']);
        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_store_transferencia_requiere_referencia()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'transferencia',
            'estado' => 'contado',
            'items' => [$this->crearItemData()],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['datos_pago.referencia']);
    }

    public function test_store_pago_movil_guarda_datos_pago()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'pago_movil',
            'estado' => 'contado',
            'datos_pago' => ['referencia' => '123456', 'telefono' => '04141234567'],
            'items' => [$this->crearItemData()],
        ]);

        $response->assertJson(['success' => true]);

        $factura = Factura::first();
        $this->assertEquals(
            ['referencia' => '123456', 'telefono' => '04141234567'],
            $factura->datos_pago
        );
    }

    public function test_store_transferencia_guarda_solo_referencia()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'transferencia',
            'estado' => 'contado',
            'datos_pago' => ['referencia' => '987654', 'telefono' => '04141234567'],
            'items' => [$this->crearItemData()],
        ]);

        $response->assertJson(['success' => true]);

        $factura = Factura::first();
        $this->assertEquals(['referencia' => '987654'], $factura->datos_pago);
    }

    public function test_pagar_credito_pago_movil_guarda_datos_pago()
    {
        $this->actingAs($this->cajero);

        $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'credito',
            'estado' => 'credito',
            'items' => [$this->crearItemData(['cantidad' => 2])],
        ]);

        $factura = Factura::first();

        $response = $this->post("/facturas/{$factura->id}/pagar-credito", [
            'metodo_pago' => 'pago_movil',
            'datos_pago' => ['referencia' => '555111', 'telefono' => '04241112233'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $factura->refresh();
        $this->assertEquals('cancelado', $factura->estado_credito);
        $this->assertEquals(
            ['referencia' => '555111', 'telefono' => '04241112233'],
            $factura->datos_pago
        );
    }

    public function test_pagar_credito_transferencia_requiere_referencia()
    {
        $factura = Factura::factory()->create([
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'credito',
            'estado' => 'credito',
            'estado_credito' => 'pendiente',
        ]);
        $this->actingAs($this->cajero);

        $response = $this->post("/facturas/{$factura->id}/pagar-credito", ['metodo_pago' => 'transferencia']);

        $response->assertSessionHasErrors('datos_pago.referencia');

        $factura->refresh();
        $this->assertEquals('pendiente', $factura->estado_credito);
    }

    public function test_recibo_muestra_rif_y_cantidad_pesable_en_kg()
    {
        Configuracion::updateOrCreate(['clave' => 'rif'], ['valor' => 'J-12345678-9']);
        Configuracion::olvidar('rif');
        $this->actingAs($this->cajero);

        $productoPesable = Producto::factory()->create([
            'nombre' => 'Queso',
            'costo_usd' => 4.00,
            'stock_actual' => 0,
            'unidad_medida' => 'kg',
            'estado' => 'disponible',
        ]);
        $presentacionKg = ProductoPresentacion::factory()->create([
            'producto_id' => $productoPesable->id,
            'nombre' => 'Kilogramo',
            'factor_conversion' => 1,
            'margen' => 30,
            'precio_usd' => 5.20,
            'activa' => true,
        ]);

        $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [[
                'producto_id' => $productoPesable->id,
                'presentacion_id' => $presentacionKg->id,
                'cantidad' => 0.8,
            ]],
        ])->assertJson(['success' => true]);

        $factura = Factura::first();

        $response = $this->get("/facturas/{$factura->id}");

        $response->assertStatus(200);
        $response->assertSee('RIF J-12345678-9');
        $response->assertSee('0,800 kg');
    }

    public function test_store_pago_mixto_requiere_referencia_y_telefono_en_pago_movil()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'mixto',
            'pagos' => [
                ['metodo' => 'pago_movil', 'monto' => 400.00],
                ['metodo' => 'efectivo', 'monto' => 180.00],
            ],
            'estado' => 'contado',
            'items' => [$this->crearItemData()],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pagos.0.referencia', 'pagos.0.telefono']);
        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_store_pago_mixto_requiere_referencia_en_transferencia()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'mixto',
            'pagos' => [
                ['metodo' => 'efectivo', 'monto' => 400.00],
                ['metodo' => 'transferencia', 'monto' => 180.00],
            ],
            'estado' => 'contado',
            'items' => [$this->crearItemData()],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pagos.1.referencia']);
        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_store_pago_mixto_guarda_referencia_y_telefono()
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'mixto',
            'pagos' => [
                ['metodo' => 'pago_movil', 'monto' => 400.00, 'referencia' => '123456', 'telefono' => '04141234567'],
                ['metodo' => 'efectivo', 'monto' => 180.00],
            ],
            'estado' => 'contado',
            'items' => [$this->crearItemData()],
        ]);

        $response->assertJson(['success' => true]);

        $factura = Factura::first();
        $this->assertEquals([
            ['metodo' => 'pago_movil', 'monto' => 400.0, 'referencia' => '123456', 'telefono' => '04141234567'],
            ['metodo' => 'efectivo', 'monto' => 180.0],
        ], $factura->detalle_pago);
    }

    public function test_recibo_pago_mixto_muestra_referencia()
    {
        $this->actingAs($this->cajero);

        $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'mixto',
            'pagos' => [
                ['metodo' => 'transferencia', 'monto' => 400.00, 'referencia' => '55443322'],
                ['metodo' => 'efectivo', 'monto' => 180.00],
            ],
            'estado' => 'contado',
            'items' => [$this->crearItemData()],
        ])->assertJson(['success' => true]);

        $factura = Factura::first();

        $response = $this->get("/facturas/{$factura->id}");

        $response->assertStatus(200);
        $response->assertSee('55443322');
    }

    public function test_store_permite_vender_sin_stock_si_esta_configurado()
    {
        Configuracion::updateOrCreate(['clave' => 'permitir_venta_sin_stock'], ['valor' => '1']);
        Configuracion::olvidar('permitir_venta_sin_stock');
        $this->producto->update(['stock_actual' => 0]);
        $this->actingAs($this->cajero);

        $response = $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [$this->crearItemData(['cantidad' => 5])],
        ]);

        $response->assertJson(['success' => true]);

        $this->producto->refresh();
        $this->assertEquals(0, (float) $this->producto->stock_actual);
    }

    public function test_anular_no_restaura_stock_si_se_vendio_sin_stock()
    {
        Configuracion::updateOrCreate(['clave' => 'permitir_venta_sin_stock'], ['valor' => '1']);
        Configuracion::olvidar('permitir_venta_sin_stock');
        $this->producto->update(['stock_actual' => 0]);
        $this->actingAs($this->cajero);

        $this->postJson('/facturas', [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [$this->crearItemData(['cantidad' => 5])],
        ])->assertJson(['success' => true]);

        $factura = Factura::first();

        $this->post("/facturas/{$factura->id}/anular")->assertRedirect();

        $this->producto->refresh();
        $this->assertEquals(0, (float) $this->producto->stock_actual);
    }

    public function test_doble_envio_con_misma_clave_idempotente_crea_una_sola_factura()
    {
        $this->actingAs($this->cajero);
        $key = '11111111-1111-4111-8111-111111111111';

        // Mismo payload y misma clave: replica el doble clic en "Facturar".
        $payload = [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'idempotencia_key' => $key,
            'items' => [$this->crearItemData(['cantidad' => 2])],
        ];

        $primero = $this->postJson('/facturas', $payload);
        $segundo = $this->postJson('/facturas', $payload);

        $primero->assertJson(['success' => true]);
        $segundo->assertJson(['success' => true]);

        // Ambos responden con el mismo correlativo y la misma factura.
        $this->assertEquals($primero->json('correlativo'), $segundo->json('correlativo'));
        $this->assertEquals($primero->json('factura_id'), $segundo->json('factura_id'));

        // Solo se creó UNA factura y sus items una sola vez.
        $this->assertEquals(1, Factura::count());
        $this->assertEquals(1, ItemFactura::count());

        // El stock se descontó una sola vez (125 - 2 = 123).
        $this->producto->refresh();
        $this->assertEquals(123, (float) $this->producto->stock_actual);
    }

    public function test_doble_envio_sin_clave_crea_dos_facturas()
    {
        $this->actingAs($this->cajero);

        // Sin clave idempotente, cada POST es una venta nueva (comportamiento previo).
        $payload = [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'items' => [$this->crearItemData(['cantidad' => 2])],
        ];

        $this->postJson('/facturas', $payload)->assertJson(['success' => true]);
        $this->postJson('/facturas', $payload)->assertJson(['success' => true]);

        $this->assertEquals(2, Factura::count());
    }

    public function test_reintento_con_misma_clave_no_duplica_ni_vuelve_a_descontar_stock()
    {
        $this->actingAs($this->cajero);
        $key = '22222222-2222-4222-8222-222222222222';

        $payload = [
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'estado' => 'contado',
            'idempotencia_key' => $key,
            'items' => [$this->crearItemData(['cantidad' => 1])],
        ];

        $primero = $this->postJson('/facturas', $payload);
        $facturaId = $primero->json('factura_id');

        // Reintento (p. ej. tras un timeout de red): misma clave.
        $reintento = $this->postJson('/facturas', $payload);

        $reintento->assertJson(['success' => true]);
        $this->assertEquals($facturaId, $reintento->json('factura_id'));
        $this->assertEquals(1, Factura::count());

        // El stock solo se descontó una vez (125 - 1 = 124).
        $this->producto->refresh();
        $this->assertEquals(124, (float) $this->producto->stock_actual);
    }
}
