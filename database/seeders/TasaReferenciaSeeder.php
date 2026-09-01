<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use App\Models\TasaCambio;
use Illuminate\Database\Seeder;

class TasaReferenciaSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            'bcv' => ['nombre' => 'Banco Central de Venezuela', 'monto' => 0],
            'usdt' => ['nombre' => 'Tether (USDT)', 'monto' => 0],
            'promedio' => ['nombre' => 'Promedio', 'monto' => 0],
        ];

        foreach ($tipos as $tipo => $datos) {
            if (TasaCambio::where('tipo', $tipo)->exists()) {
                continue;
            }

            TasaCambio::create([
                'tipo' => $tipo,
                'nombre' => $datos['nombre'],
                'monto' => $datos['monto'],
                'fecha' => now()->toDateString(),
                'activo' => true,
                'origen' => 'migracion',
            ]);
        }

        $referencia = Configuracion::where('clave', 'tasa_referencia')->exists();
        if (! $referencia) {
            Configuracion::updateOrCreate(
                ['clave' => 'tasa_referencia'],
                ['valor' => 'bcv']
            );
        }
        Configuracion::olvidar('tasa_referencia');
    }
}
