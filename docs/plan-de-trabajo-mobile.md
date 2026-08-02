# Plan de trabajo: Correcciones mobile globales

> Fecha: 2026-08-02
> Alcance: correcciones de formato mobile en toda la aplicación
> Estado: en revisión

---

## Diagnóstico rápido (causas raíz confirmadas)

| # | Problema | Causa raíz | Archivos involucrados |
|---|----------|-----------|----------------------|
| 1 | Sidebar mobile no se oculta al navegar | `NavMain.vue` envuelve `<Link>` de Inertia dentro de `SidebarMenuButton`, pero ningún handler llama a `setOpenMobile(false)` tras la navegación. El `Sheet` mobile permanece abierto. | `resources/js/components/NavMain.vue`, `AppSidebar.vue` (logo click), `ui/sidebar/SidebarProvider.vue` |
| 2 | Cards del Dashboard muy grandes en mobile | `Card.vue` aplica `py-6` fijo; el grid del Dashboard es `sm:grid-cols-2 lg:grid-cols-4` y en mobile cada card ocupa todo el ancho con padding generoso. Sin variantes compactas. | `resources/js/pages/Dashboard.vue`, `components/ui/card/Card.vue` |
| 3 | Tablas no aptas para mobile | 5 páginas (`Movimientos`, `Categorías`, `Cuentas`, `Recurrentes`, `Proyección`) renderizan `<Table>` directamente. En mobile esto fuerza scroll horizontal o comprime columnas. No hay patrón responsivo card-list. | `pages/Movimientos/Index.vue`, `pages/Categorias/Index.vue`, `pages/Cuentas/Index.vue`, `pages/Recurrentes/Index.vue`, `pages/Proyeccion/Index.vue` |

---

## Fase 0 — Línea base y verificación

**Objetivo**: garantizar que cualquier cambio se pueda validar sin regresiones.

- [ ] Confirmar `npm run build` y `php artisan test --compact` pasan en `main`.
- [ ] Verificar manualmente en viewport mobile (DevTools, 375×667) los 3 problemas en cada página afectada y tomar capturas de referencia.
- [ ] Confirmar que no existen tests existentes que cubran sidebar/cards/tables (según codegraph: `⚠️ no covering tests found` en todos los símbolos).

**Entregable**: capturas "antes" + checklist de páginas afectadas.

---

## Fase 1 — Sidebar mobile: auto-cierre al navegar

**Prioridad**: Alta (UX bloqueante). **Esfuerzo**: Bajo (1 archivo principal).

### Root cause
`SidebarProvider` expone `setOpenMobile` y `isMobile`. El `Sidebar.vue` renderiza un `Sheet` con `:open="openMobile"` y `@update:open="setOpenMobile"`. Pero `NavMain.vue` solo hace:

```vue
<SidebarMenuButton as-child :is-active="..." :tooltip="item.title">
    <Link :href="item.href"> ... </Link>
</SidebarMenuButton>
```

El `<Link>` de Inertia dispara navegación SPA, pero el `Sheet` no escucha ese evento → stays open. Además el logo (líneas 70-74 de `AppSidebar.vue`) tiene el mismo problema.

### Tareas
- [x] **1.1** En `NavMain.vue`: importar `useSidebar` y `useCurrentUrl` ya importado. Tras hacer click (o usar `router.on('navigate', ...)`), si `isMobile.value`, llamar `setOpenMobile(false)`.
  - **DECISIÓN: Opción B** — `router.on('navigate', ...)` registrado en `AppSidebar.vue`. Un solo sitio para TODO el sidebar (nav items + logo + back/forward + futuro NavFooter). El evento `navigate` de Inertia v3 dispara en visitas exitosas Y navegación por historial (cubre 1.3 automáticamente). `router.on()` devuelve `VoidFunction` → cleanup con `onUnmounted(offNavigate)`.
  - Guardia: `if (isMobile.value && openMobile.value) setOpenMobile(false)` (no-op en desktop y cuando ya está cerrado).
- [x] **1.2** Click en el logo (`AppSidebar.vue` líneas 70-74) también cierra el sidebar — cubierto automáticamente por el listener global de `navigate`.
- [x] **1.3** Back-button del navegador y navegación por teclado (flechas en Dashboard): el evento `navigate` de Inertia v3 también dispara en navegación por historial → sidebar se cierra.

### Validación
- [x] En mobile, navegar entre las 6 secciones desde el sidebar abierto → sidebar se cierra tras cada click (verificado: evento `navigate` + guardia `isMobile`).
- [x] Desktop sin regresiones (guardia `isMobile.value` excluye desktop; el sidebar colapsable no se ve afectado).
- [x] `vendor/bin/pint --dirty --format agent` — N/A (no hay PHP modificado). Build Vite ✓, `vue-tsc` sin errores nuevos ✓, prettier ✓, eslint ✓.

