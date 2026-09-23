# AGENTS.md

Laravel 12 + PHP 8.2 POS app (FACTUS — Esperanza Veliz). PostgreSQL in dev, Bootstrap 5 + jQuery/DataTables 2.x + Alpine.js + Chart.js on the front. UI strings, views and test method names are in **Spanish** — keep new code consistent.

## Commands

- Tests: `php artisan test` (runs on in-memory SQLite, no DB needed). Single: `php artisan test --filter=NomeDoTeste`
- Dev server: `php artisan serve` (port 8000). Full stack (`server + queue + pail + vite`): `composer dev`
- Assets: `npm run dev` / `npm run build` (Vite)
- Setup DB: `php artisan migrate --seed`
- Style: `vendor\bin\pint` (run on touched files)

## Testing quirks

- `phpunit.xml` forces `DB_CONNECTION=sqlite :memory:` — the pgsql `.env` is ignored by tests.
- Almost every route is guarded by the `permiso:slug` middleware, so Feature tests must seed `\Database\Seeders\PermisoSeeder::class` (typically in `setUp`) or requests return 403. Existing pattern: `User::factory()->create(['rol' => 'admin'])` + `$this->actingAs($user)`.

## Auth & permissions (non-default)

- Login is by `usuario` field, **not email** (`User::username()` returns `'usuario'`, see `app/Http/Requests/Auth/LoginRequest.php`).
- Custom middleware aliases `rol` (CheckRole) and `permiso` (CheckPermiso) are registered in `bootstrap/app.php`. CheckPermiso calls `User::hasPermiso($slug)` and aborts 403.
- **Roles are dynamic** (`roles` table): the admin manages them from `/roles` (RolController + `resources/views/roles/*`). A user's permissions come **only from its role** — there is no per-user grant (no `permiso_user` table in the rebuilt schema). `users.rol` is a plain string holding `roles.slug` (convention + `exists:roles,slug` validation, no DB FK); display name via `User::role()` (belongsTo Rol, FK `rol`, owner key `slug`).
- **Only the `admin` role is preloaded** (`PermisoSeeder`): slug `admin`, `protegido = true`, always synced with all permissions. Protected roles cannot be edited or deleted. Other roles are created by the admin; `store()` auto-generates `slug` from `nombre` via `Str::slug` (with `_2` suffix on collision, same pattern as `tasa_cambios.tipo`); `slug` is immutable. `permiso_rol` is keyed by the `rol` slug string; `Rol::permisos()` = `belongsToMany(Permiso::class, 'permiso_rol', 'rol', 'permiso_id', 'slug', 'id')`. Deleting a role is blocked while users reference it.
- `User::esAdmin()` returns `rol === 'admin'`; `HerramientasController::importar()` also checks the raw `rol` string. `CheckRole`/`rol` middleware is registered but unused.
- Seeded credentials: `admin`/`admin123` (only).

## Tasas de cambio (tasa_cambios)

- The table is an **append-only history**: `TasaCambioController::actualizar()` never updates a row, it inserts a new one per change (with `user_id` and `origen = 'manual'`). The "current" rate for a `tipo` is the **latest row by `id`** — see `TasaCambio::ultimaDe()`, `activade()`, `ultimasPorTipo()`, `mapaMontos()`. Never assume one row per tipo (no unique constraint on `tipo` in the rebuilt schema).
- `tipo` is an internal system code (`[a-z0-9_]`, unique). In `store()` it's **auto-generated from the `nombre`** via `Str::slug` (with `_2` `_3`... suffix on collision) — the UI never asks for it. The user-facing name is `nombre`; fallback display is `ucfirst($tipo)`.
- Reference rate: `Configuracion` key `tasa_referencia` (default `'bcv'`), settable only on an active rate (`fijarReferencia`). `toggleEstado()` flips `activo` on the whole series of a tipo and is **blocked when the rate is the current reference**.
- Historial (`historial()`): ordered `orderByDesc('created_at')->orderByDesc('id')` — the `id` tie-break matters since tests (and real edits) can insert in the same second. Paginated 20. `variacion` is computed per tipo against the **previous row of the same tipo** (skip other tipos) and attached to the newest row; null when not computable.
- Import: `HerramientasController::importar()` inserts a new row with `origen='importado'` (badge in historial) instead of updating.
- Invoices save a **snapshot** `tasa_cambio` at creation — later rate changes don't affect existing facturas. **Each presentation** (`producto_presentaciones.fuente_tasa`) carries its own source-type selector (default `promedio`, NOT NULL); `productos` no longer has the column (moved by migration `2026_08_14_000001`). Bs pricing per item uses the presentation's rate; POS USD totals still divide by the **reference** rate. Price helpers use `ultimasPorTipo`/`mapaMontos`; validation rule is `presentaciones.*.fuente_tasa` (required + exists). Import/export in Herramientas accepts both layouts: per-presentation `fuente_tasa` wins, falls back to legacy product-level key, else `promedio`.

