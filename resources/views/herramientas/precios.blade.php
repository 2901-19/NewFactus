@extends('layouts.app')
@section('titulo', 'Lista de Precios')
@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">{{ $productos->count() }} productos disponibles{{ $categoriaId ? ' en la categoría seleccionada' : '' }}.</p>
    <div class="d-flex gap-2 align-items-center">
        <select id="filtroCategoria" class="form-select form-select-sm" style="width:auto">
            <option value="">Todas las categorías</option>
            @foreach ($categorias as $c)
            <option value="{{ $c->id }}" @selected($categoriaId == $c->id)>{{ $c->nombre }}</option>
            @endforeach
        </select>
        <a href="{{ route('herramientas.precios.pdf', ['categoria_id' => $categoriaId]) }}" class="btn btn-danger">
            <i class="bi bi-filetype-pdf"></i> Descargar PDF
        </a>
        <a href="{{ route('herramientas.precios', ['export' => 'json', 'categoria_id' => $categoriaId]) }}" class="btn btn-success">
            <i class="bi bi-filetype-json"></i> Descargar JSON
        </a>
    </div>
</div>
<div class="table-responsive">
    <table id="preciosTable" class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th class="text-start">Producto</th>
                <th>Presentación</th>
                <th>Precio Bs</th>
                <th>Precio USD</th>
                <th>Impuesto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($productos as $p)
            @foreach ($p->presentaciones->where('activa', true) as $pr)
            @php $tasaDisponible = $tasas->has($pr->fuente_tasa); @endphp
            <tr>
                <td class="text-start">{{ $p->nombre }}</td>
                <td>{{ $pr->nombre }}</td>
                <td>
                    @if ($tasaDisponible)
                        Bs {{ number_format($pr->precio_usd * $tasas[$pr->fuente_tasa], 2) }}
                    @else
                        <span class="badge bg-danger" title="Configure la tasa '{{ $pr->fuente_tasa }}' en Tasas de Cambio">Sin tasa</span>
                    @endif
                </td>
                <td>${{ number_format($pr->precio_usd, 2) }}</td>
                <td>{{ $p->impuesto?->nombre ?? 'No' }}</td>
            </tr>
            @endforeach
            @endforeach
        </tbody>
    </table>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    $('#preciosTable').DataTable({
        language: window.DataTableSpanish,
        order: [[0, 'asc']],
        pageLength: 25,
    });
    $('#filtroCategoria').on('change', function () {
        const value = $(this).val();
        const url = new URL(window.location.href);
        if (value) {
            url.searchParams.set('categoria_id', value);
        } else {
            url.searchParams.delete('categoria_id');
        }
        window.location.href = url.toString();
    });
});
</script>
@endpush
