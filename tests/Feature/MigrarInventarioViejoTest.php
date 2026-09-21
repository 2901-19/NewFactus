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
            $this->filaSql(1, 'Refresco', 'Pepsi Cola 2L', 18, 6, 'Disponible', '', 0, 8.01, 1.33, 13, 0.17, 0, 1.50),
            $this->filaSql(2, 'Refresco', 'Cola Camp 2L', 12, 6, 'Disponible', '', 0, 6.00, 1.00, 13, 0.15, 0, 1.10),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(2, Producto::count());
        $this->assertEquals(2, ProductoPresentacion::count());

        $pepsi = Producto::where('nombre', 'Pepsi Cola 2L Refresco')->first();
        $this->assertNotNull($pepsi);
        $this->assertEquals('disponible', $pepsi->estado);
        $this->assertNull($pepsi->categoria_id);
        $this->assertNull($pepsi->imagen);
        $this->assertNull($pepsi->impuesto_id);
        $this->assertNull($pepsi->descripcion);
        $this->assertEquals('unidad', $pepsi->unidad_medida);
        $this->assertEquals(0, $pepsi->stock_actual);
        $this->assertEqualsWithDelta(1.31, $pepsi->costo_usd, 0.01);
        $this->assertEquals(1, $pepsi->presentaciones()->count());

        $presentacion = $pepsi->presentaciones()->first();
        $this->assertEquals('Unidad', $presentacion->nombre);
        $this->assertEquals(13.0, $presentacion->margen);
        $this->assertEquals('bcv', $presentacion->fuente_tasa);
        $this->assertEquals(1.33, $presentacion->precio_usd);
        $this->assertEquals(1, $presentacion->factor_conversion);
    }

    public function test_no_crea_categorias_ni_asigna_imagen_ni_impuesto()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Snacks', 'Choco LooK', 10, 12, 'Disponible', '../uploads/pepsi.jpg', 1, 12.00, 1.00, 16, 0.20, 0, 1.30),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(0, Categoria::count());
        $this->assertEquals(0, Impuesto::count());

        $producto = Producto::first();
        $this->assertNotNull($producto);
        $this->assertNull($producto->categoria_id);
        $this->assertNull($producto->imagen);
        $this->assertNull($producto->impuesto_id);
    }

    public function test_fusiona_par_normal_y_mayor()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Harina', 'Doña Belen 1Kg', 12, 20, 'Disponible', '', 0, 15.40, 0.77, 8, 0.08, 0, 0.81),
            $this->filaSql(2, 'Harina', 'Doña Belen 1Kg Mayor', 13, 20, 'Disponible', '', 0, 15.40, 0.77, 5, 0.04, 0, 0.81),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(1, Producto::count());
        $producto = Producto::first();
        $this->assertEquals('Doña Belen 1Kg Harina', $producto->nombre);
        $this->assertEquals(2, $producto->presentaciones()->count());

        $unidad = $producto->presentaciones()->where('nombre', 'Unidad')->first();
        $this->assertNotNull($unidad);
        $this->assertEquals(8.0, $unidad->margen);
        $this->assertEquals(1, $unidad->factor_conversion);

        $mayor = $producto->presentaciones()->where('nombre', 'Mayor')->first();
        $this->assertNotNull($mayor);
        $this->assertEquals(5.0, $mayor->margen);
        $this->assertEquals(20, $mayor->factor_conversion);
    }

    public function test_fusiona_cuando_mayor_esta_en_name()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Azucar Blanco', 'La Pastora 1kg', 12, 24, 'Disponible', '', 0, 15.00, 0.75, 9, 0.09, 0, 0.82),
            $this->filaSql(2, 'Azucar Blanco Mayor', 'La Pastora 1kg', 12, 24, 'Disponible', '', 0, 15.00, 0.70, 6, 0.06, 0, 0.74),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(1, Producto::count());
        $producto = Producto::first();
        $this->assertEquals('La Pastora 1kg Azucar Blanco', $producto->nombre);
        $this->assertEquals(2, $producto->presentaciones()->count());

        $mayor = $producto->presentaciones()->where('nombre', 'Mayor')->first();
        $this->assertNotNull($mayor);
        $this->assertEquals(6.0, $mayor->margen);
        $this->assertEquals(24, $mayor->factor_conversion);
    }

    public function test_nombre_omite_palabras_repetidas_entre_name_y_descripcion()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Harina maiz', 'Mary maiz blanco', 10, 20, 'Disponible', '', 0, 20.80, 1.04, 8, 0.08, 0, 1.12),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(1, Producto::count());
        $this->assertEquals('Mary maiz blanco Harina', Producto::first()->nombre);
    }

    public function test_producto_solo_mayor_se_crea_como_una_presentacion()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Frescolita 1L Mayor', 6, 6, 'Disponible', '', 0, 3.66, 0.61, 13, 0.10, 2, 0.70),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(1, Producto::count());
        $producto = Producto::first();
        $this->assertEquals('Frescolita 1L Refresco', $producto->nombre);
        $this->assertEquals(1, $producto->presentaciones()->count());

        $presentacion = $producto->presentaciones()->first();
        $this->assertEquals('Mayor', $presentacion->nombre);
        $this->assertEquals('promedio', $presentacion->fuente_tasa);
    }

    public function test_mapea_daily_dollar_a_fuente_tasa()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Cat A', 'Prod Bcv', 10, 6, 'Disponible', '', 0, 6.00, 1.00, 13, 0.20, 0, 1.20),
            $this->filaSql(2, 'Cat A', 'Prod Usdt', 10, 6, 'Disponible', '', 0, 6.00, 1.00, 13, 0.20, 1, 1.20),
            $this->filaSql(3, 'Cat A', 'Prod Promedio', 10, 6, 'Disponible', '', 0, 6.00, 1.00, 13, 0.20, 2, 1.20),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals('bcv', Producto::where('nombre', 'Prod Bcv Cat A')->first()->presentaciones()->first()->fuente_tasa);
        $this->assertEquals('usdt', Producto::where('nombre', 'Prod Usdt Cat A')->first()->presentaciones()->first()->fuente_tasa);
        $this->assertEquals('promedio', Producto::where('nombre', 'Prod Promedio Cat A')->first()->presentaciones()->first()->fuente_tasa);
    }

    public function test_calcula_costo_usd_correctamente()
    {
        // precie_usd=2.00, porcentage=25 → costo = 2.00 * 0.75 = 1.50
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 18, 6, 'Disponible', '', 0, 8.01, 1.33, 25, 0.17, 0, 2.00),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(1.5, Producto::first()->costo_usd);
    }

    public function test_costo_cero_sin_precie_usd()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 18, 6, 'Disponible', '', 0, 8.01, 1.33, 13, 0.17, 0),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(0, Producto::first()->costo_usd);
    }

    public function test_es_idempotente()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 18, 6, 'Disponible', '', 0, 8.01, 1.33, 13, 0.17, 0, 1.50),
            $this->filaSql(2, 'Refresco', 'Cola Camp 2L', 12, 6, 'Disponible', '', 0, 6.00, 1.00, 13, 0.15, 0, 1.10),
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

    public function test_force_elimina_y_recrea_y_limpia_categorias_e_impuestos()
    {
        Categoria::create(['nombre' => 'Refresco']);
        Impuesto::create(['nombre' => 'IVA', 'porcentaje' => 16.00]);

        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 18, 6, 'Disponible', '', 0, 8.01, 1.33, 13, 0.17, 0, 1.50),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertEquals(1, Producto::count());

        // Cambiar el archivo: ahora tiene 2 productos
        $archivo2 = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 18, 6, 'Disponible', '', 0, 8.01, 1.33, 13, 0.17, 0, 1.50),
            $this->filaSql(2, 'Refresco', 'Cola Camp 2L', 12, 6, 'Disponible', '', 0, 6.00, 1.00, 13, 0.15, 0, 1.10),
        ], 'archivo2.sql');

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo2, '--force' => true])
            ->assertExitCode(0);

        $this->assertEquals(2, Producto::count());
        $this->assertEquals(0, Categoria::count());
        $this->assertEquals(0, Impuesto::count());
    }

    public function test_archivo_no_existente_devuelve_error()
    {
        $this->artisan('migrar:inventario-viejo', ['--archivo' => '/no/existe.sql'])
            ->assertExitCode(1);
    }

    public function test_semillas_tasas_se_crear()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 18, 6, 'Disponible', '', 0, 8.01, 1.33, 13, 0.17, 0, 1.50),
        ]);

        $this->artisan('migrar:inventario-viejo', ['--archivo' => $archivo])
            ->assertExitCode(0);

        $this->assertDatabaseHas('tasa_cambios', ['tipo' => 'bcv', 'activo' => true]);
        $this->assertDatabaseHas('tasa_cambios', ['tipo' => 'usdt', 'activo' => true]);
        $this->assertDatabaseHas('tasa_cambios', ['tipo' => 'promedio', 'activo' => true]);
    }

    public function test_export_json_genera_archivo_sin_modificar_la_bd()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Harina', 'Doña Belen 1Kg', 12, 20, 'Disponible', '', 0, 15.40, 0.77, 8, 0.08, 1, 0.81),
            $this->filaSql(2, 'Harina', 'Doña Belen 1Kg Mayor', 13, 20, 'Disponible', '', 0, 15.40, 0.77, 5, 0.04, 2, 0.81),
        ]);
        file_put_contents($archivo, "\xEF\xBB\xBF".file_get_contents($archivo));
        $salida = $this->directorioTemporal.'/precios.json';

        $this->artisan('migrar:inventario-viejo', [
            '--archivo' => $archivo,
            '--export-json' => $salida,
        ])->assertExitCode(0);

        $this->assertEquals(0, Producto::count());
        $this->assertEquals(0, ProductoPresentacion::count());

        $data = json_decode(file_get_contents($salida), true);
        $this->assertCount(1, $data['precios']);

        $precio = $data['precios'][0];
        $this->assertEquals('Doña Belen 1Kg Harina', $precio['nombre']);
        $this->assertEquals(0.75, $precio['costo_usd']);
        $this->assertCount(2, $precio['presentaciones']);

        $unidad = $precio['presentaciones'][0];
        $this->assertEquals('Unidad', $unidad['nombre']);
        $this->assertEquals(1, $unidad['factor_conversion']);
        $this->assertEquals(8, $unidad['margen']);
        $this->assertEquals(0.81, $unidad['precio_usd']);
        $this->assertEquals('usdt', $unidad['fuente_tasa']);
        $this->assertTrue($unidad['activa']);

        $mayor = $precio['presentaciones'][1];
        $this->assertEquals('Mayor', $mayor['nombre']);
        $this->assertEquals(20, $mayor['factor_conversion']);
        $this->assertEquals(5, $mayor['margen']);
        $this->assertEquals(15.8, $mayor['precio_usd']);
        $this->assertEquals('promedio', $mayor['fuente_tasa']);
    }

    public function test_export_json_con_presentaciones_editadas_persisten_precio_calculado()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 18, 6, 'Disponible', '', 0, 8.01, 1.33, 13, 0.17, 0, 1.50),
        ]);

        $this->artisan('migrar:inventario-viejo', [
            '--archivo' => $archivo,
            '--export-json' => $this->directorioTemporal.'/pepsi.json',
        ])->assertExitCode(0);

        $this->assertEquals(0, Producto::count());
    }

    public function test_estado_no_disponible()
    {
        $archivo = $this->crearArchivoSql([
            $this->filaSql(1, 'Refresco', 'Pepsi 2L', 18, 6, 'No Disponible', '', 0, 8.01, 1.33, 13, 0.17, 0, 1.50),
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
        float $precieUsd = 0,
        float $precieBs = 0,
    ): string {
        return "('$id', '$name', '$description', '$packages', '$unitsPackage', '$status', '$image', '$iva', '$preciePackage', '$precieUnit', '$porcentage', '$porcentageProfit', '$dailyDollar', '$precieUsd', '$precieBs')";
    }
}
