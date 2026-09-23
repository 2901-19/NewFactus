# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Primary: cajeros** de abastos y bodegas en Venezuela. Operan en el mostrador, con la venta como tarea principal: buscar producto, cobrar rápido, entregar recibo. Trabajo con teclado y ratón, jornadas de alta repetición.
- **Secondary: el dueño o encargado del negocio.** Gestiona inventario, precios, tasas de cambio, créditos al fiado, reportes del día/mes y usuarios/roles. Es quien cierra cuentas y toma decisiones de negocio.

## Product Purpose

POS de mostrador para pequeños comercios venezolanos (abastos y bodegas): vender rápido, llevar inventario y precios, registrar ventas de contado y crédito (fiado) en doble moneda, imprimir recibos en impresora térmica y reportar las finanzas del día y del mes. Todo corre instalado en la PC del dueño del local, sin depender de internet.

## Positioning

Doble moneda integrada de forma nativa: el precio en Bs de cada producto sale de una tasa por tipo (BCV/usdt/promedio) configurable y con historial de cambios; la deuda de un crédito queda fijada en USD al momento de la venta y se cobra al tipo vigente de ese día. Venta por mostrador sin fricción: el contado no exige registrar cliente, la búsqueda de producto escala a miles de ítems, y los recibos salen por impresora térmica. Funciona sin conexión a internet, en una sola PC del local.

## Operating Context

- **Despliegue on-premises en Windows**: el sistema corre en la PC de mostrador del cliente (PHP + PostgreSQL locales, abierto por un lanzador de escritorio que arranca `php artisan serve` en `0.0.0.0:8000` y abre el navegador en modo app; también accesible desde otras PCs de la red por la IP del servidor).
- **Un solo punto de venta por local.** No hay requisito de varias cajas ni de multi-tienda.
- **Impresora térmica** conectada por red (puerto 9100) o compartida en Windows; recibo de venta, ticket de crédito y precio labels.
- **Pesaje por kg**: productos pesables (rebanadas, granel) con unidad y cantidad por peso.
- **Conexión a internet inestable o inexistente**: todo funciona local; no hay dependencias en la nube.
- **Instalación** hecha por el vendedor del sistema (visita a la PC del cliente, migraciones y seeders ejecutados a mano). No hay instalador automático ni control remoto.
- **Fusión/USD sin conexión**: la tasas se actualizan manualmente desde la pantalla de tasas (o importándolas), nunca por fetch automático.

## Capabilities and Constraints

### Capabilities confirmadas
- **POS**: catálogo con búsqueda server-side, carrito, venta contado (sin cliente) y crédito (requiere cliente), métodos de pago efectivo / punto / biopago / divisas / pago móvil / transferencia / mixto, descuento de inventario automático y con factor de presentación.
- **Inventario**: productos con presentaciones y factor de conversión (ej. Unidad / Mayor), control con unidades decimales, stock bajo, ajuste manual de stock.
- **Precios**: por presentación con margen sobre costo, precios en Bs y USD, lista de precios en pantalla/PDF y precio labels impresos.
- **Créditos (fiado)**: ventas a crédito 100% USD, pantalla de cobro con tasa vigente, bloqueos (no anular cobrado).
- **Tasas de cambio**: historial append-only por tipo (BCV/usdt/promedio), activación/desactivación, tasa de referencia para totales en USD.
- **Reportes**: ventas por período y método, estadísticas y gráficas, balance mensual, stock bajo; export a PDF y CSV.
- **Clientes, categorías e impuestos (IVA)**: CRUD con protección de borrado cuando hay dependencias.
- **Administración**: usuarios, roles dinámicos con permisos por slug, configuración del negocio (nombre, RIF, dirección, teléfono, IVA, tasa de referencia), importer/exporter de datos, impresora, recordatorios internos (tasa diaria).
- **Integración heredada**: migración de inventario del sistema viejo (a partir de archivo SQL), importación/exportación de tasas e inventario.

### Constraints
- Plataforma: web servida por Laravel en la máquina del cliente (Windows); PostgreSQL en producción, SQLite en pruebas (tests en memoria).
- Un solo local, una sola caja. Sin multi-tenant ni sincronización en la nube.
- UI en español; todos los textos de pantalla, recibos, PDFs y la impresora en español.
- El nombre del negocio (recibos, impresora, PDFs, login) es configurable y distinto del nombre del producto.

### Terminology
Bs, USD, tasa de referencia, presentación, factor de conversión, pesable (kg), contado, crédito/fiado, anulada, efectivo, punto (de venta), biopago, divisas, pago móvil, transferencia, mixto, correlativo, stock.

## Brand Commitments

- Nombre del producto: **Factus** (configurable vía `APP_NAME`); la marca se muestra en login, sidebar y páginas de error.
- El nombre del negocio del cliente es un dato configurable (tabla `configuraciones`, clave `nombre_negocio`) que domina en recibos, tickets térmicos y encabezados de PDF. Nada quedó hardcodeado al comercio original ("Esperanza Veliz").
- Voz e identidad: neutral y genérica, de manera que el sistema no parezca un desarrollo a la medida de un cliente concreto, sino un producto vendible a terceros.
- UI, métodos de pago y flujos orientados al comercio venezolano de barrio.

## Evidence on Hand

- 253 tests funcionales (778 assertions) cubriendo POS, créditos, tasas, inventario, reportes, roles y migración del sistema viejo. Comando: `php artisan test`.
- Migración real del sistema anterior: 1628 productos importados desde `inventories` (backup legacy), con deduplicación de nombres y presentaciones.
- Documentación física: `docs/INSTALACION.md` (despliegue a máquina cliente), `docs/IMPRESORA.md` (impresión térmica mike42/escpos), `launcher/` (lanzador de escritorio con watchdog).
- No existen testimonios, casos de éxito documentados ni material de venta. No inventar.

## Product Principles

1. **La venta por mostrador debe ser la experiencia más rápida posible**: menos clicks, contado sin cliente obligatorio, búsqueda fluida incluso con miles de productos.
2. **La doble moneda es funcionalidad central, no un extra**: precios en Bs desde tasas, fiado fijado en USD, cobro al tipo vigente del día.
3. **El comercio no depende de internet**: sistema, datos e impresión funcionan por completo en la PC local.
4. **La marca del cliente prevalece**: el nombre de su negocio en recibos, tickets y PDFs es configurable y manda sobre el branding del producto.
5. **Simplicidad de un solo local y una sola caja** por encima de features multiusuario/red que nadie pidió.