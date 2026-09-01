<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = [
        'clave',
        'valor',
    ];

    public static function obtener(string $clave, string $defecto = ''): string
    {
        return Cache::rememberForever(static::claveCache($clave), function () use ($clave, $defecto) {
            return self::where('clave', $clave)->value('valor') ?? $defecto;
        });
    }

    public static function olvidar(string $clave): void
    {
        Cache::forget(static::claveCache($clave));
    }

    public static function olvidarTodas(): void
    {
        Cache::forget([
            static::claveCache('nombre_negocio'),
            static::claveCache('rif'),
            static::claveCache('direccion'),
            static::claveCache('telefono'),
            static::claveCache('tasa_referencia'),
            static::claveCache('recordatorio_tasa_activo'),
            static::claveCache('recordatorio_tasa_hora1'),
            static::claveCache('recordatorio_tasa_hora2'),
        ]);
    }

    private static function claveCache(string $clave): string
    {
        return 'configuracion:'.$clave;
    }
}
