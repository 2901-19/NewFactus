{{-- Modal de ver factura (recibo) --}}
<div class="modal fade" id="facturaVerModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">Ver Factura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="estado-cargando"><span class="spinner-border spinner-border-sm"></span> Cargando factura…</div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    $(document).on('click', '.btn-ver-factura', function (e) {
        e.preventDefault();
        const btn = $(this);
        const modal = document.getElementById('facturaVerModal');
        const body = $(modal).find('.modal-body');
        body.html('<div class="estado-cargando"><span class="spinner-border spinner-border-sm"></span> Cargando factura…</div>');
        new bootstrap.Modal(modal).show();
        fetch(btn.data('url'))
            .then((r) => { if (!r.ok) throw new Error('error'); return r.text(); })
            .then((html) => body.html(html))
            .catch(() => body.html('<div class="estado-cargando text-danger">No se pudo cargar la factura.</div>'));
    });
});
</script>
@endpush