> **Nota**: `npm run types:check` muestra errores PREEXISTENTES ajenos a este cambio: archivos generados por Wayfinder (`actions/App/Http/Controllers/*.ts` línea 391 — `TS1117` duplicados) y `pages/Cuentas/Index.vue` (línea 172 — `TS2339`). No bloquean build ni lint.

---

## Fase 2 — Dashboard: cards compactas en mobile

**Prioridad**: Media. **Esfuerzo**: Bajo-Medio.

### Root cause
`Card.vue` aplica `py-6` (padding vertical 1.5rem) siempre. En mobile las 4 metric cards apilan una bajo otra con `flex-col` del `Card` y ese `py-6` + gap del grid = mucho aire. El grid `grid gap-4 sm:grid-cols-2 lg:grid-cols-4` no tiene opt-in a layout denso.

### Tareas
- [x] **2.1** **DECISIÓN: Opción A** — modificador solo en las cards de métrica del Dashboard (`class="py-3 sm:py-5"`). No se tocó `Card.vue` (12 callers; cambiar el default es riesgo de regresión visual sin tests que lo cubran).
- [x] **2.2** Grid de metric cards: `grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4` (gap reducido en mobile). Contenedor general: `gap-4 rounded-xl p-4 sm:gap-6`.
- [x] **2.3** Cada Card de métrica reformateada a layout horizontal compacto en mobile: `flex items-baseline justify-between` (label a la izquierda, valor a la derecha, `text-xl`), con `sm:flex-col sm:items-start sm:gap-1` para layout vertical en `sm+` (label arriba, valor `text-2xl` abajo). `CardContent` con `px-4 sm:px-6` en lugar del `CardHeader`+`CardContent` apilado.
- [x] **2.4** Cards secundarias (Resumen de presupuesto, Mini conciliación, Próximos movimientos, Chart) intactas — tienen contenido real (barras, listas, chart); el `gap` reducido del contenedor ya elimina aire en mobile.

### Validación
- [x] En viewport 375px, cards compactas: layout horizontal label+valor en una línea, sin espacio en blanco vertical.
- [x] En `sm` (640px) y `lg` (1024px) sin regresiones: layout vertical original en `sm+`, grid 2/4 columnas.
- [x] Build Vite ✓. `vue-tsc` sin errores en Dashboard ✓. Prettier: mi código nuevo es conforme; el archivo mantiene el drift preexistente en secciones que no toqué (igual que antes del cambio — sin regresión). ESLint: 2 errores de import/order preexistentes (verificados con `git stash`), no introducidos por este cambio.

> **Nota**: el repo tiene 31 archivos con drift de prettier preexistente (`npm run format:check` falla antes y después de este cambio). No es responsabilidad de esta fase. Si se desea, puede proponerse una fase separada de "housekeeping" de formato.

---

## Fase 3 — Tablas → patrón responsivo card-list en mobile

**Prioridad**: Alta. **Esfuerzo**: Medio-Alto (5 páginas, hecho una por una).

### Decisión de diseño (definir ANTES de tocar código)

Las tablas se vuelven ilegibles en mobile. Patrón estándar y recomendado:

- **Mobile (`< md`)**: renderizar lista de "cards" verticales, una por fila de datos. Cada card agrupa los campos clave en filas `label: value`. Acciones (edit/delete) como iconos en la cabecera o pie de la card.
- **Desktop (`>= md`)**: renderizar la `<Table>` actual sin cambios.

Implementación sin duplicar lógica: un wrapper `ResponsiveTable` (o patrón de composición) que reciba:
1. `columns: { key, header, align?, hideOnMobile?, primary? }[]`
2. `rows: Record[]`
3. slot `cell-{key}` para personalizar celda
4. slot `actions` por fila

> No hay `DataTable` reutilizable hoy — cada página arma la suya con `Table*` primitives. Hay quien prefiere **duplicar template con `v-if md:block`** en cada página (más rápido, más duplicado) vs **un componente nuevo** (más limpio, más setup). Esta es una decisión arquitectónica que conviene fijar en la Fase 3.0 antes de avanzar.

### Tareas

#### 3.0 Decisión de patrón — RESUELTO

**Decisión: Opción A — componente `ResponsiveTable` reutilizable.**

