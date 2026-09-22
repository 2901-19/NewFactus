@extends('layouts.app')
@section('titulo', 'Historial de Tasas')
@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2>Historial de Tasas</h2>
        <p class="text-muted small mb-0">Registro de cada cambio de monto en las tasas, con fecha, usuario y variación.</p>
    </div>
    <a href="{{ route('tasas-cambio.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Volver a Tasas
    </a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('tasas-cambio.historial') }}" class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="form-label mb-0">Filtrar por tasa:</label>
            </div>
            <div class="col-md-4">
                <select name="tipo" class="form-select" onchange="this.form.submit()">
                    <option value="">Todas las tasas</option>
                    @foreach ($tipos as $t)
                        <option value="{{ $t->tipo }}" {{ $tipoFiltro == $t->tipo ? 'selected' : '' }}>
                            {{ $t->nombre ?: ucfirst($t->tipo) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
        <thead class="table-dark">
            <tr>
                <th>Fecha</th>
                <th>Tasa</th>
                <th>Monto (Bs/USD)</th>
                <th>Variación</th>
                <th>Usuario</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($historial as $fila)
                <tr>
                    <td>{{ $fila->fecha }} <small class="text-muted d-block">{{ $fila->created_at->format('H:i') }}</small></td>
                    <td>{{ $fila->nombre ?: ucfirst($fila->tipo) }}</td>
                    <td>{{ \App\Support\Moneda::n($fila->monto) }} Bs</td>
                    <td>
                        @if ($fila->variacion === null)
                            <span class="text-muted">—</span>
                        @elseif ($fila->variacion > 0)
                            <span class="text-success"><i class="bi bi-arrow-up-right"></i> +{{ \App\Support\Moneda::n($fila->variacion) }}%</span>
                        @elseif ($fila->variacion < 0)
                            <span class="text-danger"><i class="bi bi-arrow-down-right"></i> {{ \App\Support\Moneda::n($fila->variacion) }}%</span>
                        @else
                            <span class="text-muted">0.00%</span>
                        @endif
                    </td>
                    <td>
                        @if ($fila->origen === 'importado')
                            <span class="badge bg-secondary"><i class="bi bi-download"></i> Importado</span>
                        @else
                            {{ $fila->user?->name ?: '—' }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">Sin registro de cambios hasta ahora.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="d-flex justify-content-center">
    {{ $historial->withQueryString()->links() }}
</div>
@endsection