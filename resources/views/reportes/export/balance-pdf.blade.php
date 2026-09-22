<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Balance Mensual {{ $anio }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        h1 { text-align: center; font-size: 16pt; margin-bottom: 5px; }
        .fecha { text-align: center; color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px 8px; text-align: left; }
        th { background: #333; color: #fff; font-size: 9pt; }
        td { font-size: 9pt; }
        .moneda { text-align: right; }
        .totales td { font-weight: bold; background: #eee; }
    </style>
</head>
<body>
    <h1>{{ \App\Models\Configuracion::obtener('nombre_negocio', config('app.name')) }}</h1>
    <p class="fecha">Balance Mensual {{ $anio }} - Generado el {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>Mes</th>
                <th>Facturas</th>
                <th>Total Bs</th>
                <th>Total USD</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($meses as $num => $nombre)
            @php $m = $mensual[$num] ?? null; @endphp
            <tr>
                <td>{{ $nombre }}</td>
                <td>{{ $m['cantidad'] ?? 0 }}</td>
                <td class="moneda">Bs {{ \App\Support\Moneda::n($m['total_bs'] ?? 0) }}</td>
                <td class="moneda">${{ \App\Support\Moneda::n($m['total_usd'] ?? 0) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totales">
                <td>Total</td>
                <td>{{ collect($mensual)->sum('cantidad') }}</td>
                <td class="moneda">Bs {{ \App\Support\Moneda::n(collect($mensual)->sum('total_bs')) }}</td>
                <td class="moneda">${{ \App\Support\Moneda::n(collect($mensual)->sum('total_usd')) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
