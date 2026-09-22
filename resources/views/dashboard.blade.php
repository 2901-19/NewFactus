@extends('layouts.app')
@section('titulo', 'Inicio')
@section('contenido')
@php
    use App\Services\CatalogoService;
    $esAnulada = fn ($f) => $f->estado === 'anulada';
    $esCredito = fn ($f) => $f->estado === 'credito';
    $tienePermiso = fn ($slug) => Auth::user()->hasPermiso($slug);
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-2">
    <div>
        <h1 class="titulo-pagina">Hola, {{ Auth::user()->name }}</h1>
        <div class="subtitulo-pagina mt-1">
            {{ $nombreNegocio }} &middot; {{ now()->translatedFormat('l, d \d\e F Y') }}
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if ($tienePermiso('usar-pos'))
            <a href="{{ route('facturas.pos') }}" class="btn btn-primary">
                <i class="bi bi-cart3"></i> Punto de Venta
            </a>
        @endif
        @if ($tienePermiso('gestionar-creditos'))
            <a href="{{ route('facturas.creditos') }}" class="btn btn-warning">
                <i class="bi bi-credit-card"></i> Cobrar Créditos
            </a>
        @endif
        @if ($tienePermiso('ver-facturas'))
            <a href="{{ route('facturas.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-list-ul"></i> Facturas
            </a>
        @endif
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="modulo-metrica">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <span class="metrica-caption">Ingresos hoy</span>
                        <span class="modulo-icono text-primary"><i class="bi bi-cart3"></i></span>
                    </div>
                    <div class="metrica-valor">{{ $ventasHoy }}</div>
                    <div class="metrica-secundaria">Bs {{ \App\Support\Moneda::n($totalHoyBs) }} &middot; ${{ \App\Support\Moneda::n($totalHoyUsd) }}</div>
                </div>
                <div class="mt-3">
                    @if ($variacionHoy !== null)
                        <span class="tendencia {{ $variacionHoy >= 0 ? 'tendencia-alta' : 'tendencia-baja' }}">
                            <i class="bi {{ $variacionHoy >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
                            {{ \App\Support\Moneda::n(abs($variacionHoy), 1) }}% vs ayer
                        </span>
                    @else
                        <span class="tendencia">Sin ventas ayer</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="modulo-metrica">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <span class="metrica-caption">Ingresos del mes</span>
                        <span class="modulo-icono text-success"><i class="bi bi-graph-up-arrow"></i></span>
                    </div>
                    <div class="metrica-valor">{{ $ventasMes }}</div>
                    <div class="metrica-secundaria">Bs {{ \App\Support\Moneda::n($totalMesBs) }} &middot; ${{ \App\Support\Moneda::n($totalMesUsd) }}</div>
                </div>
                <div class="mt-3">
                    @if ($variacionMes !== null)
                        <span class="tendencia {{ $variacionMes >= 0 ? 'tendencia-alta' : 'tendencia-baja' }}">
                            <i class="bi {{ $variacionMes >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
                            {{ \App\Support\Moneda::n(abs($variacionMes), 1) }}% vs mes anterior
                        </span>
                    @else
                        <span class="tendencia">Sin ventas el mes anterior</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="modulo-metrica">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <span class="metrica-caption">Créditos pendientes</span>
                        <span class="modulo-icono text-warning"><i class="bi bi-credit-card"></i></span>
                    </div>
                    <div class="metrica-valor">{{ $creditosPendientes }}</div>
                    <div class="metrica-secundaria">Bs {{ \App\Support\Moneda::n($totalCreditosPendientesBs) }}</div>
                </div>
                @if ($tienePermiso('gestionar-creditos') && $creditosPendientes > 0)
                    <a href="{{ route('facturas.creditos') }}" class="tendencia d-inline-flex mt-3 text-decoration-none">
                        <i class="bi bi-box-arrow-up-right"></i>Cobrar ahora
                    </a>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="modulo-metrica">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <span class="metrica-caption">Productos</span>
                        <span class="modulo-icono text-info"><i class="bi bi-box"></i></span>
                    </div>
                    <div class="metrica-valor">{{ $totalProductos }}</div>
                    <div class="metrica-secundaria">{{ $totalClientes }} clientes</div>
                </div>
                @if ($tienePermiso('ver-stock-bajo'))
                    <a href="{{ route('reportes.stock') }}" class="tendencia d-inline-flex mt-3 text-decoration-none">
                        <i class="bi bi-exclamation-triangle"></i>{{ $productosStockBajo->count() }} con existencia baja
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>

