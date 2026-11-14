<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>419 - Sesión expirada | {{ config('app.name') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4f6f9;
            color: #212529;
        }
        .box { text-align: center; padding: 2rem; max-width: 420px; }
        .box h1 { font-size: 5rem; font-weight: 700; color: #dc3545; line-height: 1; }
        .box h2 { font-size: 1.25rem; margin: .75rem 0 .5rem; }
        .box p { color: #6c757d; margin-bottom: 1.5rem; }
        .box a {
            display: inline-block;
            padding: .6rem 1.4rem;
            background: #0d6efd;
            color: #fff;
            text-decoration: none;
            border-radius: .375rem;
            font-weight: 600;
        }
        .box a:hover { background: #0b5ed7; }
    </style>
</head>
<body>
    <div class="box">
        <h1>419</h1>
        <h2>Tu sesión expiró por inactividad</h2>
        <p>Inicia sesión de nuevo para continuar donde quedaste.</p>
        <a href="{{ route('login') }}">Iniciar sesión</a>
    </div>
</body>
</html>
