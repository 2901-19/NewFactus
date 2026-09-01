<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\ItemFactura;
use App\Models\Producto;
use App\Services\CatalogoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    // ===== REPORTE DE VENTAS =====

    public function facturas(Request $request)
    {
        [$desde, $hasta] = $this->rangoPeriodo($request);

        $facturas = $this->queryFacturas($request, $desde, $hasta)
            ->orderBy('fecha_venta', 'desc')
            ->get();

        $kpis = $this->kpis($request, $desde, $hasta);

        $desglose = $this->desgloseMetodo(
            Factura::ingresosEn($desde, $hasta, $request->cliente_id)
        );

        if ($request->get('export') === 'pdf') {
            $pdf = Pdf::loadView('reportes.export.facturas-pdf', compact('facturas', 'kpis', 'desglose', 'desde', 'hasta'));

            return $pdf->setPaper('letter', 'portrait')
                ->download('reporte_facturas_'.date('Ymd').'.pdf');
        }

        if ($request->get('export') === 'csv') {
            return $this->facturasCsv($facturas);
        }

        $clientes = Cliente::orderBy('nombre')->get();
        $nombresMetodo = CatalogoService::metodosPago();

        return view('reportes.facturas', compact('facturas', 'kpis', 'desglose', 'desde', 'hasta', 'clientes', 'nombresMetodo'));
    }

    // ===== ESTADÍSTICAS =====

    public function estadisticas(Request $request)
    {
        [$desde, $hasta] = $this->rangoPeriodo($request);

        $kpis = $this->kpis($request, $desde, $hasta);

        $facturas = Factura::ingresosEn($desde, $hasta);

        $porDia = $this->ventasPorDia($facturas, $desde, $hasta);
        $porMetodo = $this->desgloseMetodo($facturas);

        [$semanaLabels, $seriesPresentaciones, $unidadesSeries, $resumenes, $colores, $agrupadoPor] = $this->ventasPorPresentacion($desde, $hasta, $facturas);
        $topProductos = $this->topProductos($facturas);
        $topClientes = $this->topClientes($facturas);

        $creditos = Factura::where('estado', 'credito')
            ->where('estado_credito', 'pendiente')
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total_bs), 0) as total_bs')
            ->first();

        $nombresMetodo = CatalogoService::metodosPago();

        return view('reportes.estadisticas', compact(
            'kpis', 'desde', 'hasta', 'porDia', 'porMetodo',
            'semanaLabels', 'seriesPresentaciones', 'unidadesSeries', 'resumenes', 'colores', 'agrupadoPor',
            'topProductos', 'topClientes', 'creditos', 'nombresMetodo'
        ));
    }

    // ===== BALANCE MENSUAL =====

    public function balance(Request $request)
    {
        $anio = (int) $request->get('anio', now()->year);

        $facturas = Factura::ingresosEn("{$anio}-01-01", "{$anio}-12-31");

        $mensual = [];
        foreach ($facturas as $f) {
            $mes = (int) Carbon::parse($f->fecha_ingreso)->format('n');
            if (! isset($mensual[$mes])) {
                $mensual[$mes] = ['cantidad' => 0, 'total_bs' => 0.0, 'total_usd' => 0.0];
            }
            $mensual[$mes]['cantidad']++;
            $mensual[$mes]['total_bs'] += (float) $f->ingreso_bs;
            $mensual[$mes]['total_usd'] += (float) $f->ingreso_usd;
        }
        ksort($mensual);

        foreach ($mensual as &$m) {
            $m['total_bs'] = round($m['total_bs'], 2);
            $m['total_usd'] = round($m['total_usd'], 2);
        }

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        $serieBs = array_map(fn ($n) => $mensual[$n]['total_bs'] ?? 0, range(1, 12));

        if ($request->get('export') === 'pdf') {
            $pdf = Pdf::loadView('reportes.export.balance-pdf', compact('mensual', 'meses', 'anio'));

            return $pdf->setPaper('letter', 'portrait')
                ->download('balance_mensual_'.$anio.'.pdf');
        }

        return view('reportes.balance', compact('mensual', 'meses', 'anio', 'serieBs'));
    }

    // ===== STOCK BAJO =====

    public function stock()
    {
        $productos = Producto::whereNull('deleted_at')
            ->where('estado', 'disponible')
            ->where('unidad_medida', 'unidad')
            ->where('stock_actual', '<=', CatalogoService::UMBRAL_STOCK_BAJO)
            ->orderBy('stock_actual')
            ->get();

        return view('reportes.stock', compact('productos'));
    }

    // ===== HELPERS =====

    private function rangoPeriodo(Request $request): array
    {
        $desde = $request->get('desde', now()->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());

        if (strtotime($desde) > strtotime($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde, $hasta];
    }

    private function queryFacturas(Request $request, string $desde, string $hasta, bool $conMetodo = true)
    {
        return Factura::with('cliente', 'user')
            ->where('estado', '!=', 'anulada')
            ->whereDate('fecha_venta', '>=', $desde)
            ->whereDate('fecha_venta', '<=', $hasta)
            ->when($conMetodo && $request->filled('metodo_pago'), fn ($q) => $q->where('metodo_pago', $request->metodo_pago))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('cliente_id'), fn ($q) => $q->where('cliente_id', $request->cliente_id));
    }

    private function kpis(Request $request, string $desde, string $hasta): array
    {
        $facturas = Factura::ingresosEn($desde, $hasta, $request->cliente_id, $request->metodo_pago);

        $cantidad = $facturas->count();
        $totalBs = round($facturas->sum('ingreso_bs'), 2);
        $totalUsd = round($facturas->sum('ingreso_usd'), 2);

        return [
            'cantidad' => $cantidad,
            'total_bs' => $totalBs,
            'total_usd' => $totalUsd,
            'iva_bs' => round($facturas->sum('iva_bs'), 2),
            'ticket_promedio' => $cantidad > 0 ? round($totalBs / $cantidad, 2) : 0,
        ];
    }

    private function desgloseMetodo(Collection $facturas): array
    {
        $desglose = array_fill_keys(array_keys(CatalogoService::metodosPago()), 0.0);

        foreach ($facturas as $f) {
            if ($f->metodo_pago === 'mixto') {
                foreach ($f->detalle_pago ?? [] as $pago) {
                    if (isset($desglose[$pago['metodo']])) {
                        $desglose[$pago['metodo']] += (float) $pago['monto'];
                    }
                }
            } elseif (isset($desglose[$f->metodo_pago])) {
                $desglose[$f->metodo_pago] += (float) $f->ingreso_bs;
            }
        }

        foreach ($desglose as &$monto) {
            $monto = round($monto, 2);
        }

        return $desglose;
    }

    private function ventasPorDia(Collection $facturas, string $desde, string $hasta): array
    {
        $porDia = [];
        $cursor = Carbon::parse($desde);
        $fin = Carbon::parse($hasta);
        while ($cursor->lte($fin)) {
            $porDia[$cursor->format('Y-m-d')] = 0.0;
            $cursor->addDay();
        }

        foreach ($facturas as $f) {
            $clave = $f->fecha_ingreso ?? null;
            if ($clave && isset($porDia[$clave])) {
                $porDia[$clave] += (float) $f->ingreso_bs;
            }
        }

        foreach ($porDia as &$valor) {
            $valor = round($valor, 2);
        }

        return $porDia;
    }

    private function ventasPorPresentacion(string $desde, string $hasta, Collection $facturas): array
    {
        $porId = $facturas->keyBy('id');

        $items = ItemFactura::with('factura')
            ->whereIn('factura_id', $porId->keys())
            ->get(['factura_id', 'cantidad', 'presentacion_nombre', 'subtotal']);

        $porDia = Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) <= 6;

        $acumulado = [];

        foreach ($items as $item) {
            $factura = $porId[$item->factura_id] ?? null;
            if (! $factura || ! $factura->fecha_ingreso) {
                continue;
            }
            $clave = $porDia ? $factura->fecha_ingreso : Carbon::parse($factura->fecha_ingreso)->startOfWeek()->format('Y-m-d');
            $nombre = $item->presentacion_nombre ?: 'Unidad';
            $acumulado[$nombre][$clave]['bs'] = ($acumulado[$nombre][$clave]['bs'] ?? 0) + (float) $item->subtotal;
            $acumulado[$nombre][$clave]['unidades'] = ($acumulado[$nombre][$clave]['unidades'] ?? 0) + (float) $item->cantidad;
        }

        $claves = [];
        if ($porDia) {
            $cursor = Carbon::parse($desde);
            $fin = Carbon::parse($hasta);
            while ($cursor->lte($fin)) {
                $claves[] = $cursor->format('Y-m-d');
                $cursor->addDay();
            }
        } else {
            $cursor = Carbon::parse($desde)->startOfWeek();
            $fin = Carbon::parse($hasta);
            while ($cursor->lte($fin)) {
                $claves[] = $cursor->format('Y-m-d');
                $cursor->addWeek();
            }
        }

        $totalPorNombre = [];
        foreach ($acumulado as $nombre => $porClave) {
            $totalPorNombre[$nombre] = array_sum(array_column($porClave, 'bs'));
        }

        $totalBs = round(array_sum($totalPorNombre), 2);
        $pct = fn (float $valor) => $totalBs > 0 ? round(($valor / $totalBs) * 100, 1) : 0;

        uksort($totalPorNombre, fn ($a, $b) => $totalPorNombre[$b] <=> $totalPorNombre[$a]);
        $nombres = array_keys($totalPorNombre);

        $paleta = ['#198754', '#fd7e14', '#0d6efd', '#dc3545', '#6f42c1', '#0dcaf0', '#d63384', '#ffc107'];
        $colores = [];
        foreach ($nombres as $i => $nombre) {
            $colores[$nombre] = $paleta[$i % count($paleta)];
        }

        $semanaLabels = array_map(
            fn ($clave) => $porDia ? Carbon::parse($clave)->format('d/m') : 'Sem '.Carbon::parse($clave)->format('d/m'),
            $claves
        );

        $series = [];
        $unidadesSeries = [];
        $resumenes = [];

        foreach ($nombres as $nombre) {
            $series[$nombre] = array_map(fn ($clave) => round($acumulado[$nombre][$clave]['bs'] ?? 0, 2), $claves);
            $unidadesSeries[$nombre] = array_map(fn ($clave) => $acumulado[$nombre][$clave]['unidades'] ?? 0, $claves);
            $resumenes[$nombre] = [
                'bs' => round($totalPorNombre[$nombre], 2),
                'unidades' => array_sum(array_column($acumulado[$nombre] ?? [], 'unidades')),
                'pct' => $pct($totalPorNombre[$nombre]),
            ];
        }

        return [
            $semanaLabels,
            $series,
            $unidadesSeries,
            $resumenes,
            $colores,
            $porDia ? 'día' : 'semana',
        ];
    }

    private function topProductos(Collection $facturas): Collection
    {
        $porId = $facturas->keyBy('id');

        return ItemFactura::whereIn('factura_id', $porId->keys())
            ->join('productos', 'items_factura.producto_id', '=', 'productos.id')
            ->select(
                'productos.nombre',
                DB::raw('SUM(items_factura.cantidad) as unidades'),
                DB::raw('SUM(items_factura.subtotal) as ingreso_bs')
            )
            ->groupBy('productos.nombre')
            ->orderByDesc('ingreso_bs')
            ->take(10)
            ->get();
    }

    private function topClientes(Collection $facturas): Collection
    {
        return $facturas->whereNotNull('cliente_id')
            ->groupBy('cliente_id')
            ->map(fn ($grupo) => [
                'nombre' => $grupo->first()->cliente?->nombre ?? '—',
                'facturas' => $grupo->count(),
                'total_bs' => round($grupo->sum('ingreso_bs'), 2),
            ])
            ->sortByDesc('total_bs')
            ->take(5)
            ->values();
    }

    private function facturasCsv(Collection $facturas)
    {
        $csv = "\xEF\xBB\xBF";
        $csv .= "Correlativo;Cliente;Metodo;Estado;Fecha;Subtotal Bs;IVA Bs;Total Bs;Total USD\n";

        foreach ($facturas as $f) {
            $csv .= implode(';', [
                $f->correlativo,
                $f->cliente->nombre ?? 'Contado',
                CatalogoService::nombreMetodo($f->metodo_pago) ?? $f->metodo_pago,
                $f->estado,
                $f->fecha_venta?->format('Y-m-d') ?? $f->fecha_venta,
                number_format($f->subtotal_bs, 2, ',', ''),
                number_format($f->iva_bs, 2, ',', ''),
                number_format($f->total_bs, 2, ',', ''),
                number_format($f->total_usd, 2, ',', ''),
            ])."\n";
        }

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'reporte_facturas_'.date('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
