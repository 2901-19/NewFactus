<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\Factura;
use App\Models\Impuesto;
use App\Models\Producto;
use App\Models\TasaCambio;
use App\Services\PrecioService;
use App\Services\PrinterService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HerramientasController extends Controller
{
    // ========== DATOS (Export/Import) ==========

    public function datos()
    {
        $tiposDisponibles = [
            'precios' => 'Precios de productos',
            'inventario' => 'Inventario de productos',
            'clientes' => 'Clientes',
            'tasas_cambio' => 'Tasas de cambio',
            'categorias' => 'Categorías',
        ];

        return view('herramientas.datos', compact('tiposDisponibles'));
    }

    public function exportar(Request $request)
    {
        $tipos = $request->input('tipos', []);
        $data = ['exportado_en' => now()->toDateTimeString()];

        if (in_array('precios', $tipos)) {
            $data['precios'] = Producto::with(['presentaciones', 'impuesto'])->get()->map(fn ($p) => [
                'nombre' => $p->nombre,
                'costo_usd' => $p->costo_usd,
                'impuesto' => $p->impuesto?->nombre,
                'presentaciones' => $p->presentaciones->map(fn ($pr) => [
                    'nombre' => $pr->nombre,
                    'factor_conversion' => $pr->factor_conversion,
                    'margen' => $pr->margen,
                    'precio_usd' => $pr->precio_usd,
                    'fuente_tasa' => $pr->fuente_tasa,
                    'activa' => $pr->activa,
                ])->values(),
            ]);
        }

        if (in_array('inventario', $tipos)) {
            $data['inventario'] = Producto::all()->map(fn ($p) => [
                'nombre' => $p->nombre,
                'categoria_id' => $p->categoria_id,
                'descripcion' => $p->descripcion,
                'imagen' => $p->imagen,
                'controla_inventario' => $p->controla_inventario,
                'unidad_medida' => $p->unidad_medida,
                'estado' => $p->estado,
            ]);
        }

        if (in_array('clientes', $tipos)) {
            $data['clientes'] = Cliente::all();
        }

        if (in_array('tasas_cambio', $tipos)) {
            $data['tasas_cambio'] = TasaCambio::all();
        }

        if (in_array('categorias', $tipos)) {
            $data['categorias'] = Categoria::all();
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'backup_'.now()->format('Y_m_d_His').'.json';

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function importar(Request $request)
    {
        if (auth()->user()->rol !== 'admin') {
            return back()->withErrors(['archivo' => 'Solo el administrador puede importar datos.']);
        }

        $request->validate([
            'archivo' => 'required|file|mimes:json,txt|max:10240',
        ]);

        $contenido = file_get_contents($request->file('archivo')->getRealPath());
        $data = json_decode($contenido, true);

        if (! $data) {
            return back()->withErrors(['archivo' => 'El archivo JSON no tiene el formato correcto.']);
        }

        // Detectar formato antiguo (con 'productos' en lugar de 'precios'/'inventario')
        $formatoAntiguo = isset($data['productos']) && ! isset($data['precios']) && ! isset($data['inventario']);

        $tipos = $request->input('tipos', []);

        // Si no se seleccionó ningún tipo, procesar todo lo disponible
        if (empty($tipos)) {
            if ($formatoAntiguo) {
                $tipos = ['productos', 'clientes', 'impuestos', 'tasas_cambio'];
            } else {
                $tipos = array_keys(array_intersect_key($data, array_flip(['precios', 'inventario', 'clientes', 'tasas_cambio', 'categorias'])));
            }
        }

        DB::beginTransaction();
        try {
            $contadores = ['precios' => 0, 'inventario' => 0, 'clientes' => 0, 'tasas_cambio' => 0, 'categorias' => 0];

            foreach ($tipos as $key) {
                if (! isset($data[$key]) || ! is_array($data[$key])) {
                    continue;
                }

                switch ($key) {
                    case 'precios':
                        foreach ($data['precios'] as $item) {
                            $producto = Producto::where('nombre', $item['nombre'])->first();
                            $costoUsd = (float) ($item['costo_usd'] ?? $producto->costo_usd ?? 0);

                            $presentaciones = $item['presentaciones'] ?? [];
                            if (empty($presentaciones)) {
                                $presentaciones = $this->presentacionesDesdeLegacy($item, $costoUsd);
                            }

                            // Retrocompatibilidad: la tasa vivía a nivel de producto
                            $datosPresentaciones = collect($presentaciones)->map(fn ($pr) => [
                                'nombre' => $pr['nombre'] ?? 'Unidad',
                                'factor_conversion' => (float) ($pr['factor_conversion'] ?? 1),
                                'margen' => (float) ($pr['margen'] ?? 0),
                                'precio_usd' => PrecioService::precioPresentacion(
                                    $costoUsd,
                                    (float) ($pr['margen'] ?? 0),
                                    (float) ($pr['factor_conversion'] ?? 1)
                                ),
                                'fuente_tasa' => $pr['fuente_tasa'] ?? ($item['fuente_tasa'] ?? 'promedio'),
                                'activa' => (bool) ($pr['activa'] ?? true),
                            ])->values()->all();

                            $estadoImportado = collect($datosPresentaciones)->contains(fn ($pr) => $pr['precio_usd'] > 0) ? 'disponible' : 'no_disponible';

                            $impuestoId = $this->impuestoIdDesdeItem($item, $producto?->impuesto_id);

                            if ($producto) {
                                $producto->update([
                                    'costo_usd' => $costoUsd,
                                    'impuesto_id' => $impuestoId,
                                    'estado' => $estadoImportado,
                                ]);
                                $producto->presentaciones()->delete();
                                $producto->presentaciones()->createMany($datosPresentaciones);
                            } else {
                                $producto = Producto::create([
                                    'nombre' => $item['nombre'],
                                    'costo_usd' => $costoUsd,
                                    'impuesto_id' => $impuestoId,
                                    'estado' => $estadoImportado,
                                ]);
                                $producto->presentaciones()->createMany($datosPresentaciones);
                            }
                            $contadores['precios']++;
                        }
                        break;

                    case 'inventario':
                        foreach ($data['inventario'] as $item) {
                            $existe = Producto::where('nombre', $item['nombre'])->exists();
                            if (! $existe) {
                                $unidad = $this->normalizarUnidadMedida($item['unidad_medida'] ?? 'unidad');
                                $esPesable = $unidad === 'kg';
                                $producto = Producto::create([
                                    'nombre' => $item['nombre'],
                                    'categoria_id' => $item['categoria_id'] ?? null,
                                    'descripcion' => $item['descripcion'] ?? '',
                                    'imagen' => $item['imagen'] ?? null,
                                    'unidad_medida' => $unidad,
                                    'stock_actual' => $esPesable ? 0 : (float) ($item['stock_actual'] ?? 0),
                                    'estado' => 'no_disponible',
                                    'costo_usd' => 0,
                                    'impuesto_id' => null,
                                ]);
                                $producto->presentaciones()->create([
                                    'nombre' => $esPesable ? 'Kilogramo' : 'Unidad',
                                    'factor_conversion' => 1,
                                    'margen' => 0,
                                    'precio_usd' => 0,
                                    'fuente_tasa' => 'promedio',
                                    'activa' => true,
                                ]);
                                $contadores['inventario']++;
                            }
                        }
                        break;

                    case 'clientes':
                        foreach ($data['clientes'] as $item) {
                            $fillable = (new Cliente)->getFillable();
                            $item = array_intersect_key($item, array_flip($fillable));
                            unset($item['id']);

                            $existe = Cliente::where('ci', $item['ci'] ?? '')->exists();
                            if (! $existe) {
                                Cliente::create($item);
                                $contadores['clientes']++;
                            }
                        }
                        break;

                    case 'tasas_cambio':
                        foreach ($data['tasas_cambio'] as $item) {
                            $fillable = (new TasaCambio)->getFillable();
                            $item = array_intersect_key($item, array_flip($fillable));
                            unset($item['id']);

                            if (empty($item['tipo'])) {
                                continue;
                            }

                            $item['origen'] = $item['origen'] ?? 'importado';
                            $item['fecha'] = $item['fecha'] ?? now()->toDateString();

                            if ((float) ($item['monto'] ?? 0) <= 0) {
                                continue;
                            }

                            $vigente = TasaCambio::ultimaDe($item['tipo']);

                            TasaCambio::create([
                                'tipo' => $item['tipo'],
                                'nombre' => $item['nombre'] ?? $vigente->nombre ?? null,
                                'monto' => $item['monto'],
                                'fecha' => $item['fecha'],
                                'activo' => $item['activo'] ?? ($vigente ? $vigente->activo : true),
                                'user_id' => auth()->id(),
                                'origen' => 'importado',
                            ]);
                            $contadores['tasas_cambio']++;
                        }
                        break;

                    case 'categorias':
                        foreach ($data['categorias'] as $item) {
                            $existe = Categoria::where('nombre', $item['nombre'])->exists();
                            if (! $existe) {
                                Categoria::create([
                                    'nombre' => $item['nombre'],
                                ]);
                                $contadores['categorias']++;
                            }
                        }
                        break;

                        // Compatibilidad con formato antiguo
                    case 'productos':
                        foreach ($data['productos'] as $item) {
                            if (empty($item['nombre'])) {
                                continue;
                            }

                            $costoUsd = (float) ($item['costo_usd'] ?? 0);
                            $unidad = $this->normalizarUnidadMedida($item['unidad_medida'] ?? 'unidad');
                            $esPesable = $unidad === 'kg';
                            // Retrocompatibilidad: en el formato antiguo la tasa era del producto
                            $fuenteTasa = $item['fuente_tasa'] ?? 'promedio';
                            $presentaciones = $esPesable
                                ? [[
                                    'nombre' => 'Kilogramo',
                                    'factor_conversion' => 1,
                                    'margen' => 0,
                                    'precio_usd' => PrecioService::precioPresentacion($costoUsd, 0, 1),
                                    'fuente_tasa' => $fuenteTasa,
                                    'activa' => true,
                                ]]
                                : $this->presentacionesDesdeLegacy($item, $costoUsd, $fuenteTasa);
                            $estadoImportado = collect($presentaciones)->contains(fn ($pr) => $pr['precio_usd'] > 0) ? 'disponible' : 'no_disponible';

                            $producto = Producto::create([
                                'nombre' => $item['nombre'],
                                'categoria_id' => $item['categoria_id'] ?? null,
                                'descripcion' => $item['descripcion'] ?? '',
                                'imagen' => $item['imagen'] ?? null,
                                'costo_usd' => $costoUsd,
                                'stock_actual' => $esPesable ? 0 : (float) ($item['stock_actual'] ?? 0),
                                'unidad_medida' => $unidad,
                                'impuesto_id' => $item['impuesto_id'] ?? null,
                                'estado' => $estadoImportado,
                            ]);
                            $producto->presentaciones()->createMany($presentaciones);
                            $contadores['precios']++;
                        }
                        break;

                    case 'impuestos':
                        foreach ($data['impuestos'] as $item) {
                            $fillable = (new Impuesto)->getFillable();
                            $item = array_intersect_key($item, array_flip($fillable));
                            unset($item['id']);
                            Impuesto::create($item);
                        }
                        break;
                }
            }

            DB::commit();

            $partes = [];
            if ($contadores['precios']) {
                $partes[] = $contadores['precios'].' precios';
            }
            if ($contadores['inventario']) {
                $partes[] = $contadores['inventario'].' inventarios';
            }
            if ($contadores['clientes']) {
                $partes[] = $contadores['clientes'].' clientes';
            }
            if ($contadores['tasas_cambio']) {
                $partes[] = $contadores['tasas_cambio'].' tasas';
            }
            if ($contadores['categorias']) {
                $partes[] = $contadores['categorias'].' categorías';
            }

            $mensaje = 'Importación completada: '.($partes ? implode(', ', $partes) : 'no se procesaron datos');

            return back()->with('success', $mensaje);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['archivo' => 'Error durante la importación: '.$e->getMessage()]);
        }
    }

    // ========== IMPRESIÓN ==========

    public function imprimirConfig()
    {
        $config = $this->getPrinterConfig();

        return view('herramientas.impresora', compact('config'));
    }

    public function imprimirGuardar(Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:network,windows',
            'host' => 'required_if:tipo,network|ip|nullable',
            'port' => 'required_if:tipo,network|integer|nullable',
            'nombre' => 'required_if:tipo,windows|string|nullable',
        ]);

        $config = [
            'tipo' => $request->tipo,
            'host' => $request->host ?? '',
            'port' => (int) ($request->port ?? 9100),
            'nombre' => $request->nombre ?? '',
        ];

        file_put_contents(
            storage_path('app/impresora.json'),
            json_encode($config, JSON_PRETTY_PRINT)
        );

        return back()->with('success', 'Configuración de impresora guardada.');
    }

    public function imprimirTest(Request $request)
    {
        $config = $this->getPrinterConfig();
        $service = new PrinterService;

        $ok = $service->connect($config['tipo'], $config['host'], $config['port'], $config['nombre']);

        if (! $ok) {
            return back()->withErrors(['error' => 'No se pudo conectar a la impresora. Verifique la configuración.']);
        }

        $ok = $service->printTest();

        if (! $ok) {
            return back()->withErrors(['error' => 'Error al imprimir la prueba.']);
        }

        return back()->with('success', 'Prueba de impresión enviada correctamente.');
    }

    private function getPrinterConfig()
    {
        $path = storage_path('app/impresora.json');
        if (file_exists($path)) {
            $config = json_decode(file_get_contents($path), true);
            $config['port'] = (int) ($config['port'] ?? 9100);

            return $config;
        }

        return [
            'tipo' => 'network',
            'host' => '192.168.1.100',
            'port' => 9100,
            'nombre' => '',
        ];
    }

    public function imprimirFactura($factura)
    {
        $factura = Factura::with('cliente', 'items.producto')->findOrFail($factura);
        $items = $factura->items->map(function ($item) {
            $nombre = $item->producto->nombre ?? 'Producto';
            if ($item->presentacion_nombre) {
                $nombre .= ' ('.$item->presentacion_nombre.')';
            }

            return [
                'nombre' => $nombre,
                'precio_unitario' => $item->precio_unitario_bs,
                'cantidad' => $item->cantidad,
                'total' => $item->subtotal,
                'pesable' => $item->unidad_medida === 'kg',
            ];
        })->toArray();

        $config = $this->getPrinterConfig();
        $service = new PrinterService;
        $ok = $service->connect($config['tipo'], $config['host'], $config['port'], $config['nombre']);

        if (! $ok) {
            return back()->withErrors(['error' => 'No se pudo conectar a la impresora.']);
        }

        $ok = $service->printReceipt($factura, $items, auth()->user()->usuario);

        if (! $ok) {
            return back()->withErrors(['error' => 'Error al imprimir el ticket.']);
        }

        return back()->with('success', 'Ticket impreso correctamente.');
    }

    public function imprimirPrecioProducto(Request $request)
    {
        $data = $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'presentacion_id' => 'required|exists:producto_presentaciones,id',
        ]);

        $producto = Producto::with('presentaciones')->findOrFail($data['producto_id']);
        $presentacion = $producto->presentaciones->firstWhere('id', (int) $data['presentacion_id']);

        if (! $presentacion) {
            return back()->withErrors(['error' => 'La presentación seleccionada no pertenece a este producto.']);
        }

        $montos = TasaCambio::mapaMontos();
        $tasa = $montos[$presentacion->fuente_tasa] ?? null;

        if (! $tasa) {
            return back()->withErrors(['error' => "La presentación '{$presentacion->nombre}' no tiene la tasa de cambio '{$presentacion->fuente_tasa}' configurada. Configure la tasa en Tasas de Cambio."]);
        }

        $negocio = Configuracion::obtener('nombre_negocio', config('app.name', 'Factus'));

        $config = $this->getPrinterConfig();
        $service = new PrinterService;
        $ok = $service->connect($config['tipo'], $config['host'], $config['port'], $config['nombre']);

        if (! $ok) {
            return back()->withErrors(['error' => 'No se pudo conectar a la impresora.']);
        }

        $ok = $service->printPrecioProducto([
            'negocio' => $negocio,
            'producto' => $producto->nombre,
            'presentacion' => $presentacion->nombre,
            'precio_bs' => $presentacion->precio_usd * $tasa,
        ]);

        if (! $ok) {
            return back()->withErrors(['error' => 'Error al imprimir la etiqueta de precio.']);
        }

        return back()->with('success', 'Etiqueta de precio impresa correctamente.');
    }

    // ========== PDF LISTA DE PRECIOS ==========

    public function precios(Request $request)
    {
        $categoriaId = $request->integer('categoria_id');
        $productos = $this->productosParaPrecios($categoriaId);
        $categorias = Categoria::orderBy('nombre')->get();

        $tasas = TasaCambio::mapaMontos();

        if ($request->query('export') === 'json') {
            $data = $productos->map(fn ($p) => [
                'nombre' => $p->nombre,
                'costo_usd' => $p->costo_usd,
                'impuesto' => $p->impuesto?->nombre,
                'presentaciones' => $p->presentaciones->map(fn ($pr) => [
                    'nombre' => $pr->nombre,
                    'factor_conversion' => $pr->factor_conversion,
                    'margen' => $pr->margen,
                    'precio_usd' => $pr->precio_usd,
                    'fuente_tasa' => $pr->fuente_tasa,
                    'precio_bs' => round($pr->precio_usd * ($tasas[$pr->fuente_tasa] ?? 1), 2),
                    'activa' => $pr->activa,
                ])->values(),
            ]);

            return response()->json($data);
        }

        return view('herramientas.precios', compact('productos', 'tasas', 'categorias', 'categoriaId'));
    }

    public function preciosPdf(Request $request)
    {
        $categoriaId = $request->integer('categoria_id');
        $productos = $this->productosParaPrecios($categoriaId);
        $categoria = $categoriaId ? Categoria::find($categoriaId) : null;

        $tasas = TasaCambio::mapaMontos();
        $fecha = now()->format('d/m/Y H:i');

        $sufijo = $categoria ? '_'.Str::slug($categoria->nombre) : '';
        $pdf = Pdf::loadView('herramientas.precios-pdf', compact('productos', 'tasas', 'fecha', 'categoria'));
        $pdf->setPaper('letter', 'portrait');

        return $pdf->download('lista_precios'.$sufijo.'_'.now()->format('Y_m_d').'.pdf');
    }

    private function productosParaPrecios(int $categoriaId)
    {
        return Producto::whereNull('deleted_at')
            ->where('estado', 'disponible')
            ->when($categoriaId, fn ($q) => $q->where('categoria_id', $categoriaId))
            ->with(['presentaciones', 'impuesto'])
            ->orderBy('nombre')
            ->get();
    }

    // ========== CONFIGURACIÓN DEL NEGOCIO ==========

    public function configuracion()
    {
        $configs = Configuracion::pluck('valor', 'clave')->toArray();

        return view('herramientas.configuracion', compact('configs'));
    }

    public function configuracionGuardar(Request $request)
    {
        $request->validate([
            'nombre_negocio' => 'required|string|max:255',
            'rif' => 'required|string|max:20',
            'direccion' => 'required|string|max:255',
            'telefono' => 'required|string|max:20',
        ]);

        foreach (['nombre_negocio', 'rif', 'direccion', 'telefono'] as $clave) {
            Configuracion::updateOrCreate(
                ['clave' => $clave],
                ['valor' => $request->$clave]
            );
            Configuracion::olvidar($clave);
        }

        return back()->with('success', 'Configuración guardada correctamente.');
    }

    public function recordatorioGuardar(Request $request)
    {
        $request->validate([
            // El switch no envía valor cuando se desmarca: se persiste siempre.
            'recordatorio_tasa_activo' => 'nullable|boolean',
            'recordatorio_tasa_hora1' => ['required', 'date_format:H:i'],
            'recordatorio_tasa_hora2' => ['required', 'date_format:H:i', 'after:recordatorio_tasa_hora1'],
        ], [
            'recordatorio_tasa_hora2.after' => 'La segunda hora debe ser posterior a la primera.',
        ]);

        foreach ([
            'recordatorio_tasa_activo' => $request->boolean('recordatorio_tasa_activo') ? '1' : '0',
            'recordatorio_tasa_hora1' => $request->input('recordatorio_tasa_hora1'),
            'recordatorio_tasa_hora2' => $request->input('recordatorio_tasa_hora2'),
        ] as $clave => $valor) {
            Configuracion::updateOrCreate(
                ['clave' => $clave],
                ['valor' => $valor]
            );
            Configuracion::olvidar($clave);
        }

        return back()->with('success', 'Recordatorio de tasa guardado correctamente.');
    }

    private function impuestoIdDesdeItem(array $item, ?int $actual = null): ?int
    {
        if (array_key_exists('impuesto_id', $item)) {
            $id = (int) ($item['impuesto_id'] ?? 0);

            return $id ?: null;
        }

        $nombre = $item['impuesto'] ?? null;
        if ($nombre === null && array_key_exists('tiene_iva', $item)) {
            $nombre = $item['tiene_iva'] ? 'IVA' : null;
        }

        if ($nombre === null) {
            return $actual;
        }

        return Impuesto::where('nombre', $nombre)->value('id') ?? $actual;
    }

    /**
     * Construye presentaciones a partir del formato antiguo de productos
     * (margen_detal/mayor, precio_unitario_usd/precio_mayor_usd). Coherente
     * con el esquema unificado de presentaciones.
     */
    private function presentacionesDesdeLegacy(array $item, float $costoUsd, ?string $fuenteTasa = null): array
    {
        $margenDetal = $item['margen_detal'] ?? null;
        if ($margenDetal === null) {
            $pu = $item['precio_unitario_usd'] ?? null;
            $margenDetal = ($pu !== null && $costoUsd > 0) ? round((($pu / $costoUsd) - 1) * 100, 2) : 0;
        }

        $presentaciones = [[
            'nombre' => 'Unidad',
            'factor_conversion' => 1,
            'margen' => (float) $margenDetal,
            'precio_usd' => PrecioService::precioPresentacion($costoUsd, (float) $margenDetal, 1),
            'fuente_tasa' => $fuenteTasa ?? 'promedio',
            'activa' => true,
        ]];

        $precioMayor = (float) ($item['precio_mayor_usd'] ?? 0);
        if ($precioMayor > 0) {
            $presentaciones[] = [
                'nombre' => 'Mayor',
                'factor_conversion' => 1,
                'margen' => (float) ($item['margen_mayor'] ?? 0),
                'precio_usd' => $precioMayor,
                'fuente_tasa' => $fuenteTasa ?? 'promedio',
                'activa' => true,
            ];
        }

        return $presentaciones;
    }

    private function normalizarUnidadMedida(?string $unidad): string
    {
        $unidad = strtolower(trim((string) $unidad));

        return in_array($unidad, ['kg', 'kilo', 'kilogramo'], true) ? 'kg' : 'unidad';
    }
}
