<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Factura;
use App\Support\Moneda;
use Illuminate\Support\Str;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class PrinterService
{
    /**
     * Celdas imprimibles de un ticket de 80 mm en fuente normal.
     * A tamaño doble el ancho se divide a la mitad (24 celdas).
     */
    public const ANCHO = 48;

    protected $printer;

    public function connect($tipo = 'network', $host = '127.0.0.1', $port = 9100, $nombre = null)
    {
        try {
            if ($tipo === 'network') {
                $connector = new NetworkPrintConnector($host, $port);
            } elseif ($tipo === 'windows' && $nombre) {
                $connector = new WindowsPrintConnector($nombre);
            } else {
                throw new \Exception('Tipo de conexión no soportado.');
            }

            $profile = CapabilityProfile::load('simple');
            $this->printer = new Printer($connector, $profile);

            return true;
        } catch (\Exception $e) {
            \Log::warning('PrinterService::connect falló', [
                'tipo' => $tipo,
                'host' => $host,
                'port' => $port,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public static function configuracion(): array
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

    public static function itemsDesdeFactura(Factura $factura): array
    {
        return $factura->items->map(function ($item) {
            return [
                'nombre' => $item->producto->nombre ?? 'Producto',
                'precio_unitario' => (float) $item->precio_unitario_bs,
                'cantidad' => $item->cantidad,
                'total' => (float) $item->subtotal,
                'pesable' => $item->unidad_medida === 'kg',
            ];
        })->toArray();
    }

    public function printReceipt($factura, $productos, $usuario)
    {
        if (! $this->printer) {
            return false;
        }

        try {
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->setEmphasis(true);
            $this->printer->setTextSize(2, 2);
            $this->printer->text($this->ascii(config('app.name', 'NEW FACTUS'))."\n");
            $this->printer->setEmphasis(false);
            $this->printer->setTextSize(1, 1);
            $this->printer->text($this->ascii(Configuracion::obtener('nombre_negocio', config('app.name')))."\n");
            $this->printer->feed();

            $this->printer->setJustification(Printer::JUSTIFY_LEFT);
            $this->printer->text('Correlativo: '.$this->ascii($factura->correlativo)."\n");
            $this->printer->text('Fecha: '.$factura->fecha_venta."\n");
            $this->printer->text('Cajero: '.$this->ascii($usuario)."\n");
            $this->printer->feed();

            if ($factura->cliente) {
                $this->lineaTexto('Cliente: '.$factura->cliente->nombre);
                if ($factura->cliente->ci) {
                    $this->printer->text('Cedula: '.$this->ascii($factura->cliente->ci)."\n");
                }
                $this->printer->feed();
            }

            $esCredito = $factura->estado === 'credito';
            $tasaCambio = (float) $factura->tasa_cambio ?: 1;
            $moneda = $esCredito ? '$' : 'Bs';

            if ($esCredito) {
                $productos = $this->convertirAUsd($productos, $tasaCambio);
            }

            $this->printItemsSection(null, $productos, $moneda);

            $this->printer->setEmphasis(true);
            $this->printer->setTextSize(2, 2);
            if ($esCredito) {
                $this->imprimirTotalDoble('TOTAL USD: '.Moneda::n($factura->total_usd));
            } else {
                $this->imprimirTotalDoble('TOTAL Bs: '.Moneda::n($factura->total_bs));
                $this->printer->setTextSize(1, 1);
                $this->printer->text('Total USD: '.Moneda::n($factura->total_usd)."\n");
            }
            $this->printer->setEmphasis(false);
            $this->printer->feed();

            $nombresMetodo = CatalogoService::metodosPago();

            if (! $esCredito) {
                if ($factura->metodo_pago === 'mixto') {
                    $this->printer->setTextSize(1, 1);
                    $this->printer->text('Pago Mixto'."\n");
                    foreach ($factura->detalle_pago ?? [] as $pago) {
                        $nombre = $this->ascii($nombresMetodo[$pago['metodo']] ?? $pago['metodo']);
                        $this->printer->text(str_pad('  '.$nombre.':', 30).str_pad(Moneda::bs($pago['monto']), 16, STR_PAD_LEFT)."\n");
                    }
                } else {
                    $this->printer->setTextSize(1, 1);
                    $nombre = $this->ascii($nombresMetodo[$factura->metodo_pago] ?? $factura->metodo_pago);
                    $this->printer->text('Pago: '.$nombre."\n");
                }
                $this->printer->feed();
            }

            $this->printer->setTextSize(1, 1);

            if ($factura->estado === 'credito') {
                $this->printer->setEmphasis(true);
                if ($factura->estado_credito === 'cancelado') {
                    $nombreMetodo = $this->ascii($nombresMetodo[$factura->metodo_pago] ?? $factura->metodo_pago);
                    $this->printer->text('** CREDITO COBRADO **'."\n");
                    $this->printer->text('Pago: '.$nombreMetodo."\n");
                    $this->printer->text(Moneda::bs($factura->pago_bs)."\n");
                    $this->printer->text($this->ascii($factura->fecha_pago?->format('d/m/Y H:i') ?? '')."\n");
                } else {
                    $this->printer->text('** CREDITO PENDIENTE **'."\n");
                }
                $this->printer->setEmphasis(false);
                $this->printer->feed();
            }

            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text('Gracias por su compra!'."\n");
            $this->printer->feed(3);
            $this->printer->cut();
            $this->printer->close();

            return true;
        } catch (\Exception $e) {
            \Log::warning('PrinterService::printReceipt falló', [
                'factura_id' => $factura->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function printPrecioProducto(array $datos)
    {
        if (! $this->printer) {
            return false;
        }

        try {
            $producto = $this->ascii($datos['producto'] ?? '');
            $presentacion = $this->ascii($datos['presentacion'] ?? '');
            $numero = Moneda::n($datos['precio_bs'] ?? 0);

            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->setEmphasis(true);
            $this->printer->setTextSize(2, 2);
            foreach ($this->envolver($producto, (int) floor(self::ANCHO / 2)) as $linea) {
                $this->printer->text($linea."\n");
            }
            $this->printer->setEmphasis(false);
            $this->printer->setTextSize(1, 1);

            if ($presentacion !== '') {
                $this->printer->text($presentacion."\n");
            }
            $this->printer->feed();

            $this->printer->setTextSize(2, 2);
            $this->printer->text('Bs'."\n");
            $tam = max(2, min(8, (int) floor(self::ANCHO / strlen($numero))));
            $this->printer->setEmphasis(true);
            $this->printer->setTextSize($tam, $tam);
            $this->printer->text($numero."\n");
            $this->printer->setEmphasis(false);
            $this->printer->setTextSize(1, 1);

            $this->printer->feed(3);
            $this->printer->cut();
            $this->printer->close();

            return true;
        } catch (\Exception $e) {
            \Log::warning('PrinterService::printPrecioProducto falló', [
                'producto' => $datos['producto'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function printItemsSection(?string $titulo, array $items, string $moneda = 'Bs'): void
    {
        if ($titulo) {
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->setEmphasis(true);
            $this->printer->text("{$titulo}\n");
            $this->printer->setEmphasis(false);
            $this->printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $this->printer->setEmphasis(true);
        $this->printer->text($this->filaItems('CANT', 'DESC', 'PREC U', 'PREC T')."\n");
        $this->printer->setEmphasis(false);
        $this->printer->text(str_repeat('-', self::ANCHO)."\n");

        $subtotal = 0;
        foreach ($items as $item) {
            $pesable = ! empty($item['pesable']);
            $precio = $pesable ? Moneda::n($item['precio_unitario']).'/kg' : Moneda::n($item['precio_unitario']);
            $cant = $pesable ? '-' : (string) $item['cantidad'];
            $total = Moneda::n($item['total']);
            $subtotal += (float) $item['total'];

            $this->printer->text($this->filaItems($cant, (string) $item['nombre'], $precio, $total)."\n");
        }

        $this->printer->text(str_repeat('-', self::ANCHO)."\n");

        if ($titulo) {
            $this->printer->text(str_pad("Subtotal {$titulo}:", 30).$moneda.' '.Moneda::n($subtotal)."\n");
        }
    }

    protected function convertirAUsd(array $items, float $tasa): array
    {
        return array_map(function ($item) use ($tasa) {
            $item['precio_unitario'] = (float) $item['precio_unitario'] / $tasa;
            $item['total'] = (float) $item['total'] / $tasa;

            return $item;
        }, $items);
    }

    /**
     * Fila de 4 columnas con ancho dinámico por fila: la descripción
     * cede celdas para que los números (formato español 1.234,56) entren
     * completos y queden alineados a la derecha, con un espacio entre
     * precio unitario y precio total. La descripción se envuelve por
     * palabras sin desbordar las 48 celdas del papel.
     */
    private function filaItems(string $cant, string $descripcion, string $precioU, string $precioT): string
    {
        $anchoCant = 4;
        $colCant = str_pad($cant, $anchoCant);
        $anchoPrecU = strlen($precioU);
        $anchoPrecT = strlen($precioT);
        $anchoDesc = max(12, self::ANCHO - $anchoCant - $anchoPrecU - $anchoPrecT - 1);

        $lineas = $this->envolver($this->ascii($descripcion), $anchoDesc);
        $primera = array_shift($lineas) ?? '';
        $linea = $colCant.str_pad($primera, $anchoDesc)
            .str_pad($precioU, $anchoPrecU, STR_PAD_LEFT)
            .' '.str_pad($precioT, $anchoPrecT, STR_PAD_LEFT);

        foreach ($lineas as $continuacion) {
            $linea .= "\n".str_pad('', $anchoCant).$continuacion;
        }

        return $linea;
    }

    private function lineaTexto(string $texto): void
    {
        $this->printer->setJustification(Printer::JUSTIFY_LEFT);
        foreach ($this->envolver($this->ascii($texto), self::ANCHO) as $linea) {
            $this->printer->text($linea."\n");
        }
    }

    private function imprimirTotalDoble(string $texto): void
    {
        if (strlen($this->ascii($texto)) <= (int) floor(self::ANCHO / 2)) {
            $this->printer->setTextSize(2, 2);
        } else {
            $this->printer->setTextSize(1, 2);
        }
        $this->printer->text($this->ascii($texto)."\n");
    }

    private function ascii(string $texto): string
    {
        $texto = Str::ascii(trim($texto));

        return preg_replace('/\s+/u', ' ', $texto) ?? $texto;
    }

    private function envolver(string $texto, int $ancho): array
    {
        $palabras = preg_split('/\s+/', trim($texto)) ?: [];
        $lineas = [];
        $linea = '';

        foreach ($palabras as $palabra) {
            while (strlen($palabra) > $ancho) {
                if ($linea !== '') {
                    $lineas[] = $linea;
                    $linea = '';
                }
                $lineas[] = substr($palabra, 0, $ancho);
                $palabra = substr($palabra, $ancho);
            }

            if ($linea === '') {
                $linea = $palabra;
            } elseif (strlen($linea) + 1 + strlen($palabra) <= $ancho) {
                $linea .= ' '.$palabra;
            } else {
                $lineas[] = $linea;
                $linea = $palabra;
            }
        }

        if ($linea !== '') {
            $lineas[] = $linea;
        }

        return $lineas;
    }

    public function printTest()
    {
        if (! $this->printer) {
            return false;
        }

        try {
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->setEmphasis(true);
            $this->printer->setTextSize(2, 2);
            $this->printer->text('PRUEBA DE ALINEACION'."\n");
            $this->printer->setEmphasis(false);
            $this->printer->setTextSize(1, 1);
            $this->printer->text('80 mm · '.self::ANCHO.' celdas por linea'."\n");
            $this->printer->setJustification(Printer::JUSTIFY_LEFT);
            $this->printer->text(substr(str_repeat('1234567890', 6), 0, self::ANCHO)."\n");
            $this->printer->text(str_repeat('-', self::ANCHO)."\n");
            $this->printer->text($this->filaItems('2', 'Coca Cola 2L', Moneda::n(1.30), Moneda::n(2.60))."\n");
            $this->printer->text($this->filaItems('2', 'Agua Mineral Manantial Cero Azucar', Moneda::n(1234.56), Moneda::n(2469.12))."\n");
            $this->printer->text('Nombre con acentos: '.$this->ascii('Café Crema Piña Colada Ñandú')."\n");
            $this->printer->setEmphasis(true);
            $this->printer->setTextSize(2, 2);
            $this->imprimirTotalDoble('TOTAL Bs: '.Moneda::n(1234.56));
            $this->printer->setEmphasis(false);
            $this->printer->setTextSize(1, 1);
            $this->printer->feed(3);
            $this->printer->cut();
            $this->printer->close();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
