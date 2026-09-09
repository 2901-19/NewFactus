---
version: 1
slug: "resources-views-layouts-app-blade-php"
primary_target: "resources/views/layouts/app.blade.php"
related_targets: ["resources/views/auth/login.blade.php","resources/views/layouts/sidebar.blade.php","resources/views/layouts/navbar.blade.php","resources/views/dashboard.blade.php","resources/views/facturas/pos.blade.php"]
---

# Surface brief — Factus app shell (world replacement)

## Scope
Superficie: cascarón completo de la app (login, layout/sidebar/navbar, dashboard, POS y páginas CRUD). Mundo de reemplazo del look Bootstrap genérico actual. Mode: Operate. Cliente/audiencia: cajeros y dueños de abastos/bodegas venezolanos, jornada en mostrador, luz fluorescente, sin internet. Trabajo: facturar rápido, leer lo del día, registrar fiado. La marca del titular (nombre del negocio, RIF) configurable desde /herramientas/configuracion.

## Direction contract

THESIS: Factus es una planilla mercantil, no un panel de administración. Rechaza el default SaaS (tarjetas con sombra blanda, gradientes, glass) y el Bootstrap genérico; cada pantalla es un módulo de la misma hoja de factura que el dueño firma cada noche.

OWN-WORLD: fondo papel blanco, tinta pizarra, acento azul carbón; cajas de módulos con hairline y rótulos en caps terse en mono (placard); numeración tabular/typewriter en todas las cifras; los estados viajan en icono+trazo, nunca solo color. Sin gradientes, sin glass, sin glow; botones planos sellados.

STORY: quien lee tickets y lleva cuaderno reconoce una máquina de confianza: totales tabulares, correlativo en la cabecera, tendencia junto a cada valor.

FIRST VIEWPORT: Login = una sola hoja: cabecera de factura (negocio · RIF) → celdas del formulario → botón de tinta "INGRESAR AL SISTEMA" → pie de planilla. Dentro: barra de cabecera (negocio · RIF · fecha) sobre papel, sidebar de tinta como carátula, contenido en módulos encajonados.

FORM: La Forma Libre (asignada, seed d4e5d46c), levantada por jackfield (estado por trazo), six-pack (tendencia junto al valor), pc-98 (rejilla entera), ticket (numeración tabular).

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
