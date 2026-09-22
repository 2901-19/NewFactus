@extends('layouts.app')
@section('titulo', 'Créditos')
@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Facturas a Crédito</h2>
</div>
<div class="table-responsive">
    <table id="creditosTable" class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Correlativo</th>
                <th class="text-start">Cliente</th>
                <th>Deuda US$</th>
                <th>A pagar hoy (Bs)</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($facturas as $f)
            <tr class="{{ $f->estado_credito === 'cancelado' ? 'table-success' : '' }}">
                <td>{{ $f->correlativo }}</td>
                <td class="text-start">{{ $f->cliente->nombre ?? 'Sin dato' }}</td>
                <td>${{ \App\Support\Moneda::n($f->total_usd) }}</td>
                <td>
                    @if ($f->estado_credito === 'cancelado')
                        <span class="text-muted" title="Pagado: Bs {{ \App\Support\Moneda::n($f->pago_bs) }} el {{ $f->fecha_pago?->format('d/m/Y') ?? '' }}">
                            <i class="bi bi-check2-circle"></i> Bs {{ \App\Support\Moneda::n($f->pago_bs) }}
                        </span>
                    @elseif ($tasaVigente)
                        Bs {{ \App\Support\Moneda::n($f->total_usd * $tasaVigente) }}
                    @else
                        <span class="text-muted" title="Sin tasa de referencia configurada">—</span>
                    @endif
                </td>
                <td>{{ $f->fecha_venta }}</td>
                <td>
                    @if ($f->estado_credito === 'pendiente')
                        <span class="badge bg-warning text-dark">Pendiente</span>
                    @else
                        <span class="badge bg-success">Cancelado</span>
                    @endif
                </td>
                <td>
                    <button class="btn btn-sm btn-info btn-ver-factura" data-url="{{ route('facturas.recibo', $f->id) }}">
                        <i class="bi bi-eye"></i>
                    </button>
                    @if ($f->estado_credito === 'pendiente')
                        @if ($tasaVigente)
                            <button class="btn btn-sm btn-success btn-abrir-cobro" data-id="{{ $f->id }}" data-url="{{ route('facturas.pagar-credito', $f->id) }}" data-correlativo="{{ $f->correlativo }}" data-usd="{{ \App\Support\Moneda::n($f->total_usd) }}" data-bs="{{ \App\Support\Moneda::n($f->total_usd * $tasaVigente) }}">
                                <i class="bi bi-check-lg"></i> Cobrar
                            </button>
                        @else
                            <button class="btn btn-sm btn-secondary" disabled title="No hay tasa de referencia activa. Configúrala en Tasas de Cambio para poder cobrar.">
                                <i class="bi bi-check-lg"></i> Cobrar
                            </button>
                        @endif
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="text-muted small">Total: {{ $facturas->count() }} facturas</div>
@include('facturas.partials.ver-modal')
@endsection

{{-- Modal de cobro --}}
<div class="modal fade" id="cobroModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form id="cobroForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Cobrar crédito <span id="cobroCorrelativo"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-3">
                        <strong>Deuda US$:</strong> <span id="cobroUsd"></span><br>
                        <strong>A pagar hoy (Bs):</strong> <span id="cobroBs"></span>
                    </p>
                    <div class="mb-2">
                        <label class="form-label small">Método de pago *</label>
                        <select name="metodo_pago" id="cobroMetodo" class="form-select form-select-sm" required>
                            <option value="" disabled selected>Seleccione</option>
                            <option value="efectivo">Efectivo</option>
                            <option value="punto">Punto de Venta</option>
                            <option value="biopago">Biopago</option>
                            <option value="divisas">Divisas</option>
                            <option value="pago_movil">Pago Móvil</option>
                            <option value="transferencia">Transferencia</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-lg"></i> Confirmar cobro
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    $('#creditosTable').DataTable({
        language: window.DataTableSpanish,
        order: [[4, 'desc']],
        pageLength: 25,
    });
    $(document).on('click', '.btn-abrir-cobro', function () {
        const btn = $(this);
        $('#cobroForm').attr('action', btn.data('url'));
        $('#cobroCorrelativo').text('N° ' + btn.data('correlativo'));
        $('#cobroUsd').text('US$ ' + btn.data('usd'));
        $('#cobroBs').text(btn.data('bs') !== '' ? 'Bs ' + btn.data('bs') : '—');
        $('#cobroMetodo').val('');
        new bootstrap.Modal(document.getElementById('cobroModal')).show();
    });
});
</script>
@endpush
