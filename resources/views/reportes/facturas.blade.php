@extends('layouts.app')
@section('titulo', 'Reporte de Ventas')
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
            <div class="col-6 col-md-4 col-xl">
                <label class="form-label small text-muted fw-semibold">Método de pago</label>
                <select name="metodo_pago" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach ($nombresMetodo as $valor => $nombre)
                        <option value="{{ $valor }}" {{ request('metodo_pago') == $valor ? 'selected' : '' }}>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <label class="form-label small text-muted fw-semibold">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="contado" {{ request('estado') == 'contado' ? 'selected' : '' }}>Contado</option>
                    <option value="credito" {{ request('estado') == 'credito' ? 'selected' : '' }}>Crédito</option>
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <label class="form-label small text-muted fw-semibold">Cliente</label>
                <select name="cliente_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach ($clientes as $c)
                        <option value="{{ $c->id }}" {{ request('cliente_id') == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2 border-top pt-3 mt-2">
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                    <a href="{{ route('reportes.facturas') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('reportes.facturas', array_merge(request()->query(), ['export' => 'pdf'])) }}" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                    <a href="{{ route('reportes.facturas', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> CSV</a>
                </div>
            </div>
        </form>
    </div>
</div>

@php
    $kpiCards = [
        ['label' => 'Ingresos Bs', 'valor' => $kpis['total_bs'], 'formato' => 'moneda'],
        ['label' => 'Ingresos USD', 'valor' => $kpis['total_usd'], 'formato' => 'usd'],
        ['label' => 'IVA cobrado', 'valor' => $kpis['iva_bs'], 'formato' => 'moneda'],
        ['label' => 'Facturas', 'valor' => $kpis['cantidad'], 'formato' => 'entero'],
        ['label' => 'Ticket promedio', 'valor' => $kpis['ticket_promedio'], 'formato' => 'moneda'],
    ];
@endphp
<div class="row g-2 mb-3">
    @foreach ($kpiCards as $card)
        @include('reportes.partials.kpi-card', $card)
    @endforeach
</div>

@php $totalDesglose = array_sum($desglose); @endphp
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-wallet2"></i> Ingresos por método de pago</div>
    <div class="card-body">
        <div class="row g-2">
            @foreach ($desglose as $metodo => $monto)
                @if ($monto > 0)
                <div class="col-md-4 col-lg-3">
                    <div class="border rounded p-2">
                        <div class="small text-muted">{{ $nombresMetodo[$metodo] ?? $metodo }}</div>
                        <div class="fw-bold">Bs {{ \App\Support\Moneda::n($monto, 2) }}
                            <small class="text-muted">({{ $totalDesglose > 0 ? round(($monto / $totalDesglose) * 100, 1) : 0 }}%)</small>
                        </div>
                    </div>
                </div>
                @endif
            @endforeach
            @if ($totalDesglose == 0)
                <div class="text-muted small">Sin ingresos en el período.</div>
            @endif
        </div>
    </div>
</div>

<div class="table-responsive">
    <table id="reporteFacturasTable" class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Correlativo</th>
                <th class="text-start">Cliente</th>
                <th class="text-start">Método</th>
                <th class="text-start">Vendedor</th>
                <th>Total Bs</th>
                <th>Impuesto</th>
                <th>Total USD</th>
                <th>Fecha</th>
                <th>Tipo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($facturas as $f)
            <tr>
                <td><a href="#" class="btn-ver-factura" data-url="{{ route('facturas.recibo', $f->id) }}">{{ $f->correlativo }}</a></td>
                <td class="text-start">{{ $f->cliente->nombre ?? 'Contado' }}</td>
                <td class="text-start">{{ $nombresMetodo[$f->metodo_pago] ?? $f->metodo_pago }}</td>
                <td class="text-start">{{ $f->user->name ?? '—' }}</td>
                <td>Bs {{ \App\Support\Moneda::n($f->total_bs) }}</td>
                <td>Bs {{ \App\Support\Moneda::n($f->iva_bs) }}</td>
                <td>${{ \App\Support\Moneda::n($f->total_usd) }}</td>
                <td>{{ $f->fecha_venta?->format('d/m/Y') }}</td>
                <td>
                    @if ($f->estado === 'credito')
                        <span class="badge bg-warning text-dark">Crédito</span>
                    @else
                        <span class="badge bg-success">Contado</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center text-muted">No hay facturas en el período.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="fw-bold">
                <td colspan="4" class="text-end">Totales:</td>
                <td>Bs {{ \App\Support\Moneda::n($kpis['total_bs']) }}</td>
                <td>Bs {{ \App\Support\Moneda::n($kpis['iva_bs']) }}</td>
                <td>${{ \App\Support\Moneda::n($kpis['total_usd']) }}</td>
                <td colspan="2">{{ \App\Support\Moneda::n($kpis['cantidad']) }} facturas</td>
            </tr>
        </tfoot>
    </table>
</div>
@endsection
@include('facturas.partials.ver-modal')
@push('scripts')
@include('reportes.partials.filtro-fechas')
<script>
document.addEventListener('DOMContentLoaded', function () {
    @if ($facturas->count() > 0)
    $('#reporteFacturasTable').DataTable({
        language: window.DataTableSpanish,
        order: [[7, 'desc']],
        pageLength: 25,
    });
    @endif
});
</script>
@endpush
