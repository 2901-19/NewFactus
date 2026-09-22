# Guia de Impresora Termica — Xprinter XP-E200M / E260M / E300M

Paso a paso para configurar la impresora termica de recibos por conexion **USB directa** con el sistema FACTUS.

## Impresora compatible

| Modelo | Interfaz | Velocidad | Ancho papel |
|--------|----------|-----------|-------------|
| XP-E200M | USB (algunas variantes: USB+Serial) | 200 mm/s | 80 mm |
| XP-E260M | USB + Serial + LAN | 260 mm/s | 80 mm |
| XP-E300M | USB + Serial + LAN | 300 mm/s | 80 mm (switchable a 58 mm) |

- Emulacion: **ESC/POS** (mismo protocolo que usa el sistema)
- Cortador automatico: si (parcial)
- Soporte de codigos de barras y QR: si

## Requisitos

- Windows 10/11 en la PC caja (servidor local)
- PHP 8.2+ corriendo con `php artisan serve`
- La impresora conectada por **USB** al mismo equipo donde corre PHP

---

## Paso 1 — Instalar el driver de Windows

1. Conectar la impresora por USB y encenderla.
2. Descargar el driver oficial desde:
   [https://es.xprintertech.com/xp-e200m-xp-e300m](https://es.xprintertech.com/xp-e200m-xp-e300m)
   Seccion **"Descargar"** → **"Conductores"** → **"Controlador de impresora de recibos para Windows"**.
3. Ejecutar el instalador como **Administrador** (clic derecho → Ejecutar como administrador).
4. Seguir el wizard hasta que detecte la USB y finalice.
5. Verificar que aparezca en `Configuracion → Bluetooth y dispositivos → Impresoras y escaneres`.

---

## Paso 2 — Probar el driver en Windows (sin tocar PHP)

Esto confirma que el driver quedo bien antes de integrar con el sistema.

1. En `Impresoras y escaneres`, clic en la Xprinter.
2. Boton **"Imprimir pagina de prueba"** (mas abajo en la pagina).
3. Debe imprimir un ticket con texto de prueba.
4. Si imprime → driver funciona, pasar al paso 3.
5. Si no imprime → revisar cable USB, rollo de papel (lado termico contra el cabezal) o reinstalar driver antes de seguir.

---

## Paso 3 — Compartir la impresora en Windows

PHP necesita ver la impresora como recurso compartido local para enviar comandos ESC/POS.

1. En `Impresoras y escaneres`, clic en la Xprinter → **"Propiedades de la impresora"**.
2. Pestana **"Compartir"**.
3. Marcar **"Compartir esta impresora"**.
4. Asignar un nombre corto **sin espacios ni caracteres raros**. Ejemplo: `XP-E300M`.
5. Anotar el nombre exacto que se escribio (se usara en el paso 5).

> **Importante:** el nombre debe ser identico al que se escribe aqui. PHP lo buscare exactamente asi.

---

## Paso 4 — Permisos del recurso compartido

Si PHP no puede acceder a la impresora, es un problema de permisos.

1. Volver a **"Propiedades de la impresora"** de la Xprinter.
2. Pestana **"Seguridad"**.
3. Verificar que el grupo `Everyone` tenga permiso **"Imprimir"**.
4. Si no aparece `Everyone`, clic **"Agregar"** → escribir `Everyone` → "Comprobar nombres" → Aceptar → marcar **"Imprimir"**.
5. Aceptar.

---

## Paso 5 — Configurar el sistema FACTUS

1. Abrir el navegador en `http://localhost:8000` (o el puerto de `php artisan serve`).
2. Iniciar sesion como **administrador**.
3. En el sidebar, ir a **Herramientas** → **Configuracion Impresora**.
4. En el formulario:
   - **Tipo de Conexion:** `Windows (USB/COM)`
   - **Nombre de la Impresora:** el nombre del paso 3 (ej: `XP-E300M`)
5. Clic **"Guardar"**.
6. Debe aparecer el mensaje verde "Configuracion guardada correctamente".

---

## Paso 6 — Probar impresion desde el sistema

1. En la misma pantalla de Configuracion Impresora, tarjeta **"Prueba de Impresion"**.
2. Clic **"Imprimir Prueba"**.
3. Debe imprimir:
   ```
                   NEW FACTUS
               Esperanza Veliz
                 123456789012345678901234567890123456789012345678
            Acentos: á é í ó ú ñ Ñ ¿ ¡ ü Á
                  PREC U   PREC T
          1234,56    10,50    21,00
               Impresion exitosa!
   ```
   La regla `1..48` (y `9..1` invertida) sirve para calibrar: el ticket usa 48 celdas de ancho (papel 80 mm, fuente normal). Cada numeral debe alinear con su columna: si se corre o se corta el borde, verificar el driver y el ancho del rollo. La linea de acentos confirma la que transliteracion queda bien centrada.
4. Si imprime → listo.
5. Si no imprime → ver seccion Solucion de problemas.

---

## Paso 7 — Probar con una factura real

1. Sidebar → **POS** (Punto de Venta).
2. Seleccionar productos, definir cantidades.
3. Agregar un cliente o elegir uno existente.
4. Pulsa **"Cobrar / Facturar"**.
5. La factura se **imprime automaticamente** al guardarse si el interruptor "Imprimir ticket automaticamente al facturar" esta activo (Herramientas → Configuracion Impresora, activo por defecto). Si la impresora falla, la venta **no se pierde**: se guarda igual y el POS muestra un aviso rojo ambar.
6. En la factura registrada, el boton **"Imprimir Ticket"** permite reimprimir en cualquier momento.
7. El recibo imprime: nombre del negocio, correlativo, fecha, cajero, productos (4 columnas justificadas: descripcion, cant, precio USD, precio Bs), totales en Bs/USD, leyenda de credito si aplica, y corte parcial al final. Todos los montos van con formato español (`1.234,56`).

---

## Solucion de problemas

| Sintoma | Causa probable | Solucion |
|---------|----------------|----------|
| Error "No se pudo conectar" al imprimir | Nombre compartido mal escrito | Verificar el nombre en PowerShell (`Get-Printer`) y copiarlo exacto |
| Imprime letra ilegible / basura | Driver generico equivocado | Reinstalar driver oficial del modelo exacto |
| Imprime en blanco | Cable USB flojo o papel al revés | Reasentar cable, voltear el rollo (cara termica contra el cabezal) |
| Imprime muy lento / letra por letra | Driver en modo spool | Propiedades de impresora → Avanzado → desmarcar "Spool" / elegir "Imprimir directamente" |
| Test dice "Exito" pero factura no imprime | Se imprime sobre la seleccion actual | Imprimir despues de guardar la factura (paso 7) |
| "Acceso denegado" en el log | Falta permiso de impresion | Repetir paso 4 |
| Sale un cuadrado negro despues del texto | Falta papel o rollo al revés | Colocar nuevo rollo, verificar orientacion |
| El cortador no corta | Modelo sin cortador o deshabilitado | Verificar hardware; activar en utilidad de Xprinter |

---

## Verificacion desde PowerShell

Para confirmar que Windows ve la impresora:

```powershell
Get-Printer
```

Debe aparecer la Xprinter en la lista. Tomar nota del nombre original.

Para verificar el recurso compartido:

```powershell
Get-Printer -Name "XP-E300M" -ErrorAction SilentlyContinue
```

Si aparece → todo esta bien configurado en Windows.

---

## Notas tecnicas

- El sistema usa la libreria `mike42/escpos-php` v5.0 con emulacion ESC/POS nativa.
- La conexion por USB funciona a traves del driver de Windows compartido (`WindowsPrintConnector`).
- La impresora debe estar en el **mismo equipo** donde corre PHP (`php artisan serve`).
- Si en el futuro se necesita impresion remota por red, se puede usar la opcion `Red (TCP/IP)` del formulario configurando IP + puerto 9100.
- **Diseno del ticket**: 48 celdas de ancho (80 mm). Las columnas de productos son dinamicas por fila: `Descripcion = 48 - Cant - PrecioUSD - PrecioBS` (minimo 12 celdas); el nombre se envuelve por palabras y los precios quedan alineados a la derecha en todo momento. Los totales se imprimen a tamano doble (24 celdas) en una sola linea, con fallback a `(1,2)` si el monto es muy largo.
- **Codificacion**: todo texto del usuario (nombres de producto, cliente, negocio) pasa por `Str::ascii()` antes de imprimirse: 1 caracter = 1 celda, sin desbordes ni saltos de posicion. Lo que se ve con caracteres "raros" es la transliteracion (ej: `ñ` → `n`, `á` → `a`), necesaria para que las columnas cuadren.
- Los montos del ticket se imprimen con formato español (`1.234,56`).
- Impresion automatica: la imprime `FacturaController::store()` despues de confirmar la factura en la BD (`imprimir_al_facturar` en `Configuracion`, toggle en Herramientas → Configuracion Impresora). Si no hay impresora configurada o falla, responde con `impreso=false` y el POS muestra un aviso sin afectar la venta.
- Etiqueta de precio: sin nombre del negocio; producto en negrita tamano 2x centrado (max 24 celdas por linea, se envuelve) y el precio `Bs X` en tamano adaptativo (`4x4` si ≤ 12 caracteres, luego `3x3`, `2x2`, `1x1`), siempre centrado y completo.
