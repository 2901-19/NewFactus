<?php

namespace App\Support;

use Illuminate\Support\Str;

class BusquedaProductos
{
    public static function tokens(string $busqueda): array
    {
        return preg_split('/\s+/u', Str::ascii(mb_strtolower(trim($busqueda))), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public static function aplicar($query, string $busqueda, bool $conCategoria): void
    {
        $tokens = self::tokens($busqueda);

        if (empty($tokens)) {
            return;
        }

        $query->where(function ($q) use ($tokens, $conCategoria) {
            foreach ($tokens as $token) {
                $q->where(function ($sub) use ($token, $conCategoria) {
                    $sub->whereRaw(self::columnaNormalizada('productos.nombre').' LIKE ?', ['%'.$token.'%']);

                    if ($conCategoria) {
                        $sub->orWhereHas('categoria', fn ($c) => $c->whereRaw(self::columnaNormalizada('categorias.nombre').' LIKE ?', ['%'.$token.'%']));
                    }

                    $sub->orWhereHas('presentaciones', fn ($p) => $p->whereRaw(self::columnaNormalizada('producto_presentaciones.nombre').' LIKE ?', ['%'.$token.'%']));
                });
            }
        });
    }

    /**
     * Expresión SQL portable (SQLite y PostgreSQL) que quita acentos y baja a
     * minúsculas. Incluye mayúsculas acentuadas porque LOWER() de SQLite no
     * transforma caracteres no ASCII.
     */
    private static function columnaNormalizada(string $columna): string
    {
        $sql = 'LOWER('.$columna.')';

        $pares = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ];

        foreach ($pares as $acento => $plano) {
            $sql = "REPLACE({$sql}, '{$acento}', '{$plano}')";
        }

        return $sql;
    }
}
