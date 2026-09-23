# Lanzador de escritorio FACTUS (un clic con PowerShell)

Permite abrir el sistema en la PC del cliente como una **app de escritorio**: el usuario hace doble
clic en `FACTUS.vbs`, se abre una ventana de navegador dedicada (modo app, sin pestañas) y al
cerrarla se apaga todo: se cierra la sesión del usuario, se mata el navegador y se mata el servidor
PHP. No queda ningún proceso corriendo en segundo plano.

## Cómo funciona

`launcher/FACTUS.vbs` es el único archivo que el usuario toca: lanza `launcher/factus-launcher.ps1`
en una consola PowerShell oculta. El script:

1. **Auto-recuperación**: si una instancia anterior quedó huérfana (cierre forzado), la limpia para
   que el puerto nunca quede bloqueado. Si el sistema ya está abierto, solo enfoca la ventana y sale.
2. **Levanta el servidor**: `php artisan serve --host=0.0.0.0 --port=8000` con el PHP instalado en
   la PC (`config.json.phpPath` → `PATH` → `C:\php\php.exe` → `%ProgramFiles%\php\php.exe`). Por defecto
   escucha en **todas las interfaces** (`config.json.host`), así el sistema se puede usar desde otras
   PCs de la red por su IP.
3. **Abre la ventana**: localiza el navegador (Edge → Chrome → Brave, o el que indique
   `config.json.browser`) y abre `http://127.0.0.1:8000/?_lanzador=<token>` con
   `--app` + `--user-data-dir` dedicado, para que la ventana viva en su propio proceso y sea vigilable.
4. **Vigila la ventana**: mientras el usuario la tenga abierta no hace nada; al cerrarla, en `finally`:
   mata el árbol del navegador → `POST /lanzador/cerrar-sesion` con el token → mata el árbol de PHP →
   borra los archivos de estado.

## Estructura

| Archivo | Rol |
|---|---|
| `launcher/FACTUS.vbs` | Archivo de doble clic: ejecuta el PowerShell oculto |
| `launcher/factus-launcher.ps1` | Toda la lógica del lanzador |
| `launcher/config.json` | `port`, `host`, `phpPath`, `appPath`, `browser`, `postgresPort` |

El estado se guarda en `%LOCALAPPDATA%\FACTUS\` (`php.pid`, `token.txt`, `edge-profile`).

## Cierre de sesión al cerrar la ventana

El token `?_lanzador` se vincula con la sesión del usuario mediante la tabla `lanzador_sesiones`
(token → `session_id`):

- `app/Http/Middleware/RegistrarLanzador.php` registra el token en cada petición (se mantiene al día
  incluso tras la regeneración de sesión del login). Corre **antes** del middleware `auth` para que
  también se registre en peticiones de invitados (prioridad configurada en `bootstrap/app.php`).
- `LanzadorController@cerrarSesion` (`POST /lanzador/cerrar-sesion`, público y exento de CSRF) destruye
  exactamente la sesión vinculada al token y borra el mapeo; responde 204.
- El navegador del lanzador usa su propio `--user-data-dir`, así que la cookie de sesión del cliente
  vive aislada y otras sesiones abiertas (otro navegador) no se tocan.

## Configuración (`launcher/config.json`)

```json
{
    "port": 8000,
    "host": "0.0.0.0",
    "phpPath": null,
    "appPath": null,
    "browser": "auto",
    "postgresPort": 5432
}
```

- `port`: puerto del servidor (por defecto `8000`).
- `host`: interfaz de escucha del servidor. `0.0.0.0` (defecto) = acepta conexiones de **la red** para
  usar el sistema desde otras PCs (`http://IP_DEL_SERVIDOR:8000`) y de la propia máquina. `127.0.0.1` o
  vacío = solo la PC local. Una IP concreta de la LAN = solo esa interfaz.
- `phpPath`: ruta al `php.exe` (si es `null` se busca en `PATH`, `C:\php\php.exe`,
  `%ProgramFiles%\php\php.exe`).
- `appPath`: ruta al proyecto Laravel (por defecto, la carpeta padre de `launcher/`).
- `browser`: `auto` (Edge → Chrome → Brave), `edge`, `chrome` o `brave`.
- `postgresPort`: puerto TCP de PostgreSQL para el chequeo previo (`<= 0` lo desactiva).

### Acceso desde otra PC de la red

1. En la PC servidor, permitir el puerto en Windows Firewall (una sola vez, como Administrador):
   ```powershell
   netsh advfirewall firewall add rule name="FACTUS 8000" dir=in action=allow protocol=TCP localport=8000
   ```
   (Si no se permite, Windows pide el diálogo de firewall en el primer arranque con `host=0.0.0.0`.)
2. Desde la otra PC abrir `http://IP_DEL_SERVIDOR:8000` (averiguar la IP con `ipconfig` en el servidor).
3. La última version de la app se sirve desde el servidor; cada PC usa su propia sesión y la impresora
   térmica se configura en la máquina que la tiene conectada.

## Instalación en la PC del cliente

1. **PostgreSQL**: instalar PostgreSQL 16 como servicio de Windows y crear la base de datos que
   indique el `.env` (por defecto `factus_esperanza_veliz` con usuario `postgres`). Restaurar un
   backup si es reemplazo de la PC actual.
2. Copiar el proyecto a la PC, asegurarse de que **PHP 8.2+** esté instalado y que el `.env` apunte a
   la BD local.
3. Ejecutar `php artisan migrate --seed` (la tabla `lanzador_sesiones` es nueva; el seed crea el admin).
4. Opcional: crear un acceso directo a `launcher/FACTUS.vbs` en el escritorio (doble clic lo abre).
5. Doble clic en **FACTUS.vbs**: se abre la ventana con el login (`admin` / `admin123` o el usuario
   que corresponda).
6. Configurar la impresora térmica según `docs/IMPRESORA.md`.

## Verificación

Al cerrar la ventana no deben quedar procesos:

```powershell
Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
Get-Process php -ErrorAction SilentlyContinue
```

## Notas

- El lanzador usa `php artisan serve`, no `php -S` directo (a diferencia del viejo lanzador Electron).
- `launcher/FACTUS.vbs` se puede renombrar (p. ej. "Abrir FACTUS.vbs") sin problema.
- Los tests del flujo de cierre de sesión viven en `tests/Feature/LanzadorControllerTest.php`.
