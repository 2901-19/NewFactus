# Guía de instalación de FACTUS (de un equipo limpio a listo para usar)

Guía paso a paso para dejar FACTUS funcionando en Windows: instalar y configurar cada tecnología,
preparar el sistema en la máquina de desarrollo, desplegarlo en la **PC del cliente** (caja) y dejar
el lanzador de un clic listo para empezar a facturar.

## 0. Arquitectura objetivo

| Componente | Rol |
|---|---|
| PC caja (Windows 10/11 x64) | Única máquina; corre servidor, navegador y lanzador |
| PostgreSQL 16+ | Servicio local de Windows; guarda toda la información |
| PHP 8.2+ | Ejecuta la app Laravel (`php artisan serve`) |
| Proyecto FACTUS | Código Laravel + vendor + assets compilados |
| `launcher/FACTUS.vbs` | Acceso directo de doble clic: levanta todo y abre la ventana |

## 1. Tecnologías y versiones

| Tecnología | Versión mínima | Probado en este proyecto | Descarga |
|---|---|---|---|
| Windows 10/11 (x64) | — | 11 | — |
| PHP (Thread Safe x64) | 8.2 | 8.3.32 | https://windows.php.net/download/ |
| PostgreSQL | 16 | 18 | https://www.postgresql.org/download/windows/ |
| Composer | 2 | 2.10.2 | https://getcomposer.org/download/ |
| Node.js | 20 | 24.18.0 | https://nodejs.org/ (LTS) |
| Git | 2 | 2.55.0 | https://git-scm.com/ |

> **Dónde se necesita cada cosa**: Node.js, Composer y Git solo se usan en la **máquina de desarrollo**
> (sección 2 y 3). La **PC del cliente** (sección 5) solo necesita PHP y PostgreSQL; el proyecto se
> copia ya armado con `vendor/` y `public/build` compilados.

## 2. Instalar y configurar cada tecnología

### 2.1 PHP (en `C:\php`)

1. Descargar el **zip "Thread Safe x64"** de PHP 8.2+ desde https://windows.php.net/download/.
2. Descomprimir el contenido en **`C:\php`**.
3. Copiar `C:\php\php.ini-development` → `C:\php\php.ini`.
4. Editar `C:\php\php.ini` y activar (quitar el `;`):
   - `extension_dir = "ext"`
   - `extension=curl`, `extension=fileinfo`, `extension=gd`, `extension=intl`,
     `extension=mbstring`, `extension=openssl`, `extension=pdo_pgsql`, `extension=pgsql`,
     `extension=sqlite3`, `extension=zip`
   - `date.timezone = America/Caracas`
5. Agregar `C:\php` al **PATH del sistema** (Panel de control → Sistema → Configuración avanzada →
   Variables de entorno → `Path` del sistema → Nuevo → `C:\php`). En PowerShell como administrador:
   ```powershell
   [Environment]::SetEnvironmentVariable('Path', $env:Path + ';C:\php', 'Machine')
   ```
6. Verificar en una terminal nueva:
   ```powershell
   php -v     # debe mostrar 8.x.x
   php -m     # debe listar pgsql y pdo_pgsql
   ```

### 2.2 PostgreSQL

1. Ejecutar el instalador EDB de PostgreSQL 16+.
2. En el asistente: componentes **PostgreSQL Server** (pgAdmin es opcional), puerto **5432**, y
   definir la contraseña del superusuario `postgres` (anotarla; va al `.env`).
3. Verificar que el servicio quedó corriendo:
   ```powershell
   Get-Service postgresql*   # debe aparecer "Running"
   ```
4. Crear la base de datos del sistema (ajustar `18` a la versión instalada):
   ```powershell
   & "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -h 127.0.0.1 -c "CREATE DATABASE factus_esperanza_veliz;"
   ```
   Te pedirá la contraseña de `postgres`. También puede hacerse desde pgAdmin
   (clic derecho en *Databases* → *Create* → *Database*).

### 2.3 Composer

1. Descargar y ejecutar `Composer-Setup.exe` desde https://getcomposer.org/download/.
2. En el asistente indicar el PHP instalado (`C:\php\php.exe`); Composer queda en el PATH.
3. Verificar: `composer --version`.

### 2.4 Node.js

1. Instalar la versión **LTS** desde https://nodejs.org/ (incluye npm).
2. Verificar: `node -v` y `npm -v`.

### 2.5 Git

1. Instalar desde https://git-scm.com/ (opciones por defecto).
2. Verificar: `git --version`.

## 3. Instalar el sistema FACTUS

En PowerShell (por ejemplo en `C:\Users\<usuario>\Documents\Projects`):

```powershell
# 1) Descargar el código
git clone https://github.com/2901-19/NewFactusEzperanzaVeliz.git
cd NewFactusEzperanzaVeliz

# 2) Dependencias de PHP
composer install

# 3) Dependencias y assets de frontend
npm install
npm run build

# 4) Crear el archivo .env
Copy-Item .env.example .env
```

### 3.1 Configurar `.env`

Editar `.env` con los datos de la BD creada en 2.2 y los valores de producción:

