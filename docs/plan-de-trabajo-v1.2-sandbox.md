# Plan de trabajo v1.2 — Caja Diaria (Simulación de escenarios con sandbox)

> Extensión **posterior al MVP** (fases 0–9 de v1) y **independiente de v1.1**.
> Permite probar escenarios financieros hipotéticos — aceptar un préstamo y
> proyectar las cuotas, recibir un pago grande y planificar compras, etc. — sin
> afectar el balance real. El usuario "entra en modo simulación", arma el
> escenario, lo observa sobre el dashboard/proyecciones, y luego lo **revierte**
> (descarta todo) o lo **guarda** (lo convierte en estado real).

**Modelo:** sandbox **único**, de a un escenario a la vez. No se modelan
escenarios paralelos con nombre ni comparación lado-a-lado. Se reutiliza el flag
`is_projected` existente y el `ProjectionService`; se añade un flag
`is_sandbox` ortogonal para aislar filas hipotéticas del estado real.

**Stack:** el mismo de v1 (Laravel 13 · Inertia 3 · Vue 3 + TypeScript ·
Tailwind · shadcn-vue). Sin dependencias nuevas.

**Prerequisito:** v1 completada (fases 0–9, especialmente Fase 6 de recurrentes
y proyección).

**Stack de referencia:** ver `plan-de-trabajo.md` para `is_projected`,
`ProjectionService`, `RecurringTransaction`, y toda la maquinaria que este plan
reutiliza.

---

## 1. Objetivo

Que el usuario pueda:

1. **Entrar en modo simulación** desde cualquier vista, con un banner claro de
   "Estás en modo Simulación — los cambios no afectan tu balance real".
2. **Agregar movimientos simulados** (ingresos o egresos hipotéticos, p.ej.
   "Recibí un préstamo de 5000 hoy" o "Compra grande de 1500 en 2 semanas").
3. **Agregar pagos recurrentes simulados** (p.ej. "Cuotas de 500 por 12 meses")
   que generen proyecciones sandbox — para ver cómo quedó el flujo de caja
   futuro bajo ese escenario.
4. **Ver el efecto** sobre dashboard, movimientos y proyección, distinguiendo
   visualmente lo simulado de lo real.
5. **Revertir** el escenario (borrar todas las filas sandbox y volver al
   estado real intacto) o **Guardar como real** (convertir el escenario en
   estado real).

**No** es objetivo de v1.2: múltiples escenarios paralelos con nombre,
comparación lado-a-lado, archivar escenarios como "planes" nombrados para volver
a ellos sin comprometer el estado, ni simular sobre escenarios ya guardados.

---

## 2. Alcance

### Dentro del alcance (v1.2)

- **Columna `is_sandbox` booleana** (default `false`) en `movements` y
  `recurring_transactions`.
- **Scope global `LiveScope`** en `Movement` y `RecurringTransaction` que
  excluye `is_sandbox=true` por defecto, desactivable cuando se quiere ver el
  sandbox.
- **Modo simulación** (frontend toggle en el header) con banner visualmente
  distintivo. El modo se deriva de la **existencia de filas sandbox** para ese
  usuario — sin columna `sandbox_active` en `users`, sin tabla de sesión.
- **Movimientos simulados CRUD**: alta/edición/borrado de filas con
  `is_sandbox=true`, mismas validaciones que un movimiento real.
- **Recurrentes simulados CRUD**: plantillas con `is_sandbox=true` que generan
  proyecciones también sandbox (movimientos `is_sandbox=true` e
  `is_projected=true` con `recurring_id` apuntando a la template).
- **`ProjectionService` por scope**: able to regenerate projections scoped by
  `is_sandbox` true/false. La regeneración real no toca filas sandbox y
  viceversa.
- **Movimientos timeline** muestra filas sandbox en una sección/estilo
  diferenciado (borde discontínuo, fondo muted) cuando existe sandbox activo.
- **Dashboard "con simulación" toggle**: alternar entre vista "real" (default)
  y vista "real + simulación". Cards de balance, ingresos, gastos, proyección
  y presupuesto recalculados incluyendo sandbox cuando el toggle está activo.
- **Proyeccion timeline** muestra grupos separados para proyecciones reales y
  simuladas.
- **Revertir**: acción que borra TODAS las filas `is_sandbox=true` del usuario
  (movimientos manuales + templates + proyecciones auto-generadas) en una sola
  transacción, luego sale del modo simulación.
