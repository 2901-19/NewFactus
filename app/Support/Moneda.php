<?php

namespace App\Support;

class Moneda
{
    public static function n($valor, int $decimales = 2): string
    {
        return number_format((float) $valor, $decimales, ',', '.');
    }

    public static function bs($valor, int $decimales = 2): string
    {
        return 'Bs '.self::n($valor, $decimales);
    }

    public static function usd($valor, int $decimales = 2): string
    {
        return '$ '.self::n($valor, $decimales);
    }
}
