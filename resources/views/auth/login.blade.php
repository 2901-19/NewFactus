<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Factus') }} - Iniciar Sesión</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
    <main class="login-hoja">
        <div class="login-margen">

            <header class="login-cabecera">
                <div>
                    <div class="login-marca">{{ config('app.name', 'Factus') }}</div>
                    <span class="login-negocio">{{ \App\Models\Configuracion::obtener('nombre_negocio', config('app.name')) }}</span>
                </div>
                @if (\App\Models\Configuracion::obtener('rif'))
                    <div class="login-rif">RIF<br>{{ \App\Models\Configuracion::obtener('rif') }}</div>
                @endif
            </header>

            <form method="POST" action="{{ route('login') }}" class="login-cuerpo">
                @csrf

                <div class="login-linea">
                    <label for="usuario" class="login-etiqueta">Usuario</label>
                    <div class="login-campo">
                        <i class="bi bi-person login-icono" aria-hidden="true"></i>
                        <input id="usuario" type="text" name="usuario" placeholder="Ingresa tu usuario"
                               class="login-input @error('usuario') is-invalid @enderror"
                               value="{{ old('usuario') }}" required autofocus autocomplete="username">
                    </div>
                    @error('usuario')
                        <div class="login-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="login-linea">
                    <label for="password" class="login-etiqueta">Contraseña</label>
                    <div class="login-campo">
                        <i class="bi bi-lock login-icono" aria-hidden="true"></i>
                        <input id="password" type="password" name="password" placeholder="Ingresa tu contraseña"
                               class="login-input login-input--password @error('password') is-invalid @enderror"
                               required autocomplete="current-password">
                        <button type="button" class="login-ojo" aria-label="Mostrar contraseña"
                                data-login-toggle-password>
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="login-error">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="login-boton">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Ingresar al sistema
                </button>
            </form>

            <footer class="login-pie">
                <span class="login-pie-txt">Factus · Punto de Venta</span>
                <span class="login-pie-corr">© {{ date('Y') }}</span>
            </footer>

        </div>
    </main>

    <script>
        document.querySelector('[data-login-toggle-password]')?.addEventListener('click', function () {
            const input = document.getElementById('password');
            const icon = this.querySelector('i');
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            icon.className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
            this.setAttribute('aria-label', isHidden ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    </script>
</body>
</html>