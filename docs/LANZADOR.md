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
   PCs de la red por su IP. Arranca con varios **workers** (`PHP_CLI_SERVER_WORKERS`, ver
   `serverWorkers`) para que una petición pesada (p. ej. el PDF de la lista de precios) no congele al
   resto de PCs.
3. **Abre la ventana**: localiza el navegador (Edge → Chrome → Brave, o el que indique
   `config.json.browser`) y abre `http://127.0.0.1:8000/?_lanzador=<token>` con
   `--app` + `--user-data-dir` dedicado, para que la ventana viva en su propio proceso y sea vigilable.
4. **Vigila la ventana**: mientras el usuario la tenga abierta no hace nada; al cerrarla, en `finally`:
   mata el árbol del navegador → `POST /lanzador/cerrar-sesion` con el token → mata el árbol de PHP →
   borra los archivos de estado. Si el servidor cae con la ventana abierta, el watchdog lo reinicia
   (hasta 3 intentos) y recarga la página (F5).

## Estructura

**Servidor (PC principal, `launcher/`):**

| Archivo | Rol |
|---|---|
| `launcher/FACTUS.vbs` | Archivo de doble clic: ejecuta el PowerShell oculto |
| `launcher/factus-launcher.ps1` | Toda la lógica del lanzador (PHP + PostgreSQL + watchdog) |
| `launcher/config.json` | `port`, `host`, `phpPath`, `appPath`, `browser`, `postgresPort`, `serverWorkers` |

**Cliente ligero (otras PCs, carpeta `launcher-cliente/`):**

| Archivo | Rol |
|---|---|
| `launcher-cliente/CLIENTE.vbs` | Archivo de doble clic: ejecuta el PowerShell oculto |
| `launcher-cliente/cliente.ps1` | Conecta al servidor (sin PHP/PostgreSQL), abre y vigila la ventana |
| `launcher-cliente/cliente-config.json` | `servidor`, `port`, `browser` |

El estado se guarda en `%LOCALAPPDATA%\FACTUS\`: servidor (`php.pid`, `token.txt`, `edge-profile`) y
cliente (`cliente-token.txt`, `edge-profile-cliente`), aislados para no chocar.

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
    "postgresPort": 5432,
    "serverWorkers": 2
}
```

- `port`: puerto del servidor (por defecto `8000`).
- `host`: interfaz de escucha del servidor. `0.0.0.0` (defecto) = acepta conexiones de **la red** para
  usar el sistema desde otras PCs (`http://SERVIDOR:8000`) y de la propia máquina. `127.0.0.1` o
  vacío = solo la PC local. Una IP concreta de la LAN = solo esa interfaz.
- `phpPath`: ruta al `php.exe` (si es `null` se busca en `PATH`, `C:\php\php.exe`,
  `%ProgramFiles%\php\php.exe`).
- `appPath`: ruta al proyecto Laravel (por defecto, la carpeta padre de `launcher/`).
- `browser`: `auto` (Edge → Chrome → Brave), `edge`, `chrome` o `brave`.
- `postgresPort`: puerto TCP de PostgreSQL para el chequeo previo (`<= 0` lo desactiva).
- `serverWorkers`: workers del servidor embebido (`PHP_CLI_SERVER_WORKERS`). Permite atender varias
  peticiones a la vez para que una operación pesada (p. ej. generar el PDF de la lista de precios) no
  congele a las demás PCs. **Recomendado: `2`** en el hardware típico del servidor (CPU de 4 hilos
  compartida con PostgreSQL y el navegador de caja); no conviene superarlo. La petición pesada sigue
  tardando lo mismo, pero ya no bloquea al resto.

### Recomendación de hardware (servidor)

El PDF de la lista de precios procesa el catálogo completo y es la operación más exigente. Para una
PC servidor tipo i5 6ª gen / 8 GB / HDD el sistema funciona para el POS normal, pero el PDF se nota:
- **SSD en el servidor** (Windows + PostgreSQL + proyecto): mayor impacto; reduce mucho la lectura de
  la BD y el *swap*.
- **16 GB de RAM**: holgura para picos de memoria del PDF y para crecer.
- **Workers = 2** (software): evita que el PDF bloquee a las cajas (el que lo genera sigue esperando).

### Acceso desde otra PC de la red