- **Guardar como real**: acción que convierte el sandbox en estado real:
  1. Flip `is_sandbox=false` en movimientos manuales sandbox (`recurring_id`
     null — p.ej. el ingreso "préstamo recibido" o un movimiento puntuall).
  2. Flip `is_sandbox=false` en templates recurrentes sandbox.
  3. Borrar proyecciones auto-generadas sandbox (movimientos `is_sandbox=true`
     `recurring_id NOT NULL` — las cuotas generadas).
  4. Correr `ProjectionService::regenerateForUser(sandbox: false)` para
     regenerar las cuotas reales desde las templates recién-promovidas.
  Transaccional; en una sola operación.
- **Tests Pest** por fase con fábricas que soporten estado sandbox.

### Fuera del alcance (deferido a v3 o posterior)

- **Escenarios paralelos con nombre** (entidad `Scenario` con `scenario_id`
  en filas, comparación lado-a-lado) → v3. v1.2 = sandbox único reciclando
  `is_sandbox=true` como "el escenario activo".
- **Guardar como "plan" archivado** (que el escenario quede guardado pero NO
  promueva a estado real, re-abrible después) → v3. v1.2: "guardar" SIEMPRE
  promueve a real. Si quieres conservar el escenario sin comprometerlo, no
  guardes — entra en modo simulación y déjalo activo hasta revertirlo o
  aceptarlo.
