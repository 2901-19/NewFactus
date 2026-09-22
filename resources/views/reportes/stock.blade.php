@extends('layouts.app')
@section('titulo', 'Existencia Baja')
@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Productos con Existencia Baja (10 uds o menos)</h2>
    @if (Auth::user()->hasPermiso('actualizar-inventarios'))
    <a href="{{ route('productos.ajustar-inventario') }}" class="btn btn-info text-white">
        <i class="bi bi-box"></i> Actualizar Inventario
    </a>
    @endif
</div>
<div class="table-responsive">
    <table id="stockTable" class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th class="text-start">Producto</th>
                <th>Unidad</th>
                <th>Existencia</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($productos as $p)
            <tr>
                <td class="text-start">{{ $p->nombre }}</td>
                <td>{{ $p->unidad_medida ?? 'unidad' }}</td>
                <td>
                    <span class="badge bg-danger">
                        {{ \App\Support\Moneda::n($p->stock_actual) }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    $('#stockTable').DataTable({
        language: window.DataTableSpanish,
        order: [[2, 'asc']],
        pageLength: 25,
    });
});
</script>
@endpush
