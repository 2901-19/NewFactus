@php $user = auth()->user(); @endphp
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header d-flex align-items-center justify-content-between gap-3">
        <div style="min-width: 0;">
            <a href="{{ route('dashboard') }}" class="marca-linea text-decoration-none">{{ config('app.name', 'Factus') }}</a>
            <span class="marca-negocio">{{ \App\Models\Configuracion::obtener('nombre_negocio', config('app.name')) }}</span>
        </div>
        <button type="button" class="btn-toggle-lateral" aria-label="Ocultar menú" onclick="toggleSidebar()">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>
    <div class="sidebar-body">
        <ul class="nav nav-pills flex-column">
            @if ($user->hasPermiso('ver-dashboard'))
            <li class="nav-item">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i> Inicio
                </a>
            </li>
            @endif
            @if ($user->hasPermiso('usar-pos'))
            <li class="nav-item">
                <a href="{{ route('facturas.pos') }}" class="nav-link {{ request()->routeIs('facturas.pos') ? 'active' : '' }}">
                    <i class="bi bi-cart3"></i> Punto de Venta
                </a>
            </li>
            @endif
            @if ($user->hasPermiso('gestionar-productos'))
            <li class="nav-item">
                <a href="{{ route('productos.index') }}" class="nav-link {{ request()->routeIs('productos.*') ? 'active' : '' }}">
                    <i class="bi bi-box-seam"></i> Productos
                </a>
            </li>
            @endif
            @if ($user->hasPermiso('gestionar-creditos'))
            <li class="nav-item">
                <a href="{{ route('facturas.creditos') }}" class="nav-link {{ request()->routeIs('facturas.creditos') ? 'active' : '' }}">
                    <i class="bi bi-credit-card"></i> Créditos
                </a>
            </li>
            @endif
            @if ($user->hasPermiso('gestionar-clientes'))
            <li class="nav-item">
                <a href="{{ route('clientes.index') }}" class="nav-link {{ request()->routeIs('clientes.*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i> Clientes
                </a>
            </li>
            @endif
            @if ($user->hasPermiso('gestionar-impuestos'))
            <li class="nav-item">
                <a href="{{ route('impuestos.index') }}" class="nav-link {{ request()->routeIs('impuestos.*') ? 'active' : '' }}">
                    <i class="bi bi-receipt"></i> Impuestos
                </a>
            </li>
            @endif
            @if ($user->hasPermiso('gestionar-tasas'))
            <li class="nav-item">
                <a href="{{ route('tasas-cambio.index') }}" class="nav-link {{ request()->routeIs('tasas-cambio.*') ? 'active' : '' }}">
                    <i class="bi bi-currency-exchange"></i> Tasas de Cambio
                </a>
            </li>
            @endif
            @if ($user->hasPermiso('gestionar-categorias'))
            <li class="nav-item">
                <a href="{{ route('categorias.index') }}" class="nav-link {{ request()->routeIs('categorias.*') ? 'active' : '' }}">
                    <i class="bi bi-tags"></i> Categorías
                </a>
            </li>
            @endif
            <hr>
            @if ($user->hasPermiso('ver-facturas'))
            <li class="nav-item">
                <a href="{{ route('facturas.index') }}" class="nav-link {{ request()->routeIs('facturas.index') ? 'active' : '' }}">
                    <i class="bi bi-list-ul"></i> Facturas
                </a>
            </li>
            @endif
            @if ($user->hasPermiso('ver-reporte-facturas'))
            <li class="nav-item">
                <a href="{{ route('reportes.facturas') }}" class="nav-link {{ request()->routeIs('reportes.facturas') ? 'active' : '' }}">
                    <i class="bi bi-file-text"></i> Reporte de Ventas
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('reportes.estadisticas') }}" class="nav-link {{ request()->routeIs('reportes.estadisticas') ? 'active' : '' }}">
                    <i class="bi bi-graph-up"></i> Estadísticas
                </a>
            </li>
            @endif
            @if ($user->hasPermiso('ver-balance'))
            <li class="nav-item">
                <a href="{{ route('reportes.balance') }}" class="nav-link {{ request()->routeIs('reportes.balance') ? 'active' : '' }}">
                    <i class="bi bi-bar-chart"></i> Balance Mensual
                </a>
            </li>
            @endif
            @if ($user->hasPermiso('ver-stock-bajo'))
            <li class="nav-item">
                <a href="{{ route('reportes.stock') }}" class="nav-link {{ request()->routeIs('reportes.stock') ? 'active' : '' }}">
                    <i class="bi bi-exclamation-triangle"></i> Existencia Baja
                </a>
            </li>
            @endif
            <hr>
            @if ($user->hasPermiso('gestionar-usuarios'))
            <li class="nav-item">
                <a href="{{ route('usuarios.index') }}" class="nav-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}">
                    <i class="bi bi-people-fill"></i> Usuarios
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('roles.index') }}" class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                    <i class="bi bi-person-gear"></i> Roles
                </a>
            </li>
            @endif
            @if ($user->hasPermiso('exportar-datos'))
            <li class="nav-item">
                <a href="{{ route('herramientas.datos') }}" class="nav-link {{ request()->routeIs('herramientas.datos') ? 'active' : '' }}">
                    <i class="bi bi-database"></i> Exportar / Importar
                </a>
            </li>
            @endif
            @if ($user->hasPermiso('configurar-impresora'))
            <li class="nav-item">
                <a href="{{ route('herramientas.impresora') }}" class="nav-link {{ request()->routeIs('herramientas.impresora*') ? 'active' : '' }}">
                    <i class="bi bi-printer"></i> Impresora
                </a>
            </li>
            @endif
            @if ($user->hasPermiso('ver-lista-precios'))
            <li class="nav-item">
                <a href="{{ route('herramientas.precios') }}" class="nav-link {{ request()->routeIs('herramientas.precios*') ? 'active' : '' }}">
                    <i class="bi bi-tags"></i> Lista de Precios
                </a>
            </li>
            @endif
            @if ($user->hasPermiso('configuracion'))
            <li class="nav-item">
                <a href="{{ route('herramientas.configuracion') }}" class="nav-link {{ request()->routeIs('herramientas.configuracion') ? 'active' : '' }}">
                    <i class="bi bi-gear"></i> Configuración
                </a>
            </li>
            @endif
            <hr>
            <li class="nav-item">
                <a href="{{ route('profile.edit') }}" class="nav-link">
                    <i class="bi bi-person-circle"></i> Mi Perfil
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('logout') }}"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <i class="bi bi-box-arrow-right"></i> Salir
                </a>
                <form id="logout-form" method="POST" action="{{ route('logout') }}" class="d-none">
                    @csrf
                </form>
            </li>
        </ul>
    </div>
</nav>