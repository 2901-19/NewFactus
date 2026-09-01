<?php

namespace App\Console\Commands;

use App\Models\Producto;
use App\Models\ProductoPresentacion;
use Database\Seeders\TasaReferenciaSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrarInventarioViejo extends Command
{
    protected $signature = 'migrar:inventario-viejo
                            {--archivo= : Ruta al archivo SQL del sistema viejo}
                            {--force : Eliminar productos existentes antes de migrar}';

    protected $description = 'Migrar inventario del sistema viejo (tabla inventories) al nuevo esquema de productos y presentaciones';

    public function handle(): int
    {
        $archivo = $this->option('archivo');

        if (! $archivo || ! file_exists($archivo)) {
            $this->error('Archivo no encontrado. Especifica la ruta con --archivo="ruta/al/archivo.sql"');

            return 1;
        }

        try {
            return $this->ejecutarMigracion($archivo);
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");

            return 1;
        }
    }

    private function ejecutarMigracion(string $archivo): int
    {
        $this->info('Migrando inventario del sistema viejo...');
        $this->newLine();

        // 1. Parsear SQL
        $registros = $this->parsearSql($archivo);

        if (empty($registros)) {
            $this->error('No se encontraron registros INSERT en el archivo.');

            return 1;
        }

        $this->info('  Parsing SQL... '.count($registros).' registros encontrados');

        // 3. Sembrar tasas de referencia
        (new TasaReferenciaSeeder)->run();
        $this->line('  Tasas de referencia verificadas (bcv, usdt, promedio)');

        // 4. Si --force, eliminar datos existentes (catálogo, categorías e impuestos)
        if ($this->option('force')) {
            $this->info('  Eliminando productos existentes...');
            DB::statement('DELETE FROM producto_presentaciones');
            DB::statement('DELETE FROM productos');
            DB::statement('DELETE FROM categorias');
            DB::statement('DELETE FROM impuestos');
        }

        // 5. Detectar pares normal+Mayor
        $grupos = $this->agruparPorNombre($registros);
        $paresDetectados = collect($grupos)->filter(fn ($g) => count($g) > 1)->count();
        $this->line('  Detectando pares normal+Mayor... '.$paresDetectados.' pares encontrados');

        // 6. Crear productos y presentaciones
        $this->newLine();
        $creados = 0;
        $conMayor = 0;
        $saltados = 0;

        DB::transaction(function () use ($grupos, &$creados, &$conMayor, &$saltados) {
            foreach ($grupos as $nombreNormalizado => $grupo) {
                $normal = collect($grupo)->first(fn ($r) => ! $this->esMayor($r));
                $mayor = collect($grupo)->first(fn ($r) => $this->esMayor($r));

                // Usar el registro normal como base; si solo hay Mayor, usar ese
                $base = $normal ?? $mayor;

                $nombreProducto = $this->componerNombre($base['name'], $base['description']);

                // Verificar si ya existe (idempotencia)
                if (Producto::where('nombre', $nombreProducto)->exists()) {
                    $saltados++;

                    continue;
                }

                $producto = Producto::create([
                    'nombre' => $nombreProducto,
                    'categoria_id' => null,
                    'descripcion' => null,
                    'imagen' => null,
                    'unidad_medida' => 'unidad',
                    'impuesto_id' => null,
                    'costo_usd' => $this->calcularCosto($base['precie_usd'], $base['porcentage']),
                    'estado' => $base['status'] === 'Disponible' ? 'disponible' : 'no_disponible',
                ]);

                // Presentación Unidad (solo si hay registro normal)
                if ($normal) {
                    ProductoPresentacion::create([
                        'producto_id' => $producto->id,
                        'nombre' => 'Unidad',
                        'factor_conversion' => 1,
                        'margen' => round((float) $base['porcentage'], 2),
                        'fuente_tasa' => $this->mapearFuenteTasa($base['daily_dollar']),
                        'precio_usd' => (float) $base['precie_unit'],
                        'activa' => true,
                    ]);
                }

                // Presentación Mayor (si existe)
                if ($mayor) {
                    ProductoPresentacion::create([
                        'producto_id' => $producto->id,
                        'nombre' => 'Mayor',
                        'factor_conversion' => (int) $mayor['units_package'] ?: 1,
                        'margen' => round((float) $mayor['porcentage'], 2),
                        'fuente_tasa' => $this->mapearFuenteTasa($mayor['daily_dollar']),
                        'precio_usd' => (float) $mayor['precie_unit'],
                        'activa' => true,
                    ]);

                    if ($normal) {
                        $conMayor++;
                    }
                }

                $creados++;
            }
        });

        // 7. Resumen
        $this->newLine();
        $this->info('  Productos creados: '.$creados);
        if ($conMayor > 0) {
            $this->line('    - '.$conMayor.' productos con 2 presentaciones (Unidad + Mayor)');
        }
        $this->line('    - '.($creados - $conMayor).' productos con 1 presentación');
        if ($saltados > 0) {
            $this->line('    - '.$saltados.' productos saltados (ya existían)');
        }
        $this->newLine();
        $this->info('  ¡Migración completada!');

        return 0;
    }

    private function parsearSql(string $archivo): array
    {
        $contenido = file_get_contents($archivo);

        // Detectar y convertir codificación (el archivo viejo suele ser Latin1/Win-1252)
        $detected = mb_detect_encoding($contenido, ['ASCII', 'UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($detected && $detected !== 'UTF-8') {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', $detected);
        }

        $lineas = explode("\n", $contenido);
        $registros = [];

        foreach ($lineas as $linea) {
            $linea = trim($linea);

            if (! str_starts_with($linea, 'INSERT INTO inventories')) {
                continue;
            }

            if (preg_match("/VALUES\s*\((.+)\);$/", $linea, $matches)) {
                $valores = $this->parsearValores($matches[1]);

                if (count($valores) >= 15) {
                    $registros[] = [
                        'id' => $valores[0],
                        'name' => $valores[1],
                        'description' => $valores[2],
                        'packages' => $valores[3],
                        'units_package' => $valores[4],
                        'status' => $valores[5],
                        'image' => $valores[6],
                        'iva' => $valores[7],
                        'precie_package' => $valores[8],
                        'precie_unit' => $valores[9],
                        'porcentage' => $valores[10],
                        'porcentage_profit' => $valores[11],
                        'daily_dollar' => $valores[12],
                        'precie_usd' => $valores[13],
                        'precie_bs' => $valores[14],
                    ];
                }
            }
        }

        return $registros;
    }

    private function parsearValores(string $cadena): array
    {
        $valores = [];
        $i = 0;
        $len = strlen($cadena);

        while ($i < $len) {
            // Saltar espacios y comas
            while ($i < $len && in_array($cadena[$i], [' ', ','])) {
                $i++;
            }

            if ($i >= $len) {
                break;
            }

            if ($cadena[$i] === "'") {
                // Valor entrecomillado
                $i++; // saltar apertura
                $valor = '';

                while ($i < $len && $cadena[$i] !== "'") {
                    // Escapar comillas simples duplicadas
                    if ($cadena[$i] === "'" && ($i + 1) < $len && $cadena[$i + 1] === "'") {
                        $valor .= "'";
                        $i += 2;
                    } else {
                        $valor .= $cadena[$i];
                        $i++;
                    }
                }

                $i++; // saltar cierre
                $valores[] = $valor;
            } else {
                // Valor sin comillas
                $valor = '';

                while ($i < $len && $cadena[$i] !== ',') {
                    $valor .= $cadena[$i];
                    $i++;
                }

                $valores[] = trim($valor);
            }
        }

        return $valores;
    }

    private function agruparPorNombre(array $registros): array
    {
        $grupos = [];

        foreach ($registros as $registro) {
            $clave = mb_strtolower($this->componerNombre($registro['name'], $registro['description']));

            if (! isset($grupos[$clave])) {
                $grupos[$clave] = [];
            }

            $grupos[$clave][] = $registro;
        }

        return $grupos;
    }

    private function componerNombre(string $name, string $description): string
    {
        $tokens = array_merge(
            $this->tokens($description),
            $this->tokens($name)
        );

        $usadas = [];
        $nombre = [];

        foreach ($tokens as $token) {
            $clave = mb_strtolower($token);

            if ($clave === '' || isset($usadas[$clave])) {
                continue;
            }

            $usadas[$clave] = true;
            $nombre[] = $token;
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', $nombre)));
    }

    private function tokens(string $texto): array
    {
        $sinMayor = trim(preg_replace('/\bMayor\b/i', '', $texto));

        return preg_split('/\s+/', $sinMayor) ?: [];
    }

    private function esMayor(array $registro): bool
    {
        return preg_match('/\bMayor\b/i', $registro['name'].' '.$registro['description']) === 1;
    }

    private function calcularCosto(string $precieUsd, string $porcentage): float
    {
        $precio = (float) $precieUsd;
        $margen = (float) $porcentage;

        if ($precio <= 0 || $margen <= 0) {
            return 0;
        }

        return max(0, round($precio * (1 - $margen / 100), 2));
    }

    private function mapearFuenteTasa(string $dailyDollar): string
    {
        return match ($dailyDollar) {
            '0' => 'bcv',
            '1' => 'usdt',
            default => 'promedio',
        };
    }
}
