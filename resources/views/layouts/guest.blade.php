<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Factus') }} - Punto de Venta</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="login-page">
        <main class="login-hoja login-hoja--guest">
            <div class="login-margen">

                <header class="login-cabecera">
                    <div>
                        <div class="login-marca">{{ config('app.name', 'Factus') }}</div>
                        <span class="login-negocio">{{ \App\Models\Configuracion::obtener('nombre_negocio', config('app.name')) }}</span>
                    </div>
                </header>

                <div class="login-cuerpo">
                    {{ $slot }}
                </div>

                <footer class="login-pie">
                    <span class="login-pie-txt">Factus · Punto de Venta</span>
                    <span class="login-pie-corr">© {{ date('Y') }}</span>
                </footer>

            </div>
        </main>
    </body>
</html>