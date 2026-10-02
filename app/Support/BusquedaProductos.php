<?php

namespace App\Support;

class BusquedaProductos
{
    public static function tokens(string $busqueda): array
    {
        return preg_split('/\s+/u', mb_strtolower(trim($busqueda)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
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
                    $sub->whereRaw('LOWER(productos.nombre) LIKE ?', ['%'.$token.'%']);

                    if ($conCategoria) {
                        $sub->orWhereHas('categoria', fn ($c) => $c->whereRaw('LOWER(categorias.nombre) LIKE ?', ['%'.$token.'%']));
                    }

                    $sub->orWhereHas('presentaciones', fn ($p) => $p->whereRaw('LOWER(producto_presentaciones.nombre) LIKE ?', ['%'.$token.'%']));
                });
            }
        });
    }
}
