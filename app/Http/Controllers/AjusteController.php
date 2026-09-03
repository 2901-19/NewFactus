<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\TasaCambio;
use App\Services\PrecioService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AjusteController extends Controller
{
    public function editarPrecios()
    {
        $tasas = TasaCambio::mapaMontos();

        return view('productos.ajustar-precios', compact('tasas'));
    }

    public function preciosData(Request $request)
    {
        $draw = (int) $request->integer('draw');
        $start = (int) $request->integer('start');
        $length = $request->integer('length');

        $query = Producto::whereNull('deleted_at')->with(['categoria', 'presentaciones']);

        $search = trim((string) $request->input('search.value'));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhereHas('categoria', fn ($c) => $c->whereRaw('LOWER(categorias.nombre) LIKE ?', ['%'.mb_strtolower($search).'%']));
            });
        }

        $total = (clone $query)->count();

        if ($length < 0) {
            $length = $total;
        }

        $query->orderBy('nombre');

        $productos = $query->offset($start)->limit($length ?: 100)->get();

        $data = $productos->map(function ($producto) {
            return [
                'producto_id' => $producto->id,
                'costo_usd' => (float) $producto->costo_usd,
                'nombre' => e($producto->nombre),
                'categoria' => $producto->categoria?->nombre ? e($producto->categoria->nombre) : '-',
                'presentaciones' => $producto->presentaciones->map(fn ($p) => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'margen' => (float) $p->margen,
                    'factor_conversion' => (float) $p->factor_conversion,
                    'fuente_tasa' => $p->fuente_tasa,
                ])->toArray(),
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => Producto::whereNull('deleted_at')->count(),
            'recordsFiltered' => $total,
            'data' => $data,
        ]);
    }

    public function guardarPrecio(Request $request, Producto $producto)
    {
        try {
            $validated = $request->validate([
                'costo_usd' => 'required|numeric|min:0',
                'presentaciones' => 'required|array|min:1',
                'presentaciones.*.id' => 'required|exists:producto_presentaciones,id',
                'presentaciones.*.margen' => 'required|numeric|min:0',
            ]);

            $producto->update(['costo_usd' => (float) $validated['costo_usd']]);

            $resultados = [];
            foreach ($validated['presentaciones'] as $pres) {
                $margen = (float) $pres['margen'];
                $precio = PrecioService::precioPresentacion((float) $validated['costo_usd'], $margen, (float) $this->factorDe($producto, $pres['id']));

                $producto->presentaciones()->whereKey($pres['id'])->update([
                    'margen' => $margen,
                    'precio_usd' => $precio,
                ]);

                $resultados[] = ['id' => $pres['id'], 'margen' => $margen, 'precio_usd' => $precio];
            }

            $producto->load('presentaciones');

            return response()->json([
                'success' => true,
                'message' => 'Precios actualizados correctamente',
                'presentaciones' => $resultados,
                'precio_base_usd' => $producto->precio_base,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->first()[0],
            ], 422);
        }
    }

    private function factorDe(Producto $producto, $id): float
    {
        return (float) ($producto->presentaciones()->whereKey($id)->value('factor_conversion') ?? 1);
    }

    public function editarInventario()
    {
        $productos = Producto::whereNull('deleted_at')
            ->where('unidad_medida', 'unidad')
            ->with('categoria')
            ->get();

        return view('productos.ajustar-inventario', compact('productos'));
    }

    public function ajustarInventario(Request $request, Producto $producto)
    {
        try {
            $validated = $request->validate([
                'cantidad' => 'required|numeric|min:0.01',
                'operacion' => 'required|in:+,-',
            ]);

            if (! $producto->controla_inventario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este producto no lleva control de inventario',
                ], 422);
            }

            $totalActual = (float) $producto->stock_actual;
            $cantidad = (float) $validated['cantidad'];

            if ($validated['operacion'] === '+') {
                $nuevoTotal = $totalActual + $cantidad;
            } else {
                $nuevoTotal = $totalActual - $cantidad;
                if ($nuevoTotal < 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No puedes restar más de lo que hay en stock',
                    ], 422);
                }
            }

            $producto->update(['stock_actual' => $nuevoTotal]);

            return response()->json([
                'success' => true,
                'message' => 'Inventario actualizado correctamente',
                'stock_actual' => $nuevoTotal,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->first()[0],
            ], 422);
        }
    }
}
