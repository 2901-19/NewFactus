<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lista de Precios</title>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        h1 { text-align: center; font-size: 16pt; margin-bottom: 5px; }
        .fecha { text-align: center; color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px 8px; text-align: left; }
        th { background: #333; color: #fff; font-size: 9pt; }
        td { font-size: 9pt; }
        .moneda { text-align: right; }
        .iva { text-align: center; }
    </style>
</head>
<body>
    <h1>{{ \App\Models\Configuracion::obtener('nombre_negocio', config('app.name')) }}</h1>
    <p class="fecha">Lista de Precios{{ $categoria ? ' - '.$categoria->nombre : '' }}{{ $filtro ? ' - Presentación: '.implode(', ', $filtro) : '' }} - Generado el {{ $fecha }}</p>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Presentación</th>
                <th>Precio Bs</th>
                <th>Precio USD</th>
                <th>Impuesto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($productos as $p)
            @foreach ($p->presentaciones->where('activa', true)->when($filtro, fn ($col) => $col->whereIn('nombre', $filtro)) as $pr)
            @php $tasaDisponiblePdf = $tasas->has($pr->fuente_tasa); @endphp
            <tr>
                <td>{{ $p->nombre }}</td>
                <td>{{ $pr->nombre }}</td>
                <td class="moneda">
                    @if ($tasaDisponiblePdf)
                        Bs {{ \App\Support\Moneda::n($pr->precio_usd * $tasas[$pr->fuente_tasa]) }}
                    @else
                        Sin tasa
                    @endif
                </td>
                <td class="moneda">${{ \App\Support\Moneda::n($pr->precio_usd) }}</td>
                <td class="iva">{{ $p->impuesto?->nombre ?? 'No' }}</td>
            </tr>
            @endforeach
            @endforeach
        </tbody>
    </table>
</body>
</html>
