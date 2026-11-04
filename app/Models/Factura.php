<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Factura extends Model
{
    use HasFactory;

    protected $fillable = [
        'correlativo',
        'cliente_id',
        'user_id',
        'productos',
        'tasa_cambio',
        'metodo_pago',
        'detalle_pago',
        'datos_pago',
        'subtotal_bs',
        'iva_bs',
        'total_bs',
        'total_usd',
        'pago_bs',
        'fecha_pago',
        'estado',
        'estado_credito',
        'fecha_venta',
    ];

    protected function casts(): array
    {
        return [
            'productos' => 'array',
            'detalle_pago' => 'array',
            'datos_pago' => 'array',
            'fecha_venta' => 'datetime',
            'fecha_pago' => 'datetime',
        ];
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function items()
    {
        return $this->hasMany(ItemFactura::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Facturas que representan ingreso real dentro de un rango de fechas:
     * - facturas normales (no anuladas, no a crédito) por su fecha_venta y total_bs/total_usd.
     * - créditos ya cobrados por su fecha_pago y pago_bs/total_usd.
     * Los créditos pendientes no son ingreso todavía y se excluyen.
     *
     * @return Collection<int, Factura>
     */
    public static function ingresosEn(string $desde, string $hasta, ?int $clienteId = null, ?string $metodoPago = null): Collection
    {
        $normales = static::with('cliente', 'user')
            ->where('estado', '!=', 'anulada')
            ->where('estado', '!=', 'credito')
            ->whereDate('fecha_venta', '>=', $desde)
            ->whereDate('fecha_venta', '<=', $hasta)
            ->when($clienteId, fn ($q) => $q->where('cliente_id', $clienteId))
            ->when($metodoPago, fn ($q) => $q->where('metodo_pago', $metodoPago))
            ->get()
            ->each(function (Factura $f) {
                $f->fecha_ingreso = $f->fecha_venta?->format('Y-m-d');
                $f->ingreso_bs = (float) $f->total_bs;
                $f->ingreso_usd = (float) $f->total_usd;
            });

        $cobradas = static::with('cliente', 'user')
            ->where('estado', 'credito')
            ->where('estado_credito', 'cancelado')
            ->whereNotNull('fecha_pago')
            ->whereDate('fecha_pago', '>=', $desde)
            ->whereDate('fecha_pago', '<=', $hasta)
            ->when($clienteId, fn ($q) => $q->where('cliente_id', $clienteId))
            ->when($metodoPago, fn ($q) => $q->where('metodo_pago', $metodoPago))
            ->get()
            ->each(function (Factura $f) {
                $f->fecha_ingreso = $f->fecha_pago?->format('Y-m-d');
                $f->ingreso_bs = (float) ($f->pago_bs ?? 0);
                $f->ingreso_usd = (float) $f->total_usd;
            });

        return $normales->concat($cobradas)->sortByDesc('fecha_ingreso')->values();
    }
}
