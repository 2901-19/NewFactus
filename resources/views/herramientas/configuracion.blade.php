@extends('layouts.app')
@section('titulo', 'Configuración del Negocio')
@section('contenido')
<div class="card">
    <div class="card-header">Datos del Negocio</div>
    <div class="card-body">
        <form method="POST" action="{{ route('herramientas.configuracion.guardar') }}">
            @csrf
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre del Negocio *</label>
                    <input type="text" name="nombre_negocio" class="form-control @error('nombre_negocio') is-invalid @enderror"
                        value="{{ old('nombre_negocio', $configs['nombre_negocio'] ?? '') }}" required>
                    @error('nombre_negocio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">RIF *</label>
                    <input type="text" name="rif" class="form-control @error('rif') is-invalid @enderror"
                        value="{{ old('rif', $configs['rif'] ?? '') }}" required>
                    @error('rif') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Dirección *</label>
                    <input type="text" name="direccion" class="form-control @error('direccion') is-invalid @enderror"
                        value="{{ old('direccion', $configs['direccion'] ?? '') }}" required>
                    @error('direccion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Teléfono *</label>
                    <input type="text" name="telefono" class="form-control @error('telefono') is-invalid @enderror"
                        value="{{ old('telefono', $configs['telefono'] ?? '') }}" required>
                    @error('telefono') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Ventas</div>
    <div class="card-body">
        <form method="POST" action="{{ route('herramientas.venta-sin-stock.guardar') }}">
            @csrf
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="permitir_venta_sin_stock"
                    name="permitir_venta_sin_stock" value="1"
                    @checked(old('permitir_venta_sin_stock', ($configs['permitir_venta_sin_stock'] ?? '0') === '1'))>
                <label class="form-check-label" for="permitir_venta_sin_stock">Permitir vender productos sin stock</label>
            </div>
            <p class="text-muted small">Con esta opción activa, el POS no bloquea la venta cuando el stock está en cero y la factura no descuenta inventario (al anular tampoco lo restaura).</p>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Recordatorio de Tasa de Cambio</div>
    <div class="card-body">
        <form method="POST" action="{{ route('herramientas.recordatorio.guardar') }}">
            @csrf
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="recordatorio_activo"
                        name="recordatorio_tasa_activo" value="1"
                        @checked(old('recordatorio_tasa_activo', ($configs['recordatorio_tasa_activo'] ?? '1') === '1'))>
                    <label class="form-check-label" for="recordatorio_activo">Habilitar recordatorio</label>
                </div>
            </div>
            <p class="text-muted small">Avisa a quienes pueden gestionar tasas cuando la tasa de referencia no se ha actualizado después de cada hora programada.</p>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Primera hora del día</label>
                    <input type="time" name="recordatorio_tasa_hora1" class="form-control @error('recordatorio_tasa_hora1') is-invalid @enderror"
                        value="{{ old('recordatorio_tasa_hora1', $configs['recordatorio_tasa_hora1'] ?? '09:00') }}">
                    @error('recordatorio_tasa_hora1') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Segunda hora del día</label>
                    <input type="time" name="recordatorio_tasa_hora2" class="form-control @error('recordatorio_tasa_hora2') is-invalid @enderror"
                        value="{{ old('recordatorio_tasa_hora2', $configs['recordatorio_tasa_hora2'] ?? '14:00') }}">
                    @error('recordatorio_tasa_hora2') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </form>
    </div>
</div>
@endsection