```
APP_NAME="Factus Esperanza Veliz"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=factus_esperanza_veliz
DB_USERNAME=postgres
DB_PASSWORD=<contraseña de postgres>

SESSION_DRIVER=file
QUEUE_CONNECTION=database
CACHE_STORE=file
LOG_LEVEL=warning
```

### 3.2 Clave, migraciones y datos iniciales

```powershell
php artisan key:generate
php artisan migrate --seed
```

> `migrate --seed` crea las tablas (incluida `lanzador_sesiones`, usada por el lanzador) y siembra
> los datos iniciales: datos por defecto del negocio, el rol `admin` con todos los permisos y el
> usuario **`admin` / `admin123`** (única credencial que se siembra).

### 3.3 Probar el sistema

```powershell
php artisan serve
```

Abrir `http://localhost:8000` y entrar con `admin` / `admin123`.

## 4. Poner en producción (máquina de desarrollo)

Con todo funcionando, cachear la configuración para arranques más rápidos:

```powershell
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> Si después cambias el `.env`, vuelve a ejecutar `php artisan config:clear` y `config:cache`.

## 5. Instalar en la PC del cliente (ruta mínima)

La PC de caja **no necesita Node.js, Composer ni Git**: se copia el proyecto ya armado. Solo se
instalan **PHP** y **PostgreSQL**.

### 5.1 Prerrequisitos

1. Instalar **PHP** siguiendo el paso 2.1 (extensiones `pdo_pgsql`/`pgsql` obligatorias).
2. Instalar **PostgreSQL** siguiendo el paso 2.2 (servicio Windows, puerto 5432, contraseña `postgres`).
3. Crear la base de datos:
   ```powershell
   & "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -h 127.0.0.1 -c "CREATE DATABASE factus_esperanza_veliz;"
   ```

### 5.2 Copiar el proyecto

En la máquina de desarrollo, comprimir la carpeta del proyecto (o copiarla por red/USB) **incluyendo**
`vendor/` y `public/build/` (ya compilados). Copiar la carpeta a, por ejemplo, `C:\Factus\` en el cliente.

### 5.3 Configurar el `.env` del cliente

Dentro de `C:\Factus\`:

```powershell
Copy-Item .env.example .env
```

Editar `.env` igual que en 3.1 (con la contraseña de PostgreSQL de la PC del cliente) y generar la clave:

```powershell
php artisan key:generate
```

### 5.4 Migrar y sembrar

Sistema nuevo (sin datos previos):

```powershell
php artisan migrate --seed
```

> **Reemplazo de una PC con datos**: NO correr `migrate --seed`; restaurar el respaldo de la BD
> (sección 7) y luego solo `php artisan migrate` para crear la tabla `lanzador_sesiones` si el
> respaldo es anterior a ella.

### 5.5 Configurar el lanzador

Editar `C:\Factus\launcher\config.json`:

```json
{
    "port": 8000,
    "host": "0.0.0.0",
    "phpPath": "C:\\php\\php.exe",
    "appPath": "C:\\Factus",
    "browser": "auto"
}
```

- `port`: puerto del servidor (por defecto `8000`).
- `host`: interfaz de escucha. `0.0.0.0` (defecto) permite usar el sistema **desde otras PCs de la red**
  por `http://IP_DEL_SERVIDOR:8000`; `127.0.0.1` o vacío lo deja local.
- `phpPath`: ruta al PHP del cliente (o `null` si está en el PATH).
- `appPath`: carpeta raíz del proyecto (contiene `artisan`).
- `browser`: `auto` (Edge → Chrome → Brave), `edge`, `chrome` o `brave`.

**Firewall (una sola vez, como Administrador) si se usará desde más de una PC:**

```powershell
netsh advfirewall firewall add rule name="FACTUS 8000" dir=in action=allow protocol=TCP localport=8000
```

### 5.6 Acceso directo en el escritorio

Crear un acceso directo a `launcher\FACTUS.vbs` (se puede renombrar a "Abrir FACTUS"). En PowerShell:

```powershell
$ws = New-Object -ComObject WScript.Shell
$lnk = $ws.CreateShortcut("$env:USERPROFILE\Desktop\Abrir FACTUS.lnk")
$lnk.TargetPath = "C:\Factus\launcher\FACTUS.vbs"
$lnk.WorkingDirectory = "C:\Factus\launcher"
$lnk.Save()
```

Doble clic en **Abrir FACTUS**: se abre la ventana con el login. Al cerrarla se apaga el servidor,
el navegador y se cierra la sesión (no quedan procesos). Más detalles en `docs/LANZADOR.md`.

## 6. Configuración inicial del sistema (primer uso)

1. Entrar con `admin` / `admin123` (cambiar la clave al finalizar).
2. **Herramientas → Configuración**: actualizar nombre del negocio, RIF, dirección, teléfono e IVA
   (el seed deja valores de ejemplo).
3. Crear los roles necesarios en **Roles** (el rol `cajero` se crea ahí; no viene sembrado) y luego
   los usuarios en **Usuarios** asignándoles su rol.
4. **Tasas de Cambio**: crear las monedas/fuentes (`bcv`, `paralelo`, etc.), activarlas y fijar la
   de referencia.
