@extends('layouts.app')
@section('titulo', 'Configuración de Impresora')
@section('contenido')
<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Configuración</div>
            <div class="card-body">
                <form method="POST" action="{{ route('herramientas.impresora.guardar') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Tipo de Conexión</label>
                        <select name="tipo" class="form-select" id="tipoConexion">
                            <option value="network" {{ $config['tipo'] == 'network' ? 'selected' : '' }}>Red (TCP/IP)</option>
                            <option value="windows" {{ $config['tipo'] == 'windows' ? 'selected' : '' }}>Windows (USB/COM)</option>
                        </select>
                    </div>
                    <div id="networkConfig" style="{{ $config['tipo'] == 'network' ? '' : 'display:none' }}">
                        <div class="mb-3">
                            <label class="form-label">Dirección IP</label>
                            <input type="text" name="host" class="form-control" value="{{ $config['host'] }}" placeholder="192.168.1.100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Puerto</label>
                            <input type="number" name="port" class="form-control" value="{{ $config['port'] }}" placeholder="9100">
                        </div>
                    </div>
                    <div id="windowsConfig" style="{{ $config['tipo'] == 'windows' ? '' : 'display:none' }}">
                        <div class="mb-3">
                            <label class="form-label">Nombre de la Impresora (Compartida)</label>
                            <input type="text" name="nombre" class="form-control" value="{{ $config['nombre'] }}" placeholder="XP-E300M">
                            <small class="text-muted">Nombre exacto con que está compartida en Windows.</small>
                        </div>
                    </div>
                    <div class="border-top pt-3 mt-2 mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="imprimir_al_facturar" id="imprimirAlFacturar" value="1" {{ $imprimirAlFacturar === '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="imprimirAlFacturar">
                                <strong>Imprimir ticket automáticamente al facturar</strong>
                            </label>
                            <small class="d-block text-muted">Al generar una factura desde el POS se imprime el ticket al momento. Si la impresora falla, la venta se conserva y se podrá imprimir desde Facturas.</small>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Prueba de Impresión</div>
            <div class="card-body">
                <p class="text-muted">Verifica que la impresora esté configurada correctamente.</p>
                <form method="POST" action="{{ route('herramientas.impresora.test') }}">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-printer"></i> Imprimir Prueba
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
    document.getElementById('tipoConexion').addEventListener('change', function() {
        document.getElementById('networkConfig').style.display = this.value === 'network' ? 'block' : 'none';
        document.getElementById('windowsConfig').style.display = this.value === 'windows' ? 'block' : 'none';
    });
</script>
@endpush
