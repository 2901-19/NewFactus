@extends('layouts.app')
@section('titulo', 'Lista de Precios')
@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">{{ $productos->count() }} productos disponibles{{ $categoriaId ? ' en la categoría seleccionada' : '' }}{{ $presentacionesFiltro ? ' — presentación: '.implode(', ', $presentacionesFiltro) : '' }}.</p>
    <div class="d-flex gap-2 align-items-center">
        <select id="filtroCategoria" class="form-select form-select-sm" style="width:auto">
            <option value="">Todas las categorías</option>
            @foreach ($categorias as $c)
            <option value="{{ $c->id }}" @selected($categoriaId == $c->id)>{{ $c->nombre }}</option>
            @endforeach
        </select>
        <a href="{{ route('herramientas.precios.pdf', array_merge($categoriaId ? ['categoria_id' => $categoriaId] : [], $presentacionesFiltro ? ['presentacion' => $presentacionesFiltro] : [])) }}" class="btn btn-danger">
            <i class="bi bi-filetype-pdf"></i> Descargar PDF
        </a>
        <a href="{{ route('herramientas.precios', array_merge(['export' => 'json'], $categoriaId ? ['categoria_id' => $categoriaId] : [], $presentacionesFiltro ? ['presentacion' => $presentacionesFiltro] : [])) }}" class="btn btn-success">
            <i class="bi bi-filetype-json"></i> Descargar JSON
        </a>
    </div>
</div>
<div class="d-flex flex-wrap align-items-center gap-1 mb-3">
    <span class="text-muted small fw-semibold me-1">Presentación:</span>
    @php $paramsCategoria = $categoriaId ? ['categoria_id' => $categoriaId] : []; @endphp
    <a href="{{ route('herramientas.precios', $paramsCategoria) }}" class="btn btn-sm {{ $presentacionesFiltro ? 'btn-outline-secondary' : 'btn-primary' }}">Todas</a>
    @foreach ($presentacionesOpciones as $opcion)
    @php
        $seleccionada = in_array($opcion, $presentacionesFiltro, true);
        $nuevas = $seleccionada
            ? array_values(array_diff($presentacionesFiltro, [$opcion]))
            : array_values(array_merge($presentacionesFiltro, [$opcion]));
        $urlPill = route('herramientas.precios', array_merge($paramsCategoria, $nuevas ? ['presentacion' => $nuevas] : []));
    @endphp
    <a href="{{ $urlPill }}" class="btn btn-sm {{ $seleccionada ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $opcion }}</a>
    @endforeach
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
                <th>Imprimir</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($productos as $p)
            @foreach ($p->presentaciones->where('activa', true)->when($presentacionesFiltro, fn ($col) => $col->whereIn('nombre', $presentacionesFiltro)) as $pr)
            @php $tasaDisponible = $tasas->has($pr->fuente_tasa); @endphp
            <tr>
                <td class="text-start">{{ $p->nombre }}</td>
                <td>{{ $pr->nombre }}</td>
                <td>
                    @if ($tasaDisponible)
                        Bs {{ \App\Support\Moneda::n($pr->precio_usd * $tasas[$pr->fuente_tasa]) }}
                    @else
                        <span class="badge bg-danger" title="Configure la tasa '{{ $pr->fuente_tasa }}' en Tasas de Cambio">Sin tasa</span>
                    @endif
                </td>
                <td>${{ \App\Support\Moneda::n($pr->precio_usd) }}</td>
                <td>{{ $p->impuesto?->nombre ?? 'No' }}</td>
                <td class="text-center">
                    @if ($tasaDisponible)
                    <a href="{{ route('herramientas.precios.imprimir', ['producto_id' => $p->id, 'presentacion_id' => $pr->id]) }}" class="btn btn-sm btn-outline-primary" title="Imprimir etiqueta de precio">
                        <i class="bi bi-printer"></i>
                    </a>
                    @else
                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Configure la tasa '{{ $pr->fuente_tasa }}' para imprimir la etiqueta">
                        <i class="bi bi-printer"></i>
                    </button>
                    @endif
                </td>
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
