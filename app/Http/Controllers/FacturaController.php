<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Factura;
use App\Models\ItemFactura;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\TasaCambio;
use App\Services\CatalogoService;
use App\Services\ImpuestoService;
use App\Services\PrinterService;
use App\Services\StockService;
use App\Services\TasaCambioService;
use App\Support\Moneda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FacturaController extends Controller
{
    public function index()
    {
        $facturas = Factura::with('cliente', 'user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('facturas.index', compact('facturas'));
    }

    public function anular(Factura $factura)
    {
        if ($factura->estado === 'anulada') {
            return back()->withErrors(['error' => 'La factura ya está anulada.']);
        }

        if ($factura->estado_credito === 'cancelado') {
            return back()->withErrors(['error' => 'No se puede anular un crédito ya cobrado. Reversa el pago primero.']);
        }

        DB::transaction(function () use ($factura) {
            $factura->update(['estado' => 'anulada', 'estado_credito' => null]);

            $factura->load('items');
            $productos = Producto::withTrashed()
                ->whereIn('id', $factura->items->pluck('producto_id'))
                ->get()
                ->keyBy('id');

            foreach ($factura->items as $item) {
                $producto = $productos->get($item->producto_id);
                if ($producto) {
                    StockService::agregar($producto, (float) $item->cantidad * (float) ($item->factor_conversion ?: 1));
                }
            }
        });

        return redirect()->route('facturas.index')->with('success', 'Factura N° '.$factura->correlativo.' anulada. Stock restaurado.');
    }

    public function pos()
    {
        $productos = Producto::where('estado', 'disponible')->whereNull('deleted_at')
            ->with(['presentaciones', 'impuesto'])
            ->get()
            ->map(fn ($producto) => $this->productoParaCatalogo($producto))
            ->values();

        $clientes = Cliente::all();

        $tasas = TasaCambio::ultimasPorTipo();
        $tasaReferenciaTipo = Configuracion::obtener('tasa_referencia', 'bcv');
        $tasaReferenciaMonto = $tasas->has($tasaReferenciaTipo) ? (float) $tasas[$tasaReferenciaTipo]->monto : null;

        return view('facturas.pos', compact('productos', 'clientes', 'tasas', 'tasaReferenciaTipo', 'tasaReferenciaMonto'));
    }

    public function posProductos(Request $request)
    {
        $productos = Producto::where('estado', 'disponible')->whereNull('deleted_at')
            ->with(['presentaciones', 'impuesto'])
            ->when($request->filled('search.value'), function ($q) use ($request) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($request->input('search.value')).'%']);
            })
            ->orderBy('nombre')
            ->get();

        $tasas = TasaCambio::ultimasPorTipo();
        $tasaReferenciaTipo = Configuracion::obtener('tasa_referencia', 'bcv');
        $tasaReferenciaMonto = $tasas->has($tasaReferenciaTipo) ? (float) $tasas[$tasaReferenciaTipo]->monto : null;

        $filas = collect();
        foreach ($productos as $producto) {
            $presentacionesActivas = $producto->presentaciones->where('activa', true);

            if ($presentacionesActivas->isEmpty()) {
                $filas->push($this->filaPos($producto, null, null, $tasas, $tasaReferenciaMonto, true));

                continue;
            }

            foreach ($presentacionesActivas as $presentacion) {
                $filas->push($this->filaPos($producto, $presentacion, $presentacionesActivas, $tasas, $tasaReferenciaMonto, false));
            }
        }

        $start = max(0, (int) $request->input('start', 0));
        $length = max(1, (int) $request->input('length', 15));
        $data = $filas->slice($start, $length)->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $filas->count(),
            'recordsFiltered' => $filas->count(),
            'data' => $data,
        ]);
    }

    private function productoParaCatalogo(Producto $producto): array
    {
        return [
            'id' => $producto->id,
            'nombre' => $producto->nombre,
            'controla_inventario' => $producto->controla_inventario,
            'stock_actual' => (float) $producto->stock_actual,
            'unidad_medida' => $producto->unidad_medida,
            'impuesto' => $producto->impuesto ? [
                'id' => $producto->impuesto->id,
                'nombre' => $producto->impuesto->nombre,
                'porcentaje' => (float) $producto->impuesto->porcentaje,
            ] : null,
            'presentaciones' => $producto->presentaciones
                ->filter(fn ($pr) => $pr->activa)
                ->values()
                ->map(fn ($pr) => [
                    'id' => $pr->id,
                    'nombre' => $pr->nombre,
                    'factor_conversion' => (float) $pr->factor_conversion,
                    'margen' => (float) $pr->margen,
                    'precio_usd' => (float) $pr->precio_usd,
                    'fuente_tasa' => $pr->fuente_tasa,
                ]),
        ];
    }

    private function filaPos(Producto $producto, ?ProductoPresentacion $presentacion, $presentacionesActivas, $tasas, ?float $tasaReferenciaMonto, bool $sinPresentaciones): array
    {
        $tasaFila = $presentacion ? $tasas->get($presentacion->fuente_tasa) : null;
        $filaOk = $presentacion && $tasaFila && (float) $tasaFila->monto > 0;

        if ($sinPresentaciones) {
            return [
                'nombre' => $producto->nombre,
                'imagen' => $producto->imagen_url,
                'presentacion' => null,
                'precio_bs' => null,
                'precio_usd_bs' => null,
                'precio_usd' => null,
                'fila_ok' => false,
                'sin_presentaciones' => true,
                'producto_id' => $producto->id,
            ];
        }

        $precioUsd = (float) $presentacion->precio_usd;
        $precioBs = $filaOk ? $precioUsd * (float) $tasaFila->monto : null;
        $precioUsdBs = $precioBs !== null && $tasaReferenciaMonto ? $precioBs / $tasaReferenciaMonto : ($precioBs !== null ? $precioUsd : null);

        return [
            'nombre' => $producto->nombre,
            'imagen' => $producto->imagen_url,
            'presentacion' => $presentacion->nombre,
            'precio_bs' => $precioBs,
            'precio_usd_bs' => $precioUsdBs,
            'precio_usd' => $precioUsd,
            'fila_ok' => $filaOk,
            'sin_presentaciones' => false,
            'producto_id' => $producto->id,
            'presentacion_id' => $presentacion->id,
            'fuente_tasa' => $presentacion->fuente_tasa,
        ];
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'required|exists:productos,id',
            'items.*.presentacion_id' => 'required|exists:producto_presentaciones,id',
            'items.*.cantidad' => 'required|numeric|min:0.001',
            'metodo_pago' => 'required|string',
            'pagos' => 'required_if:metodo_pago,mixto|array|size:2',
            'pagos.*.metodo' => ['required', Rule::in(CatalogoService::metodosValidos()), 'distinct'],
            'pagos.*.monto' => 'required|numeric|min:0.01',
            'cliente_id' => 'nullable|exists:clientes,id',
            'estado' => 'required|in:contado,credito',
        ]);

        if ($request->estado === 'credito' && ! $request->cliente_id) {
            return back()->withErrors(['cliente_id' => 'Debe seleccionar un cliente para facturas a crédito.']);
        }

        $correlativo = $this->generarCorrelativo();

        $itemsData = [];
        $subtotalBs = 0;
        $ivaBs = 0;

        DB::beginTransaction();
        try {
            $productos = Producto::whereIn('id', collect($request->items)->pluck('producto_id'))
                ->with('impuesto')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $presentaciones = ProductoPresentacion::whereIn('id', collect($request->items)->pluck('presentacion_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($request->items as $item) {
                $producto = $productos[$item['producto_id']] ?? null;
                if (! $producto) {
                    throw new \Exception('Uno de los productos ya no está disponible.');
                }

                $presentacion = $presentaciones[$item['presentacion_id']] ?? null;
                if (! $presentacion || $presentacion->producto_id !== $producto->id || ! $presentacion->activa) {
                    throw new \Exception("La presentación seleccionada para {$producto->nombre} ya no está disponible.");
                }

                $cantidad = (float) $item['cantidad'];
                $factor = (float) $presentacion->factor_conversion;
                $cantidadBase = round($cantidad * $factor, 4);

                if ($producto->controla_inventario && $cantidadBase > (float) $producto->stock_actual) {
                    throw new \Exception("Stock insuficiente para {$producto->nombre}. Disponible: {$producto->stock_actual} {$producto->unidad_medida}.");
                }

                $precioUsd = (float) $presentacion->precio_usd;

                if ($precioUsd <= 0) {
                    throw new \Exception("El producto {$producto->nombre} no tiene precio configurado.");
                }

                $tasa = TasaCambioService::montoOException($presentacion->fuente_tasa);
                $precioBs = $precioUsd * $tasa;
                $subtotalItemBs = round($precioBs * $cantidad, 2);

                StockService::descontar($producto, $cantidadBase);

                $itemsData[] = [
                    'producto_id' => $producto->id,
                    'presentacion_id' => $presentacion->id,
                    'presentacion_nombre' => $presentacion->nombre,
                    'factor_conversion' => $factor,
                    'cantidad' => $cantidad,
                    'precio_unitario_usd' => $precioUsd,
                    'precio_unitario_bs' => round($precioBs, 2),
                    'subtotal' => $subtotalItemBs,
                    'unidad_medida' => $producto->unidad_medida,
                ];

                $subtotalBs = round($subtotalBs + $subtotalItemBs, 2);

                $porcentajeIva = ImpuestoService::porcentajeDe($producto->impuesto);
                if ($porcentajeIva > 0) {
                    $ivaBs = round($ivaBs + $subtotalItemBs * ($porcentajeIva / 100), 2);
                }
            }

            $totalBs = round($subtotalBs + $ivaBs, 2);

            $tasaReferencia = TasaCambioService::montoOException(Configuracion::obtener('tasa_referencia', 'bcv'));
            $totalUsd = round($totalBs / $tasaReferencia, 2);
            $tasaEfectiva = $tasaReferencia;

            $detallePago = null;
            if ($request->metodo_pago === 'mixto') {
                $pagos = $request->input('pagos', []);
                $sumaPagos = round(array_sum(array_column($pagos, 'monto')), 2);

                if (abs($sumaPagos - $totalBs) > 0.01) {
                    throw new \Exception("Los montos del pago mixto deben sumar el total de la factura (Bs {$totalBs}).");
                }

                $detallePago = array_map(function ($p) {
                    return ['metodo' => $p['metodo'], 'monto' => round((float) $p['monto'], 2)];
                }, $pagos);
            }

            $factura = Factura::create([
                'correlativo' => $correlativo,
                'cliente_id' => $request->cliente_id,
                'user_id' => auth()->id(),
                'productos' => $itemsData,
                'tasa_cambio' => round($tasaEfectiva, 2),
                'metodo_pago' => $request->estado === 'credito' ? 'credito' : $request->metodo_pago,
                'detalle_pago' => $detallePago,
                'subtotal_bs' => $subtotalBs,
                'iva_bs' => $ivaBs,
                'total_bs' => $totalBs,
                'total_usd' => $totalUsd,
                'estado' => $request->estado,
                'estado_credito' => $request->estado === 'credito' ? 'pendiente' : null,
                'fecha_venta' => now(),
            ]);

            foreach ($itemsData as $item) {
                $item['factura_id'] = $factura->id;
                ItemFactura::create($item);
            }

            DB::commit();

            $impreso = false;
            $errorImpresion = null;

            if ((string) Configuracion::obtener('imprimir_al_facturar', '1') === '1') {
                try {
                    $configPrinter = PrinterService::configuracion();
                    $service = new PrinterService;
                    if ($service->connect($configPrinter['tipo'], $configPrinter['host'], $configPrinter['port'], $configPrinter['nombre'])) {
                        $factura->load('items.producto');
                        $impreso = $service->printReceipt($factura, PrinterService::itemsDesdeFactura($factura), auth()->user()->usuario);
                    }

                    if (! $impreso) {
                        $errorImpresion = 'no_impreso';
                    }
                } catch (\Exception $e) {
                    \Log::warning('Impresión automática de factura falló', [
                        'factura_id' => $factura->id,
                        'error' => $e->getMessage(),
                    ]);
                    $errorImpresion = 'no_impreso';
                }
            }

            return response()->json([
                'success' => true,
                'correlativo' => $correlativo,
                'factura_id' => $factura->id,
                'impreso' => $impreso,
                'impresion_error' => $errorImpresion,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function creditos()
    {
        $facturas = Factura::where('estado', 'credito')
            ->with('cliente')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $tasaReferencia = TasaCambio::ultimaDe(Configuracion::obtener('tasa_referencia', 'bcv'));
        $tasaVigente = $tasaReferencia ? (float) $tasaReferencia->monto : null;

        return view('facturas.creditos', compact('facturas', 'tasaVigente'));
    }

    public function show(Factura $factura)
    {
        $factura->load('cliente', 'items.producto');

        return view('facturas.partials.recibo', compact('factura'));
    }

    public function pagarCredito(Request $request, Factura $factura)
    {
        if ($factura->estado !== 'credito' || $factura->estado_credito === 'cancelado') {
            return back()->withErrors(['error' => 'Esta factura no está pendiente de crédito.']);
        }

        $request->validate([
            'metodo_pago' => ['required', Rule::in(CatalogoService::metodosValidos())],
        ]);

        $tasaReferencia = TasaCambio::ultimaDe(Configuracion::obtener('tasa_referencia', 'bcv'));

        if (! $tasaReferencia || (float) $tasaReferencia->monto <= 0) {
            return back()->withErrors(['error' => 'No hay tasa de referencia activa. Configúrala en Tasas de Cambio antes de cobrar.']);
        }

        $tasaVigente = (float) $tasaReferencia->monto;
        $pagoUsd = (float) $factura->total_usd;
        $pagoBs = round($pagoUsd * $tasaVigente, 2);

        $factura->update([
            'metodo_pago' => $request->metodo_pago,
            'estado_credito' => 'cancelado',
            'pago_bs' => $pagoBs,
            'fecha_pago' => now(),
        ]);

        return redirect()->route('facturas.creditos')
            ->with('success', 'Crédito N° '.$factura->correlativo.' cobrado correctamente: Bs '.Moneda::n($pagoBs).' (US$ '.Moneda::n($pagoUsd).').');
    }

    private function generarCorrelativo(): string
    {
        do {
            $correlativo = strtoupper(substr(uniqid(), -7));
        } while (Factura::where('correlativo', $correlativo)->exists());

        return $correlativo;
    }
}
