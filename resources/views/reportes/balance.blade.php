@extends('layouts.app')
@section('titulo', 'Balance Mensual')
@section('contenido')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-auto">
                <label class="form-label small text-muted fw-semibold">Año</label>
                <select name="anio" class="form-select form-select-sm">
                    @for ($y = now()->year; $y >= now()->year - 5; $y--)
                        <option value="{{ $y }}" {{ $anio == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2 border-top pt-3 mt-2">
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-sm btn-primary">Ver</button>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('reportes.balance', ['anio' => $anio, 'export' => 'pdf']) }}" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-bar-chart"></i> Ingresos Bs por mes ({{ $anio }})</div>
    <div class="card-body">
        <canvas id="chartBalance" height="80"></canvas>
    </div>
</div>

@php
    $totalCantidad = collect($mensual)->sum('cantidad');
    $totalBs = collect($mensual)->sum('total_bs');
    $totalUsd = collect($mensual)->sum('total_usd');
@endphp
<div class="table-responsive">
    <table id="balanceTable" class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th class="text-start">Mes</th>
                <th>Facturas</th>
                <th>Total Bs</th>
                <th>Total USD</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($meses as $num => $nombre)
                @php $m = $mensual[$num] ?? null; @endphp
                <tr>
                    <td class="text-start">{{ $nombre }}</td>
                    <td>{{ $m['cantidad'] ?? 0 }}</td>
                    <td>Bs {{ \App\Support\Moneda::n($m['total_bs'] ?? 0) }}</td>
                    <td>${{ \App\Support\Moneda::n($m['total_usd'] ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="fw-bold">
            <tr>
                <td>Total</td>
                <td>{{ $totalCantidad }}</td>
                <td>Bs {{ \App\Support\Moneda::n($totalBs) }}</td>
                <td>${{ \App\Support\Moneda::n($totalUsd) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('chartBalance'), {
        type: 'bar',
        data: {
            labels: @json(array_values($meses)),
            datasets: [{ label: 'Ingresos Bs', data: @json($serieBs), backgroundColor: '#0d6efd' }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { ticks: { callback: (v) => 'Bs ' + v.toLocaleString('es-VE') } } },
        },
    });

    $('#balanceTable').DataTable({
        language: window.DataTableSpanish,
        order: [[0, 'asc']],
        pageLength: 12,
        paging: false,
        info: false,
        searching: false,
    });
});
</script>
@endpush
