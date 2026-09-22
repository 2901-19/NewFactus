@extends('layouts.app')
@section('titulo', 'Estadísticas de Ventas')
@section('contenido')

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            @include('reportes.partials.selector-rango')
            <div class="col-6 col-md-4 col-xl">
                <label class="form-label small text-muted fw-semibold">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="{{ $desde }}">
            </div>
            <div class="col-6 col-md-4 col-xl">
                <label class="form-label small text-muted fw-semibold">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="{{ $hasta }}">
            </div>
            <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2 border-top pt-3 mt-2">
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                    <a href="{{ route('reportes.estadisticas') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
                </div>
            </div>
        </form>
    </div>
</div>

@php
    $kpiCards = [
        ['label' => 'Ingresos Bs', 'valor' => $kpis['total_bs'], 'formato' => 'moneda'],
        ['label' => 'Ingresos USD', 'valor' => $kpis['total_usd'], 'formato' => 'usd'],
        ['label' => 'Facturas', 'valor' => $kpis['cantidad'], 'formato' => 'entero'],
        ['label' => 'Ticket promedio', 'valor' => $kpis['ticket_promedio'], 'formato' => 'moneda'],
    ];
@endphp
<div class="row g-2 mb-3">
    @foreach ($kpiCards as $card)
        @include('reportes.partials.kpi-card', $card)
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-graph-up"></i> Ingresos Bs por día</div>
            <div class="card-body">
                <canvas id="chartVentasDiarias" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart"></i> Métodos de pago</div>
            <div class="card-body">
                <canvas id="chartMetodos" height="120"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-layers"></i> Ingresos por presentación
                <div class="small text-muted fw-normal">
                    Ingresos según la presentación vendida (Unidad, Mayor, bultos...). Agrupado por {{ $agrupadoPor }} del período
                </div>
            </div>
            <div class="card-body">
                @if (count($semanaLabels) > 0)
                <div class="d-flex flex-wrap gap-2 mb-2">
                    @foreach ($resumenes as $nombre => $res)
                    <span class="badge rounded-pill text-white" style="background-color:{{ $colores[$nombre] }}">{{ $nombre }} {{ $res['pct'] }}%</span>
                    @endforeach
                </div>
                <canvas id="chartPresentaciones" height="120"></canvas>
                <div class="row g-2 mt-2 text-center">
                    @foreach ($resumenes as $nombre => $res)
                    <div class="col-6 col-md-4">
                        <div class="border rounded p-2" style="background-color:{{ $colores[$nombre] }}18; border-color:{{ $colores[$nombre] }}">
                            <div class="small text-muted">{{ $nombre }}</div>
                            <div class="fw-bold">Bs {{ \App\Support\Moneda::n($res['bs']) }}</div>
                            <div class="small">{{ \App\Support\Moneda::n($res['unidades']) }} unidades</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center text-muted py-4">Sin ingresos en el período.</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart"></i> Top 10 productos por ingresos</div>
            <div class="card-body">
                <canvas id="chartTopProductos" height="120"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-trophy"></i> Top clientes</div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr><th class="text-start">Cliente</th><th>Compras</th><th>Total Bs</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($topClientes as $c)
                        <tr>
                            <td class="text-start">{{ $c['nombre'] }}</td>
                            <td>{{ $c['facturas'] }}</td>
                            <td>Bs {{ \App\Support\Moneda::n($c['total_bs']) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted">Sin datos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100 border-danger">
            <div class="card-header"><i class="bi bi-credit-card"></i> Créditos pendientes</div>
            <div class="card-body d-flex flex-column justify-content-center text-center">
                <div class="fs-4 fw-bold text-danger">Bs {{ \App\Support\Moneda::n($creditos->total_bs) }}</div>
                <div class="text-muted">{{ $creditos->cantidad }} facturas por cobrar</div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
@include('reportes.partials.filtro-fechas')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const porDia = @json($porDia);

    new Chart(document.getElementById('chartVentasDiarias'), {
        type: 'line',
        data: {
            labels: Object.keys(porDia),
            datasets: [{
                label: 'Ingresos Bs',
                data: Object.values(porDia),
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.12)',
                fill: true,
                tension: 0.3,
            }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { ticks: { callback: (v) => 'Bs ' + v.toLocaleString('es-VE') } } },
        },
    });

    const nombresMetodo = @json($nombresMetodo);
    const porMetodo = @json($porMetodo);
    const metodos = Object.entries(porMetodo).filter(([, v]) => v > 0);

    new Chart(document.getElementById('chartMetodos'), {
        type: 'doughnut',
        data: {
            labels: metodos.map(([k]) => nombresMetodo[k] || k),
            datasets: [{ data: metodos.map(([, v]) => v), borderWidth: 1 }],
        },
    });

    @if (count($semanaLabels) > 0)
    const series = @json($seriesPresentaciones);
    const colores = @json($colores);
    const unidadesSeries = @json($unidadesSeries);
    const nombresPresentaciones = Object.keys(series);

    new Chart(document.getElementById('chartPresentaciones'), {
        type: 'bar',
        data: {
            labels: @json($semanaLabels),
            datasets: nombresPresentaciones.map((nombre) => ({
                label: nombre,
                data: series[nombre],
                backgroundColor: colores[nombre] || '#6f42c1',
                stack: 'ventas',
            })),
        },
        options: {
            scales: {
                x: { stacked: true },
                y: { stacked: true, ticks: { callback: (v) => 'Bs ' + v.toLocaleString('es-VE') } },
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const unidades = unidadesSeries[ctx.dataset.label] || [];
                            return ' ' + ctx.dataset.label + ': Bs ' + ctx.parsed.y.toLocaleString('es-VE') + ' · ' + (unidades[ctx.dataIndex] ?? 0) + ' unidades';
                        },
                    },
                },
            },
        },
    });
    @endif

    const topProductos = @json($topProductos);

    new Chart(document.getElementById('chartTopProductos'), {
        type: 'bar',
        data: {
            labels: topProductos.map((p) => p.nombre),
            datasets: [{ label: 'Ingresos Bs', data: topProductos.map((p) => p.ingreso_bs), backgroundColor: '#0dcaf0' }],
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
        },
    });
});
</script>
@endpush
