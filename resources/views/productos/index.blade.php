@extends('layouts.app')
@section('titulo', 'Productos')
@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Productos</h2>
    <div>
        @if (Auth::user()->hasPermiso('actualizar-precios'))
        <a href="{{ route('productos.ajustar-precios') }}" class="btn btn-warning me-1">
            <i class="bi bi-currency-dollar"></i> Actualizar Precios
        </a>
        @endif
        @if (Auth::user()->hasPermiso('actualizar-inventarios'))
        <a href="{{ route('productos.ajustar-inventario') }}" class="btn btn-info me-1 text-white">
            <i class="bi bi-box"></i> Actualizar Inventario
        </a>
        @endif
        <a href="{{ route('productos.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nuevo Producto
        </a>
    </div>
</div>
<div class="table-responsive">
    <table id="dt-productos" class="table table-bordered table-striped thumbs-table">
        <thead class="table-dark">
            <tr>
                <th class="text-start">Nombre</th>
                <th>Ref.</th>
                <th>Categoría</th>
                <th>Existencia</th>
                <th>Precios (presentaciones)</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if ($.fn.DataTable) {
        $('#dt-productos').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '/productos/data',
                data: (d) => ({
                    draw: d.draw,
                    start: d.start !== undefined ? d.start : 0,
                    length: d.length,
                    ['search[value]']: d.search ? d.search.value : '',
                    ['order[0][column]']: d.order && d.order[0] ? d.order[0].column : 0,
                    ['order[0][dir]']: d.order && d.order[0] ? d.order[0].dir : 'asc',
                }),
            },
            columns: [
                { data: 'nombre', className: 'text-start' },
                { data: 'ref' },
                { data: 'categoria' },
                { data: 'existencia' },
                { data: 'precios' },
                { data: 'estado' },
                { data: 'acciones', orderable: false },
            ],
            columnDefs: [{ orderable: false, targets: -1 }],
            order: [[0, 'asc']],
        });
    }
    $(document).on('click', '.btn-delete', function () {
        const btn = $(this);
        Swal.fire({
            title: '¿Desactivar producto?',
            text: 'El producto quedará inactivo, pero podrás restaurarlo después.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Sí, desactivar',
            cancelButtonText: 'Cancelar',
        }).then((r) => { if (r.isConfirmed) $.post(btn.data('url'), { _token: csrf, _method: 'DELETE' }).then(() => location.reload()); });
    });
    $(document).on('click', '.btn-restore', function () {
        const btn = $(this);
        Swal.fire({
            title: '¿Activar producto?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            confirmButtonText: 'Sí, activar',
            cancelButtonText: 'Cancelar',
        }).then((r) => { if (r.isConfirmed) $.post(btn.data('url'), { _token: csrf }).then(() => location.reload()); });
    });
});
</script>
@endpush