## Créditos (facturas a crédito)

- A credit invoice is **100% USD**: debt is fixed in `total_usd` at sale (`total_bs / tasa_cambio` snapshot). All money columns on the ticket, POS confirm modal and `facturas.show` render USD for `estado === 'credito'` (items too, via `precio_unitario_bs / tasa_cambio`).
- Payment method is **not chosen at sale**: `store()` forces `metodo_pago = 'credito'` as a sentinel. At collection time `pagarCredito()` **updates the same row** (no extra columns): sets `metodo_pago` to the real single method (validation `in:efectivo,punto,biopago,divisas,pago_movil,transferencia` — mixto is rejected), `estado_credito = 'cancelado'`, `pago_bs` (= `total_usd * tasa vigente` at that moment) and `fecha_pago`. `pago_bs`/`fecha_pago` are the only credit columns on the table; there is **no `pago_usd`** — `total_usd` doubles as amount paid.
- "A pagar hoy (Bs)" is shown **only** in the collection screen (`facturas.creditos`), computed live as `total_usd * tasaVigente`; the cobrar flow uses a Bootstrap modal with a required method select. `anular()` is blocked once `estado_credito === 'cancelado'`.
- `show()`/`creditos()` pass `tasaVigente` from `TasaCambio::ultimaDe()` (id-based), never `latest()`.

## Environment

- Dev `.env`: `APP_ENV=production`, `APP_DEBUG=false`, DB `pgsql` → `factus_esperanza_veliz` on 127.0.0.1:5432 (user `postgres`). This is intentional; don't "fix" it.
- Migration files carry manual date prefixes (e.g. `2026_08_13_000000_...`); follow that pattern for new ones. The schema was **rebuilt from scratch** (squashed) in the `2026_08_13_*` batch — one migration per table with the final schema, no legacy backfills. After pulling, run `php artisan migrate:fresh --seed` on the dev pgsql DB to rebuild it.

## Frontend gotchas

- DataTables is initialized with Spanish via `window.DataTableSpanish` in `resources/views/layouts/app.blade.php` — it passes a **literal Spanish object** (same shape as the defaults in `resources/js/app.js`). Do NOT switch it back to an async `url: '/js/i18n/es-ES.json'` fetch: the async load combined with `order` + `columnDefs:[{targets:'_all',defaultContent:''}]` makes DataTables 2.3.8 wipe all rows ("Ningún dato disponible").
- Blades mix server-rendered HTML with DataTables/Alpine; UI copy is Spanish.

## Lanzador de escritorio (launcher/)