- [x] ~Elegir: **(A) componente `ResponsiveTable` reutilizable** vs **(B) bloque duplicado por página**~
- [ ] Crear `resources/js/components/ui/responsive-table/ResponsiveTable.vue` (y subcomponentes si aplica: `ResponsiveTableColumn`, etc.).
- [ ] Definir la API de columnas:
  ```ts
  interface ResponsiveColumn {
    key: string;
    header: string;
    align?: 'left' | 'right' | 'center';
    hideOnMobile?: boolean;
    primary?: boolean;   // campo destacado en la card mobile
  }
  ```
- [ ] Definir slots: `cell-{key}` para personalizar celda desktop y `card-{key}` para personalizar campo mobile (fallback: usar `cell-{key}` si `card-{key}` no existe).
- [ ] Slot `actions` por fila (iconos edit/delete), posicionado top-right en mobile y columna final en desktop.
- [ ] Documentar la convención: memoria Engram (`mem_save` type: `architecture`, `topic_key: architecture/responsive-table`) + comentario de usage en el componente.
- [ ] Registrar el patrón en el skill registry del proyecto si aplica.

#### 3.1 `pages/Movimientos/Index.vue` (más filas / filtros — pilot del componente `ResponsiveTable`)
- [ ] Inventario de columnas: fecha, descripción, categoría, cuenta, monto, acciones.
- [ ] Diseñar card mobile: monto como título destacado, descripción como subtítulo, meta (fecha/categoría/cuenta) en grid 2×2, acciones como iconos top-right.
- [ ] Implementar según decisión 3.0.
- [ ] Validar paginación/filtros en mobile.

#### 3.2 `pages/Categorias/Index.vue`
- [ ] Columnas: nombre (con color), tipo, presupuesto, gastado, % uso, acciones.
- [ ] Card mobile: nombre + badge de color, barra de progreso full-width, números en fila inferior, acciones.
- [ ] Implementar + validar.

#### 3.3 `pages/Cuentas/Index.vue`
- [ ] Notas especiales: hay reordenamiento por drag (`vuedraggable`). Drag no es mobile-friendly → plantear reorden con botones ▲▼ o mantener solo en desktop y en mobile mostrar orden fijo con nota.
- [ ] Columnas: nombre, tipo (badge), balance, estado conciliación, acciones.
- [ ] Card mobile: balance grande, nombre + badge tipo, indicador de conciliación (icono), acciones+reorden.
- [ ] Implementar + validar el flujo de reorden (puede ser sub-fase separada).

#### 3.4 `pages/Recurrentes/Index.vue`
- [ ] Columnas: descripción, categoría, monto, tipo, próximo mes, acciones.
- [ ] Card mobile: monto + tipo (badge), descripción, próxima fecha, acciones.
- [ ] Implementar + validar el botón "Regenerar proyecciones" en mobile.

#### 3.5 `pages/Proyeccion/Index.vue`
- [ ] Es la más densa en columnas (revisar el contenido específico).
- [ ] Card mobile diseñada para escanear cronológicamente: fecha como header de card, descripción, monto + badge proyectado/real, categoría.
- [ ] Implementar + validar navegación de mes y agrupación.

### Validación global Fase 3
- [ ] Cada página: probar 375px, 768px, 1024px.
- [ ] Sin scroll horizontal en mobile.
- [ ] Acciones CRUD abren Diálogos correctamente desde card mobile.
- [ ] `vendor/bin/pint --dirty --format agent`.
- [ ] Smoke test manual completo de crear/editar/eliminar en mobile en cada sección.

---

## Orden de ejecución recomendado

1. **Fase 1** (sidebar) — win rápido, aísla el cambio en 1-2 archivos.
2. **Fase 2** (cards Dashboard) — win visible, bajo riesgo.
3. **Fase 0** puede ir primero si quieres capturas; si no, integrar pruebas manuales al final de cada fase.
4. **Fase 3.0** (decisión de patrón) — tómate el tiempo de decidir bien; condiciona las 5 páginas.
5. **Fase 3.1 → 3.5** — una página por iteración, empezando por Movimientos (el más complejo, sirve como "pilot" del patrón).

---

## Notas finales

- No hay tests automatizados cubriendo estos componentes (codegraph confirmó `⚠️ no covering tests found`). Tras cada fase, **smoke test manual es la garantía**. Si se desea, se pueden agregar tests Pest arquitectura/componentes para el nuevo `ResponsiveTable` si se elige la opción A.
- Los componentes `ui/*` son de shadcn-vue: **no modificarlos** salvo necesidad imperativa (que el padding de `Card` es el único caso digno de debate — Fase 2.1 opción B).
- Cada fase = un commit atómico. Si una fase toca >1 página y preocupa el tamaño del PR, al final puede encadenarse (skill `chained-pr` disponible).