- **Comparar escenarios lado a lado** → v3.
- **Simular cambios a un movimiento existente** (p.ej. "qué tal si el próximo
  pago de Falabella fuera el doble") sin crear una fila nueva → fuera de v1.2.
  En v1.2 se simula agregando filas sandbox, no mutando filas existentes.
- **Sandbox sobre recurrentes ya existentes**: no se puede "simular que
  Falabella sube de 200 a 250" modificando la template real — se debe crear
  una template sandbox nueva con el importe modificado (la real sigue activa
  en el estado base). v1.2 lo deja explícito. v3 podría modelar "override"
  puntual.
- **Auditoría de quién probó qué** (log de escenarios reverted/guardados con
  histórico) → fuera.

---

## 3. Decisiones de arquitectura

### 3.1 `is_sandbox` booleano ortogonal a `is_projected`

`movements` y `recurring_transactions` ganan `is_sandbox` bool default `false`.
`is_sandbox` y `is_projected` son **ortogonales**: una fila puede ser real
(`is_sandbox=false`) proyectada o no; o sandbox (`is_sandbox=true`) proyectada
(las cuotas generadas de una template sandbox) o no (un movimiento puntual
sandbox). Las cuatro combinaciones son válidas. Los queries existentes que
filtran por `is_projected` siguen funcionando sin cambios.

### 3.2 Scope global `LiveScope` en los dos modelos

Para **proteger** todos los queries existentes (`Movement::where user_id`...,
`RecurringTransaction::...`) sin cazar uno por uno, se añade un global scope
`LiveScope` que en cada query añade automáticamente `where is_sandbox = false`.
Las vistas y servicios sandbox quieren ver filas sandbox se optan explícitamente
con `Movement::withoutSandboxScope()` (o método análogo) usando
`withoutGlobalScope(LiveScope::class)`.

Las proyecciones reales (`ProjectionService::regenerateForUser($sandbox=false)`)
ya no tocan filas sandbox por la combinación de scope+condición explícita. Las
sandbox usan `regenerateForUserSandbox()` (otro método con la lógica espejo
sobre filas sandbox), o un parámetro de scope en el método existente —
preferimos método separado para claridad y no acoplar la lógica real.

### 3.3 El estado del sandbox es la existencia de filas sandbox

No hay tabla `sandbox_sessions` ni columna `sandbox_active` en `users`. La
question "¿hay un sandbox activo para este usuario?" se resuelve con:
`Movement::withoutSandboxScope()->where('user_id', $u)->where('is_sandbox', true)->exists()`.
Rápido con índice compuesto `(user_id, is_sandbox)`. Sin sync entre flag y
datos — no puede haber "sandbox activo sin filas" ni "filas sandbox sin
sandbox activo". Revertir = borrar filas; Guardar = flip filas; ambos dejan el
estado limpio.

### 3.4 Único sandbox, sin nombre ni ID

Todas las filas `is_sandbox=true` del usuario son "el sandbox activo". No hay
entidad `Scenario` ni `scenario_id`. Por diseño, esto impide comparación
lado-a-lado y `Guardar como plan` archivado (ver Fuera del alcance). Ventaja:
no hay columna extra, no hay migración de scenario, el modelo de datos apenas
crece.

### 3.5 Modo Simulación es UI-only

El toggle "Modo Simulación" en el header es solo para mostrar/ocultar el
banner yuxtaponiendo acciones en la UI. Encenderlo no crea estado; apagarlo
no borra filas. Si el usuario apaga el modo sin revertir ni guardar, las filas
siguen en la base, y al volver a prenderlo, el escenario sigue ahí. Solo
**Revertir** y **Guardar como real** mutan datos.

### 3.6 Datos reales pueden cambiar durante el sandbox

El "snapshot" del que habló el usuario al describir la feature es **conceptual**,
no un snapshot físico. El sandbox **se acumula** sobre el estado real; revertir
borra solo filas sandbox y deja el estado real exactamente como estaba al
momento de revertir (no al momento de empezar — el usuario pudo haber agregado
movimientos reales durante el sandbox). v1.2 lo documenta explícitamente: el
sandbox no bloquea cambios al estado real. Es una capa aditiva.

Si el usuario quisiera "congelar" el estado real durante una simulation, v1.2
no lo soporta — tendría que no tocar movimientos reales por su cuenta. v3 podría
introducir un snapshot físico real si la necesidad emerge.

### 3.7 Commit (Guardar) solo promueve, no archiva

"Guardar" en v1.2 significa SIEMPRE promocionar el escenario a estado real.
No es "Guardar como plan para revisarlo después sin comprometerlo". A la fecha
de commit:

1. Movimientos manuales sandbox (`recurring_id null`) → `is_sandbox=false`.
2. Templates recurrentes sandbox → `is_sandbox=false`.
3. Proyecciones auto-generadas sandbox (`is_sandbox=true AND recurring_id not
   null`) → DELETE (las cuotas se regeneran en el paso siguiente como reales).
4. `ProjectionService::regenerateForUser(sandbox: false)` regenera las cuotas
   reales desde las templates recién-promovidas (más las templates reales que
   ya existían).

Todo en una transacción. Resultado: el balance real ahora incluye el préstamo
y las cuotas; la timeline real muestra todo; las próximas regeneraciones ya no
distinguen lo que fue sandbox.

Si el usuario quiere "probar antes de comprometer" — ya lo hizo durante el
sandbox. Si quiere **no** comprometer pero tampoco perder el escenario — v1.2
no lo soporta (derivar a v3). En v1.2 simplemente no presione "Guardar"; siga
en modo simulación.

### 3.8 Regeneración real no choca con sandbox

`ProjectionService::regenerateForUser(sandbox: false)` borra solo proyecciones
`where is_sandbox = false AND recurring_id is not null` y regenera desde
templates reales (`where is_sandbox=false`). El sandbox nunca se tocó. Idem
para `regenerateForSandboxUser()` sobre el scope sandbox. Cada scope se limpia
y rellena solo. La lógica de generación (cantidad de meses, día del mes,
importes) se reutiliza del `ProjectionService` actual.

### 3.9 UI: banner + estilo diferenciado + sección aparte en timeline

Header toggle "Modo Simulación" (icono beaker/flask). Cuando hay filas sandbox
para el usuario, una pastilla visible al lado del toggle indica "1 escenario
activo". Al activar el modo:

- Banner con fondo amber/muted: "Estás en modo Simulación — los cambios no
  afectan tu balance real".
- Acciones nuevas disponibles: "Agregar movimiento simulado", "Agregar
  recurrente simulado", "Revertir escenario", "Guardar como real".
- Movimientos timeline: filas sandbox con fondo distinto (dashed outline) y
  badge "Simulado"; filas reales sin cambios.
- Dashboard un toggle "Incluir simulación" en las cards de balance/ingresos.
- Proyeccion timeline: las cuotas sandbox se muestran en un bloque "Proyección
  simulada" bajo las proyecciones reales.

Si el usuario desactiva el toggle (sin revertir): banner y acciones se ocultan;
filas sandbox siguen existiendo pero no se muestran — para no liar la vista
normal. Al reactivar el toggle, todo vuelve. Solo Revertir/Guardar limpia.

### 3.10 Cuándo permitir entrar en modo simulación

Si ya existe un sandbox (`exists sandbox=true`), el toggle "Entrar en modo
simulación" reanuda el sandbox existente — no lo sobreescribe. El usuario
puede continuar desde donde lo dejó, agregar más movimientos sandbox, o
revertirlo y empezar uno nuevo. Aceptar uno nuevo requiere Revertir primero.

---

## 4. Modelo de datos

### 4.1 Migración

```php
// movements: añadir is_sandbox
Schema::table('movements', function (Blueprint $table) {
    $table->boolean('is_sandbox')->default(false)->after('is_projected');
    $table->index(['user_id', 'is_sandbox']);
});

// recurring_transactions: añadir is_sandbox
Schema::table('recurring_transactions', function (Blueprint $table) {
    $table->boolean('is_sandbox')->default(false)->after(/* última col actual */);
    $table->index(['user_id', 'is_sandbox']);
});
```

### 4.2 Modelos

- `Movement`: añade `is_sandbox` a `$fillable`, `$casts` (`'is_sandbox' =>
  'boolean'`), y registra el global scope `LiveScope`. Métodos:
  `withoutSandboxScope()` (static, devuelve query builder sin el scope) y
  `sandbox()` (scope que explícitamente filtra `is_sandbox=true`).
- `RecurringTransaction`: idem, `LiveScope` + `withoutSandboxScope()` +
  `sandbox()`.

### 4.3 Scope global `LiveScope`

```php
class LiveScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->getTable() . '.is_sandbox', false);
    }
}
```

Helper en el modelo:

```php
public static function withoutSandboxScope(): Builder
{
    return static::query()->withoutGlobalScope(LiveScope::class);
}
```

### 4.4 `ProjectionService` por scope

Dos métodos (preferido sobre un bool):

```php
public function regenerateForUser(User $user): void;        // scope real
public function regenerateForSandboxUser(User $user): void; // scope sandbox
```

Los dos comparten la lógica de generación (fechas, día del mes, cantidad de
meses, importes) vía un método privado `generateProjectionsFor($templates,
$isSandbox)`. La diferencia es el set de templates y filas que borran/regeneran.

---

## 5. Fases del plan

### Fase V1.2-0 — Migración, modelos y scope global

**Objetivo:** añadir `is_sandbox` sin romper nada existente. Todas las vistas y
queries de v1 siguen funcionando idéntico (las filas nuevas defaultean en
`false`, fuera del LiveScope.

**Tareas**

1. Migración: añadir `is_sandbox` (default false) + index `(user_id,
   is_sandbox)` en `movements` y `recurring_transactions`.
2. Modelos `Movement` y `RecurringTransaction`: `$fillable`, `$casts`,
   registrar `LiveScope`.
3. Crear `LiveScope` class en `app/Models/Scopes/`.
4. Helpers `withoutSandboxScope()` y `sandbox()` scope en ambos modelos.
5. Actualizar factories (`MovementFactory`, `RecurringTransactionFactory`) con
   `is_sandbox` state (default false) y estado helper `sandboxed()`.
6. Tests: que un query normal no trae filas sandbox; `withoutSandboxScope()`
   las trae todas; `sandbox()` trae solo sandbox; regenerate real no toca
   sandbox.

**Criterios de aceptación**

- [ ] Migración corre sin perder datos existentes (todos defaultean false).
- [ ] Todas las pruebas existentes de v1 siguen pasando.
- [ ] Nuevo query normal `Movement::where('user_id', $u)->get()` excluye filas
  sandbox sin tocar el código existente.
- [ ] `Movement::withoutSandboxScope()->get()` incluye filas sandbox.

**Commits:** `feat: is_sandbox column on movements and recurring`,
`feat: LiveScope global scope and helpers`,
`test: sandbox scope filtering`.

---

### Fase V1.2-1 — Backend del modo simulación: estado y endpoints base

**Objetivo:** exponer al frontend si existe sandbox activo para el usuario y
preparar validaciones.

**Tareas**

1. Método en `UserController` (o un `SandboxController` resource-ish):
   `status()` → `{ has_sandbox: bool }`. Calculado por query con cache corto
   (recordar por request via memoization en Inertia shared prop es lo más
   simple — enviar `has_sandbox` en cada render Inertia).
2. Middleware o trait que valide: cualquier mutation endpoint que cree
   movimiento o recurring sandbox, rechace si el usuario no aceptó "entrar en
   modo simulación" (defensivo — el frontend ya lo controla, el backend también
   por security). Opcional en v1.2 fase inicial si se opta por validity from UI
   — evaluar.
3. Shared prop Inertia `hasSandbox` calculada en `HandleInertiaRequests`.
4. Test Pest: endpoint `status` retorna `has_sandbox=false` sin filas y
   `true` tras agregar una fila sandbox.

**Criterios de aceptación**

- [ ] Cada página Inertia recibe prop `hasSandbox` booleano.
- [ ] El cálculo usa `withoutSandboxScope()` correctamente (las queries tradicionales no).
- [ ] Tests cubren el happy path.

**Commits:** `feat: has_sandbox Inertia shared prop`,
`test: sandbox status endpoint`.

---

### Fase V1.2-2 — Movimientos simulados CRUD + UI de entrada

**Objetivo:** permitir crear/listar/editar/borrar movimientos simulados desde
una UI visible solo cuando `hasSandbox` o "modo simulación" activos.

**Tareas**

1. Backend: `SandboxMovementController` resource (store/update/destroy) que
   fuerza `is_sandbox=true` en cada insert; valida como `MovementRequest` pero
   requiere `is_sandbox=true` halla añadido explícitamente — o un
   `SandboxMovementRequest` que extiende `MovementRequest` y agrega
   `'is_sandbox' => 'required|true'`. Reutiliza todas las validaciones de
   `MovementRequest` existente (idealmente composición, no herencia).
2. Frontend: `useSandbox` composable que expone `hasSandbox`, `modeActive`,
   `enter()`, `exit()`, `revert()`, `save()`. Internamente llamando a endpoints
   `SandboxMovementController` y `SandboxRecurringController` (estas dos últimas
   se crean en fases posteriores).
3. "Modo Simulación" toggle en el header (visible siempre; al activarse sin
   `hasSandbox` y sin filas sandbox, lo marca como "empezaré un nuevo sandbox")
   — primer click crea el estado "modo on"; el siguiente movimiento simulado
   crea la primera fila y `hasSandbox` pasa a true.
4. Banner "Estás en modo Simulación — los cambios no afectan tu balance real"
   al activar el modo. Color amber/muted; botones "Revertir escenario" y
   "Guardar como real" (estos se conectan funcionalmente en V1.2-6).
5. Form "Agregar movimiento simulado" — recicla `MovementDialog` existente, con
   prop `mode: 'sandbox'` que añade flag visual (pastilla "simulado" en la
   cabecera del diálogo) y fuerza `is_sandbox=true` en submit. Usa endpoint
   sandbox, no `/movements`.
6. Test Pest: crear movimiento sandbox no aparece en lista real, aparece en
   lista sandbox; editar/borrar sandbox movimiento no toca real.

**Criterios de aceptación**

- [ ] Activo "Modo Simulación" y agrego un "préstamo recibido 5000 hoy" como
  movimiento sandbox. Aparece en la lista con badge "Simulado". El balance
  real (lista normal) sigue sin contar los 5000.
- [ ] El dashboard NO muestra el movimiento simulado todavía (esa integración
  va en V1.2-5).
- [ ] Edición/borrado funciona sin afectar filas reales.

**Commits:** `feat: SandboxMovementController`,
`feat: useSandbox composable`,
`feat: simulation mode toggle and banner`,
`feat: sandbox movement dialog`,
`test: sandbox movement CRUD`.

---

### Fase V1.2-3 — Timeline con sección sandbox + vista "con simulación"

**Objetivo:** que el usuario VEA el efecto de los movimientos sandbox en la
timeline y pueda alternar entre "real" y "real + simulación".

**Tareas**

1. `MovementController::index` ya retorna real y projected — ampliar para
   retornar TRES grupos cuando `hasSandbox`: `realMovements`, `projectedMovements`
   (reales proyectadas), y `sandboxMovements` (filas sandbox, sean pasadas o
   futuras). Las sandbox pueden tener `is_projected=true` (cuotas) o false
   (puntual); ordenar todas por `date`.
2. `Movimientos/Index.vue`: tercera sección "Simulación" debajo de las otras,
   solo cuando `sandboxMovements.length > 0`. Filas con fondo dashed/amber y
   badge "Simulado". Mantiene acciones de editar/borrar sandbox (de V1.2-2).
3. Toggle "Incluir simulación" sobre la timeline: si activado, 'mescla'
   sandbox en la lista real (al modo de cómo v1 ya muestra real+projected). Default off.
4. DashboardCard integración inicial: sólo si toggle "Incluir simulación" está
   on, las cards (balance, ingresos, gastos) suman también sandbox. — La
   integración detallada del dashboard va en V1.2-5; aquí primeroachica solo el
   estado de toggle via composable.
5. Tests feature: el controller retorna `sandboxMovements` solo cuando
   `hasSandbox`; las cards calculadas con/sin sandbox según toggle.

**Criterios de aceptación**

- [ ] Tras crear un movimiento sandbox, en la página Movimientos veo una sección
  "Simulación" con la fila y distinct visual.
- [ ] Enciendo "Incluir simulación": la fila aparece integrada con las reales.
- [ ] Apago el toggle: la sección "Simulación" reaparece aparte y las cards
  vuelven a valores reales.

**Commits:** `feat: movements index returns sandbox group`,
`feat: simulation section in Movimientos`,
`feat: include simulation toggle`,
`test: movements index with sandbox`.

---

### Fase V1.2-4 — Recurrentes simulados + ProjectionService por scope

**Objetivo:** simular préstamos/cuotas. Crear templates sandbox que generen
proyecciones sandbox.

**Tareas**

1. `ProjectionService`: extraer la lógica de generación a un método privado
   `generateProjectionsFor(Collection $templates, bool $isSandbox)`. Dividir el
   método `regenerateForUser` existente en `regenerateForUser` (scope real) y
   `regenerateForSandboxUser` (scope sandbox). El real borra `where is_sandbox=false
   AND recurring_id is not null` y regenera desde templates `is_sandbox=false`.
   El sandbox borra `where is_sandbox=true AND recurring_id is not null` y
   regenera desde templates `is_sandbox=true`.
2. `SandboxRecurringController` resource: store/update/destroy que fuerzan
   `is_sandbox=true`. Tras create/update, llamar a
   `regenerateForSandboxUser($user)`. Tras delete, idem.
3. `RecurringDialog` existente con prop `mode: 'sandbox'`: usa endpoint sandbox,
   pastilla "simulado", `is_sandbox=true` forzado.
4. Frontend: `useSandbox().addRecurring()` y `regenerate()` que disparan
   endpoints sandbox.
5. Vista recurrentes (`Recurentes/Index.vue`): sección "Plantillas simuladas"
   abajo, análoga a la sección de movements en V1.2-3.
6. Vista Proyección (`Proyeccion/Index.vue`): bloque "Proyección simulada"
   separado con las cuotas sandbox (las `is_sandbox=true AND is_projected=true`);
   estilo differenciado.
7. Tests: crear template sandbox → genera N movimientos sandbox proyectados; no
   genera filas reales. Editar/borrar template sandbox → regenerate sandbox
   las recibe/regenera. Las proyecciones reales no cambian.

**Criterios de aceptación**

- [ ] Creo "Préstamo x12 cuotas 500" como template sandbox. Aparecen 12 filas
  sandbox proyectadas en Proyección (sección "Proyección simulada"). La
  proyección real sigue sin verse afectada.
- [ ] Borro la template sandbox → las 12 filas se borran automático (por
  regeneration).
- [ ] La timeline real sigue intacta.

**Commits:** `refactor: ProjectionService split by scope`,
`feat: SandboxRecurringController`,
`feat: sandbox recurring dialog`,
`feat: simulation group in Recurring list`,
`feat: simulation projection block`,
`test: sandbox recurring and projection`.

---

### Fase V1.2-5 — Dashboard "con simulación" completo

**Objetivo:** que las tarjetas/mini-reconciliación del dashboard reflejen el
sandbox cuando el usuario lo pida.

**Tareas**

1. `DashboardController`: aceptar query param `include_sandbox=1`. Si activo,
   calcular métricas sumando filas sandbox también (vía
   `Movement::withoutSandboxScope()->where('user_id', $u)->...`).
2. Integra al `Dashboard.vue` existente: el toggle "Incluir simulación" activa
   el query param; al cambiarlo, refetch Inertia visita `/dashboard?include_sandbox=1`.
3. Cards que cambian: balance real, ingresos del mes, gastos del mes,
   proyección de fin de mes, presupuesto usado. Cada card muestra tachado del
   valor real y nuevo valor con+sandbox (o un pariente pequeño) — diseño pequeño.
4. Mini-reconciliación: el balance "esperado según cuentas" no cambia (es
   estática manual), pero el "balance real" calculado puede incrementarse al
   incluir sandbox — podrías crear un pequeño descuento de reconciliación
   "esperado vs simulado". Mantener display simple: solo_marcar_difference como
   info, sin tocar la reconciliacion existente.
5. Balance line chart del dashboard: línea adicional "con simulación" sobre la
   real si `include_sandbox`. Mantiene la real como baseline.
6. Proyección al fin de mes: si `include_sandbox`, calcula también la
   proyección con intereses/cuotas simuladas.
7. Tests: dashboard calcula correctamente con/filas sandbox según toggle.

**Criterios de aceptación**

- [ ] Con un préstamo sandbox + 12 cuotas y `include_sandbox` activo: la
  card de balance inicial sube por el préstamo; la card de proyección de fin
  de mes muestra el flujo impactado por las cuotas.
- [ ] Toggle off → todo vuelve a valores reales.

**Commits:** `feat: dashboard include_sandbox param`,
`feat: dashboard include simulation toggle`,
`feat: balance line chart simulation overlay`,
`test: dashboard with sandbox`.

---

### Fase V1.2-6 — Revertir y Guardar como real

**Objetivo:** las dos acciones terminales del sandbox.

**Tareas**

1. `SandboxController::revert()`: en una transacción:
   - DELETE `movements WHERE user_id=u AND is_sandbox=true`.
   - DELETE `recurring_transactions WHERE user_id=u AND is_sandbox=true`.
   (Generadas en cascada si hay FK — las projections autogeneradas son filas
   movements con `recurring_id`, quedan en el DELETE de movimientos.)
2. `SandboxController::commit()` (Guardar como real): en una transacción:
   - UPDATE `movements SET is_sandbox=false WHERE user_id=u AND is_sandbox=true
     AND recurring_id IS NULL` (movimientos manuales → reales).
   - UPDATE `recurring_transactions SET is_sandbox=false WHERE user_id=u AND
     is_sandbox=true` (templates → reales).
   - DELETE `movements WHERE user_id=u AND is_sandbox=true AND recurring_id IS
     NOT NULL` (cuotas sandbox autogeneradas — serán regeneradas en el
     siguiente paso como reales).
   - `$projectionService->regenerateForUser($user)` (regenera cuotas reales).
3. Frontend: botones "Revertir" (rojo/destructivo con confirm) y "Guardar como
   real" (verde/primary con confirm explicando que el escenario se vuelve
   permanente). Tras cualquiera, refetch de las páginas abiertas para ver el
   resultado.
