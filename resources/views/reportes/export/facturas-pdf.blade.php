<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Ventas</title>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        h1 { text-align: center; font-size: 16pt; margin-bottom: 5px; }
        .fecha { text-align: center; color: #666; margin-bottom: 10px; }
        .periodo { text-align: center; margin-bottom: 20px; }
        .resumen { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .resumen td { border: 1px solid #333; padding: 6px 8px; text-align: center; }
        .resumen .titulo { background: #333; color: #fff; font-size: 8pt; }
        .resumen .valor { font-size: 12pt; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px 8px; text-align: left; }
        th { background: #333; color: #fff; font-size: 9pt; }
        td { font-size: 9pt; }
        .moneda { text-align: right; }
        .totales td { font-weight: bold; background: #eee; }
        .desglose { margin-bottom: 20px; }
        .desglose td { text-align: right; }
    </style>
</head>
<body>
    <h1>{{ \App\Models\Configuracion::obtener('nombre_negocio', config('app.name')) }}</h1>
    <p class="fecha">Reporte de Ventas - Generado el {{ now()->format('d/m/Y H:i') }}</p>
    <p class="periodo">Período: {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}</p>

    <table class="resumen">
        <tr>
            <td class="titulo">Ingresos Bs</td>
            <td class="titulo">Ingresos USD</td>
            <td class="titulo">Impuesto</td>
            <td class="titulo">Facturas</td>
            <td class="titulo">Ticket promedio</td>
        </tr>
        <tr>
            <td class="valor">Bs {{ \App\Support\Moneda::n($kpis['total_bs']) }}</td>
            <td class="valor">${{ \App\Support\Moneda::n($kpis['total_usd']) }}</td>
            <td class="valor">Bs {{ \App\Support\Moneda::n($kpis['iva_bs']) }}</td>
            <td class="valor">{{ \App\Support\Moneda::n($kpis['cantidad']) }}</td>
            <td class="valor">Bs {{ \App\Support\Moneda::n($kpis['ticket_promedio']) }}</td>
        </tr>
    </table>

    <table class="desglose">
        <tr><th class="titulo">Ingresos por método de pago</th><th class="titulo">Monto</th></tr>
        @foreach ($desglose as $metodo => $monto)
            @if ($monto > 0)
            <tr>
                <td>{{ ucfirst(str_replace('_', ' ', $metodo)) }}</td>
                <td class="moneda">Bs {{ \App\Support\Moneda::n($monto) }}</td>
            </tr>
            @endif
        @endforeach
    </table>

    <table>
        <thead>
            <tr>
                <th>Correlativo</th>
                <th>Cliente</th>
                <th>Método</th>
                <th>Total Bs</th>
                <th>Total USD</th>
                <th>Fecha</th>
                <th>Tipo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($facturas as $f)
            <tr>
                <td>{{ $f->correlativo }}</td>
                <td>{{ $f->cliente->nombre ?? 'Contado' }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $f->metodo_pago)) }}</td>
                <td class="moneda">Bs {{ \App\Support\Moneda::n($f->total_bs) }}</td>
                <td class="moneda">${{ \App\Support\Moneda::n($f->total_usd) }}</td>
                <td>{{ $f->fecha_venta?->format('d/m/Y') }}</td>
                <td>{{ ucfirst($f->estado) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totales">
                <td colspan="3" style="text-align:right">Totales:</td>
                <td class="moneda">Bs {{ \App\Support\Moneda::n($kpis['total_bs']) }}</td>
                <td class="moneda">${{ \App\Support\Moneda::n($kpis['total_usd']) }}</td>
                <td colspan="2">{{ \App\Support\Moneda::n($kpis['cantidad']) }} facturas</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