- `launcher/` es un lanzador de **un clic en PowerShell** (reemplazó a Electron): `FACTUS.vbs` (doble clic) ejecuta `factus-launcher.ps1` en consola oculta. Levanta el servidor con `php artisan serve --host=0.0.0.0 --port=8000` (escucha en todas las interfaces → también se usa desde otras PCs de la red por IP; `launcher/config.json` `host` puede ser `127.0.0.1`/vacío para dejarlo solo local o una IP concreta), abre el navegador en modo app y al cerrarse la ventana apaga todo: mata el navegador, hace `POST /lanzador/cerrar-sesion` y mata PHP. Cierre de ventana = apagado total, sin procesos huérfanos.
- El lanzador además: **verifica PostgreSQL** (puerto `postgresPort`, TCP) antes de arrancar y, con la ventana abierta, ejecuta un **watchdog del servidor**: si el puerto cae, reinicia PHP (hasta 3 intentos) y refresca la página en su lugar (F5, sin cerrar la ventana). Si falla repetidamente, muestra error y apaga. Las comprobaciones del servidor usan el **destino calculado** (127.0.0.1 para `0.0.0.0`/vacío/local, o la IP configurada); PostgreSQL siempre se chequea en 127.0.0.1. Acceso desde otra PC requiere regla entrante en Firewall (`netsh advfirewall`), ver `docs/LANZADOR.md`.
- Config en `launcher/config.json`: `port`, `host` (defecto `0.0.0.0`), `phpPath`, `appPath`, `browser` (`auto` = Edge → Chrome → Brave, o `edge`/`chrome`/`brave`), `postgresPort` (defecto `5432`; `<= 0` desactiva el chequeo). Estado en `%LOCALAPPDATA%\FACTUS\` (`php.pid`, `token.txt`, perfil del navegador).
- **Cierre de sesión**: la tabla `lanzador_sesiones` (token → `session_id`) y el middleware `RegistrarLanzador` registran el token por petición. `LanzadorController@cerrarSesion` (`POST /lanzador/cerrar-sesion`, exento de CSRF) destruye solo la sesión vinculada. El middleware corre **antes que `auth`** (se registra en `prependToPriorityList(AuthenticatesRequests, ...)` en `bootstrap/app.php`) para capturar peticiones de invitados.
- Tests: `tests/Feature/LanzadorControllerTest.php` (solo el de token desconocido → 204; el flujo completo se probó manualmente).
- Instalación de cero en un equipo (PHP + PostgreSQL + despliegue en la PC del cliente sin Node/Composer/Git): ver `docs/INSTALACION.md`.

## Notificaciones programadas

- Componente genérico en `app/Services/Notificaciones/` (sin tablas nuevas, evaluación perezosa por request — no hay cron en la PC del cliente). Para **agregar un tipo nuevo**: crea una clase que implemente `Contracts/Notificacion.php` (`tipo`, `permisoRequerido`, `debeMostrar(Carbon $ahora)`, `titulo`, `mensaje`, `accionUrl`, `textoAccion`, `posponerHasta`) y regístrala en `config/notificaciones.php`. Nada más: el registro (`RegistroNotificaciones::pendientes(User)`), las rutas (`GET /notificaciones/pendientes`, `POST /notificaciones/{tipo}/posponer`) y el widget ya lo recogen.
- El widget (Alpine `campanaNotificaciones()` en `layouts/app.blade.php`) consulta cada 60s y muestra banners fijos arriba a la derecha con Acción / "Recordar más tarde". "Posponer" guarda un flag de sesión hasta `posponerHasta()`.
- Primer tipo: `Tipos/RecordatorioTasa` — avisa a quien tiene permiso `gestionar-tasas` cuando la tasa de referencia no se ha actualizado después del inicio de cada ventana. Interruptor y horas configurables desde `/herramientas/configuracion` (tarjeta propia, endpoint `POST /herramientas/recordatorio`, guardado por `HerramientasController::recordatorioGuardar`). Claves `Configuracion`: `recordatorio_tasa_activo` (`'1'`/`'0'`, defecto `'1'`), `recordatorio_tasa_hora1` (`'09:00'`), `recordatorio_tasa_hora2` (`'14:00'`); validación `hora2 > hora1`. El checkbox desmarcado **no viaja** en el POST: persistir siempre con `$request->boolean()`.
- Tests: `tests/Feature/NotificacionesTest.php` (usa `$this->travelTo()`; para sembrar tasas viejas usa `$tasa->forceFill(['created_at' => ...])->save()` — `update(['created_at'])` se descarta por `$fillable`).

## Misc

- **Manejo de errores en UI**: los flujos que pueden fallar deben redirigir con `withErrors(['error' => ...])` (el layout lo muestra como toast), nunca dejar 404/500 crudos. Guards vigentes: producto desactivado no se edita (`edit`/`update` cargan `withTrashed()` y redirigen; el botón Editar se oculta en filas trashed), impuesto con productos / cliente con facturas / último admin / auto-eliminación no se eliminan, `pagarCredito` sin tasa de referencia redirige con aviso (botón Cobrar se deshabilita sin `$tasaVigente`). Páginas de error propias (español) en `resources/views/errors/{403,404,500,503}.blade.php` — Laravel las usa automáticamente al existir.
- **Cache de configuración**: `Configuracion::obtener()` lee desde cache (`configuracion:{clave}`, store `file`). Todo guardado debe llamar `Configuracion::olvidar($clave)` (o `olvidarTodas()`); los `updateOrCreate` de `configuracionGuardar`/`recordatorioGuardar`/`fijarReferencia` ya lo hacen. Al buscar claves en seeders/commands usa `where('clave')->exists()` directo (nunca `obtener()`) para no leer cache, y `olvidar` al final.
- **DataTables server-side**: POS (`/pos/productos`), listado de productos (`/productos/data`) y ajustar precios (`/productos/ajustar-precios/data`) consumen DataTables server-side: el endpoint devuelve `{draw, recordsTotal, recordsFiltered, data}` y la vista no embebe colecciones grandes. Nuevas páginas pesadas deben seguir este patrón. La búsqueda usa `LOWER(columna) LIKE ?` (compatible SQLite tests + pgsql), nunca `ilike` (SQLite no lo soporta).
- **Lista de precios con filtros**: `HerramientasController@precios`/`preciosPdf` aceptan `categoria_id` y `presentacion[]` (multi-selección de nombres de presentación activa, validate contra las opciones reales; nombres inexistentes se ignoran, sin filtro = todas). La vista (`herramientas/precios.blade.php`) renderiza pills "Todas/Unidad/Mayor…" como links (patrón server-side, sin JS) y los botones PDF/JSON heredan los filtros en la URL (idem `precios-pdf.blade.php`, que añade sufijo `_unidad_mayor` al nombre del archivo). El `export=json` filtra por el mismo criterio.
- **Formato numérico español**: todos los montos en pantalla/impr TICKET usan `\App\Support\Moneda` (`n($v, $dec=2)` → `1.234,56`; `bs()`/`usd()` con prefijo) y en JS `window.fmtMoneda` (Intl `es-VE`) definido en `resources/js/app.js`. **Excepción**: los `<input type="number">` siguen con punto decimal (`0.00`) y la aritmética interna del POS compara números crudos (`diferenciaPagos > 0.01`). El CSV de `ReporteController` y la clave interna de `StockService` usan formato propio deliberadamente.
- **Thermal printing**: see `docs/IMPRESORA.md` (mike42/escpos-php). Central en `app/Services/PrinterService.php` (`ANCHO=48` celdas para papel 80 mm): ticket con columnas dinámicas por fila (`DESC = 48 − CANT − PREC_U − PREC_T`, mín 12, desc envuelve por palabras), **`Str::ascii()`** en todo texto usuario (1 char = 1 celda; no usar el Unicode original al imprimir), totales a tamaño doble en una línea con fallback `(1,2)`, montos con `Moneda::n`. `itemsDesdeFactura(Factura)` es el builder compartido (usa `posiciones`/`pagos`, no el booking viejo). Etiqueta de precio sin negocio, producto 2x negrita centrado (24 celdas), precio adaptativo 2x2 (tamaño 4 si ≤12 chars).
- **Impresión automática al facturar**: `FacturaController::store()` imprime tras `DB::commit()` en `try/catch` — la venta nunca falla por la impresora. Interruptor `Configuracion` `imprimir_al_facturar` (default `'1'`, toggle en `herramientas/impresora.blade.php`, guardado en `printerGuardar`/`imprimirGuardar` con `updateOrCreate` + `olvidar`). El JSON del POS lleva `impreso` y `impresion_error='no_impreso'`; el POS muestra toast si `impreso === false`. En tests de FacturaController el `setUp` desactiva el flag (`updateOrCreate` valor `'0'` + `olvidar`). `getPrinterConfig` delega en `PrinterService::configuracion()` (lee `storage/app/impresora.json`).
- Git: `main` is the integration branch; `login-mejorado` is an open feature branch (login redesign). Local feature branches (e.g. `tasas-dinamicas`) get fast-forwarded into `main` after their feature tests pass. Keep commits in Spanish. Brand name: `NEW FACTUS` (`APP_NAME`), también en la copia visual del login/encabezados. Actualizar una PC de cliente sin Git/Node: ver sección 8 de `docs/INSTALACION.md` (no sobrescribir `.env` ni `launcher/config.json`, restaurar uploads, `migrate --force`).

## Migración del sistema viejo

- Comando artisan: `php artisan migrar:inventario-viejo --archivo="ruta/archivo.sql" [--force] [--export-json="ruta.json"]`
- **`--export-json`**: convierte sin tocar la BD — vuelca el catálogo en el layout JSON del importador (`precios[]` con `costo_usd` y `presentaciones[]`) para editar precios y luego Importar por la UI. El `precio_usd` que escribe es el **recalculado** (`PrecioService::precioPresentacion(costo, margen, factor)`), igual que aplicará el importador. Acepta archivos con BOM UTF-8 (se elimina al parsear).
- Parsea INSERT SQL de la tabla vieja `inventories` (15 columnas por fila, encoding Latin1/Win-1252 auto-detectado).
- **Nombre producto**: combinación deduplicada de `description` + `name` (tokens primero de `description`, luego `name`, palabras repetidas solo una vez, case-insensitive; se quita la palabra `Mayor` antes). Ej: `Pepsi Cola 2L` + `Refresco` → `Pepsi Cola 2L Refresco`. La clave de agrupación usa el mismo nombre normalizado, así normal↔Mayor se emparejan aunque `Mayor` venga en `name` o en `description`.
- **Categoría, imagen e impuesto quedan vacíos** (null): no se crean categorías ni IVA; `estado` viene de `status` (`Disponible`→`disponible`).
- **Costo**: regla inversa `round(precie_usd * (1 - porcentage/100), 2)` (mín 0, 0 si falta precie_usd). `unidad_medida='unidad'`, `stock_actual=0` (conteo manual). Precios `precie_unit` van a la presentación.
- **Presentaciones**: fila normal → `Unidad` (factor 1); fila con palabra `Mayor` en `name` o `description` → presentación extra `Mayor` (`factor_conversion = units_package` de esa fila). Solo-Mayor se crea como 1 presentación "Mayor". `margen = porcentage` en ambas; `fuente_tasa` mapea `daily_dollar`: `0`→bcv, `1`→usdt, `2`→promedio.
- `--force` trunca `producto_presentaciones`, `productos`, `categorias` e `impuestos` antes de re-ejecutar. Es idempotente sin `--force` (salta productos ya existentes por `nombre`).
- Seeder: `TasaReferenciaSeeder` crea filas iniciales para tipos `bcv`, `usdt`, `promedio` en `tasa_cambios` (monto 0) y fija `Configuracion.tasa_referencia = 'bcv'`.
- Tests: `tests/Feature/MigrarInventarioViejoTest.php` (14 tests: parseo, nombre dedup, fusión normal+Mayor en `name`/`description`, categoría/imagen/impuesto vacíos, costo, fuente_tasa, idempotencia, force, encoding).