4. Tras commit: `hasSandbox` vuelve a false automáticamente (no hay filas
   sandbox); toggle "Modo Simulación" apagado automáticamente.
5. Tests: revert borra todo y deja estado real intacto; commit promueve
   manual + templates, regenera cuotas reales; estado sandbox limpia en ambos
   casos.

**Criterios de aceptación**

- [ ] Tras configurar préstamo sandbox + 12 cuotas, "Revertir" deja el sistema
  exactamente como antes (sin las 12 filas, sin la template, sin el ingreso).
- [ ] "Guardar como real": el ingreso "préstamo recibido" aparece como real
  en timeline; las 12 cuotas aparecen como proyección real; `hasSandbox=false`.
- [ ] Ambas acciones son transaccionales (fallar a mitad no deja basura —
  simular un throw mock y revert).

**Commits:** `feat: sandbox revert`,
`feat: sandbox commit to real`,
`test: sandbox revert and commit transactions`.

---

### Fase V1.2-7 — Pulido, edge cases y tests finales

**Objetivo:** cerrar v1.2 robusto.

**Tareas**

1. Edge case: ¿qué pasa si un usuario tiene `is_sandbox=true` rows pero el
   `ProjectionService` real es corrido por un commando CLI (sin interacción)?
   Verificar que `regenerateForUser` no toca sandbox — explícito test.
