# Plan de trabajo v1.4 — Metas (planificación)

> Módulo nuevo basado en `docs/planes-futuros.md`.
> Cada fase termina en un **hito revisable** (test o checklist manual) para
> avanzar y validar de a una fase a la vez.
> Documento hermano: [`plan-de-trabajo-v1.3-deudas.md`](./plan-de-trabajo-v1.3-deudas.md).

**Stack:** el mismo de v1 (Laravel 13 · Inertia 3 · Vue 3 + TypeScript ·
Tailwind · shadcn-vue). Sin dependencias nuevas.

---

## 0. Dudas aclaradas (de `planes-futuros.md`)

### 0.1 ¿Cómo se relaciona una meta con los movimientos?

Respuesta corta: **los aportes a una meta NO deben ser movimientos**. Se modelan
en una tabla propia (`goal_contributions`) que referencia directamente a la meta
(`goal_id`), y el progreso es la suma de esos aportes. El formulario de aporte
obliga a elegir la meta, así que la referencia al "ítem en particular" queda
garantizada por diseño, no por convención.

Descarté las alternativas con evidencia del código actual:

- **Agregar `goal_id` a `movements`** (estilo `recurring_id`): obliga a decidir
  un signo para el movimiento (ver 0.2), y cualquier edición/eliminación del
  movimiento distorsiona el progreso de la meta. Si algún día se quiere
  *trazabilidad* con un movimiento real, se puede agregar
  `movement_id nullable` a `goal_contributions` — pero es un adorno, no la fuente
  de verdad.
- **Solo categoría "Meta" en movimientos**: la categoría agrupa, pero no
  identifica la meta; con dos metas activas no sabes a cuál fue cada aporte.

### 0.2 ¿El dinero apartado a una meta es ingreso o gasto?

**Ni ingreso ni gasto: es "apartado" (earmark).** Un movimiento con signo dice
que el dinero *entró* o *salió* de tus cuentas; apartar no mueve dinero — sigue
en BCP1 u otra cuenta, solo cambia su etiqueta ("está reservado").

Evidencia de por qué forzar un signo rompe el sistema actual
(`DashboardController@index`):

- Si lo registras como **ingreso (`amount > 0`)**: infla el card "Ingresos del
  mes" aunque no entró dinero nuevo.
- Si lo registras como **gasto (`amount < 0`)**: infla "Gastos del mes",
  distorsiona los presupuestos por categoría (kind `expense` con límite) y baja
  el **saldo real**, rompiendo la conciliación con la suma de cuentas
  (`$difference = totalAccounts - realBalance`).

Por eso el modelo correcto es:

1. **Fuente de verdad:** `goal_contributions` (fecha, monto positivo, nota).
2. **Métrica derivada anti-sobregiro:**
   `Disponible real = realBalance − Σ apartado en metas activas`.
   Ese número se muestra en el dashboard y en la página de metas, y es lo que
   resuelve el miedo de "tocar el dinero ahorrado": ves cuánto queda realmente
   libre sin falsear ningún balance.
3. **Gasto final:** cuando compras lo de la meta, es un gasto normal en la
   categoría que corresponda. La meta se cierra; no hay doble conteo.

---

## 1. Objetivo

Que el usuario pueda:

1. Crear una meta con nombre y monto total (fecha objetivo opcional).
2. Registrar aportes (fecha, monto, nota) contra una meta específica.
3. Ver las metas activas en cards con barra de progreso; historial de aportes
   desplegable dentro del card (sin página individual).
4. Ver "Disponible real = saldo real − apartado en metas activas" en el
   dashboard, junto al card resumen de metas.

---

## 2. Alcance

**Dentro de v1.4:**

- Tablas `goals` y `goal_contributions`.
- CRUD de metas + aportes; cierre/reapertura automática por progreso.
- Página índice con cards, progreso, días restantes (si hay fecha objetivo),
  e historial de aportes desplegable en el card.
- Métrica "Disponible real" y card resumen en dashboard.
- Tests Pest por fase.

**Fuera de v1.4 (diferido):**

- Retiros parciales de una meta (aportes negativos).
- Vínculo `movement_id` en aportes (trazabilidad con el libro mayor).
- Proyección "¿llego a la fecha?" con ritmo de ahorro sugerido.
- Sub-metas o metas compartidas entre usuarios.

---

## 3. Decisiones de arquitectura

1. **Aportes ≠ movimientos** (ver §0): no tocan `movements` ni los balances.
   La relación meta↔ítem es la FK `goal_id` en `goal_contributions`.
2. **Cierre automático:** al guardar/borrar un aporte se recalcula el progreso;
   si `Σ ≥ target` → `completed_at = now()`; si baja del objetivo →
   `completed_at = null` (se reabre). Encapsulado en el modelo `Goal` (servicio
   ligero u observer) con tests.
3. **Progreso con una query:** `withSum('contributions', 'amount')` en el
   índice; sin N+1.
4. **Disponible real derivado:** `realBalance − Σ contributions de metas
   activas (completed_at null)`. No se guarda; se calcula.
