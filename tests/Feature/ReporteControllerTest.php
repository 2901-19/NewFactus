<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Impuesto;
use App\Models\ItemFactura;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\TasaCambio;
use App\Models\User;
use App\Services\CatalogoService;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;

    private Cliente $cliente;

    private Producto $producto;

    private ProductoPresentacion $presentacionUnidad;

    private ProductoPresentacion $presentacionMayor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisoSeeder::class);
        $this->cajero = User::factory()->create(['rol' => 'admin']);
        $this->cliente = Cliente::factory()->create();
        $categoria = Categoria::factory()->create();
        $this->producto = Producto::factory()->create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Producto Test',
            'costo_usd' => 5.00,
            'stock_actual' => 125,
            'unidad_medida' => 'unidad',
            'impuesto_id' => null,
            'estado' => 'disponible',
        ]);
        $this->presentacionUnidad = ProductoPresentacion::factory()->create([
            'producto_id' => $this->producto->id,
            'nombre' => 'Unidad',
            'factor_conversion' => 1,
            'margen' => 0,
            'precio_usd' => 10.00,
            'activa' => true,
        ]);
        $this->presentacionMayor = ProductoPresentacion::factory()->create([
            'producto_id' => $this->producto->id,
            'nombre' => 'Mayor',
            'factor_conversion' => 12,
            'margen' => 0,
            'precio_usd' => 96.00,
            'activa' => true,
        ]);
        TasaCambio::factory()->create(['tipo' => 'promedio', 'monto' => 50.00, 'fecha' => '2026-07-04']);
        TasaCambio::factory()->create(['tipo' => 'bcv', 'monto' => 45.00, 'fecha' => '2026-07-04']);
        Impuesto::factory()->create(['porcentaje' => 16.00]);
    }

    private function crearFactura(array $datos = []): Factura
    {
        return Factura::factory()->create(array_merge([
            'user_id' => $this->cajero->id,
            'cliente_id' => $this->cliente->id,
            'metodo_pago' => 'efectivo',
            'subtotal_bs' => 500,
            'iva_bs' => 80,
            'total_bs' => 580,
            'total_usd' => 12.89,
            'fecha_venta' => '2026-07-10',
        ], $datos));
    }

    private function crearItem(Factura $factura, array $datos = []): ItemFactura
    {
        return ItemFactura::create(array_merge([
            'factura_id' => $factura->id,
            'producto_id' => $this->producto->id,
            'presentacion_id' => $this->presentacionUnidad->id,
            'presentacion_nombre' => 'Unidad',
            'factor_conversion' => 1,
            'cantidad' => 2,
            'precio_unitario_usd' => 10.00,
            'precio_unitario_bs' => 500.00,
            'subtotal' => 1000.00,
        ], $datos));
    }

    public function test_facturas_muestra_kpis_y_desglose()
    {
        $this->actingAs($this->cajero);

        $this->crearFactura();
        $this->crearFactura([
            'metodo_pago' => 'mixto',
            'detalle_pago' => [
                ['metodo' => 'efectivo', 'monto' => 400.00],
                ['metodo' => 'punto', 'monto' => 180.00],
            ],
        ]);

        $response = $this->get('/reportes/facturas?desde=2026-07-01&hasta=2026-07-31');

        $response->assertStatus(200);
        $response->assertViewHas(['facturas', 'kpis', 'desglose']);

        $kpis = $response->viewData('kpis');
        $this->assertEquals(1160.0, $kpis['total_bs']);
        $this->assertEquals(2, $kpis['cantidad']);
        $this->assertEquals(580.0, $kpis['ticket_promedio']);

        $desglose = $response->viewData('desglose');
        $this->assertEquals(980.0, $desglose['efectivo']);
        $this->assertEquals(180.0, $desglose['punto']);
    }

    public function test_facturas_filtra_por_metodo_y_excluye_anuladas()
    {
        $this->actingAs($this->cajero);

        $this->crearFactura();
        $this->crearFactura(['metodo_pago' => 'pago_movil']);
        $this->crearFactura(['estado' => 'anulada']);

        $response = $this->get('/reportes/facturas?desde=2026-07-01&hasta=2026-07-31&metodo_pago=pago_movil');

        $response->assertStatus(200);
        $facturas = $response->viewData('facturas');
        $kpis = $response->viewData('kpis');

        $this->assertCount(1, $facturas);
        $this->assertEquals(580.0, $kpis['total_bs']);
        $this->assertEquals(1, $kpis['cantidad']);
    }

    public function test_facturas_no_muestra_comparacion_con_periodo_anterior()
    {
        $this->actingAs($this->cajero);

        $this->crearFactura(['fecha_venta' => '2026-06-05', 'total_bs' => 500, 'subtotal_bs' => 450, 'iva_bs' => 50]);
        $this->crearFactura();

        $response = $this->get('/reportes/facturas?desde=2026-07-01&hasta=2026-07-31');

        $kpis = $response->viewData('kpis');
        $this->assertEquals(580.0, $kpis['total_bs']);
    }

    public function test_facturas_export_csv()
    {
        $this->actingAs($this->cajero);

        $factura = $this->crearFactura();

        $response = $this->get('/reportes/facturas?desde=2026-07-01&hasta=2026-07-31&export=csv');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Correlativo;Cliente;Metodo;Estado', $response->streamedContent());
        $this->assertStringContainsString($factura->correlativo, $response->streamedContent());
    }

    public function test_facturas_export_pdf()
    {
        $this->actingAs($this->cajero);

        $this->crearFactura();

        $response = $this->get('/reportes/facturas?desde=2026-07-01&hasta=2026-07-31&export=pdf');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_estadisticas_muestra_graficas_y_agrupaciones()
    {
        $this->actingAs($this->cajero);

        $factura = $this->crearFactura();
        $this->crearItem($factura);
        $this->crearItem($factura, [
            'presentacion_id' => $this->presentacionMayor->id,
            'presentacion_nombre' => 'Mayor',
            'factor_conversion' => 12,
            'precio_unitario_usd' => 96.00,
            'precio_unitario_bs' => 4800.00,
            'subtotal' => 800.00,
        ]);

        $response = $this->get('/reportes/estadisticas?desde=2026-07-01&hasta=2026-07-31');

        $response->assertStatus(200);
        $response->assertViewHas(['porDia', 'porMetodo', 'semanaLabels', 'topProductos', 'topClientes', 'creditos']);

        $porDia = $response->viewData('porDia');
        $this->assertEquals(580.0, $porDia['2026-07-10']);

        $porMetodo = $response->viewData('porMetodo');
        $this->assertEquals(580.0, $porMetodo['efectivo']);

        $topProductos = $response->viewData('topProductos');
        $this->assertCount(1, $topProductos);
        $this->assertEquals('Producto Test', $topProductos[0]->nombre);
        $this->assertEquals(4, $topProductos[0]->unidades);
        $this->assertEquals(1800.0, $topProductos[0]->ingreso_bs);

        $this->assertEquals('semana', $response->viewData('agrupadoPor'));
        $resumenes = $response->viewData('resumenes');
        $this->assertEquals(1000.0, $resumenes['Unidad']['bs']);
        $this->assertEquals(2, $resumenes['Unidad']['unidades']);
        $this->assertEquals(55.6, $resumenes['Unidad']['pct']);
        $this->assertEquals(1800.0, $resumenes['Unidad']['bs'] + $resumenes['Mayor']['bs']);

        $series = $response->viewData('seriesPresentaciones');
        $this->assertContains(1000.0, $series['Unidad']);
        $this->assertContains(800.0, $series['Mayor']);
        $this->assertEquals(1800.0, array_sum($series['Unidad']) + array_sum($series['Mayor']));

        $creditos = $response->viewData('creditos');
        $this->assertEquals(0, $creditos->cantidad);
    }

    public function test_estadisticas_agrupa_por_dia_en_rango_corto()
    {
        $this->actingAs($this->cajero);

        $factura = $this->crearFactura(['fecha_venta' => '2026-07-10']);
        $this->crearItem($factura);

        $response = $this->get('/reportes/estadisticas?desde=2026-07-10&hasta=2026-07-10');

        $response->assertStatus(200);
        $this->assertEquals('día', $response->viewData('agrupadoPor'));
        $this->assertCount(1, $response->viewData('semanaLabels'));
        $this->assertEquals(['Unidad' => 1000.0], ['Unidad' => $response->viewData('seriesPresentaciones')['Unidad'][0]]);
    }

    public function test_balance_agrupa_mensualmente()
    {
        $this->actingAs($this->cajero);

        $this->crearFactura(['fecha_venta' => '2026-01-15', 'total_bs' => 1000, 'subtotal_bs' => 900, 'iva_bs' => 100]);
        $this->crearFactura();

        $response = $this->get('/reportes/balance?anio=2026');

        $response->assertStatus(200);
        $mensual = $response->viewData('mensual');
        $serieBs = $response->viewData('serieBs');

        $this->assertEquals(1000.0, $mensual[1]['total_bs']);
        $this->assertEquals(1, $mensual[1]['cantidad']);
        $this->assertEquals(1, $mensual[7]['cantidad']);
        $this->assertEquals(1000.0, $serieBs[0]);
        $this->assertEquals(580.0, $serieBs[6]);
    }

    public function test_kpis_excluyen_credito_pendiente()
    {
        $this->actingAs($this->cajero);

        $this->crearFactura(['fecha_venta' => '2026-07-10']);
        Factura::factory()->credito()->create([
            'user_id' => $this->cajero->id,
            'cliente_id' => $this->cliente->id,
            'fecha_venta' => '2026-07-12',
            'total_bs' => 9000,
            'total_usd' => 200,
        ]);

        $response = $this->get('/reportes/facturas?desde=2026-07-01&hasta=2026-07-31');

        $kpis = $response->viewData('kpis');
        $this->assertEquals(1, $kpis['cantidad']);
        $this->assertEquals(580.0, $kpis['total_bs']);

        $desglose = $response->viewData('desglose');
        $this->assertEquals(580.0, $desglose['efectivo']);
    }

    public function test_credito_cobrado_cuenta_como_ingreso_en_fecha_del_cobro()
    {
        $this->actingAs($this->cajero);

        $this->crearFactura(['fecha_venta' => '2026-07-10']);
        Factura::factory()->credito()->create([
            'user_id' => $this->cajero->id,
            'cliente_id' => $this->cliente->id,
            'fecha_venta' => '2026-07-08',
            'fecha_pago' => '2026-07-12',
            'estado_credito' => 'cancelado',
            'metodo_pago' => 'punto',
            'total_bs' => 4000,
            'total_usd' => 80,
            'pago_bs' => 7000,
        ]);

        $response = $this->get('/reportes/facturas?desde=2026-07-01&hasta=2026-07-31');

        $kpis = $response->viewData('kpis');
        $this->assertEquals(2, $kpis['cantidad']);
        $this->assertEquals(7580.0, $kpis['total_bs']);
        $this->assertEquals(92.89, $kpis['total_usd']);

        $desglose = $response->viewData('desglose');
        $this->assertEquals(7000.0, $desglose['punto']);
        $this->assertEquals(580.0, $desglose['efectivo']);
    }

    public function test_balance_credito_cobrado_cuenta_en_mes_del_cobro()
    {
        $this->actingAs($this->cajero);

        Factura::factory()->credito()->create([
            'user_id' => $this->cajero->id,
            'cliente_id' => $this->cliente->id,
            'fecha_venta' => '2026-06-20',
            'fecha_pago' => '2026-07-05',
            'estado_credito' => 'cancelado',
            'metodo_pago' => 'efectivo',
            'total_bs' => 1000,
            'total_usd' => 20,
            'pago_bs' => 1500,
        ]);

        $response = $this->get('/reportes/balance?anio=2026');

        $mensual = $response->viewData('mensual');
        $this->assertEquals(0, $mensual[6]['total_bs'] ?? 0);
        $this->assertEquals(1500.0, $mensual[7]['total_bs']);
        $this->assertEquals(1, $mensual[7]['cantidad']);
    }

    public function test_balance_export_pdf()
    {
        $this->actingAs($this->cajero);

        $this->crearFactura();

        $response = $this->get('/reportes/balance?anio=2026&export=pdf');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_stock_usa_umbral_como_parametro_no_columna()
    {
        $this->actingAs($this->cajero);

        $sql = Producto::whereNull('deleted_at')
            ->where('estado', 'disponible')
            ->where('unidad_medida', 'unidad')
            ->where('stock_actual', '<=', CatalogoService::UMBRAL_STOCK_BAJO)
            ->toSql();

        $this->assertStringContainsString('stock_actual" <= ?', $sql);
        $this->assertStringNotContainsString('"'.CatalogoService::UMBRAL_STOCK_BAJO.'"', $sql);

        $response = $this->get('/reportes/stock');
        $response->assertStatus(200);
    }
}