2. Edge case: "Modificar recurrente real existente" mientras existe sandbox
   activo. La template real no fue tocada por nada sandbox; regenerate real
   borra proyecciones reales y recrea. El sandbox permanece intacto. Test.
3. Edge case: borrar un movimiento sandbox que es un `recurring_id` parent
   (no debería existir — las templates son `recurring_transactions`, no
   movements). Aclarar y proteger si fuese posible borrar el recurrent que
   originó cuotas sandbox — cascade delete o bloquear con mensaje claro.
4. UX: si el usuario intenta "Guardar como real" sin filas sandbox (no debería
   darse porque el botón solo aparece con `hasSandbox`), responder 422 con
   mensaje claro.
5. UX copy y confirmación: redactar el copy de los botones y los
   confirm-dialogs (español neutro) — "Revertir descartará TODO el escenario
   simulado. ¿Confirmar?", "Guardar convertirá el escenario en permanente. No
   se puede deshacer. ¿Confirmar?".
6. Documentación: `docs/plan-de-trabajo-v1.2-sandbox.md` marcado completo; breve
   nota en `README` o `docs/README` de que la feature existe.
7. Lint/format con Pint; build de producción sin warnings de Vue.

**Criterios de aceptación**

- [ ] Edge cases cubiertos con tests.
- [ ] Copy final de botones y confirmes en español neutro.
- [ ] Pint pasa; `npm run build` sin warnings.
- [ ] Plan marcado como completado.

