---
name: Factus
description: Planilla mercantil POS - tinta, hairline, modulos encajonados, numeracion tabular. Sin gradientes, sin glass, sin glow.
colors:
  papel: "#f3f2ed"
  hoja: "#ffffff"
  tinta: "#20242b"
  tinta-2: "#4d545c"
  tinta-3: "#5f666e"
  agua: "#d9dce1"
  agua-2: "#c3c8d0"
  agua-3: "#adb4be"
  acero: "#eceef1"
  acero-2: "#f2f3f5"
  carbono: "#2a4782"
  carbono-osc: "#1e3463"
  carbono-suave: "#7488b4"
  carbono-pasto: "#e5eaf4"
  carbono-barra: "#8fa8d6"
  sello-verde: "#246b42"
  sello-verde-pasto: "#e2efe7"
  sello-rojo: "#a9241c"
  sello-rojo-pasto: "#f6e5e3"
  sello-ambar: "#8a5a00"
  sello-ambar-pasto: "#f3ecdc"
  sello-azul: "#20537e"
  sello-azul-pasto: "#e3ecf3"
typography:
  ui:
    fontFamily: "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif"
  mono:
    fontFamily: "ui-monospace, 'Cascadia Mono', 'Cascadia Code', Consolas, 'Courier New', monospace"
  heading:
    fontFamily: "var(--font-ui)"
    fontSize: "1.35rem"
    fontWeight: 700
    letterSpacing: "-0.01em"
    lineHeight: 1.25
  subtitle:
    fontFamily: "var(--font-mono)"
    fontSize: "0.76rem"
    fontWeight: 400
    letterSpacing: "0.06em"
    textTransform: "uppercase"
  caption:
    fontFamily: "var(--font-mono)"
    fontSize: "0.7rem"
    fontWeight: 400
    letterSpacing: "0.12em"
    textTransform: "uppercase"
  metric-value:
    fontSize: "1.9rem"
    fontWeight: 700
    lineHeight: 1
    letterSpacing: "-0.02em"
  table-header:
    fontFamily: "var(--font-mono)"
    fontSize: "0.76rem"
    fontWeight: 700
    letterSpacing: "0.05em"
    textTransform: "uppercase"
  table-body:
    fontSize: "0.87rem"
    lineHeight: 1.4
  label:
    fontFamily: "var(--font-ui)"
    fontSize: "0.79rem"
    fontWeight: 600
    letterSpacing: "0.02em"
  input:
    fontSize: "0.9rem"
    lineHeight: 1.5
  button:
    fontWeight: 600
    letterSpacing: "0.01em"
  badge:
    fontSize: "0.74em"
    fontWeight: 600
    letterSpacing: "0.03em"
  recibo-brand:
    fontFamily: "var(--font-mono)"
    fontSize: "0.9rem"
    fontWeight: 700
    letterSpacing: "0.22em"
    textTransform: "uppercase"
  recibo-total-label:
    fontFamily: "var(--font-mono)"
    fontSize: "0.74rem"
    fontWeight: 700
    letterSpacing: "0.1em"
    textTransform: "uppercase"
  recibo-total-value:
    fontSize: "1.05rem"
    fontWeight: 700
  pos-total:
    fontFamily: "var(--font-mono)"
    fontWeight: 700
    letterSpacing: "0.01em"
  ui-icon:
    fontSize: "1rem"
  login-brand:
    fontFamily: "var(--font-mono)"
    fontSize: "1.25rem"
    fontWeight: 700
    letterSpacing: "0.22em"
    textTransform: "uppercase"
  login-input:
    fontSize: "0.95rem"
    lineHeight: 1.5
  meta:
    fontSize: "0.83rem"
    lineHeight: 1.4
  micro-label:
    fontFamily: "var(--font-mono)"
    fontSize: "0.66rem"
    letterSpacing: "0.06em"
  nano-label:
    fontFamily: "var(--font-mono)"
    fontSize: "0.64rem"
    letterSpacing: "0.1em"
rounded:
  hoja: "4px"
spacing:
  sidebar-width: "248px"
  page-gutter: "1.5rem"
  card-padding: "0.9rem 0.9rem"
  module-gap: "0.55rem"
  badge-padding: "0.33em 0.6em"
