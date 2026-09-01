<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Impuesto;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MigrarInventarioViejoTest extends TestCase
{
    use RefreshDatabase;

    private string $directorioTemporal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directorioTemporal = sys_get_temp_dir().'/factus_test_migration_'.uniqid();
        File::makeDirectory($this->directorioTemporal);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directorioTemporal);

        parent::tearDown();
    }

    public function test_parsea_archivo_sql_y_crea_productos()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi Cola 2L', 18, 6, 'Disponible', '', 0, 8.01, 1.33, 13, 0.17, 0),
            $this->filaSql(2, 'Refresco', 'Cola Camp 2L', 12, 6, 'Disponible', '', 0, 6.00, 1.00, 13, 0.15, 0),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(2, Producto::count());
        $this->assertEquals(2, ProductoPresentacion::count());

        $pepsi = Producto::where('nombre', 'Pepsi Cola 2L')->first();
        $this->assertNotNull($pepsi);
        $this->assertEquals('disponible', $pepsi->estado);
        $this->assertEquals(1, $pepsi->presentaciones()->count());

        $presentacion = $pepsi->presentaciones()->first();
        $this->assertEquals('Unidad', $presentacion->nombre);
        $this->assertEquals(17.0, $presentacion->margen);
        $this->assertEquals('bcv', $presentacion->fuente_tasa);
        $this->assertEquals(1.33, $presentacion->precio_usd);
    }

    public function test_crea_categorias_desde_nombre()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 10, 6, 'Disponible', '', 0, 6.00, 1.00, 13, 0.20, 0),
            $this->filaSql(2, 'Aceite Soya', 'Mavesa 1L', 5, 12, 'Disponible', '', 0, 5.00, 0.50, 13, 0.25, 0),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(2, Categoria::count());
        $this->assertDatabaseHas('categorias', ['nombre' => 'Refresco']);
        $this->assertDatabaseHas('categorias', ['nombre' => 'Aceite Soya']);

        $producto = Producto::first();
        $this->assertNotNull($producto->categoria_id);
    }

    public function test_crea_impuesto_iva_cuando_hay_productos_con_iva()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Snacks', 'Choco LooK', 10, 12, 'Disponible', '', 1, 12.00, 1.00, 16, 0.20, 0),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertDatabaseHas('impuestos', ['nombre' => 'IVA', 'porcentaje' => 16.00]);

        $producto = Producto::where('nombre', 'Choco LooK')->first();
        $this->assertNotNull($producto->impuesto_id);
    }

    public function test_no_crea_impuesto_cuando_ninguno_tiene_iva()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 10, 6, 'Disponible', '', 0, 6.00, 1.00, 13, 0.20, 0),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(0, Impuesto::count());
        $this->assertNull(Producto::first()->impuesto_id);
    }

    public function test_fusiona_par_normal_y_mayor()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Harina', 'Doña Belen 1Kg', 12, 20, 'Disponible', '', 0, 15.40, 0.77, 8, 0.08, 0),
            $this->filaSql(2, 'Harina', 'Doña Belen 1Kg Mayor', 13, 20, 'Disponible', '', 0, 15.40, 0.77, 8, 0.04, 0),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(1, Producto::count());
        $producto = Producto::first();
        $this->assertEquals('Doña Belen 1Kg', $producto->nombre);
        $this->assertEquals(2, $producto->presentaciones()->count());

        $unidad = $producto->presentaciones()->where('nombre', 'Unidad')->first();
        $this->assertNotNull($unidad);
        $this->assertEquals(8.0, $unidad->margen);
        $this->assertEquals(1, $unidad->factor_conversion);

        $mayor = $producto->presentaciones()->where('nombre', 'Mayor')->first();
        $this->assertNotNull($mayor);
        $this->assertEquals(4.0, $mayor->margen);
        $this->assertEquals(20, $mayor->factor_conversion);
    }

    public function test_producto_solo_mayor_se_crea_como_una_presentacion()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Frescolita 1L Mayor', 6, 6, 'Disponible', '', 0, 3.66, 0.61, 13, 0.10, 0),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(1, Producto::count());
        $producto = Producto::first();
        $this->assertEquals('Frescolita 1L Mayor', $producto->nombre);
        $this->assertEquals(1, $producto->presentaciones()->count());

        $presentacion = $producto->presentaciones()->first();
        $this->assertEquals('Mayor', $presentacion->nombre);
    }

    public function test_mapea_daily_dollar_a_fuente_tasa()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Cat A', 'Prod Bcv', 10, 6, 'Disponible', '', 0, 6.00, 1.00, 13, 0.20, 0),
            $this->filaSql(2, 'Cat A', 'Prod Usdt', 10, 6, 'Disponible', '', 0, 6.00, 1.00, 13, 0.20, 1),
            $this->filaSql(3, 'Cat A', 'Prod Promedio', 10, 6, 'Disponible', '', 0, 6.00, 1.00, 13, 0.20, 2),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals('bcv', Producto::where('nombre', 'Prod Bcv')->first()->presentaciones()->first()->fuente_tasa);
        $this->assertEquals('usdt', Producto::where('nombre', 'Prod Usdt')->first()->presentaciones()->first()->fuente_tasa);
        $this->assertEquals('promedio', Producto::where('nombre', 'Prod Promedio')->first()->presentaciones()->first()->fuente_tasa);
    }

    public function test_calcula_costo_usd_correctamente()
    {
        // precie_unit=1.33, porcentage_profit=0.17 → costo = 1.33 / 1.17 ≈ 1.14
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 18, 6, 'Disponible', '', 0, 8.01, 1.33, 13, 0.17, 0),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $producto = Producto::first();
        $this->assertEqualsWithDelta(1.14, $producto->costo_usd, 0.01);
    }

    public function test_es_idempotente()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 18, 6, 'Disponible', '', 0, 8.01, 1.33, 13, 0.17, 0),
            $this->filaSql(2, 'Refresco', 'Cola Camp 2L', 12, 6, 'Disponible', '', 0, 6.00, 1.00, 13, 0.15, 0),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(2, Producto::count());

        // Ejecutar de nuevo — no debe duplicar
        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(2, Producto::count());
        $this->assertEquals(2, ProductoPresentacion::count());
    }

    public function test_force_elimina_y_recrea()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 18, 6, 'Disponible', '', 0, 8.01, 1.33, 13, 0.17, 0),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(1, Producto::count());

        // Cambiar el archivo: ahora tiene 2 productos
        $archivo2 = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 18, 6, 'Disponible', '', 0, 8.01, 1.33, 13, 0.17, 0),
            $this->filaSql(2, 'Refresco', 'Cola Camp 2L', 12, 6, 'Disponible', '', 0, 6.00, 1.00, 13, 0.15, 0),
        ], 'archivo2.sql');

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo2, '--force' => true])
            ->assertExitCode(0);

        $this->assertEquals(2, Producto::count());
    }

    public function test_archivo_no_existente_devuelve_error()
    {
        $this->artisan('migrar:inventario-viejo', ['--archivo' => '/no/existe.sql'])
            ->assertExitCode(1);
    }

    public function test_semillas_tasas_se_crear()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 18, 6, 'Disponible', '', 0, 8.01, 1.33, 13, 0.17, 0),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertDatabaseHas('tasa_cambios', ['tipo' => 'bcv', 'activo' => true]);
        $this->assertDatabaseHas('tasa_cambios', ['tipo' => 'usdt', 'activo' => true]);
        $this->assertDatabaseHas('tasa_cambios', ['tipo' => 'promedio', 'activo' => true]);
    }

    public function test_limpia_prefijo_imagen()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 18, 6, 'Disponible', '../uploads/pepsi.jpg', 0, 8.01, 1.33, 13, 0.17, 0),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals('pepsi.jpg', Producto::first()->imagen);
    }

    public function test_estado_no_disponible()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 18, 6, 'No Disponible', '', 0, 8.01, 1.33, 13, 0.17, 0),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals('no_disponible', Producto::first()->estado);
    }

    private function crearArchivoSql(array $filas, string $nombre = 'test.sql'): string
    {
        $header = 'INSERT INTO inventories (id, name, description, packages, units_package, status, image, iva, precie_package, precie_unit, porcentage, porcentage_profit, daily_dollar, precie_usd, precie_bs) VALUES ';
        $contenido = '';
        foreach ($filas as $fila) {
            $contenido .= $header.$fila.";\n";
        }

        $ruta = $this->directorioTemporal.'/'.$nombre;
        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    private function filaSql(
        int $id,
        string $name,
        string $description,
        int $packages,
        int $unitsPackage,
        string $status,
        string $image,
        int $iva,
        float $preciePackage,
        float $precieUnit,
        int $porcentage,
        float $porcentageProfit,
        int $dailyDollar,
    ): string {
        return "('$id', '$name', '$description', '$packages', '$unitsPackage', '$status', '$image', '$iva', '$preciePackage', '$precieUnit', '$porcentage', '$porcentageProfit', '$dailyDollar', '0', '0')";
    }
}
