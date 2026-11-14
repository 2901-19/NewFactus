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
            {{-- Filas construidas por DataTables a partir de /facturas/data (server-side) --}}
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
        serverSide: true,
        processing: true,
        ajax: '{{ route('facturas.data') }}',
        order: [[5, 'desc']],
        pageLength: 25,
        columns: [
            { data: 'correlativo' },
            { data: 'cliente', className: 'text-start' },
            { data: 'usuario', className: 'text-start' },
            { data: 'total_bs' },
            { data: 'total_usd' },
            { data: 'fecha' },
            { data: 'estado' },
            { data: 'acciones', orderable: false, searchable: false },
        ],
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
