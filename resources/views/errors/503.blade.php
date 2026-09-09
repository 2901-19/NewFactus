<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>503 - En mantenimiento | {{ config('app.name') }}</title>
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
        .box h1 { font-size: 5rem; font-weight: 700; color: #ffc107; line-height: 1; }
        .box h2 { font-size: 1.25rem; margin: .75rem 0 .5rem; }
        .box p { color: #6c757d; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <div class="box">
        <h1>503</h1>
        <h2>En mantenimiento</h2>
        <p>El sistema se está actualizando. Vuelve a intentarlo en unos minutos.</p>
    </div>
</body>
</html>
