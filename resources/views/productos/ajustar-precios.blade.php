@extends('layouts.app')
@section('titulo', 'Actualizar Precios')
@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Actualizar Precios</h2>
    <a href="{{ route('productos.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver a Productos
    </a>
</div>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabla-precios" class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th class="text-start">Producto</th>
                        <th>Categoría</th>
                        <th style="width:110px">Costo USD</th>
                        <th class="text-start">Presentaciones (margen)</th>
                        <th style="width:90px">Acción</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
window.__tasasMapa = @json($tasas);
function precioRow(id, costoUsd, presentaciones) {
    return {
        costo_usd: costoUsd || 0,
        presentaciones: presentaciones || [],
        cargando: false,
        tasaDe(tipo) {
            return parseFloat((window.__tasasMapa || {})[tipo] || 0) || 0;
        },
        precioPres(i) {
            const p = this.presentaciones[i] || {};
            const base = this.r2((this.costo_usd || 0) * (1 + (parseFloat(p.margen) || 0) / 100));
            return this.r2(base * (parseFloat(p.factor_conversion) || 1));
        },
        r2(n) { return Math.round((n + Number.EPSILON) * 100) / 100; },
        guardar() {
            this.cargando = true;
            fetch('/productos/' + id + '/ajustar-precio', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({
                    costo_usd: this.costo_usd,
                    presentaciones: this.presentaciones.map(p => ({ id: p.id, margen: p.margen }))
                })
            })
            .then(r => r.json())
            .then(data => {
                this.cargando = false;
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Actualizado', text: data.message, timer: 2000, showConfirmButton: false });
                    (data.presentaciones || []).forEach(upd => {
                        const idx = this.presentaciones.findIndex(p => String(p.id) === String(upd.id));
                        if (idx >= 0) this.presentaciones[idx].margen = upd.margen;
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                }
            })
            .catch(() => {
                this.cargando = false;
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión' });
            });
        }
    };
}

function cargaTemplates() {
    return `<template x-for="(pres, i) in presentaciones" :key="pres.id">
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="small text-nowrap" style="min-width:110px" x-text="pres.nombre"></span>
            <input type="number" step="0.01" min="0" class="form-control form-control-sm text-center" style="width:90px" x-model.number="pres.margen">
            <span class="small text-muted text-nowrap">factor: <span x-text="pres.factor_conversion"></span></span>
            <span class="small fw-bold text-nowrap" x-text="tasaDe(pres.fuente_tasa) > 0 ? '$' + precioPres(i).toFixed(2) + ' / Bs ' + (precioPres(i) * tasaDe(pres.fuente_tasa)).toFixed(2) : '$' + precioPres(i).toFixed(2) + ' / Bs sin tasa'"></span>
        </div>
    </template>`;
}

document.addEventListener('DOMContentLoaded', function () {
    if ($.fn.DataTable) {
        $('#tabla-precios').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '/productos/ajustar-precios/data',
                data: (d) => ({
                    draw: d.draw,
                    start: d.start !== undefined ? d.start : 0,
                    length: d.length,
                    ['search[value]']: d.search ? d.search.value : '',
                }),
            },
            columns: [
                { data: 'nombre', className: 'text-start' },
                { data: 'categoria' },
                { data: null, orderable: false },

                { data: null, orderable: false },
                { data: null, orderable: false },
            ],
            columnDefs: [
                { targets: 2, render: () => '<input type="number" step="0.01" min="0" class="form-control form-control-sm" x-model.number="costo_usd">' },
                { targets: 3, render: () => cargaTemplates() },
                { targets: 4, render: () => '<button class="btn btn-sm btn-primary" @click="guardar" :disabled="cargando"><i class="bi bi-check-lg" x-show="!cargando"></i><span x-show="cargando" class="spinner-border spinner-border-sm"></span></button>' },
            ],
            order: [],
            language: window.DataTableSpanish,
            rowCallback: (row, data) => {
                $(row).attr('x-data', 'precioRow(' + data.producto_id + ',' + data.costo_usd + ',' + JSON.stringify(data.presentaciones || []) + ')');
                if (window.Alpine) window.Alpine.initTree(row);
            },
        });
    }
});
</script>
@endpush
