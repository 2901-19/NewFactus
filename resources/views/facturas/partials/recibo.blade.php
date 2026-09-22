@php
    $esCredito = $factura->estado === 'credito';
    $tasaCambio = (float) $factura->tasa_cambio ?: 1;
    $nombreNegocio = \App\Models\Configuracion::obtener('nombre_negocio', config('app.name'));
    $nombresMetodo = [
        'efectivo' => 'Efectivo',
        'punto' => 'Punto de Venta',
        'biopago' => 'Biopago',
        'divisas' => 'Divisas',
        'pago_movil' => 'Pago Móvil',
        'transferencia' => 'Transferencia',
        'mixto' => 'Mixto',
        'credito' => 'Crédito',
    ];
@endphp

<div class="recibo">
    <div class="recibo-head">
        <div class="recibo-brand">{{ $nombreNegocio }}</div>
        <span class="recibo-titulo">FACTURA DE VENTA</span>
        <div>
            @if ($factura->estado === 'anulada')
                <span class="badge bg-danger">Anulada</span>
            @elseif ($esCredito && $factura->estado_credito === 'cancelado')
                <span class="badge bg-info">Crédito Cobrado</span>
            @elseif ($esCredito)
                <span class="badge bg-warning text-dark">Crédito Pendiente</span>
            @else
                <span class="badge bg-success">Contado</span>
            @endif
        </div>
    </div>
    <div class="p-3 pt-1">
        <div class="recibo-meta">
            <div class="d-flex justify-content-between">
                <span class="recibo-meta-label">N° Factura</span>
                <span class="recibo-meta-valor">{{ $factura->correlativo }}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span class="recibo-meta-label">Fecha</span>
                <span class="recibo-meta-valor">{{ $factura->fecha_venta }}</span>
            </div>
            @if ($factura->cliente)
            <div class="d-flex justify-content-between">
                <span class="recibo-meta-label">Cliente</span>
                <span class="recibo-meta-valor">{{ $factura->cliente->nombre }}</span>
            </div>
            @endif
            <div class="d-flex justify-content-between">
                <span class="recibo-meta-label">Tasa de cambio</span>
                <span class="recibo-meta-valor">Bs {{ \App\Support\Moneda::n($tasaCambio) }}</span>
            </div>
        </div>

        <div class="recibo-sep"></div>

        <table class="table table-sm tabla-recibo">
            <thead>
                <tr>
                    <th class="cant">CANT</th>
                    <th class="desc">DESC</th>
                    <th class="num">PREC U</th>
                    @if (!$esCredito)<th class="num">USD</th>@endif
                    <th class="num">PREC T</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($factura->items as $item)
                @php $esPesable = $item->unidad_medida === 'kg'; @endphp
                <tr>
                    <td class="cant">{{ $esPesable ? '—' : $item->cantidad }}</td>
                    <td class="desc">
                        {{ $item->producto->nombre ?? 'Producto' }}
                        @if ($item->presentacion_nombre)<small class="text-muted"> ({{ $item->presentacion_nombre }})</small>@endif
                    </td>
                    @if ($esCredito)
                    <td class="num">{{ $esPesable ? '$ ' . \App\Support\Moneda::n($item->precio_unitario_bs / $tasaCambio) . ' /kg' : '$ ' . \App\Support\Moneda::n($item->precio_unitario_bs / $tasaCambio) }}</td>
                    <td class="num fw-semibold">$ {{ \App\Support\Moneda::n($item->subtotal / $tasaCambio) }}</td>
                    @else
                    <td class="num">{{ $esPesable ? \App\Support\Moneda::n($item->precio_unitario_bs) . ' /kg' : \App\Support\Moneda::n($item->precio_unitario_bs) }}</td>
                    <td class="num">{{ $esPesable ? '$ ' . \App\Support\Moneda::n($item->precio_unitario_bs / $tasaCambio) . ' /kg' : '$ ' . \App\Support\Moneda::n($item->precio_unitario_bs / $tasaCambio) }}</td>
                    <td class="num fw-semibold">{{ \App\Support\Moneda::n($item->subtotal) }}</td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="recibo-sep"></div>

        <div class="totales">
            <div class="fila-total">
                <span>{{ $esCredito ? 'Subtotal USD' : 'Subtotal Bs' }}</span>
                <span>{{ $esCredito ? '$ ' . \App\Support\Moneda::n($factura->subtotal_bs / $tasaCambio) : 'Bs ' . \App\Support\Moneda::n($factura->subtotal_bs) }}</span>
            </div>
            <div class="fila-total">
                <span>Impuesto</span>
                <span>{{ $esCredito ? '$ ' . \App\Support\Moneda::n($factura->iva_bs / $tasaCambio) : 'Bs ' . \App\Support\Moneda::n($factura->iva_bs) }}</span>
            </div>
            <div class="total-final">
                <span class="total-label">{{ $esCredito ? 'Total USD' : 'Total Bs' }}</span>
                <span class="total-valor">{{ $esCredito ? '$ ' . \App\Support\Moneda::n($factura->total_usd) : 'Bs ' . \App\Support\Moneda::n($factura->total_bs) }}</span>
            </div>
            @if (!$esCredito)
            <div class="fila-total">
                <span>Total USD</span>
                <span>$ {{ \App\Support\Moneda::n($factura->total_usd) }}</span>
            </div>
            @endif
            @if ($factura->metodo_pago === 'mixto' && !$esCredito)
            <div class="recibo-sep"></div>
            <div class="seccion-titulo">Pago Mixto</div>
            @foreach ($factura->detalle_pago ?? [] as $pago)
            <div class="fila-total">
                <span>{{ $nombresMetodo[$pago['metodo']] ?? $pago['metodo'] }}</span>
                <span>Bs {{ \App\Support\Moneda::n($pago['monto']) }}</span>
            </div>
            @endforeach
            @elseif ($factura->estado === 'credito' && $factura->estado_credito === 'cancelado')
            <div class="recibo-sep"></div>
            <div class="fila-total">
                <span>Pago</span>
                <span>{{ $nombresMetodo[$factura->metodo_pago] ?? $factura->metodo_pago }}</span>
            </div>
            @elseif ($factura->estado !== 'credito')
            <div class="fila-total">
                <span>Pago</span>
                <span>{{ $nombresMetodo[$factura->metodo_pago] ?? $factura->metodo_pago }}</span>
            </div>
            @endif
            @if ($factura->estado === 'credito')
            <div class="recibo-sep"></div>
            @if ($factura->estado_credito === 'cancelado')
            <div class="fila-total">
                <span>Crédito cobrado (US$)</span>
                <span>$ {{ \App\Support\Moneda::n($factura->total_usd) }}</span>
            </div>
            <div class="fila-total">
                <span>Cobrado en Bs</span>
                <span>Bs {{ \App\Support\Moneda::n($factura->pago_bs) }}</span>
            </div>
            <div class="fila-total">
                <span>Fecha de cobro</span>
                <span>{{ $factura->fecha_pago?->format('d/m/Y') ?? '—' }}</span>
            </div>
            @else
            <div class="fila-total">
                <span>Crédito pendiente (US$)</span>
                <span>$ {{ \App\Support\Moneda::n($factura->total_usd) }}</span>
            </div>
            @endif
            @endif
        </div>

        <div class="recibo-sep"></div>

        <div class="recibo-foot">¡Gracias por su compra!</div>

        <div class="text-center mt-3">
            <a href="{{ route('herramientas.imprimir-factura', $factura->id) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-printer"></i> Imprimir Ticket
            </a>
        </div>
    </div>
</div>
