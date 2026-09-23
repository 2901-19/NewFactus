@extends('layouts.app')
@section('titulo', 'Facturas')
@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Todas las Facturas</h2>
</div>
<div class="table-responsive">
    <table id="facturasTable" class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Correlativo</th>
                <th class="text-start">Cliente</th>
                <th class="text-start">Usuario</th>
                <th>Total Bs</th>
                <th>Total USD</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($facturas as $f)
            <tr class="{{ $f->estado === 'anulada' ? 'table-danger text-muted' : '' }}">
                <td>{{ $f->correlativo }}</td>
                <td class="text-start">{{ $f->cliente->nombre ?? 'Contado' }}</td>
                <td class="text-start">{{ $f->user->usuario ?? 'Sin dato' }}</td>
                <td>Bs {{ \App\Support\Moneda::n($f->total_bs) }}</td>
                <td>${{ \App\Support\Moneda::n($f->total_usd) }}</td>
                <td>{{ $f->fecha_venta }}</td>
                <td>
                    @if ($f->estado === 'anulada')
                        <span class="badge bg-danger">Anulada</span>
                    @elseif ($f->estado === 'credito')
                        <span class="badge bg-warning text-dark">
                            Crédito {{ $f->estado_credito === 'cancelado' ? '(Cancelado)' : '(Pendiente)' }}
                        </span>
                    @else
                        <span class="badge bg-success">Contado</span>
                    @endif
                </td>
                <td>
                    <button class="btn btn-sm btn-info btn-ver-factura" data-url="{{ route('facturas.recibo', $f->id) }}">
                        <i class="bi bi-eye"></i>
                    </button>
                    @if ($f->estado !== 'anulada' && auth()->user()->hasPermiso('anular-facturas'))
                        <button class="btn btn-sm btn-danger btn-anular" data-url="{{ route('facturas.anular', $f->id) }}" data-correlativo="{{ $f->correlativo }}">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@include('facturas.partials.ver-modal')
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    $('#facturasTable').DataTable({
        language: window.DataTableSpanish,
        order: [[5, 'desc']],
        pageLength: 25,
    });
    $(document).on('click', '.btn-anular', function () {
        const btn = $(this);
        Swal.fire({
            title: '¿Anular factura?',
            text: 'Se restaurará la existencia de todos los productos.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar',
        }).then((r) => { if (r.isConfirmed) $.post(btn.data('url'), { _token: csrf }).then(() => location.reload()); });
    });
});
</script>
@endpush