**Commits:** `test: sandbox edge cases`,
`docs: v1.2 simulation feature complete`.

---

## 6. Orden recomendado y dependencias

```
V1.2-0 (migración + scope)
   ↓
V1.2-1 (estado + shared prop)
   ↓
V1.2-2 (movimientos sandbox CRUD + UI)
   ↓
V1.2-3 (timeline sandbox view)   ←── ver el efecto parcial
   ↓
V1.2-4 (recurrentes sandbox + proyección)
   ↓
V1.2-5 (dashboard con simulación)
   ↓
V1.2-6 (revertir / guardar)
   ↓
V1.2-7 (pulido y tests)
```

V1.2-3 debería entregarse antes que V1.2-4 porque видеть el efecto de un
movimiento puntual sandbox es una validación rápida del modelo antes de meter
el motor de proyección. V1.2-4 es la fase más densa; conviene tener feedback
visual de V1.2-3 antes.

V1.2-5 requiere V1.2-4 para que el dashboard pueda reflejar cuotas. V1.2-6
cierra y debe ir después de V1.2-5 para que el usuario pueda "ver antes de
decidir".

---

## 7. Estimación orientativa

| Fase     | Esfuerzo | Comentario                                                           |
| -------- | -------- | -------------------------------------------------------------------- |
| V1.2-0   | M        | Migración + global scope — tocar todas las queries indirectamente.  |
| V1.2-1   | S        | Shared prop + 1 endpoint status.                                     |
| V1.2-2   | M        | Controller nuevo + composable + dialog + UI toggle.                 |
| V1.2-3   | M        | Controller index split + sección nueva en timeline.                 |
| V1.2-4   | L        | Refactor ProjectionService por scope + controller + vistas.         |
| V1.2-5   | M        | Integración dashboard + chart overlay.                              |
| V1.2-6   | M        | Revertir y commit transaccional — cuidado.                          |
| V1.2-7   | S–M      | Edge cases y pulido.                                                 |