@if ($tasasVigentes->isNotEmpty())
    <div class="tira-tasas mb-4">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="tira-caption"><i class="bi bi-currency-exchange me-1"></i>Tasas vigentes</span>
            @foreach ($tasasVigentes as $tasa)
                <span class="tasa-chip">
                    {{ $tasa->nombre ?: ucfirst($tasa->tipo) }}: <strong>{{ \App\Support\Moneda::n($tasa->monto) }}</strong>
                </span>
            @endforeach
        </div>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-graph-up"></i> Ingresos de los últimos 7 días</div>
            <div class="card-body">
                @if ($totalSemanaBs > 0)
                    <canvas id="chart7dias"></canvas>
                @else
                    <div class="text-center text-muted py-4">Sin ingresos en los últimos 7 días.</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart"></i> Métodos de pago (ingresos hoy)</div>
            <div class="card-body">
                @if (array_sum($metodosHoy) > 0)
                    <canvas id="chartMetodosHoy"></canvas>
                @else
                    <div class="text-center text-muted py-4">Sin ingresos registrados hoy.</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-receipt"></i> Últimas facturas</span>
                @if ($tienePermiso('ver-facturas'))
                    <a href="{{ route('facturas.index') }}" class="btn btn-sm btn-outline-secondary">Ver todas</a>
                @endif
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Correlativo</th>
                            <th class="text-start">Cliente</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ultimasFacturas as $f)
                            <tr>
                                <td>{{ $f->correlativo }}</td>
                                <td class="text-start">{{ $f->cliente->nombre ?? 'Contado' }}</td>
                                <td>Bs {{ \App\Support\Moneda::n($f->total_bs) }}</td>
                                <td>
                                    @if ($esAnulada($f))
                                        <span class="badge bg-danger">Anulada</span>
                                    @elseif ($esCredito($f))
                                        <span class="badge bg-warning">
                                            <i class="bi {{ $f->estado_credito === 'cancelado' ? 'bi-check-circle' : 'bi-hourglass-split' }}"></i>
                                            {{ $f->estado_credito === 'cancelado' ? 'Crédito cancelado' : 'Crédito pendiente' }}
                                        </span>
                                    @else
                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Contado</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-info btn-ver-factura" data-url="{{ route('facturas.recibo', $f->id) }}" title="Ver recibo">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">Sin facturas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @if ($tienePermiso('gestionar-creditos'))
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clock-history"></i> Créditos por cobrar</span>
                    <a href="{{ route('facturas.creditos') }}" class="btn btn-sm btn-outline-secondary">Cobrar</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Correlativo</th>
                                <th class="text-start">Cliente</th>
                                <th>Deuda</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($creditosPendientesLista as $c)
                                <tr>
                                    <td>{{ $c->correlativo }}</td>
                                    <td class="text-start">{{ $c->cliente->nombre ?? 'Sin dato' }}</td>
                                    <td>${{ \App\Support\Moneda::n($c->total_usd) }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('facturas.creditos') }}" class="btn btn-sm btn-success" title="Cobrar">
                                            <i class="bi bi-check-lg"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">No hay créditos pendientes.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($creditosPendientes > 0)
                    <div class="card-footer small text-muted">
                        Total pendiente: Bs {{ \App\Support\Moneda::n($totalCreditosPendientesBs) }} en {{ $creditosPendientes }} factura(s)
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

<div class="row g-3">
    @if ($tienePermiso('ver-stock-bajo'))
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-exclamation-triangle"></i> Existencia baja ({{ CatalogoService::UMBRAL_STOCK_BAJO }} o menos)</span>
                    <a href="{{ route('reportes.stock') }}" class="btn btn-sm btn-outline-secondary">Ver reporte</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Existencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($productosStockBajo as $p)
                                <tr>
                                    <td>{{ $p->nombre }}</td>
                                    <td><span class="badge bg-danger">{{ \App\Support\Moneda::n($p->stock_actual) }} {{ $p->unidad_medida ?? 'unidad' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted">Sin productos con existencia baja.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-trophy"></i> Más vendidos (últimos 30 días)</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Total Vendido</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($masVendidos as $p)
                            <tr>
                                <td>{{ $p->nombre }}</td>
                                <td>{{ $p->total }} uds</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted">Sin ventas aún.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('facturas.partials.ver-modal')
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tintaCarbon = '#2a4782';
    const tintasDona = ['#2a4782', '#5d7298', '#93a4c4', '#c6cfdf', '#4d545c', '#8f6f2e', '#246b42'];

    @if ($totalSemanaBs > 0)
    const porDia7 = @json($porDia7);
    new Chart(document.getElementById('chart7dias'), {
        type: 'line',
        data: {
            labels: Object.keys(porDia7),
            datasets: [{
                label: 'Ventas Bs',
                data: Object.values(porDia7),
                borderColor: tintaCarbon,
                backgroundColor: 'rgba(42, 71, 130, 0.10)',
                pointBackgroundColor: tintaCarbon,
                pointBorderColor: '#fff',
                pointRadius: 3,
                fill: true,
                tension: 0.3,
            }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { ticks: { callback: (v) => 'Bs ' + Number(v).toLocaleString('es-VE') } },
                x: { grid: { display: false } },
            },
        },
    });
    @endif

    @if (array_sum($metodosHoy) > 0)
    const metodosHoy = @json($metodosHoy);
    const nombresMetodo = @json($metodosPago);
    const filas = Object.entries(metodosHoy).filter(([, v]) => v > 0);
    new Chart(document.getElementById('chartMetodosHoy'), {
        type: 'doughnut',
        data: {
            labels: filas.map(([k]) => nombresMetodo[k] || k),
            datasets: [{
                data: filas.map(([, v]) => v),
                backgroundColor: filas.map((_, i) => tintasDona[i % tintasDona.length]),
                borderColor: '#ffffff',
                borderWidth: 2,
            }],
        },
        options: {
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10 } },
                tooltip: { callbacks: { label: (ctx) => ' Bs ' + Number(ctx.parsed).toLocaleString('es-VE') } },
            },
        },
    });
    @endif
});
</script>
@endpush