components:
  button-primary:
    backgroundColor: "{colors.carbono}"
    textColor: "#ffffff"
    rounded: "{rounded.hoja}"
    padding: "0.5rem 1rem"
  button-primary-hover:
    backgroundColor: "{colors.carbono-osc}"
    textColor: "#ffffff"
    rounded: "{rounded.hoja}"
  button-secondary:
    backgroundColor: "transparent"
    textColor: "{colors.tinta-2}"
    rounded: "{rounded.hoja}"
    padding: "0.5rem 1rem"
  button-secondary-hover:
    backgroundColor: "{colors.acero}"
    textColor: "{colors.tinta}"
    rounded: "{rounded.hoja}"
  badge-success:
    backgroundColor: "{colors.sello-verde-pasto}"
    textColor: "{colors.sello-verde}"
    rounded: "{rounded.hoja}"
  badge-danger:
    backgroundColor: "{colors.sello-rojo-pasto}"
    textColor: "{colors.sello-rojo}"
    rounded: "{rounded.hoja}"
  badge-warning:
    backgroundColor: "{colors.sello-ambar-pasto}"
    textColor: "{colors.sello-ambar}"
    rounded: "{rounded.hoja}"
  badge-info:
    backgroundColor: "{colors.sello-azul-pasto}"
    textColor: "{colors.sello-azul}"
    rounded: "{rounded.hoja}"
  input:
    backgroundColor: "{colors.hoja}"
    textColor: "{colors.tinta}"
    rounded: "{rounded.hoja}"
    padding: "0.5rem 0.75rem"
  module-card:
    backgroundColor: "{colors.hoja}"
    textColor: "{colors.tinta}"
    rounded: "{rounded.hoja}"
    padding: "0.9rem 0.9rem"
  table-header:
    backgroundColor: "{colors.tinta}"
    textColor: "#eef0f3"
    rounded: "{rounded.hoja}"
  recibo-totals-row:
    backgroundColor: "transparent"
    textColor: "{colors.tinta}"
    rounded: "0"
    padding: "0.22rem 0"
---

# Design System: Factus - La Forma Libre

## Overview

**Creative North Star: "The Invoice Ledger"**

Factus looks like a single, never-ending planilla mercantil - a commercial invoice sheet that has been torn into modules. Every screen is a section of that same document: the paper is always white (Papel Algodon), the ink is always slate (Tinta Pizarra), and the only accent is the single Azul Carbon that would appear on a carbon-copy form. There are no gradient fills, no glassmorphism, no drop shadows at rest, no rounded corners wider than a thumb's breadth. The visual language is a direct rejection of SaaS defaults - no soft-shadow cards, no pastel gradient headers, no frosted navbars. Every pixel is drawn in ink on paper.

The system runs on-premises in Venezuelan bodegas without internet: cashiers at the counter need to process sales fast, in dual currency, with receipts that print on thermal paper. The typeface choice - system fonts only, zero web fonts - is a constraint born from offline deployment, but it becomes the aesthetic: the mechanical precision of ui-monospace for labels and tabular data, and the clean neutrality of system-ui for body text. Numbers always use tabular-nums lining figures so columns of currency align without fuss.

Every module on screen is a hairline-bordered box that could be a section of a physical invoice form. Module headers are stamped in uppercase monospaced caps (the "placard" voice). Status badges carry both an icon and a colored stroke - never color alone. Buttons are flat ink stamps with no elevation at rest. The result is a POS that feels like operating a well-organized ledger book, not a modern web application.

**Key Characteristics:**

- Papel (cotton-white) ground, Tinta (slate) text, Azul Carbon single accent
- Hairline borders everywhere; no shadows at rest
- 4px radius maximum on all corners
- Monospaced uppercase placard labels for module headers, captions, and date stamps
- Tabular-nums lining figures on every numeric value
- Status badges: icon + stroke, never bare color
- System fonts only (system-ui + ui-monospace); zero web font requests
- Flat, sealed buttons with ink-on-paper weight

## Colors

The palette is built from ink on paper: a warm off-white ground, near-black slate text, and one carbon-blue accent. State seals use four hues (green, red, amber, blue) each paired with a pale pastel wash. There is no secondary or tertiary accent - Azul Carbon alone carries the system's identity.

