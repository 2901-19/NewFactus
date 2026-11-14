<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Factus') }} - @yield('titulo')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    {{-- Estado guardado del sidebar: se aplica antes de pintar para evitar parpadeo en escritorio --}}
    <script>
        try {
            if (localStorage.getItem('sidebarEstado') === 'cerrado' && window.innerWidth >= 992) {
                document.body.classList.add('sidebar-collapsed');
            }
        } catch (e) { /* sin storage: queda el estado default */ }
    </script>
    {{-- Flash messages ocultos para SweetAlert2 --}}
    @if (session('success'))
        <input type="hidden" id="flash-success" value="{{ session('success') }}">
    @endif
    @if (session('error') || $errors->any())
        <input type="hidden" id="flash-error" value="{{ session('error') ?: $errors->first() }}">
    @endif

    {{-- Overlay --}}
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    {{-- Sidebar fijo --}}
    @include('layouts.sidebar')

    {{-- Contenido principal --}}
    <div class="main-content">
        @include('layouts.navbar')
        <main class="p-3 p-md-4">
            @yield('contenido')
        </main>
    </div>

    {{-- Notificaciones vencidas (recordatorios programados) --}}
    <div x-data="campanaNotificaciones()" x-init="iniciar()" class="position-fixed top-0 end-0 p-3" style="z-index: 1055; width: 380px; max-width: 94vw;" x-show="notificaciones.length > 0" x-cloak>
        <template x-for="n in notificaciones" :key="n.tipo">
            <div class="alert alert-warning shadow-sm border-0 d-flex align-items-start gap-2 mb-2 notif-banner" role="alert">
                <i class="bi bi-bell-fill mt-1"></i>
                <div class="flex-grow-1">
                    <strong x-text="n.titulo"></strong>
                    <div class="small" x-text="n.mensaje"></div>
                    <div class="mt-1 d-flex gap-2">
                        <a :href="n.accion_url" class="btn btn-sm btn-dark" x-show="n.accion_url" x-text="n.texto_accion || 'Ver'"></a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="posponer(n)">Recordar más tarde</button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <script>
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    function campanaNotificaciones() {
        return {
            notificaciones: [],
            iniciar() {
                this.consultar();
                setInterval(() => this.consultar(), 60000);
            },
            async consultar() {
                try {
                    const r = await fetch('{{ route('notificaciones.pendientes') }}', { headers: { 'Accept': 'application/json' } });
                    if (!r.ok) return;
                    const json = await r.json();
                    // No re-mostrar banners que el usuario ya pospuso en esta pestaña.
                    this.notificaciones = json.data.filter(n => !this.ocultados.has(n.tipo));
                } catch (e) { /* sin conexión: ignorar */ }
            },
            ocultados: new Set(),
            async posponer(n) {
                this.ocultados.add(n.tipo);
                this.notificaciones = this.notificaciones.filter(x => x.tipo !== n.tipo);
                try {
                    await fetch(`{{ url('notificaciones') }}/${n.tipo}/posponer`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    });
                } catch (e) { /* se reintentará al recargar */ }
            },
        };
    }
    window.DataTableSpanish = {
        processing: 'Procesando...',
        lengthMenu: 'Mostrar _MENU_ registros',
        zeroRecords: 'No se encontraron resultados',
        emptyTable: 'Ningún dato disponible en esta tabla',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
        infoEmpty: 'Mostrando 0 a 0 de 0 registros',
        infoFiltered: '(filtrado de _MAX_ registros totales)',
        infoThousands: '.',
        loadingRecords: 'Cargando...',
        search: 'Buscar:',
        paginate: {
            first: 'Primero',
            last: 'Último',
            next: 'Siguiente',
            previous: 'Anterior',
        },
        aria: {
            sortAscending: 'Activar para ordenar ascendente',
            sortDescending: 'Activar para ordenar descendente',
        },
    };
    function toggleSidebar() {
        if (window.innerWidth < 992) {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('show');
            guardarSidebarEstado(document.getElementById('sidebar').classList.contains('open') ? 'abierto' : 'cerrado');
        } else {
            document.body.classList.toggle('sidebar-collapsed');
            guardarSidebarEstado(document.body.classList.contains('sidebar-collapsed') ? 'cerrado' : 'abierto');
        }
    }
    function guardarSidebarEstado(estado) {
        try { localStorage.setItem('sidebarEstado', estado); } catch (e) { /* sin storage */ }
    }
    // Móvil arranca cerrado: reabrir si quedó abierto (en escritorio ya se aplicó antes de pintar).
    try {
        if (localStorage.getItem('sidebarEstado') === 'abierto' && window.innerWidth < 992) {
            document.getElementById('sidebar').classList.add('open');
            document.getElementById('sidebarOverlay').classList.add('show');
        }
    } catch (e) { /* sin storage: queda el estado default */ }
    </script>
    @stack('scripts')
</body>
</html>
