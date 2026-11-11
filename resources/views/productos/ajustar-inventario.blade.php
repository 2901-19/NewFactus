@extends('layouts.app')
@section('titulo', 'Actualizar Inventario')
@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Actualizar Inventario</h2>
    <a href="{{ route('productos.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver a Productos
    </a>
</div>
<div class="card">
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-12 col-md-4">
                <label class="form-label small text-muted fw-semibold">Categoría</label>
                <select id="filtro-categoria" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach ($categorias as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table id="tabla-inventario" class="table table-bordered table-striped" style="width:100%">
                <thead class="table-dark">
                    <tr>
                        <th class="text-start">Producto</th>
                        <th>Categoría</th>
                        <th>Existencia Actual</th>
                        <th>Cant. Ajustar</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dataUrl = '{{ route('productos.ajustar-inventario.data') }}';

    const tabla = $('#tabla-inventario').DataTable({
        serverSide: true,
        processing: true,
        ajax: {
            url: dataUrl,
            data: function (d) {
                d.categoria_id = $('#filtro-categoria').val();
            }
        },
        columns: [
            { data: 'nombre' },
            { data: 'categoria' },
            {
                data: 'stock_actual',
                render: function (data, type, row) {
                    if (!row.controla_inventario) {
                        return '<span class="badge bg-secondary">No lleva inventario</span>';
                    }
                    return '<span class="fw-bold stock-valor" data-id="' + row.id + '" data-unidad="' + row.unidad_medida + '">'
                        + fmtMoneda(data) + ' ' + row.unidad_medida + '</span>';
                }
            },
            {
                data: null,
                orderable: false,
                render: function (data, type, row) {
                    return '<input type="number" min="0.01" step="0.001" class="form-control form-control-sm cantidad-ajuste" data-id="' + row.id + '" placeholder="0">';
                }
            },
            {
                data: null,
                orderable: false,
                render: function (data, type, row) {
                    return '<button type="button" class="btn btn-sm btn-success me-1 btn-ajuste" data-id="' + row.id + '" data-op="+"><i class="bi bi-plus-lg"></i></button>'
                        + '<button type="button" class="btn btn-sm btn-danger btn-ajuste" data-id="' + row.id + '" data-op="-"><i class="bi bi-dash-lg"></i></button>';
                }
            }
        ],
        language: window.DataTableSpanish,
        pageLength: 25,
    });

    $('#filtro-categoria').on('change', function () {
        tabla.ajax.reload();
    });

    $(document).on('click', '.btn-ajuste', function () {
        const btn = $(this);
        const id = btn.data('id');
        const operacion = btn.data('op');
        const input = $('.cantidad-ajuste[data-id="' + id + '"]');
        const cantidad = parseFloat(input.val());

        if (!cantidad || cantidad < 0.01) return;

        btn.prop('disabled', true);
        fetch('/productos/' + id + '/ajustar-inventario', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ cantidad: cantidad, operacion: operacion })
        })
        .then(r => r.json())
        .then(data => {
            btn.prop('disabled', false);
            if (data.success) {
                const valor = $('.stock-valor[data-id="' + id + '"]');
                valor.text(fmtMoneda(data.stock_actual) + ' ' + valor.data('unidad'));
                input.val('');
                Swal.fire({ icon: 'success', title: 'Actualizado', text: data.message, timer: 2000, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        })
        .catch(() => {
            btn.prop('disabled', false);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión' });
        });
    });
});
</script>
@endpush