### Primary

- **Azul Carbon** (#2a4782): The sole accent. Used on primary buttons, focus rings, sidebar active state, link color, checkbox fill, and the caret. It is the color of a carbon-copy stamp on a form.
- **Azul Carbon Deep** (#1e3463): Hover and active state for the primary accent. A half-step darker, never a different hue.
- **Azul Carbon Soft** (#7488b4): Disabled state for primary buttons. The accent at reduced intensity, signaling non-interactivity.
- **Azul Carbon Wash** (#e5eaf4): The palest tint of the accent. Used for table row hover backgrounds - a hint of blue ink bleeding through the paper.

### Neutral

- **Papel Algodon** (#f3f2ed): The page ground - a warm off-white that reads as unbleached paper, not clinical white. Body background.
- **Hoja Blanca** (#ffffff): The module surface - pure white sheets sitting on the paper ground. Card backgrounds, modal panels, input fields, receipt surface.
- **Tinta Pizarra** (#20242b): Primary text - near-black slate ink. Headings, body copy, table cell text.
- **Tinta Segunda** (#4d545c): Secondary text and interactive label color. Subheadings, form labels, secondary buttons.
- **Tinta Tercera** (#5f666e): Muted text - captions, hints, placeholders, disabled text. The palest ink.
- **Hilo de Agua** (#d9dce1): The primary hairline border. Used on module borders, table dividers, input borders, receipt separators.
- **Hilo de Agua 2** (#c3c8d0): Stronger hairline - card header bottom borders, secondary button borders, table header rules.
- **Hilo de Agua 3** (#adb4be): Active hairline - scrollbar thumb, dashed separators, disabled elements.
- **Acero Claro** (#eceef1): Module header backgrounds, input group appendages, card header fill, table stripe alternate.
- **Acero Fantasma** (#f2f3f5): Table striped alternating rows, placeholder backgrounds. The lightest structural tint.

### State Seals (Sello)

Each state seal is a dark hue for icon/badge stroke and a pale pastel wash for alert and background tinting.

- **Sello Verde** (#246b42) / **Sello Verde Pasto** (#e2efe7): Success. Active status, completed credits, in-stock indicators.
- **Sello Rojo** (#a9241c) / **Sello Rojo Pasto** (#f6e5e3): Danger. Anulled invoices, validation errors, low-stock warnings.
- **Sello Ambar** (#8a5a00) / **Sello Ambar Pasto** (#f3ecdc): Warning. Pending credits, cautionary states.
- **Sello Azul** (#20537e) / **Sello Azul Pasto** (#e3ecf3): Info. Neutral informational badges, dashboard product counts.

### Named Rules

**The Pamper-Only-One-Accent Rule.** Azul Carbon is the only chromatic accent in the system. No other hue is used for interactive elements, links, or emphasis at full saturation. State seals are semantic signals, not competing accents.

**The Border-Tells-The-Story Rule.** Depth in this system is communicated entirely through hairline border weight and tonal background shifts (Acero Claro headers, Acero Fantasma stripes), never through drop shadows. Cards have `box-shadow: none !important`.

**The State-Seal-Pair Rule.** Every state color is always deployed as a pair: the dark hue for text/icon stroke plus the pale pastel for background wash. Never use the dark hue as a background fill at full opacity on large areas.

## Typography

**Display Font:** system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif (with system fallbacks)
**Label/Mono Font:** ui-monospace, 'Cascadia Mono', 'Cascadia Code', Consolas, 'Courier New', monospace

**Character:** The pairing is mechanical and functional: a neutral sans-serif for reading and a monospaced face that evokes typewriters and invoice line items. Both are native OS fonts requiring zero network requests - essential for offline deployment. The mono font is the voice of the system: it speaks in uppercase placard labels, tabular figures, and date stamps.

### Hierarchy

- **Heading** (700, 1.35rem, 1.25 line-height): Page titles in ui. Bold, tight-tracked (-0.01em), used once per page as the "invoice header."
- **Subtitle** (400, 0.76rem, uppercase, 0.06em tracking): Page subtitles in mono. The business name and date beneath headings.
- **Caption** (400, 0.7rem, uppercase, 0.12em tracking): Module captions in mono. The tightest, most spaced-out tier - used on metric labels, rate strip labels, and receipt field names.
- **Metric Value** (700, 1.9rem, 1 line-height, -0.02em): Dashboard KPI numbers. The largest type in the system, always in UI font with tabular figures.
- **Table Header** (600, 0.7rem, uppercase, 0.06em tracking): Column headers in mono. Stamped on the Acero Claro background.
- **Table Body** (400, 0.87rem): Data rows. Standard weight, UI font, tabular-nums active globally on html.
- **Label** (600, 0.79rem, 0.02em tracking): Form labels in UI. Semi-bold, slightly smaller than body.
- **Input** (400, 0.9rem): Form field text. Slightly smaller than body for density.
- **Button** (600, 0.01em tracking): All buttons. Semi-bold, near-zero tracking.
- **Badge** (600, 0.74em, 0.03em tracking): Status chips. The smallest tier, uppercase implied by context.
- **Recibo Brand** (700, 0.9rem, 0.22em tracking, uppercase): Receipt brand name in mono. Maximum letter-spacing in the system.
- **Recibo Total Label** (700, 0.74rem, 0.1em tracking, uppercase): Receipt "TOTAL" stamp in mono.
- **Recibo Total Value** (700, 1.05rem): The final number on the receipt. Bold, UI font.
- **POS Total** (700, mono): The running total in the point-of-sale cart.

### Named Rules

**The Monospaced-Placard Rule.** All structural labels - module headers, table column names, metric captions, receipt field names, date stamps, and the rate strip - are set in uppercase monospaced type with generous letter-spacing (0.06em-0.22em). This is the "printed form" voice. Body text and interactive elements use UI font; only structural annotations use mono.

**The Tabular-Figures-Everywhere Rule.** The html element sets `font-variant-numeric: tabular-nums lining-nums` globally. Every number in the system - prices, quantities, rates, dates, KPIs - aligns in columns without explicit width hacks. This is non-negotiable for a dual-currency POS.

## Layout

The layout is a fixed sidebar plus fluid content area, never a centered container. The sidebar is 248px wide (`--sidebar-width`), dark ink (#1d232c), and fixed to the viewport. On screens narrower than 992px it slides off-screen and an overlay appears; a toggle button restores it. The main content area takes the remaining width with a consistent 1.5rem gutter (`main.main-contenido { padding: 1.5rem 1.5rem 2.5rem }`).

Page content uses Bootstrap's 12-column grid (`.row.g-3`) with 1rem gutters. Dashboard modules, report sections, and POS panels all follow the same grid rhythm. Cards fill their grid cell (`.h-100`) and stack vertically on mobile.

The masthead (navbar) sits above the content area at 56px minimum height, white background, bordered bottom. It carries the business name in uppercase mono and the date in smaller mono - functioning as the "invoice header" that stays visible while scrolling.

## Elevation & Depth

This system is flat. `box-shadow: none !important` is the explicit rule on `.card`. Depth is conveyed exclusively through:

1. **Tonal layering**: Papel Algodon (page) > Hoja Blanca (module) > Acero Claro (header). Three tiers of background lightness create the visual hierarchy that shadows would provide in other systems.
2. **Hairline borders**: Every module has a 1px solid border in Hilo de Agua. Headers and footers use the stronger Hilo de Agua 2.
3. **The sidebar**: the only dark surface in the entire system. Its ink-dark background (#1d232c) creates the deepest level of the z-axis.

The two exceptions are intentional and limited:
- **Modals**: `box-shadow: 0 10px 40px rgba(20, 24, 31, 0.22)` - the only surface that lifts above the paper plane.
- **Dropdown menus**: `box-shadow: 0 8px 28px rgba(20, 24, 31, 0.18)` - floating elements need to detach from the page.

### Named Rules

**The Flat-By-Default Rule.** All module surfaces are flat at rest. Shadows appear only on floating overlays (modals, dropdowns) that must visually detach from the paper plane. Dashboard cards, table containers, and form panels never cast shadows.

## Shapes

The form language is paper-cut geometry: every surface has the same 4px radius (`--radio-hoja: 4px`), which is the size of a stamp's corner. This single value is applied uniformly to cards, inputs, buttons, badges, scrollbars (except thumb which is sharp), sidebar links, modals, alerts, and the receipt container. There are no pill shapes, no circular elements, no varied radius tokens.

Borders are always 1px solid in the Hilo de Agua family. The system defines two border tokens: `--borde-hoja: 1px solid var(--agua)` (standard) and `--borde-fuerte: 1px solid var(--agua-2)` (strong). There are no 2px or 3px borders except the receipt's final-total rule (2px solid Tinta) and the login sheet header (2px solid Tinta) - both are "ink stamps" on the invoice form.

Badges are rectangular: the seal's pale pastel wash for the background, the seal's dark ink for text and 1px stroke. Icons within badges are 16-20px Bootstrap Icons inline. The receipt uses dashed separators (`1px dashed var(--agua-3)`) to distinguish from the solid hairlines of the module system.

## Components

### Buttons

- **Shape:** 4px radius, 1px border, semi-bold (600) text
- **Primary:** Azul Carbon background (#2a4782), white text, bordered. Hover deepens to Azul Carbon Deep (#1e3463). Focus: 2px white ring + 2px Azul Carbon ring. Disabled: Azul Carbon Soft (#7488b4).
- **Success / Danger / Warning / Info:** Each uses its Sello hue as fill with white text. Hover darkens by one step. Focus ring matches button color.
- **Secondary / Ghost:** Transparent background, Tinta Segunda (#4d545c) text, Hilo de Agua 2 border. Hover fills Acero Claro. The workhorse for non-primary actions (Ver todas, View icons).
- **All buttons:** Font weight 600, 0.01em tracking, Bootstrap icon alignment via `.bi` vertical-align fix. Small variant (`.btn-sm`) uses reduced padding for table row actions.

### Badge (Status Chip)

- **Style:** Pastel wash background, 1px solid currentColor border, 600 weight, 0.74em font size. Inline-flex with gap for icon + text.
- **States:** `bg-success` = Sello Verde stroke + icon. `bg-danger` = Sello Rojo + icon. `bg-warning` = Sello Ambar + icon. `bg-info` = Sello Azul + icon. `bg-secondary` = Tinta Segunda stroke with Hilo de Agua 2 border (neutral).
- **Usage:** Invoice status (Contado/Anulada/Credito pendiente/Credito cancelado), item count badges, stock warnings. Always carries an icon (bi-check-circle, bi-hourglass-split, bi-exclamation-triangle).

### Module Card (Metric)

- **Shape:** 4px radius, 1px Hilo de Agua border, Hoja Blanca background, zero shadow.
- **Header:** Acero Claro (#eceef1) background, 1px Hilo de Agua 2 bottom border. Mono uppercase 0.7rem text in Tinta Segunda. May contain an action button (secondary style, reset to UI font).
- **Body:** Flex column with 0.55rem gap. Houses metrica-caption (mono uppercase 0.7rem Tinta Tercera), metrica-valor (1.9rem bold Tinta Pizarra), metrica-secundaria (mono 0.8rem Tinta Segunda), and modulo-icono (42px bordered square with status-colored icon).
- **Footer:** Hoja Blanca background, Hilo de Agua top border. Used for summary lines.

### Input Field

- **Style:** Hoja Blanca background, Hilo de Agua 2 border (`--borde-fuerte`), 4px radius, 0.9rem text in Tinta Pizarra. Placeholder in Tinta Tercera at 70% opacity.
- **Focus:** Border shifts to Azul Carbon, 3px ring in Azul Carbon at 18% opacity. Caret color is Azul Carbon.
- **Error:** Border shifts to Sello Rojo, 3px ring in Sello Rojo at 16% opacity.
- **Disabled:** Inherits Bootstrap disabled state (Acero Claro background).
- **Input Group Appendage:** Acero Claro background, matching border, Tinta Segunda text (e.g. "Bs" prefix in POS).

### Sidebar Navigation

- **Shape:** 4px radius on each link, transparent background at rest.
- **Default:** Tinta de la Planilla text at 66% opacity, icon at 40% opacity, 0.88rem size, 1px transparent border.
- **Hover:** White text, 6% white background fill, icon at 85% opacity. 0.12s ease transition.
- **Active:** Full Hoja Blanca background with Tinta Pizarra text, bold weight (600), 3px Azul Carbon left-edge indicator (via ::before pseudo-element), icon in Azul Carbon. Inverted from the dark sidebar - the active item looks like a white invoice tab.
- **Grouping:** Thin horizontal rules (rgba white 12%) separate permission groups.

### Table

- **Style:** Tinta Pizarra text, Hilo de Agua borders, 0.87rem font. Striped rows alternate between Hoja Blanca and Acero Fantasma. Hover fills Azul Carbon Wash.
- **Header:** Acero Claro background, mono uppercase 0.7rem in Tinta Segunda, Hilo de Agua 2 bottom border. Fixed, non-scrolling.
- **Numeric cells:** Right-aligned with `font-variant-numeric: tabular-nums`. CSS class `.num` enforces right alignment.
- **Dark variant:** Tinta Pizarra background, white text, #3a4250 borders. Used in specialized contexts.

### Recibo (Receipt)

- **Shape:** Max 460px, centered. Hoja Blanca background, 1px Hilo de Agua border, 4px radius.
- **Brand header:** Centered, mono 0.9rem 700 weight, 0.22em tracking, uppercase. Business name plus sub-line in Tinta Tercera.
- **Separators:** Dashed 1px Hilo de Agua 3 lines between sections (not solid hairlines).
- **Meta rows:** Flex pairs with mono uppercase 0.68rem label (Tinta Tercera) and bold value (Tinta Pizarra, right-aligned).
- **Line items table:** 0.8rem, mono uppercase 0.64rem headers on Acero, dashed hairlines between items.
- **Totals section:** Monospaced "TOTAL" label (0.74rem, bold, uppercase, 0.1em tracking) paired with bold 1.05rem value. Final total separated by 2px solid Tinta Pizarra (the thickest ink line in the system).
- **Footer:** Centered muted text in Tinta Tercera.

### Tira de Tasas (Rate Strip)

- **Shape:** Hoja Blanca container, 1px Hilo de Agua border, 4px radius, 0.55rem 0.9rem padding.
- **Caption:** Mono uppercase 0.7rem in Tinta Tercera with exchange icon.
- **Chips:** Inline-flex items with 1px Hilo de Agua border, Acero Fantasma background, 0.8rem text. The numeric value is bold with tabular-nums. Each chip represents one rate type (BCV, USDT, Promedio).

## Do's and Don'ts

### Do:

- **Do** use hairline borders and tonal background shifts to create depth - never drop shadows on module surfaces.
- **Do** set all structural labels (module headers, table columns, captions, receipt fields) in uppercase monospaced with generous letter-spacing.
- **Do** pair every state seal color with its pastel wash: dark hue for text/icon, pale hue for background tinting.
- **Do** use tabular-nums on every numeric value (globally set on html, but verify in custom contexts).
- **Do** keep Azul Carbon as the sole interactive accent. Focus rings, links, active states, and primary buttons use this color and only this color.
- **Do** use 4px radius uniformly on all surfaces - cards, inputs, buttons, badges, modals, receipts.
- **Do** include an icon alongside every status badge text. Color alone is not sufficient.
- **Do** use Bootstrap's system of utility classes (`btn-*`, `text-*`, `bg-*`, `border-*`) since the CSS custom property bridge maps them to the palette automatically.

### Don't:

- **Don't** add box-shadow to cards, tables, or module containers. The only exceptions are modals and dropdown menus.
- **Don't** use gradients, glassmorphism, backdrop-filter, or any translucent overlay on module surfaces.
- **Don't** introduce a second accent hue for interactive elements. State seals are semantic signals, not clickable accents.
- **Don't** use pill-shaped buttons, circular avatars, or varied border-radius values. 4px is the universal radius.
- **Don't** load web fonts (Google Fonts, Adobe Fonts, or self-hostedwoff files). The type system is built entirely from OS-native fonts for offline reliability.
- **Don't** use color alone to convey status. Every badge and state indicator must carry an icon.
- **Don't** make module headers any background other than Acero Claro (#eceef1). The header is the "formstamp" of the invoice.
- **Don't** use the receipt's 2px total separator anywhere except the receipt total and login header. That weight is reserved for "ink stamp" moments.