**Total:** ~4–5 días de trabajo concentrado para un feature usable de principio
a fin. V1.2-4 es probablemente el día más caro (refactor + tests).

---

## 8. Riesgos y mitigaciones

| Riesgo                                                              | Mitigación                                                                                          |
| ------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| Global scope olvidado produce fuga de filas sandbox en vistas real  | Composable helper + test arquitectura (`arch()`) que exija `Movement` use `LiveScope`.              |
| `regenerateForUser` real borra filas sandbox por error              | Condição explícita `where is_sandbox=false` en el delete; tests cubren.                            |
| `Guardar` deja filas huérfanas si `regenerate` falla a mitad        | Todo en `DB::transaction`; rollback automático al throw.                                           |
| Usuario confunde "Modo Simulación" toggle con "Revertir"          | Copy claro; banner visible siempre; toggle apagado no borra nada (solo Revertir/Guardar mutan).     |
| Snapshot físico esperado por usuario (freezing real)                | Documentar en UI: "los cambios reales que hagas durante la simulación se quedan". v3 si surge.     |
| Tickets que dependen de relaciones Movement↔Recurring             | Las relaciones ya existen por `recurring_id` —sandbox hereda; borrar template dispara `regenerate`. |
| Performance con muchos escenarios grandes (100+ cuotas)            | Single sandbox + transacción corta + index `(user_id, is_sandbox)`. No se espera escala.           |

