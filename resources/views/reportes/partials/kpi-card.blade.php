<div class="col-6 col-md-4 col-xl">
    <div class="card h-100">
        <div class="card-body py-3">
            <div class="small text-muted text-uppercase fw-semibold">{{ $label }}</div>
            <div class="fs-4 fw-bold">
                @if (($formato ?? 'entero') === 'moneda')
                    Bs {{ \App\Support\Moneda::n($valor, 2) }}
                @elseif (($formato ?? 'entero') === 'usd')
                    ${{ \App\Support\Moneda::n($valor, 2) }}
                @else
                    {{ \App\Support\Moneda::n($valor) }}
                @endif
            </div>
        </div>
    </div>
</div>
