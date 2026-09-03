<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\TasaCambio;
use App\Models\User;
use Database\Seeders\PermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasaCambioControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermisoSeeder::class);
        $this->admin = User::factory()->create(['rol' => 'admin']);
        $this->actingAs($this->admin);
    }

    public function test_index_muestra_tasas()
    {
        TasaCambio::factory()->create(['tipo' => 'promedio']);
        TasaCambio::factory()->create(['tipo' => 'dolar']);
        TasaCambio::factory()->create(['tipo' => 'bcv']);

        $response = $this->get('/tasas-cambio');

        $response->assertStatus(200);
        $response->assertViewHas('tasas');
        $response->assertViewHas('tasaReferencia', 'bcv');
    }

    public function test_actualizar_guarda_tasa()
    {
        TasaCambio::factory()->create(['tipo' => 'promedio', 'nombre' => 'Promedio', 'monto' => 50.00]);

        $response = $this->post('/tasas-cambio/actualizar', [
            'tipo' => 'promedio',
            'monto' => 45.50,
        ]);

        $response->assertRedirect('/tasas-cambio');
        $this->assertDatabaseCount('tasa_cambios', 2);
        $this->assertDatabaseHas('tasa_cambios', [
            'tipo' => 'promedio',
            'monto' => 45.50,
            'origen' => 'manual',
        ]);
    }

    public function test_actualizar_conserva_historial_y_vigente_es_la_ultima()
    {
        TasaCambio::factory()->create(['tipo' => 'promedio', 'nombre' => 'Promedio', 'monto' => 50.00]);

        $this->post('/tasas-cambio/actualizar', ['tipo' => 'promedio', 'monto' => 60.00]);
        $this->post('/tasas-cambio/actualizar', ['tipo' => 'promedio', 'monto' => 70.00]);

        $this->assertDatabaseCount('tasa_cambios', 3);
        $this->assertEquals(70.0, (float) TasaCambio::ultimaDe('promedio')->monto);
    }

    public function test_actualizar_registra_usuario_que_modifica()
    {
        TasaCambio::factory()->create(['tipo' => 'promedio', 'nombre' => 'Promedio', 'monto' => 50.00]);

        $this->post('/tasas-cambio/actualizar', ['tipo' => 'promedio', 'monto' => 55.00]);

        $this->assertDatabaseHas('tasa_cambios', [
            'tipo' => 'promedio',
            'monto' => 55.00,
            'user_id' => $this->admin->id,
            'origen' => 'manual',
        ]);
    }

    public function test_crear_registra_usuario()
    {
        $this->post('/tasas-cambio', [
            'tipo' => 'zelle',
            'nombre' => 'Dólar Zelle',
            'monto' => 62.75,
        ]);

        $this->assertDatabaseHas('tasa_cambios', [
            'tipo' => 'zelle',
            'user_id' => $this->admin->id,
            'origen' => 'manual',
        ]);
    }

    public function test_historial_muestra_registros()
    {
        TasaCambio::factory()->create(['tipo' => 'promedio', 'nombre' => 'Promedio', 'monto' => 50.00]);
        $this->post('/tasas-cambio/actualizar', ['tipo' => 'promedio', 'monto' => 55.00]);
        TasaCambio::factory()->create(['tipo' => 'bcv', 'nombre' => 'BCV', 'monto' => 45.00]);

        $response = $this->get('/tasas-cambio/historial');

        $response->assertStatus(200);
        $response->assertViewHas('historial');
        $this->assertCount(3, $response->viewData('historial'));
    }

    public function test_historial_filtra_por_tipo()
    {
        TasaCambio::factory()->create(['tipo' => 'promedio', 'nombre' => 'Promedio', 'monto' => 50.00]);
        $this->post('/tasas-cambio/actualizar', ['tipo' => 'promedio', 'monto' => 55.00]);
        $this->post('/tasas-cambio/actualizar', ['tipo' => 'promedio', 'monto' => 60.00]);
        TasaCambio::factory()->create(['tipo' => 'bcv', 'nombre' => 'BCV', 'monto' => 45.00]);

        $response = $this->get('/tasas-cambio/historial?tipo=promedio');

        $response->assertStatus(200);
        $this->assertCount(3, $response->viewData('historial'));
        foreach ($response->viewData('historial') as $fila) {
            $this->assertEquals('promedio', $fila->tipo);
        }
    }

    public function test_historial_variacion_no_se_contamina_entre_tipos()
    {
        TasaCambio::factory()->create(['tipo' => 'promedio', 'nombre' => 'Promedio', 'monto' => 50.00]);
        TasaCambio::factory()->create(['tipo' => 'bcv', 'nombre' => 'BCV', 'monto' => 60.00]);
        $this->post('/tasas-cambio/actualizar', ['tipo' => 'bcv', 'monto' => 63.00]);
        $this->post('/tasas-cambio/actualizar', ['tipo' => 'promedio', 'monto' => 55.00]);

        $response = $this->get('/tasas-cambio/historial');

        $filas = $response->viewData('historial');
        $this->assertEquals('promedio', $filas[0]->tipo);
        $this->assertEquals(10.0, $filas[0]->variacion);
        $this->assertEquals('bcv', $filas[1]->tipo);
        $this->assertEquals(5.0, $filas[1]->variacion);
        $this->assertEquals('bcv', $filas[2]->tipo);
        $this->assertNull($filas[2]->variacion);
        $this->assertEquals('promedio', $filas[3]->tipo);
        $this->assertNull($filas[3]->variacion);
    }

    public function test_historial_variacion_no_divide_por_cero()
    {
        TasaCambio::factory()->create(['tipo' => 'bcv', 'nombre' => 'BCV', 'monto' => 0]);
        $this->post('/tasas-cambio/actualizar', ['tipo' => 'bcv', 'monto' => 60.00]);

        $response = $this->get('/tasas-cambio/historial');

        $response->assertStatus(200);
        $filas = $response->viewData('historial');
        $this->assertNull($filas[1]->variacion);
    }

    public function test_actualizar_valida_campos()
    {
        $response = $this->post('/tasas-cambio/actualizar', []);

        $response->assertSessionHasErrors(['tipo', 'monto']);
    }

    public function test_actualizar_rechaza_monto_cero_o_negativo()
    {
        $response = $this->post('/tasas-cambio/actualizar', [
            'tipo' => 'promedio',
            'monto' => 0,
        ]);

        $response->assertSessionHasErrors(['monto']);
        $this->assertDatabaseCount('tasa_cambios', 0);
    }

    public function test_crear_nueva_tasa()
    {
        $response = $this->post('/tasas-cambio', [
            'tipo' => 'zelle',
            'nombre' => 'Dólar Zelle',
            'monto' => 62.75,
        ]);

        $response->assertRedirect('/tasas-cambio');
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tasa_cambios', [
            'tipo' => 'zelle',
            'nombre' => 'Dólar Zelle',
            'monto' => 62.75,
            'activo' => true,
        ]);
    }

    public function test_crear_tipo_existente_inserta_historial()
    {
        TasaCambio::factory()->create(['tipo' => 'promedio', 'monto' => 50.00]);

        $response = $this->post('/tasas-cambio', [
            'tipo' => 'promedio',
            'nombre' => 'Promedio',
            'monto' => 60.00,
        ]);

        $response->assertRedirect('/tasas-cambio');
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('tasa_cambios', 2);
        $this->assertEquals(60.00, (float) TasaCambio::ultimaDe('promedio')->monto);
    }

    public function test_crear_genera_tipo_desde_el_nombre()
    {
        $response = $this->post('/tasas-cambio', [
            'nombre' => 'Dólar Zelle',
            'monto' => 62.75,
        ]);

        $response->assertRedirect('/tasas-cambio');
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tasa_cambios', [
            'tipo' => 'dolar_zelle',
            'nombre' => 'Dólar Zelle',
            'monto' => 62.75,
        ]);
    }

    public function test_crear_genera_tipo_unico_cuando_el_nombre_ya_existe()
    {
        TasaCambio::factory()->create(['tipo' => 'dolar_zelle']);

        $response = $this->post('/tasas-cambio', [
            'nombre' => 'Dólar Zelle',
            'monto' => 63.00,
        ]);

        $response->assertRedirect('/tasas-cambio');
        $this->assertDatabaseHas('tasa_cambios', ['tipo' => 'dolar_zelle_2']);
    }

    public function test_crear_rechaza_tipo_invalido()
    {
        $response = $this->post('/tasas-cambio', [
            'tipo' => 'Dolar Zelle',
            'nombre' => 'Dólar Zelle',
            'monto' => 62.75,
        ]);

        $response->assertSessionHasErrors(['tipo']);
    }

    public function test_crear_rechaza_monto_no_positivo()
    {
        $response = $this->post('/tasas-cambio', [
            'tipo' => 'zelle',
            'nombre' => 'Dólar Zelle',
            'monto' => 0,
        ]);

        $response->assertSessionHasErrors(['monto']);
        $this->assertDatabaseCount('tasa_cambios', 0);
    }

    public function test_toggle_desactiva_tasa()
    {
        $tasa = TasaCambio::factory()->create(['tipo' => 'dolar', 'activo' => true]);

        $response = $this->post("/tasas-cambio/{$tasa->id}/toggle");

        $response->assertRedirect('/tasas-cambio');
        $this->assertDatabaseHas('tasa_cambios', ['id' => $tasa->id, 'activo' => false]);
    }

    public function test_toggle_reactiva_tasa()
    {
        $tasa = TasaCambio::factory()->create(['tipo' => 'dolar', 'activo' => false]);

        $response = $this->post("/tasas-cambio/{$tasa->id}/toggle");

        $response->assertRedirect('/tasas-cambio');
        $this->assertDatabaseHas('tasa_cambios', ['id' => $tasa->id, 'activo' => true]);
    }

    public function test_toggle_bloquea_desactivar_referencia()
    {
        $tasa = TasaCambio::factory()->create(['tipo' => 'bcv', 'activo' => true]);
        Configuracion::updateOrCreate(['clave' => 'tasa_referencia'], ['valor' => 'bcv']);

        $response = $this->post("/tasas-cambio/{$tasa->id}/toggle");

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('tasa_cambios', ['id' => $tasa->id, 'activo' => true]);
    }

    public function test_fijar_referencia_guarda_configuracion()
    {
        TasaCambio::factory()->create(['tipo' => 'dolar', 'activo' => true]);

        $response = $this->post('/tasas-cambio/referencia', ['referencia' => 'dolar']);

        $response->assertRedirect('/tasas-cambio');
        $this->assertDatabaseHas('configuraciones', ['clave' => 'tasa_referencia', 'valor' => 'dolar']);
    }

    public function test_fijar_referencia_rechaza_tasa_inactiva()
    {
        TasaCambio::factory()->create(['tipo' => 'dolar', 'activo' => false]);

        $response = $this->post('/tasas-cambio/referencia', ['referencia' => 'dolar']);

        $response->assertSessionHasErrors('referencia');
        $this->assertDatabaseMissing('configuraciones', ['clave' => 'tasa_referencia', 'valor' => 'dolar']);
    }

    public function test_fijar_referencia_rechaza_tipo_inexistente()
    {
        $response = $this->post('/tasas-cambio/referencia', ['referencia' => 'inexistente']);

        $response->assertSessionHasErrors('referencia');
    }
}
