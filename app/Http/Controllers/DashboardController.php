<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Factura;
use App\Models\Producto;
use App\Models\TasaCambio;
use App\Services\CatalogoService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $hoy = now()->toDateString();
        $inicioMes = now()->startOfMonth()->toDateString();
        $ayer = now()->subDay()->toDateString();
        $inicioMesAnterior = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $mismoDiaMesAnterior = now()->subMonthNoOverflow()->toDateString();

        $hoyStats = Factura::ingresosEn($hoy, $hoy);
        $ayerStats = Factura::ingresosEn($ayer, $ayer);
        $mesStats = Factura::ingresosEn($inicioMes, $hoy);
        $mesAnteriorStats = Factura::ingresosEn($inicioMesAnterior, $mismoDiaMesAnterior);

        $creditosStats = Factura::where('estado', 'credito')
            ->where('estado_credito', 'pendiente')
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(total_bs), 0) as total_bs')
            ->first();

        $ventasHoy = $hoyStats->count();
        $totalHoyBs = round($hoyStats->sum('ingreso_bs'), 2);
        $totalHoyUsd = round($hoyStats->sum('ingreso_usd'), 2);

        $ventasMes = $mesStats->count();
        $totalMesBs = round($mesStats->sum('ingreso_bs'), 2);
        $totalMesUsd = round($mesStats->sum('ingreso_usd'), 2);

        $creditosPendientes = $creditosStats->total;
        $totalCreditosPendientesBs = $creditosStats->total_bs;

        $productosStockBajo = Producto::whereNull('deleted_at')
            ->where('estado', 'disponible')
            ->where('unidad_medida', 'unidad')
            ->where('stock_actual', '<=', CatalogoService::UMBRAL_STOCK_BAJO)
            ->orderBy('stock_actual')
            ->take(10)
            ->get();

        $totalProductos = Producto::whereNull('deleted_at')->count();
        $totalClientes = Cliente::count();

        $variacionHoy = $this->variacion((float) $totalHoyBs, (float) round($ayerStats->sum('ingreso_bs'), 2));
        $variacionMes = $this->variacion((float) $totalMesBs, (float) round($mesAnteriorStats->sum('ingreso_bs'), 2));

        $porDia7 = $this->porDia7();
        $totalSemanaBs = array_sum($porDia7);

        $metodosPago = CatalogoService::metodosPago();
        $metodosHoy = $this->metodosHoy($metodosPago);

        $ultimasFacturas = Factura::with('cliente')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        $creditosPendientesLista = Factura::where('estado', 'credito')
            ->where('estado_credito', 'pendiente')
            ->with('cliente')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        $tasasVigentes = TasaCambio::ultimasPorTipo();

        $masVendidos = DB::table('items_factura')
            ->join('productos', 'items_factura.producto_id', '=', 'productos.id')
            ->join('facturas', 'items_factura.factura_id', '=', 'facturas.id')
            ->whereDate('facturas.fecha_venta', '>=', now()->subDays(30)->toDateString())
            ->where('facturas.estado', '!=', 'anulada')
            ->select('productos.nombre', DB::raw('SUM(items_factura.cantidad) as total'))
            ->groupBy('productos.nombre')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        $nombreNegocio = Configuracion::obtener('nombre_negocio', config('app.name', 'Factus'));

        return view('dashboard', compact(
            'ventasHoy', 'totalHoyBs', 'totalHoyUsd',
            'ventasMes', 'totalMesBs', 'totalMesUsd',
            'creditosPendientes', 'totalCreditosPendientesBs',
            'productosStockBajo', 'totalProductos', 'totalClientes',
            'variacionHoy', 'variacionMes',
            'porDia7', 'totalSemanaBs',
            'metodosPago', 'metodosHoy',
            'ultimasFacturas', 'creditosPendientesLista',
            'tasasVigentes',
            'masVendidos',
            'nombreNegocio'
        ));
    }

    private function variacion(float $actual, float $anterior): ?float
    {
        if ($anterior <= 0) {
            return null;
        }

        return round((($actual - $anterior) / $anterior) * 100, 1);
    }

    private function porDia7(): array
    {
        $hoy = now()->toDateString();
        $desde = now()->subDays(6)->toDateString();
        $dias = [];
        for ($i = 0; $i < 7; $i++) {
            $dias[now()->subDays(6)->addDays($i)->format('Y-m-d')] = 0.0;
        }

        Factura::ingresosEn($desde, $hoy)
            ->each(function ($f) use (&$dias) {
                if (isset($dias[$f->fecha_ingreso])) {
                    $dias[$f->fecha_ingreso] += (float) $f->ingreso_bs;
                }
            });

        return array_map(fn ($monto) => round($monto, 2), $dias);
    }

    private function metodosHoy(array $metodosPago): array
    {
        $metodosHoy = array_fill_keys(array_keys($metodosPago), 0.0);

        Factura::ingresosEn(now()->toDateString(), now()->toDateString())
            ->each(function ($f) use (&$metodosHoy) {
                if ($f->metodo_pago === 'mixto') {
                    foreach ($f->detalle_pago ?? [] as $pago) {
                        if (isset($metodosHoy[$pago['metodo']])) {
                            $metodosHoy[$pago['metodo']] += (float) $pago['monto'];
                        }
                    }
                } elseif (isset($metodosHoy[$f->metodo_pago])) {
                    $metodosHoy[$f->metodo_pago] += (float) $f->ingreso_bs;
                }
            });

        return array_map(fn ($monto) => round($monto, 2), $metodosHoy);
    }
}
