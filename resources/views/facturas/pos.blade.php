@extends('layouts.app')
@section('titulo', 'Punto de Venta')
@section('contenido')
<div x-data="pos" class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-box"></i> Productos</span>
                <span class="small text-muted" x-text="'Agregados: ' + carrito.length"></span>
            </div>
            <div class="card-body">
                <table id="productosTable" class="table table-sm table-hover thumbs-table" style="width:100%">
                    <thead>
                        <tr>
                            <th class="text-start">Nombre</th>
                            <th>Ref.</th>
                            <th>Presentación</th>
                            <th>Precio</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-cart"></i> Carrito</span>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary" x-text="carrito.length + ' items'"></span>
                    <button type="button" class="btn btn-sm btn-outline-danger py-0" @click="vaciarCarrito" :disabled="carrito.length === 0" title="Vaciar carrito">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-2">
                <template x-for="(item, index) in carrito" :key="index">
                    <div class="border-bottom pb-2 mb-2">
                        <div class="d-flex justify-content-between">
                            <strong class="small" x-text="item.nombre"></strong>
                            <button class="btn btn-sm btn-outline-danger py-0" @click="carrito.splice(index, 1)">&times;</button>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <template x-if="item.controla_inventario">
                                <div class="d-flex align-items-center gap-2">
                                    <button class="btn btn-sm btn-outline-secondary" @click="item.cantidad = Math.max(1, item.cantidad - 1)">-</button>
                                    <input type="number" step="1" min="1" :max="maxPresentaciones(index)" x-model.number="item.cantidad" @blur="item.cantidad = Math.max(1, Math.min(parseFloat(item.cantidad) || 1, maxPresentaciones(index)))" class="form-control form-control-sm text-center" style="width: 70px;">
                                    <button class="btn btn-sm btn-outline-secondary" @click="item.cantidad = Math.min((parseFloat(item.cantidad) || 0) + 1, maxPresentaciones(index))" :disabled="item.cantidad >= maxPresentaciones(index)">+</button>
                                </div>
                            </template>
                            <template x-if="!item.controla_inventario">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="input-group input-group-sm" style="width: 160px;">
                                        <span class="input-group-text">Bs</span>
                                        <input type="number" step="0.01" min="0.01" x-model.number="item.montoBs" @input="sincronizarPeso(index)" @blur="sincronizarPeso(index)" class="form-control text-center" placeholder="Monto">
                                    </div>
                                    <span class="small text-muted text-nowrap" x-text="'≈ ' + pesoReferencia(index)"></span>
                                </div>
                            </template>
                            <span class="ms-auto small" x-text="'Bs ' + getBsPriceTotal(index).toFixed(2)"></span>
                        </div>
                        <div class="text-muted small" x-show="factorDe(item) !== 1 && factorDe(item) > 0" x-text="descripcionPresentacion(index)"></div>
                    </div>
                </template>
                <div class="text-center text-muted small py-3" x-show="carrito.length === 0">
                    Carrito vacío. Seleccione productos.
                </div>
            </div>

            <div class="card-footer">
                <div class="mb-2" x-show="tipoFactura !== 'credito'">
                    <label class="form-label small">Método de Pago</label>
                    <select x-model="metodoPago" class="form-select form-select-sm">
                        <option value="efectivo">Efectivo</option>
                        <option value="punto">Punto de Venta</option>
                        <option value="biopago">Biopago</option>
                        <option value="divisas">Divisas</option>
                        <option value="pago_movil">Pago Móvil</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="mixto">Mixto</option>
                    </select>
                </div>
                <div x-show="metodoPago === 'mixto' && tipoFactura !== 'credito'" class="mb-2 p-2 border rounded bg-light">
                    <div class="small fw-bold mb-2"><i class="bi bi-wallet2"></i> Pago Mixto</div>
                    <div class="row g-2">
                        <div class="col-6">
                            <select x-model="pago1.metodo" @change="if (pago1.metodo === pago2.metodo) pago2.metodo = (opcionesPago2[0] || 'punto')" class="form-select form-select-sm">
                                <template x-for="m in opcionesPago1" :key="m">
                                    <option :value="m" x-text="nombreMetodo(m)"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-6">
                            <input type="number" min="0" step="0.01" x-model.number="pago1.monto" class="form-control form-control-sm" placeholder="Monto Bs">
                        </div>
                        <div class="col-6">
                            <select x-model="pago2.metodo" class="form-select form-select-sm">
                                <template x-for="m in opcionesPago2" :key="m">
                                    <option :value="m" x-text="nombreMetodo(m)"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-6">
                            <input type="number" min="0" step="0.01" x-model.number="pago2.monto" class="form-control form-control-sm" placeholder="Monto Bs">
                        </div>
                    </div>
                    <div class="small mt-2" :class="pagosValidos ? 'text-success' : 'text-danger'">
                        <span x-show="diferenciaPagos > 0.01">Falta Bs <span x-text="diferenciaPagos.toFixed(2)"></span></span>
                        <span x-show="diferenciaPagos < -0.01">Sobran Bs <span x-text="Math.abs(diferenciaPagos).toFixed(2)"></span></span>
                        <span x-show="Math.abs(diferenciaPagos) <= 0.01">Montos cuadrados con el total.</span>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Tipo de Factura</label>
                    <select x-model="tipoFactura" class="form-select form-select-sm" @change="clienteId = ''; if (tipoFactura === 'credito') metodoPago = 'efectivo'">
                        <option value="contado">Contado</option>
                        <option value="credito">Crédito</option>
                    </select>
                </div>
                <div x-show="tipoFactura === 'credito'" class="mb-2">
                    <label class="form-label small">Cliente *</label>
                    <div class="input-group input-group-sm">
                        <select x-model="clienteId" class="form-select" required>
                            <option value="">Seleccione un cliente</option>
                            <template x-for="c in clientes" :key="c.id">
                                <option :value="c.id" x-text="c.nombre + ' (' + c.ci + ')'"></option>
                            </template>
                        </select>
                        <button class="btn btn-outline-primary" type="button" @click="abrirModalCliente" title="Crear cliente rápido">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between small">
                    <span>Subtotal Bs:</span>
                    <span x-text="subtotalBs.toFixed(2)"></span>
                </div>
                <div x-show="impuestosDesglose.length > 0">
                    <div class="small text-muted">Impuesto</div>
                    <template x-for="f in impuestosDesglose" :key="f.id">
                        <div class="d-flex justify-content-between small">
                            <span x-text="f.nombre + ' ' + f.porcentaje + '%'"></span>
                            <span x-text="'= ' + f.bs.toFixed(2) + ' bs'"></span>
                        </div>
                    </template>
                    <div class="d-flex justify-content-between small fw-semibold">
                        <span>Total</span>
                        <span x-text="'= ' + ivaBs.toFixed(2) + ' bs'"></span>
                    </div>
                </div>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total Bs:</span>
                    <span x-text="totalBs.toFixed(2)"></span>
                </div>
                <div class="d-flex justify-content-between small text-muted">
                    <span>Total USD:</span>
                    <span x-text="'$' + totalUsdRef.toFixed(2)"></span>
                </div>
                <div x-show="pesablesIncompletos" class="small text-danger mt-2">
                    <i class="bi bi-exclamation-triangle"></i> Complete el monto en Bs de los productos pesables.
                </div>
                <button class="btn btn-success w-100 mt-3" @click="confirmarFactura" :disabled="carrito.length === 0 || (tipoFactura === 'credito' && !clienteId) || (metodoPago === 'mixto' && !pagosValidos) || pesablesIncompletos || cargando">
                    <span x-show="!cargando"><i class="bi bi-check-lg"></i> Generar Factura</span>
                    <span x-show="cargando"><span class="spinner-border spinner-border-sm"></span> Procesando...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Modal de confirmación --}}
    <div class="modal fade" id="confirmModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-body p-3">
                    <div class="recibo">
                        <div class="recibo-head">
                            <div class="recibo-brand">FACTUS<span class="recibo-brand-sub">ESPERANZA VELIZ</span></div>
                            <span class="recibo-titulo">FACTURA DE VENTA</span>
                        </div>
                        <div class="p-3">
                            <table class="table table-sm tabla-recibo">
                                <thead>
                                    <tr>
                                        <th class="cant">CANT</th>
                                        <th class="desc">DESC</th>
                                        <th class="num">PREC U</th>
                                        <th class="num">PREC T</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(item, index) in carrito" :key="index">
                                        <tr>
                                            <td class="cant" x-text="item.controla_inventario ? item.cantidad : '—'"></td>
                                            <td class="desc">
                                                <span x-text="item.nombre"></span>
                                                <span class="text-muted small" x-show="presNombre(index)" x-text="'(' + presNombre(index) + ')'"></span>
                                            </td>
                                            <td class="num" x-text="(tipoFactura === 'credito' ? getUsdPrice(index) : getBsPrice(index)).toFixed(2) + (item.controla_inventario ? '' : '/kg')"></td>
                                            <td class="num fw-semibold" x-text="(tipoFactura === 'credito' ? getUsdPriceTotal(index) : getBsPriceTotal(index)).toFixed(2)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <div x-show="carrito.length === 0" class="text-center small text-muted py-2">Sin productos</div>
                            <div class="recibo-sep"></div>
                            <div class="totales">
                                <div class="fila-total">
                                    <span>Subtotal <span x-text="tipoFactura === 'credito' ? 'USD' : 'Bs'"></span></span>
                                    <span x-text="(tipoFactura === 'credito' ? subtotalBs / tasaRef : subtotalBs).toFixed(2)"></span>
                                </div>
                                <template x-if="impuestosDesglose.length > 0">
                                    <div>
                                        <div class="seccion-titulo">Impuesto</div>
                                        <template x-for="f in impuestosDesglose" :key="f.id">
                                            <div class="fila-total">
                                                <span x-text="f.nombre + ' ' + f.porcentaje + '%'"></span>
                                                <span x-text="tipoFactura === 'credito' ? '= ' + (f.bs / tasaRef).toFixed(2) + ' usd' : '= ' + f.bs.toFixed(2) + ' bs'"></span>
                                            </div>
                                        </template>
                                        <div class="fila-total fw-semibold">
                                            <span>Total</span>
                                            <span x-text="tipoFactura === 'credito' ? '= ' + ivaUsd.toFixed(2) + ' usd' : '= ' + ivaBs.toFixed(2) + ' bs'"></span>
                                        </div>
                                    </div>
                                </template>
                                <div class="total-final">
                                    <span class="total-label">TOTAL <span x-text="tipoFactura === 'credito' ? 'USD' : 'Bs'"></span></span>
                                    <span class="total-valor" x-text="(tipoFactura === 'credito' ? totalUsdRef : totalBs).toFixed(2)"></span>
                                </div>
                                <div class="fila-total" x-show="tipoFactura !== 'credito'">
                                    <span>Total USD</span>
                                    <span>$ <span x-text="totalUsdRef.toFixed(2)"></span></span>
                                </div>
                                <div class="fila-total" x-show="tipoFactura !== 'credito' && metodoPago !== 'mixto'">
                                    <span>Pago</span>
                                    <span x-text="nombreMetodo(metodoPago)"></span>
                                </div>
                                <template x-if="tipoFactura !== 'credito' && metodoPago === 'mixto'">
                                    <div>
                                        <div class="recibo-sep"></div>
                                        <div class="seccion-titulo">Pago Mixto</div>
                                        <div class="fila-total">
                                            <span x-text="nombreMetodo(pago1.metodo)"></span>
                                            <span x-text="'Bs ' + (parseFloat(pago1.monto) || 0).toFixed(2)"></span>
                                        </div>
                                        <div class="fila-total">
                                            <span x-text="nombreMetodo(pago2.metodo)"></span>
                                            <span x-text="'Bs ' + (parseFloat(pago2.monto) || 0).toFixed(2)"></span>
                                        </div>
                                    </div>
                                </template>
                                <div class="fila-total">
                                    <span>Tipo</span>
                                    <span x-text="tipoFactura.charAt(0).toUpperCase() + tipoFactura.slice(1)"></span>
                                </div>
                                <template x-if="tasaReferenciaInfo">
                                    <div class="fila-total">
                                        <span>Tasa ref</span>
                                        <span x-text="tasaReferenciaInfo.nombre + ' ' + tasaReferenciaInfo.monto.toFixed(2)"></span>
                                    </div>
                                </template>
                                <template x-if="!tasaReferenciaInfo">
                                    <div class="fila-total">
                                        <span>Tasa ref</span>
                                        <span>Sin tasa de referencia</span>
                                    </div>
                                </template>
                            </div>
                            <div class="recibo-sep"></div>
                            <div class="recibo-foot">
                                <i class="bi bi-bag-heart me-1"></i> ¡Gracias por su compra!
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-success" @click="guardarFactura" :disabled="metodoPago === 'mixto' && !pagosValidos">
                        <i class="bi bi-check-lg"></i> Facturar
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal crear cliente rápido --}}
    <div class="modal fade" id="clienteModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small">Nombre *</label>
                        <input type="text" x-model="nuevoCliente.nombre" class="form-control form-control-sm" placeholder="Nombre del cliente">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Cédula *</label>
                        <input type="text" x-model="nuevoCliente.ci" class="form-control form-control-sm" placeholder="V-12345678">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Teléfono</label>
                        <input type="text" x-model="nuevoCliente.telefono" class="form-control form-control-sm" placeholder="0412-1234567">
                    </div>
                    <div x-show="errorCliente" class="text-danger small" x-text="errorCliente"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-primary" @click="guardarClienteRapido" :disabled="guardandoCliente">
                        <span x-show="!guardandoCliente"><i class="bi bi-check"></i> Guardar</span>
                        <span x-show="guardandoCliente"><span class="spinner-border spinner-border-sm"></span></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('pos', () => ({
        productos: @json($productos),
        clientes: @json($clientes),
        tasas: @json($tasas),
        tasaReferencia: '{{ $tasaReferenciaTipo }}',
        carrito: [],
        metodoPago: 'efectivo',
        tipoFactura: 'contado',
        clienteId: '',
        pago1: { metodo: 'efectivo', monto: 0 },
        pago2: { metodo: 'punto', monto: 0 },
        cargando: false,
        nuevoCliente: { nombre: '', ci: '', telefono: '' },
        errorCliente: '',
        guardandoCliente: false,

        init() {
            if ($.fn.DataTable) {
                this.dataTable = $('#productosTable').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '/pos/productos',
                        data: (d) => ({
                            draw: d.draw,
                            start: d.start !== undefined ? d.start : 0,
                            length: d.length,
                            ['search[value]']: d.search ? d.search.value : '',
                        }),
                    },
                    pageLength: 15,
                    lengthMenu: [10, 15, 25, 50],
                    order: [[0, 'asc']],
                    columns: [
                        { data: 'nombre' },
                        { data: 'imagen' },
                        { data: 'presentacion' },
                        { data: 'precio_bs' },
                        { data: null, orderable: false, searchable: false },
                    ],
                    columnDefs: [
                        {
                            targets: 1,
                            render: (data) => data
                                ? '<img src="' + data + '" class="thumb" alt="">'
                                : '<span class="text-muted sin-ref">Sin referencia</span>',
                        },
                        {
                            targets: 2,
                            render: (data, type, row) => {
                                if (row.sin_presentaciones) {
                                    return '<span class="text-muted">Sin presentaciones activas</span>';
                                }
                                return data;
                            },
                        },
                        {
                            targets: 3,
                            className: 'small text-nowrap',
                            render: (data, type, row) => {
                                if (row.sin_presentaciones || !row.fila_ok) {
                                    const title = row.fuente_tasa
                                        ? "Configure la tasa '" + row.fuente_tasa + "' en Tasas de Cambio para poder vender este producto"
                                        : 'Sin presentaciones activas';
                                    return '<span class="badge bg-danger" title="' + title + '">Sin tasa</span>';
                                }
                                let html = '<strong>Bs ' + Number(data).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong>';
                                const usd = Number(row.precio_usd_bs);
                                if (!isNaN(usd) && usd > 0) {
                                    html += '<small class="text-muted">($' + usd.toFixed(2) + ')</small>';
                                }
                                return html;
                            },
                        },
                        {
                            targets: 4,
                            className: 'text-center',
                            render: (data, type, row) => {
                                if (row.sin_presentaciones || !row.fila_ok) {
                                    return '<button class="btn btn-sm btn-outline-primary agregar-producto" disabled title="Sin tasa"><i class="bi bi-cart-plus"></i></button>';
                                }
                                return '<button class="btn btn-sm btn-outline-primary agregar-producto" data-id="' + row.producto_id + '" data-presentacion="' + row.presentacion_id + '" title="Agregar al carrito"><i class="bi bi-cart-plus"></i></button>';
                            },
                        },
                    ],
                });

                $('#productosTable tbody').on('click', '.agregar-producto', (e) => {
                    const id = parseInt($(e.currentTarget).data('id'));
                    const presId = parseInt($(e.currentTarget).data('presentacion'));
                    const producto = this.productos.find(p => p.id === id);
                    if (producto) this.agregar(producto, presId);
                });
            }

            document.addEventListener('keydown', (e) => {
                if (e.key === 'F2') {
                    e.preventDefault();
                    const input = document.querySelector('.dataTables_filter input');
                    if (input) input.focus();
                }
                if (e.key === 'F8' && this.carrito.length > 0) {
                    e.preventDefault();
                    this.confirmarFactura();
                }
            });
        },

        agregar(p, presId) {
            const presentaciones = p.presentaciones || [];
            if (!presentaciones.length) {
                this.mostrarError('Este producto no tiene presentaciones activas.');
                return;
            }
            if (!presentaciones.some(pr => pr.id === presId)) {
                presId = presentaciones[0].id;
            }
            const prSel = presentaciones.find(pr => pr.id === presId);
            const tasaFila = this.tasas[prSel.fuente_tasa];
            if (!tasaFila || !(parseFloat(tasaFila.monto) > 0)) {
                this.mostrarError('La presentación "' + prSel.nombre + '" de ' + p.nombre + ' no tiene tasa de cambio configurada. Actualícela en Tasas de Cambio.');
                return;
            }
            if (!this.hayStockPara(p, presId)) {
                const pr = presentaciones.find(pr => pr.id === presId);
                const nombrePres = pr ? pr.nombre : '';
                const stock = parseFloat(p.stock_actual) || 0;
                const unidad = p.unidad_medida || 'unidad';
                this.mostrarError('Stock insuficiente para ' + p.nombre + (nombrePres ? ' (' + nombrePres + ')' : '') + '. Disponible: ' + stock + ' ' + unidad + '.');
                return;
            }
            const existente = this.carrito.find(i => i.id === p.id && i.presentacion_id === presId);
            if (existente) {
                if (existente.controla_inventario) {
                    existente.cantidad++;
                }
            } else {
                const pesable = !p.controla_inventario;
                this.carrito.push({
                    id: p.id,
                    nombre: p.nombre,
                    cantidad: pesable ? 0 : 1,
                    controla_inventario: !!p.controla_inventario,
                    montoBs: pesable ? '' : undefined,
                    presentaciones,
                    presentacion_id: presId,
                    impuesto: p.impuesto || null,
                    fuente_tasa: prSel.fuente_tasa,
                });
            }
        },

        sincronizarPeso(index) {
            const it = this.carrito[index];
            if (!it) return;
            const precioBs = this.getBsPrice(index);
            if (!(precioBs > 0)) {
                it.cantidad = 0;
                return;
            }
            const monto = parseFloat(it.montoBs) || 0;
            it.cantidad = monto > 0 ? Math.max(0.001, monto / precioBs) : 0;
        },

        pesoReferencia(index) {
            const it = this.carrito[index];
            if (!it) return '';
            const cantidad = parseFloat(it.cantidad) || 0;
            return cantidad > 0 ? cantidad.toFixed(3) + ' kg' : '—';
        },

        get pesablesIncompletos() {
            return this.carrito.some(i => !i.controla_inventario && !(parseFloat(i.montoBs) > 0));
        },

        factorDe(item) {
            const pr = (item.presentaciones || []).find(pr => pr.id === item.presentacion_id);
            return pr ? parseFloat(pr.factor_conversion) || 0 : 0;
        },

        unidadesOcupadas(productoId) {
            let total = 0;
            this.carrito.forEach(it => {
                if (it.id !== productoId) return;
                const pr = (it.presentaciones || []).find(pr => pr.id === it.presentacion_id);
                total += (parseFloat(it.cantidad) || 0) * (pr ? parseFloat(pr.factor_conversion) || 0 : 0);
            });
            return total;
        },

        maxPresentaciones(index) {
            const it = this.carrito[index];
            if (!it || !it.controla_inventario) return Infinity;
            const p = this.productos.find(x => x.id === it.id);
            if (!p) return Infinity;
            const factor = this.factorDe(it);
            if (!(factor > 0)) return Infinity;
            const ocupado = this.unidadesOcupadas(it.id) - ((parseFloat(it.cantidad) || 0) * factor);
            const restante = (parseFloat(p.stock_actual) || 0) - ocupado;
            return Math.max(0, Math.floor(restante / factor));
        },

        hayStockPara(p, presId) {
            if (!p.controla_inventario) return true;
            const pr = (p.presentaciones || []).find(pr => pr.id === presId);
            if (!pr) return true;
            const stock = parseFloat(p.stock_actual) || 0;
            const ocupado = this.unidadesOcupadas(p.id);
            return (ocupado + (parseFloat(pr.factor_conversion) || 0)) <= stock + 1e-9;
        },

        unidadDe(item) {
            const p = this.productos.find(x => x.id === item.id);
            return p ? (p.unidad_medida || 'unidad') : 'unidad';
        },

        unidadLabel(item, cantidad) {
            let unidad = this.unidadDe(item);
            const n = parseFloat(cantidad) || 0;
            if (unidad === 'unidad') unidad = n === 1 ? 'unidad' : 'unidades';
            return unidad;
        },

        descripcionPresentacion(index) {
            const it = this.carrito[index];
            if (!it) return '';
            const factor = this.factorDe(it);
            if (!(factor > 0) || factor === 1) return '';
            return this.presNombre(index) + ' = ' + factor + ' ' + this.unidadLabel(it, factor);
        },

        precioUsdItem(index) {
            const it = this.carrito[index];
            if (!it) return 0;
            const pr = (it.presentaciones || []).find(pr => pr.id === it.presentacion_id);
            return pr ? parseFloat(pr.precio_usd) || 0 : 0;
        },

        presNombre(index) {
            const it = this.carrito[index];
            const pr = (it.presentaciones || []).find(pr => pr.id === it.presentacion_id);
            return pr ? pr.nombre : '';
        },

        get subtotalBs() {
            return this.carrito.reduce((sum, i, idx) => {
                const tasa = this.tasas[i.fuente_tasa]
                    ? parseFloat(this.tasas[i.fuente_tasa].monto)
                    : 1;
                return sum + (this.precioUsdItem(idx) * i.cantidad * tasa);
            }, 0);
        },

        get impuestosDesglose() {
            const filas = new Map();
            this.carrito.forEach((i, idx) => {
                const imp = i.impuesto;
                if (!imp) return;
                const porcentaje = parseFloat(imp.porcentaje) || 0;
                if (!(porcentaje > 0)) return;
                const tasa = this.tasas[i.fuente_tasa]
                    ? parseFloat(this.tasas[i.fuente_tasa].monto)
                    : 1;
                const impuestoBs = this.precioUsdItem(idx) * i.cantidad * tasa * (porcentaje / 100);
                const key = String(imp.id);
                if (!filas.has(key)) {
                    filas.set(key, { id: imp.id, nombre: imp.nombre, porcentaje, bs: 0 });
                }
                filas.get(key).bs += impuestoBs;
            });
            return Array.from(filas.values()).map(f => ({ ...f, bs: Math.round(f.bs * 100) / 100 }));
        },

        get ivaBs() {
            return this.impuestosDesglose.reduce((sum, f) => sum + f.bs, 0);
        },

        get totalBs() {
            return this.subtotalBs + this.ivaBs;
        },

        get tasaRef() {
            return this.tasas[this.tasaReferencia]
                ? parseFloat(this.tasas[this.tasaReferencia].monto) || 1
                : 1;
        },

        get tasaReferenciaInfo() {
            const t = this.tasas[this.tasaReferencia];
            if (!t) return null;
            const monto = parseFloat(t.monto) || 0;
            if (!(monto > 0)) return null;
            return { tipo: t.tipo, nombre: t.nombre || t.tipo, monto };
        },

        get totalUsdRef() {
            return this.totalBs / this.tasaRef;
        },

        get metodosDisponibles() {
            return ['efectivo', 'punto', 'biopago', 'divisas', 'pago_movil', 'transferencia'];
        },

        get opcionesPago1() {
            return this.metodosDisponibles;
        },

        get opcionesPago2() {
            return this.metodosDisponibles.filter(m => m !== this.pago1.metodo);
        },

        get montoTotalPagos() {
            return (parseFloat(this.pago1.monto) || 0) + (parseFloat(this.pago2.monto) || 0);
        },

        get diferenciaPagos() {
            return this.totalBs - this.montoTotalPagos;
        },

        get pagosValidos() {
            return this.pago1.metodo !== this.pago2.metodo
                && (parseFloat(this.pago1.monto) || 0) > 0
                && (parseFloat(this.pago2.monto) || 0) > 0
                && Math.abs(this.diferenciaPagos) <= 0.01;
        },

        nombreMetodo(m) {
            const nombres = {
                efectivo: 'Efectivo',
                punto: 'Punto de Venta',
                biopago: 'Biopago',
                divisas: 'Divisas',
                pago_movil: 'Pago Móvil',
                transferencia: 'Transferencia',
                mixto: 'Mixto',
            };
            return nombres[m] || m;
        },

        getBsPrice(index) {
            const it = this.carrito[index];
            if (!it) return 0;
            const tasa = this.tasas[it.fuente_tasa]
                ? parseFloat(this.tasas[it.fuente_tasa].monto)
                : 1;
            return this.precioUsdItem(index) * tasa;
        },

        getBsPriceTotal(index) {
            const it = this.carrito[index];
            if (!it) return 0;
            return this.getBsPrice(index) * it.cantidad;
        },

        getUsdPrice(index) {
            return this.getBsPrice(index) / this.tasaRef;
        },

        getUsdPriceTotal(index) {
            return this.getBsPriceTotal(index) / this.tasaRef;
        },

        get subtotalUsd() {
            return this.subtotalBs / this.tasaRef;
        },

        get ivaUsd() {
            return this.ivaBs / this.tasaRef;
        },

        abrirModalCliente() {
            this.nuevoCliente = { nombre: '', ci: '', telefono: '' };
            this.errorCliente = '';
            const modal = new bootstrap.Modal(document.getElementById('clienteModal'));
            modal.show();
        },

        async guardarClienteRapido() {
            if (!this.nuevoCliente.nombre.trim() || !this.nuevoCliente.ci.trim()) {
                this.errorCliente = 'Nombre y cédula son obligatorios.';
                return;
            }
            this.guardandoCliente = true;
            this.errorCliente = '';
            try {
                const res = await fetch('{{ route("clientes.rapido") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(this.nuevoCliente),
                });
                const data = await res.json();
                if (data.success) {
                    this.clientes.push(data.cliente);
                    this.clienteId = data.cliente.id;
                    const modal = bootstrap.Modal.getInstance(document.getElementById('clienteModal'));
                    if (modal) modal.hide();
                } else {
                    this.errorCliente = data.message || 'Error al guardar el cliente.';
                }
            } catch (e) {
                this.errorCliente = 'Error al conectar con el servidor.';
            } finally {
                this.guardandoCliente = false;
            }
        },

        confirmarFactura() {
            if (this.carrito.length === 0) return;
            if (this.tipoFactura === 'credito' && !this.clienteId) {
                this.mostrarError('Debe seleccionar un cliente para facturas a crédito.');
                return;
            }
            const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
            modal.show();
        },

        async guardarFactura() {
            this.cargando = true;
            const modal = bootstrap.Modal.getInstance(document.getElementById('confirmModal'));
            if (modal) modal.hide();

            const items = this.carrito.map(i => ({
                producto_id: i.id,
                presentacion_id: i.presentacion_id,
                cantidad: i.cantidad,
            }));

            try {
                const res = await fetch('{{ route("facturas.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        items,
                        metodo_pago: this.metodoPago,
                        pagos: this.metodoPago === 'mixto'
                            ? [
                                { metodo: this.pago1.metodo, monto: parseFloat(this.pago1.monto) },
                                { metodo: this.pago2.metodo, monto: parseFloat(this.pago2.monto) },
                            ]
                            : undefined,
                        cliente_id: this.tipoFactura === 'credito' ? this.clienteId : null,
                        estado: this.tipoFactura,
                    }),
                });

                const data = await res.json();

                if (data.success) {
                    this.mostrarExito('Factura N° ' + data.correlativo + ' generada correctamente.');
                    this.carrito = [];
                    this.metodoPago = 'efectivo';
                    this.tipoFactura = 'contado';
                    this.clienteId = '';
                    this.pago1 = { metodo: 'efectivo', monto: 0 };
                    this.pago2 = { metodo: 'punto', monto: 0 };
                } else {
                    this.mostrarError('Error: ' + data.message);
                }
            } catch (e) {
                this.mostrarError('Error al conectar con el servidor.');
            } finally {
                this.cargando = false;
            }
        },

        mostrarExito(msg) {
            Swal.fire({ icon: 'success', title: 'Éxito', text: msg, timer: 3000, showConfirmButton: false });
        },

        mostrarError(msg) {
            Swal.fire({ icon: 'error', title: 'Error', text: msg });
        },

        vaciarCarrito() {
            if (this.carrito.length === 0) return;
            Swal.fire({
                title: '¿Vaciar carrito?',
                text: 'Se eliminarán todos los productos del carrito.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, vaciar',
                cancelButtonText: 'Cancelar',
            }).then((r) => {
                if (r.isConfirmed) {
                    this.carrito = [];
                }
            });
        },
    }));
});
</script>
@endpush
@endsection
