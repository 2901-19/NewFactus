@php
    $tasaPorDefecto = $opcionesTasa->keys()->first() ?? 'promedio';
    $presentacionesIniciales = old('presentaciones', $producto?->presentaciones ?? [['nombre' => 'Unidad', 'factor_conversion' => 1, 'margen' => 0, 'fuente_tasa' => $tasaPorDefecto, 'activa' => true]]);
    $costoUsdInicial = old('costo_usd', $producto?->costo_usd ?? 0);
    $unidadMedidaInicial = old('unidad_medida', $producto?->unidad_medida ?? 'unidad');
    $stockInicial = old('stock_actual', $producto?->stock_actual ?? 0);
    $tieneImagen = ! empty($producto?->imagen);
@endphp
<script>
    window.__productoForm = {
        mapaTasas: @json($mapaTasas),
        presentaciones: @json($presentacionesIniciales),
        tasaPorDefecto: @json($tasaPorDefecto),
        unidadMedida: @json($unidadMedidaInicial),
    };
</script>
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-info-circle me-1"></i> Información del Producto</div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $producto?->nombre) }}" required>
                    @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Categoría</label>
                    <select name="categoria_id" class="form-select @error('categoria_id') is-invalid @enderror">
                        <option value="">Sin categoría</option>
                        @foreach (\App\Models\Categoria::orderBy('nombre')->get() as $cat)
                            <option value="{{ $cat->id }}" {{ old('categoria_id', $producto?->categoria_id) == $cat->id ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                        @endforeach
                    </select>
                    @error('categoria_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="disponible" {{ old('estado', $producto?->estado ?? 'disponible') == 'disponible' ? 'selected' : '' }}>Disponible</option>
                        <option value="no_disponible" {{ old('estado', $producto?->estado ?? 'disponible') == 'no_disponible' ? 'selected' : '' }}>No Disponible</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6 d-flex align-items-center justify-content-center">
                <div class="text-center" x-data="{ preview: null, tieneImagen: {{ $tieneImagen ? 'true' : 'false' }} }">
                    <div class="marco-imagen mx-auto">
                        <img x-show="preview" :src="preview" class="marco-imagen-img" alt="Vista previa de la imagen">
                        <img x-show="!preview && tieneImagen" src="{{ $producto?->imagen ? asset('storage/'.$producto->imagen) : '' }}" class="marco-imagen-img" alt="{{ $producto?->nombre ?? '' }}">
                        <div x-show="!preview && !tieneImagen" class="marco-imagen-placeholder">
                            <i class="bi bi-image fs-1"></i>
                            <span>Sin imagen</span>
                        </div>
                    </div>
                    <label class="btn btn-outline-secondary btn-sm mt-3 mb-0">
                        <i class="bi bi-upload me-1"></i>
                        <span x-text="tieneImagen || preview ? 'Cambiar imagen' : 'Seleccionar imagen'"></span>
                        <input type="file" name="imagen" accept="image/*" class="d-none" @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                    </label>
                    @error('imagen') <div class="invalid-feedback d-block mt-1">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>
</div>

<div x-data="productoForm(
    {{ $costoUsdInicial }},
    window.__productoForm.presentaciones,
    window.__productoForm.mapaTasas,
    window.__productoForm.tasaPorDefecto,
    window.__productoForm.unidadMedida,
    {{ $stockInicial }}
)">
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-cash-coin me-1"></i> Costo e Inventario</div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label mb-0">Costo (USD) *</label>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Modo de costo" x-show="medida === 'unidad'">
                            <button type="button" class="btn btn-outline-primary py-0" :class="costoModo === 'unidad' ? 'active' : ''" @click="cambiarModo('unidad')">Unidad</button>
                            <button type="button" class="btn btn-outline-primary py-0" :class="costoModo === 'lote' ? 'active' : ''" @click="cambiarModo('lote')">Lote</button>
                        </div>
                    </div>
                    <div class="mt-1">
                        <input type="number" step="0.01" min="0" name="costo_usd" x-model.number="costoUsd" x-show="costoModo === 'unidad'" class="form-control @error('costo_usd') is-invalid @enderror" value="{{ old('costo_usd', $producto?->costo_usd) }}">
                        <div class="row g-2" x-show="medida === 'unidad' && costoModo === 'lote'">
                            <div class="col-6">
                                <input type="number" step="1" min="1" x-model.number="uniLote" @input="sincronizarLote" class="form-control" placeholder="Uni. Lote">
                            </div>
                            <div class="col-6">
                                <input type="number" step="0.01" min="0" x-model.number="precioLote" @input="sincronizarLote" class="form-control" placeholder="Precio Lote (USD)">
                            </div>
                        </div>
                    </div>
                    @error('costo_usd') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Impuesto</label>
                    <select name="impuesto_id" class="form-select">
                        <option value="">No tiene</option>
                        @foreach ($impuestos as $imp)
                            <option value="{{ $imp->id }}" {{ (string) old('impuesto_id', $producto?->impuesto_id) === (string) $imp->id ? 'selected' : '' }}>
                                {{ $imp->nombre }} ({{ $imp->porcentaje }}%)
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Unidad de medida *</label>
                    <select name="unidad_medida" class="form-select" x-model="medida">
                        <option value="unidad">Unidad</option>
                        <option value="kg">Kilogramo (pesable)</option>
                    </select>
                    @error('unidad_medida') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3" x-show="medida === 'unidad'">
                    <label class="form-label">Existencia actual</label>
                    <input type="number" name="stock_actual" step="0.001" min="0" x-model.number="stockActual" class="form-control @error('stock_actual') is-invalid @enderror" value="{{ old('stock_actual', $producto?->stock_actual ?? 0) }}">
                    @error('stock_actual') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="bi bi-boxes me-1"></i> Presentaciones y Precios</div>
    <div class="card-body">
        @error('presentaciones') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

        <template x-if="medida === 'unidad'">
            <div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start" style="width:22%">Presentación</th>
                                <th class="text-center" style="width:100px">Unidades</th>
                                <th class="text-center" style="width:100px">Margen (%)</th>
                                <th class="text-center" style="width:150px">Tipo de tasa</th>
                                <th class="text-end" style="width:100px">Precio USD</th>
                                <th class="text-end" style="width:120px">Precio Bs</th>
                                <th class="text-center text-nowrap" style="width:130px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(pres, i) in presentaciones" :key="i">
                                <tr>
                                    <td class="text-start">
                                        <input type="hidden" :name="'presentaciones[' + i + '][id]'" :value="pres.id || ''">
                                        <input type="text" class="form-control" :name="'presentaciones[' + i + '][nombre]'" x-model="pres.nombre" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.0001" min="0.0001" class="form-control text-center" :name="'presentaciones[' + i + '][factor_conversion]'" x-model.number="pres.factor_conversion" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" class="form-control text-center" :name="'presentaciones[' + i + '][margen]'" x-model.number="pres.margen" required>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" :name="'presentaciones[' + i + '][fuente_tasa]'" x-model="pres.fuente_tasa">
                                            @forelse ($opcionesTasa as $tipo => $nombre)
                                                <option value="{{ $tipo }}">{{ $nombre }}</option>
                                            @empty
                                                <option value="" disabled>No hay tasas activas</option>
                                            @endforelse
                                        </select>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold" x-text="'$' + fmtMoneda(precioPor(i))"></span>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold" x-text="'Bs ' + fmtMoneda(precioPor(i) * (tasaDe(pres.fuente_tasa) || 1))"></span>
                                    </td>
                                    <td class="text-center text-nowrap">
                                        <div class="d-inline-flex align-items-center gap-2">
                                            <input type="hidden" :name="'presentaciones[' + i + '][activa]'" :value="pres.activa ? '1' : '0'">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" role="switch" x-model="pres.activa" :id="'activa-' + i">
                                                <label class="form-check-label small text-muted" :for="'activa-' + i" x-text="pres.activa ? 'Sí' : 'No'"></label>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger" @click="quitar(i)" :disabled="presentaciones.length <= 1" title="Quitar presentación">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" @click="agregar">
                    <i class="bi bi-plus-lg"></i> Agregar presentación
                </button>
            </div>
        </template>

        <template x-if="medida === 'kg'">
            <div>
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Presentación</label>
                        <input type="text" class="form-control" value="Kilogramo" disabled>
                        <input type="hidden" name="presentaciones[0][nombre]" value="Kilogramo">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Margen (%)</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="presentaciones[0][margen]" x-model.number="presentaciones[0].margen" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo de tasa</label>
                        <select class="form-select" name="presentaciones[0][fuente_tasa]" x-model="presentaciones[0].fuente_tasa">
                            @forelse ($opcionesTasa as $tipo => $nombre)
                                <option value="{{ $tipo }}">{{ $nombre }}</option>
                            @empty
                                <option value="" disabled>No hay tasas activas</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted">Precio por kilo</label>
                        <div class="fw-bold" x-text="'$ ' + fmtMoneda(precioKiloUsd) + ' / Bs ' + fmtMoneda(precioKiloBs)"></div>
                    </div>
                </div>
                <input type="hidden" name="presentaciones[0][id]" :value="presentaciones.length ? (presentaciones[0].id || '') : ''">
                <input type="hidden" name="presentaciones[0][factor_conversion]" value="1">
                <input type="hidden" name="presentaciones[0][activa]" value="1">
            </div>
        </template>
    </div>
</div>
</div>

@push('scripts')
<script>
    function productoForm(costoUsd, presentaciones, tasas, tasaPorDefecto, medida, stockActual) {
        const filas = (presentaciones && presentaciones.length ? presentaciones : [{ nombre: 'Unidad', factor_conversion: 1, margen: 0, activa: true }])
            .map(p => ({ fuente_tasa: tasaPorDefecto, ...p }));
        return {
            costoUsd: parseFloat(costoUsd) || 0,
            costoModo: 'unidad',
            uniLote: '',
            precioLote: '',
            tasas: tasas || {},
            tasaPorDefecto: tasaPorDefecto || (Object.keys(tasas || {})[0] || ''),
            medida: medida || 'unidad',
            stockActual: parseFloat(stockActual) || 0,
            presentaciones: filas,
            get esPesable() { return this.medida === 'kg'; },
            tasaDe(tipo) {
                return parseFloat(this.tasas[tipo] || 0) || 0;
            },
            cambiarModo(modo) {
                this.costoModo = modo;
            },
            sincronizarLote() {
                this.costoUsd = this.uniLote > 0 ? (parseFloat(this.precioLote) || 0) / this.uniLote : 0;
            },
            agregar() {
                this.presentaciones.push({ nombre: '', factor_conversion: 1, margen: 0, fuente_tasa: this.tasaPorDefecto, activa: true });
            },
            quitar(i) {
                if (this.presentaciones.length <= 1) return;
                this.presentaciones.splice(i, 1);
            },
            precioPor(i) {
                const pres = this.presentaciones[i] || {};
                const precioBase = (this.costoUsd || 0) * (1 + (parseFloat(pres.margen) || 0) / 100);
                return this.redon2(precioBase * (parseFloat(pres.factor_conversion) || 1));
            },
            redon2(n) {
                return Math.round((n + Number.EPSILON) * 100) / 100;
            },
            get precioKiloUsd() {
                return this.precioPor(0);
            },
            get precioKiloBs() {
                return this.precioKiloUsd * (this.tasaDe(this.presentaciones[0]?.fuente_tasa) || 1);
            },
        };
    }
</script>
@endpush