1. En la PC servidor, permitir el puerto en Windows Firewall (una sola vez, como Administrador):
   ```powershell
   netsh advfirewall firewall add rule name="FACTUS 8000" dir=in action=allow protocol=TCP localport=8000
   ```
   (Si no se permite, Windows pide el diálogo de firewall en el primer arranque con `host=0.0.0.0`.)
2. La última versión de la app se sirve desde el servidor; cada PC usa su propia sesión y la impresora
   térmica se configura en la máquina que la tiene conectada.
3. Para que las PCs clientes no dependan de la IP (que puede cambiar con DHCP), usar el **nombre de
   equipo** del servidor (ver sección de PCs clientes) y, opcional pero recomendado, una **reserva DHCP**
   en el router o IP estática.

## Instalación en la PC servidor

1. **PostgreSQL**: instalar PostgreSQL 16 como servicio de Windows y crear la base de datos que
   indique el `.env` (por defecto `factus_esperanza_veliz` con usuario `postgres`). Restaurar un
   backup si es reemplazo de la PC actual.
2. Copiar el proyecto a la PC, asegurarse de que **PHP 8.2+** esté instalado y que el `.env` apunte a
   la BD local.
3. Ejecutar `php artisan migrate --seed` (la tabla `lanzador_sesiones` es nueva; el seed crea el admin).
4. Abrir el puerto 8000 en el Firewall (sección "Acceso desde otra PC de la red").
5. Opcional: crear un acceso directo a `launcher/FACTUS.vbs` en el escritorio (doble clic lo abre).
6. Doble clic en **FACTUS.vbs**: se abre la ventana con el login (`admin` / `admin123` o el usuario
   que corresponda).
7. Configurar la impresora térmica según `docs/IMPRESORA.md`.

## PCs clientes (segunda caja / encargado) — `launcher-cliente/`

Las PCs clientes **no instalan PHP ni PostgreSQL**: abren el sistema que sirve la PC principal. Para
evitar revisar la IP cada vez que el servidor cambie (DHCP), se usa el **nombre de equipo** del servidor.

### 1. Dar al servidor una dirección estable (una sola vez)

- **Recomendado — nombre de equipo**: renombrar la PC servidor a un nombre fijo y descriptivo, p. ej.
  `FACTUS-SERVIDOR` (Configuración → Sistema → Acerca de → Cambiar nombre / `Rename-Computer`), y
  reiniciar. En la LAN de Windows ese nombre se resuelve aunque la IP cambie; los clientes usarán
  `http://FACTUS-SERVIDOR:8000`.
- **Refuerzo opcional (muy recomendado) — reserva DHCP**: en el router, reservar siempre la misma IP
  para la MAC de la PC servidor (o asignarle IP estática). Da una IP fija de respaldo y evita depender
  solo de la resolución de nombres.

### 2. Configurar el lanzador cliente

1. Copiar la carpeta **`launcher-cliente/`** a la PC cliente (puede vivir sola, no requiere el proyecto
   Laravel).
2. Editar `launcher-cliente/cliente-config.json`:

```json
{
    "servidor": "FACTUS-SERVIDOR",
    "port": 8000,
    "browser": "auto"
}
```

   - `servidor`: **nombre de equipo** del servidor (recomendado) o su IP fija/reservada. Se configura
     una vez.
   - `port`: puerto del servidor (debe coincidir con el del servidor, por defecto `8000`).
   - `browser`: `auto` (Edge → Chrome → Brave), `edge`, `chrome` o `brave`.

3. Crear un acceso directo a **`CLIENTE.vbs`** en el escritorio (doble clic lo abre).

### 3. Uso y verificación

- Doble clic en **`CLIENTE.vbs`**: verifica que el servidor responda, abre la ventana en modo app y, al
  cerrarla, cierra la sesión del usuario y borra el estado local. No quedan procesos huérfanos.
- Si no conecta, el lanzador muestra un mensaje indicando que se revise que la PC principal esté
  encendida, el sistema abierto y el nombre/IP correcto.
- La PC principal debe estar encendida con el sistema abierto (`launcher/FACTUS.vbs`) y el Firewall
  permitiendo el puerto 8000.
- **Impresora térmica**: se configura en la máquina que la tiene conectada físicamente (ver
  `docs/IMPRESORA.md`), normalmente cada caja en su propia PC.

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