5. Cargar **Categorías**, **Clientes** y **Productos** (o usar Herramientas → Importar con el JSON
   de un respaldo).
6. Configurar la impresora térmica siguiendo `docs/IMPRESORA.md`.

## 7. Respaldo y restauración de la base de datos

Respaldo (formato custom, comprimido):

```powershell
& "C:\Program Files\PostgreSQL\18\bin\pg_dump.exe" -U postgres -h 127.0.0.1 -F c -b -f "D:\respaldo\factus_$(Get-Date -Format yyyyMMdd_HHmm).backup" factus_esperanza_veliz
```

Restauración (en una BD vacía `factus_esperanza_veliz`):

```powershell
& "C:\Program Files\PostgreSQL\18\bin\pg_restore.exe" -U postgres -h 127.0.0.1 -d factus_esperanza_veliz -c "D:\respaldo\factus_XXXX.backup"
```

> Recomendado respaldar al final de cada jornada y guardar el archivo en otra unidad o medio externo.

## 8. Actualizar el sistema en la PC del cliente

> La PC del cliente **no tiene** Git/Composer/Node, así que la actualización es reemplazar la carpeta del
> proyecto con la copia nueva. Antes de empezar: **respaldo** (sección 7) y cerrar FACTUS (doble clic → se apaga todo).

1. **Copiar los archivos nuevos** de la máquina de desarrollo (o del ZIP/red) de forma que **reemplacen**
   la carpeta del proyecto en la PC del cliente. Los archivos que generan los assets ya vienen compilados
   en `public/build` (`npm run build` se corre en desarrollo).
2. **NO sobrescribir el `.env`** del cliente. El proyecto incluye un `.env` de desarrollo (PostgreSQL,
   `APP_DEBUG=false`) y el de la PC tiene su propia `APP_KEY` y credenciales. Al copiar, **excluir** `.env`
   (y `.env.example`). Si se respetaron los permisos, conservar el que ya existía; tras el copiado verificar
   que `DB_DATABASE=factus_esperanza_veliz` y las credenciales sigan siendo las del cliente.
3. **NO sobrescribir `launcher/config.json`** si el cliente tiene rutas/puertos propios (`phpPath`,
   `appPath`, `browser`, `postgresPort`).
4. **Restaurar subidas**: los archivos de la copia nueva reemplazan `storage/app/public/uploads`, así que
   si la PC guarda fotos/importados ahí, respaldar esa carpeta antes y volverla a colocar después (los
   archivos que viven en la BD, como las facturas, no se pierden porque están en PostgreSQL).
5. **Migraciones pendientes**: con la PC del cliente (o por escritorio remoto con el lanzador abierto)
   ejecutar en la carpeta del proyecto:
   ```powershell
   php artisan migrate --force
   ```
   (interactivo o forzado; las tablas nuevas son aditivas y no tocan los datos existentes).
6. **Limpiar caches** por si quedaron viejas en `storage/framework/cache`:
   ```powershell
   php artisan config:clear; php artisan view:clear; php artisan cache:clear
   ```
7. Abrir FACTUS, iniciar sesión y verificar: login, un cobro en el POS, **impresión automática del ticket**
   (si el interruptor de "Imprimir ticket automáticamente al facturar" está activo) y los mis montos ahora
   con formato español (`1.234,56`).

> Si la versión nueva cambia la BD, la orden siempre es: respaldo → respaldo → copiar archivos (sin `.env`
> ni `launcher/config.json`) → `migrate --force` → probar.

## 9. Checklist final de verificación

- [ ] `php artisan migrate` no muestra migraciones pendientes (incluye `lanzador_sesiones`).
- [ ] Login con `admin`/`admin123` funciona.
- [ ] Datos del negocio correctos en Herramientas → Configuración.
- [ ] Se puede registrar una venta de contado (POS) y una a crédito.
- [ ] El cobro de un crédito queda marcado como cancelado.
- [ ] La impresora térmica imprime el ticket de prueba y una factura real (`docs/IMPRESORA.md`).
- [ ] Doble clic en **Abrir FACTUS** abre la ventana y el login.
- [ ] Al cerrar la ventana no quedan procesos (`Get-NetTCPConnection -LocalPort 8000 -State Listen`
      no muestra nada; `Get-Process php` no lista procesos).

## 10. Solución de problemas rápidos

| Síntoma | Causa probable | Solución |
|---|---|---|
| Página con **500 Server Error** al abrir el lanzador | Tabla `lanzador_sesiones` no creada | Ejecutar `php artisan migrate` en la carpeta del proyecto |
| Error de conexión a la base de datos | PostgreSQL apagado o credenciales mal | `Get-Service postgresql*`; revisar bloque `DB_*` del `.env` |
| No abre la ventana del lanzador | PHP no encontrado | Revisar `phpPath` en `launcher/config.json` y `php -v` |

## Enlaces relacionados

- `docs/LANZADOR.md` — funcionamiento del lanzador de un clic y cierre de sesión.
- `docs/IMPRESORA.md` — configuración de la impresora térmica ESC/POS.