5. **Sugerencia de diseño (duda del documento original):** en lugar de página
   individual, el card despliega el historial de aportes (accordion), porque la
   data por meta es chica (aportes + progreso). Si más adelante una meta crece,
   se promueve a página propia sin romper el modelo.

---

## 4. Modelo de datos

```php
// migration: create_goals_table
Schema::create('goals', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->decimal('target_amount', 12, 2);
    $table->date('target_date')->nullable();
    $table->string('color')->nullable();
    $table->integer('sort_order')->default(0);
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
});

// migration: create_goal_contributions_table
Schema::create('goal_contributions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
    $table->date('date');
    $table->decimal('amount', 12, 2); // siempre positiva (validar en Request)
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->index(['goal_id', 'date']);
});
```

Relaciones: `User → goals`, `Goal → contributions`. Accessors en `Goal`:
`progress = Σ contributions.amount`, `percent = min(100, progress/target)`,
`isComplete = completed_at !== null`.

---

## 5. Fases

### Fase V1.4-1 — Modelo de datos: metas y aportes

- `php artisan make:model Goal --migration --factory --no-interaction`
- `php artisan make:model GoalContribution --migration --factory --no-interaction`
- Migraciones de §4 + relaciones + accessors + lógica de cierre/reapertura.
- Factories: `GoalFactory`, `GoalContributionFactory` (aporte positivo).
- Tests de modelo: cierre/reapertura automática, accessors de progreso.

**Hito de revisión:** tests verdes + `php artisan migrate:fresh --seed` OK.

### Fase V1.4-2 — CRUD backend de metas y aportes

- `GoalController` resource + `GoalContributionController` (`store`, `destroy`).
- Rutas `metas.*` con convención + `metas.reorder`; aportes anidados en la meta.
- Form Requests: `target_amount > 0`; aporte `amount > 0`, `date` válida;
  aporte en meta completada → permitido y reabre (limpia `completed_at`).
- Índice con `withSum('contributions', 'amount')`.
- Tests feature: validaciones 422, cierre/reapertura vía endpoints, orden de
  suma del progreso.

**Hito de revisión:** feature tests verdes; ciclo completo crear meta →
aportar → completar → reabrir, verificable en tinker.

### Fase V1.4-3 — Página Metas (índice con cards + aportes)

- `resources/js/pages/Metas/Index.vue`:
  - Cards: nombre, barra de progreso con %, monto acumulado/total, días
    restantes si hay fecha objetivo, badge "Completada".
  - Modal crear/editar: solo nombre + monto (fecha opcional).
  - Modal de aporte: monto, fecha (hoy por defecto), nota opcional.
  - Historial de aportes desplegable dentro del card (accordion) — la
    "sugerencia" del documento original en lugar de página individual.
- Entrada en el sidebar/navegación (`metas`).
- Wayfinder: usar imports desde `@/actions/.../GoalController` y `@/routes/metas`
  (corre `npm run dev` para regenerar tipos).

**Hito de revisión:** recorrido visual — crear meta, aportar, ver barra moverse
y completarse; borrar un aporte y ver la meta reabrirse.

### Fase V1.4-4 — "Disponible real" y cards en dashboard + pulido

- `DashboardController`: `availableReal = realBalance − Σ aportes activos`;
  card "Apartado en metas" + valor "Disponible real"; card resumen de metas
  (top N activas con progreso).
- Si v1.3 ya está aplicada (ver
  [`plan-de-trabajo-v1.3-deudas.md`](./plan-de-trabajo-v1.3-deudas.md)), estas
  tarjetas conviven con el card de deudas sin conflicto.
- Actualizar `DashboardTest` con metas activas/completadas.
- `vendor/bin/pint --dirty --format agent` y suite completa
  `php artisan test --compact`.

**Hito de revisión:** con datos reales, `Disponible real = Saldo real −
Apartado` cuadra a mano; suite completa verde.

---

## 6. Orden recomendado y dependencias

`V1.4-1 → V1.4-2 → V1.4-3 → V1.4-4`. Independiente de v1.3 Deudas
([`plan-de-trabajo-v1.3-deudas.md`](./plan-de-trabajo-v1.3-deudas.md)) salvo por
compartir el dashboard en la Fase V1.4-4.

---

## 7. Riesgos y mitigaciones

| Riesgo | Mitigación |
| --- | --- |
| "Apartado" alto hace ilusorio el saldo libre | El card muestra ambos números juntos (saldo real **y** disponible), nunca uno solo. |
| Meta completada "olvida" el apartado | Al completarse, sus aportes dejan de restar el disponible — correcto: ya es dinero para gastar. |
| Aportes editables destruyen progreso histórico | Historial en card + notas; borrar exige confirmación. |

---

## 8. Estimación orientativa

| Fases | Estimación |
| --- | --- |
| 4 fases (V1.4-1 a V1.4-4) | ~1.5–2 sesiones de trabajo |

## 9. Próximos pasos inmediatos

1. Empezar **Fase V1.4-1** (modelo de metas) y revisar su hito.
2. Nota: las tarjetas de dashboard conviven con las de v1.3 Deudas si esa
   versión está aplicada.
