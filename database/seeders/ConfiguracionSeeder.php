<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        $configuraciones = [
            ['clave' => 'nombre_negocio', 'valor' => 'Factus'],
            ['clave' => 'rif', 'valor' => 'J-12345678-9'],
            ['clave' => 'direccion', 'valor' => 'Av. Principal, Local 1'],
            ['clave' => 'telefono', 'valor' => '0412-1234567'],
            ['clave' => 'iva_porcentaje', 'valor' => '16'],
        ];

        foreach ($configuraciones as $config) {
            DB::table('configuraciones')->updateOrInsert(
                ['clave' => $config['clave']],
                ['valor' => $config['valor'], 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
