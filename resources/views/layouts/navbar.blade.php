<nav class="navbar navbar-light px-3 px-md-4">
    <div class="d-flex align-items-center w-100" style="gap: 1rem;">
        <button class="btn btn-outline-secondary" onclick="toggleSidebar()" title="Mostrar/Ocultar menú">
            <i class="bi bi-list"></i>
        </button>

        <div class="d-none d-md-flex align-items-center" style="gap: 0.9rem;">
            <span class="masthead-dato">{{ \App\Models\Configuracion::obtener('nombre_negocio', config('app.name')) }}</span>
            @if (\App\Models\Configuracion::obtener('rif'))
                <span class="masthead-sep" aria-hidden="true"></span>
                <span class="masthead-fecha">RIF {{ \App\Models\Configuracion::obtener('rif') }}</span>
            @endif
            <span class="masthead-sep" aria-hidden="true"></span>
            <span class="masthead-fecha">{{ now()->translatedFormat('d/m/Y H:i') }}</span>
        </div>

        <div class="ms-auto d-flex align-items-center" style="gap: 0.9rem;">
            <span class="masthead-dato d-none d-sm-inline">
                <i class="bi bi-person"></i> {{ Auth::user()->name }}
            </span>
            <span class="badge bg-info">{{ Auth::user()->role?->nombre ?? ucfirst(Auth::user()->rol) }}</span>
        </div>
    </div>
</nav>