---

## 9. Próximos pasos inmediatos

1. Validar que `is_sandbox` no colisiona con nombres/columnas existentes
   (revisar migración actual de `movements`).
2. Confirmar que `ProjectionService` actual puede refactorizarse a
   `generateProjectionsFor(templates, isSandbox)` sin romper tests de v1 — leerlo
   antes de V1.2-0 si hace falta.
3. Empezar por Fase V1.2-0.

---

## 10. Decisiones confirmadas

- **Sandbox único**, de a un escenario a la vez. No hay entidad `Scenario` ni
  `scenario_id`. Todas las filas `is_sandbox=true` del usuario = el sandbox
  activo.
- **`is_sandbox` ortogonal a `is_projected`**. Las cuatro combinaciones son
  válidas; ambos flags conviven sin contrato.
- **`LiveScope` global** sobre `Movement` y `RecurringTransaction` protege las
  queries existentes; opt-in `withoutSandboxScope()` para vistas sandbox.
- **Sin snapshot físico del estado real**. El sandbox es una capa aditiva; los
  cambios reales durante la simulación se quedan. El "revertir" del usuario se
  reduce a borrar filas sandbox, no a restaurar el estado al momento de empezar.
- **"Guardar" SIEMPRE promueve a real**. No existe "guardar como plan archivado"
  en v1.2 — derivado a v3.
- **No se puede simular mutando una template real existente**: para "qué tal si
  Falabella sube a 250" hay que crear una template sandbox nueva (la real sigue
  existiendo en paralelo bajo el scope real). Otro candidato a v3.
- **Modo Simulación es UI-only**: el toggle no crea estado, solo disclosure.
- **Plan independiente de v1.1 (PWA)**: ambos se pueden implementar en
  paralelo o en el orden que el usuario prefiera.