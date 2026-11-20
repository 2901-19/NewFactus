<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Impuesto;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\TasaCambio;
use Illuminate\Database\Seeder;

class ProductosPruebaSeeder extends Seeder
{
    public function run(): void
    {
        $impuesto = Impuesto::firstOrCreate(
            ['nombre' => 'IVA 16%'],
            ['porcentaje' => 16.00]
        );

        $nombresCategorias = ['Alimentos', 'Bebidas', 'Limpieza', 'Higiene', 'Otros'];
        $categorias = collect($nombresCategorias)->map(
            fn (string $nombre) => Categoria::firstOrCreate(['nombre' => $nombre])
        );

        // Tasa con monto real para que los precios en Bs se calculen y el PDF/JSON renderice montos.
        $tasa = TasaCambio::where('tipo', 'promedio')->latest('id')->first();
        if ($tasa) {
            $tasa->forceFill(['monto' => 36.50, 'activo' => true])->save();
        } else {
            TasaCambio::create([
                'tipo' => 'promedio',
                'nombre' => 'Promedio',
                'monto' => 36.50,
                'fecha' => now()->toDateString(),
                'activo' => true,
                'origen' => 'manual',
            ]);
        }

        $total = 2000;

        for ($n = 1; $n <= $total; $n++) {
            $costo = round(fake()->randomFloat(2, 0.3, 40), 2);
            $producto = Producto::create([
                'nombre' => 'Producto '.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                'categoria_id' => $categorias->random()->id,
                'impuesto_id' => $impuesto->id,
                'costo_usd' => $costo,
                'stock_actual' => round(fake()->randomFloat(3, 0, 100), 3),
                'unidad_medida' => 'unidad',
                'estado' => 'disponible',
            ]);

            ProductoPresentacion::create([
                'producto_id' => $producto->id,
                'nombre' => 'Unidad',
                'factor_conversion' => 1,
                'margen' => 25,
                'precio_usd' => round($costo * 1.25, 2),
                'fuente_tasa' => 'promedio',
                'activa' => true,
            ]);
        }
    }
}
