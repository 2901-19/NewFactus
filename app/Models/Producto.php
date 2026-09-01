<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'categoria_id',
        'descripcion',
        'imagen',
        'costo_usd',
        'stock_actual',
        'unidad_medida',
        'impuesto_id',
        'estado',
    ];

    protected $appends = [
        'controla_inventario',
    ];

    public function getControlaInventarioAttribute(): bool
    {
        return $this->unidad_medida === 'unidad';
    }

    public function getImagenUrlAttribute(): ?string
    {
        return $this->imagen ? asset('storage/'.$this->imagen) : null;
    }

    public function getPrecioBaseAttribute(): float
    {
        if ($this->relationLoaded('presentaciones')) {
            $base = $this->presentaciones->first(
                fn ($pres) => $pres->activa && (float) $pres->factor_conversion === 1.0
            );
        } else {
            $base = $this->presentaciones()->where('activa', true)->where('factor_conversion', 1)->first();
        }

        return $base ? (float) $base->precio_usd : 0;
    }

    protected function casts(): array
    {
        return [
            'costo_usd' => 'float',
            'stock_actual' => 'float',
        ];
    }

    public function items()
    {
        return $this->hasMany(ItemFactura::class);
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function impuesto()
    {
        return $this->belongsTo(Impuesto::class);
    }

    public function presentaciones()
    {
        return $this->hasMany(ProductoPresentacion::class);
    }

    public function presentacionesActivas()
    {
        return $this->presentaciones()->where('activa', true);
    }
}
