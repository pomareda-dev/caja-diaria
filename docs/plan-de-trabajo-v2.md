# Plan de trabajo v2 — Caja Diaria (etapa comercial)

> Extensión **posterior al MVP** (fases 0–9 de v1). La app pasa de uso
> exclusivamente personal a soportar también **cuentas comerciales (negocios)**:
> al registrarse, el usuario elige modo `personal` o `commercial`. El modo
> comercial añade gestión de clientes, ventas (de contado y a crédito) y cuentas
> por cobrar, integradas a los reportes y dashboards.

**Stack:** el mismo de v1 (Laravel 13 · Inertia 3 · Vue 3 Composition API +
TypeScript · Tailwind · shadcn-vue).

**Prerequisito:** v1 completada (fases 0–9, incluida la Fase 8 de
personalización).

**Stack de referencia:** ver `plan-de-trabajo.md` para el modelo y decisiones de
v1 que este plan extiende.

---

## 1. Objetivo

Extender Caja Diaria para que un negocio pequeño pueda:

1. Registrarse como cuenta **commercial** y acceder a módulos de
   clientes/ventas.
2. Llevar ventas de contado y a crédito, con seguimiento de cuentas por cobrar.
3. Ver dashboards y reportes que incluyan ventas por cliente y saldos
   pendientes.
4. Mantener el modelo simple: una venta es un movimiento de ingreso vinculado a
   un cliente (sin líneas de producto ni facturación SUNAT).

El modo **personal** sigue funcionando exactamente como en v1.

---

## 2. Alcance

### Dentro del alcance (v2)

- **Tipo de cuenta** al registrar: `personal` o `commercial` (enum en `users`).
- **Tabla de clientes** (`customers`) para cuentas comerciales: nombre,
  documento (DNI/RUC/none), contacto, notas.
- **Ventas de contado:** un `movement` con `source=sale`, `customer_id`,
  `on_credit=false`.
- **Ventas a crédito:** un `movement` con `source=sale`, `customer_id`,
  `on_credit=true` + seguimiento de saldo pendiente.
- **Pagos de crédito** (parciales o totales): `movement` con
  `source=credit_payment`, `customer_id`, `related_movement_id` (la venta).
- **Cuentas por cobrar:** vista de clientes con saldo pendiente y detalle por
  cliente.
- **Dashboard comercial:** tarjetas de ventas del mes, cuentas por cobrar, top
  clientes. El dashboard personal se mantiene intacto.
- **Reportes:** ventas por cliente y por período (mes seleccionable).
- Moneda **PEN** (igual que v1).

### Fuera del alcance (deferido a v3 o posterior)

- **Múltiples libros por usuario** (personal + varios negocios en la misma
  cuenta) → v3. En v2, un usuario = un tipo (personal o comercial).
- **Multi-usuario con roles** (cajero, contador, dueño) → v3. En v2, un solo
  operador por cuenta.
- **Impuestos (IGV)** y reportes de impuestos → v3. En v2, los importes son
  totales.
- **Facturación electrónica SUNAT** (factura/boleta, XML, OSE, firma digital)
  → v3 o posterior.
- **Catálogo de productos/servicios y líneas de venta** → v3. En v2, venta =
  monto único por cliente.
- **Multi-moneda / conversiones** → fuera (igual que v1).
- **Sincronización bancaria** (open banking) → fuera (igual que v1).
- **Cambio de tipo de cuenta post-registro** (personal↔commercial) → fuera en
  v2. Si se necesita, se recrea la cuenta.

---

## 3. Decisiones de arquitectura

### 3.1 Tipo de cuenta como enum en `users` (no workspaces)

Se añade `account_type` enum (`personal`|`commercial`, default `personal`) a la
tabla `users`. El tipo se elige al registrarse y controla qué módulos son
visibles (clientes/ventas solo si `commercial`). **No** se modelan "libros"
separados por usuario en v2 (deferido a v3). Los usuarios existentes de v1 se
migran automáticamente a `personal`.

### 3.2 Venta = movimiento (no entidad separada)

Una venta es un `movement` de ingreso con `customer_id` y `source=sale`. Esto
reutiliza toda la maquinaria de v1 (línea temporal, balance acumulado, filtros
de mes, proyección, recurrentes). **No** hay tabla `sales` ni catálogo de
productos en v2. El desglose por producto se defiere a v3.

### 3.3 Balance de caja (cash basis); el crédito no lo incrementa

El balance acumulado de la línea temporal representa **caja** (dinero en mano),
igual que en v1. Para que el crédito encaje sin doble conteo:

- Venta de contado → incrementa el balance de caja (el dinero entra).
- Venta a crédito → **NO** incrementa el balance de caja; incrementa
  **cuentas por cobrar** (el cliente te debe).
- Pago de crédito → incrementa el balance de caja y reduce las cuentas por
  cobrar.

**Por qué:** la venta a crédito ya reconoció el ingreso (devengado); el pago
posterior es solo la conversión de deuda a caja. Si ambos sumaran al balance,
se duplicaría el ingreso. La reconciliación cuenta-vs-balance sigue como en v1;
el panel comercial muestra además el total de cuentas por cobrar como
información complementaria (no como descuadre).

### 3.4 Pagos de crédito como movimientos auto-referenciados

Un pago de crédito es un `movement` con `source=credit_payment` y
`related_movement_id` → la venta original (auto-FK). Múltiples pagos parciales
apuntan a la misma venta. El saldo del cliente se calcula por suma:
`SUM(ventas a crédito) − SUM(pagos de crédito)`.

### 3.5 Personal vs commercial: diferencia por visibilidad, no por código paralelo

El modo `personal` reutiliza las mismas vistas y composables de v1; los módulos
comerciales (clientes, cuentas por cobrar, reportes de ventas) se montan solo
si `account_type=commercial`. El layout añade items de navegación comerciales
condicionalmente. Esto evita mantener dos apps paralelas y previene regresiones
en el flujo personal.

### 3.6 Movimientos existentes sin cambios para usuarios personales

Los usuarios `personal` no ven `customer_id` ni `source=sale/credit_payment`.
Sus movimientos siguen siendo `manual`/`recurring` (como en v1). La columna
`customer_id` es nullable y se omite del flujo personal.

---

## 4. Modelo de datos

### 4.1 Cambios a tablas existentes

```
users
  + account_type (enum: personal|commercial, default personal)

movements
  + customer_id (FK NULL → customers)
  + on_credit (bool, default false)
  + related_movement_id (FK NULL → movements, self-ref)
  ~ source (enum: manual|recurring|sale|credit_payment)
```

> `source=import` nunca se implementó en v1 (la importación Excel se descartó).
> v2 parte del enum `manual|recurring` y añade `sale` y `credit_payment`.

### 4.2 Tabla nueva

```
customers
  id, user_id (FK), name,
  document_type (enum: dni|ruc|none, default none),
  document_number (string NULL),
  phone (string NULL), email (string NULL),
  address (string NULL), notes (text NULL),
  timestamps
  unique(user_id, document_number)  -- NULLs ignorados
  index(user_id, name)
```

### 4.3 Relaciones nuevas

- `movements.customer_id → customers` (nullable: lo usan ventas y pagos de
  crédito; el resto null).
- `movements.related_movement_id → movements` (self-FK, nullable: un pago de
  crédito apunta a la venta que liquida).
- `customers.user_id → users`.

### 4.4 Reglas de negocio clave (comercial)

- **Saldo pendiente de un cliente:**
  `SUM(movements.amount WHERE customer_id=C AND source=sale AND on_credit=true)
  − SUM(movements.amount WHERE customer_id=C AND source=credit_payment)`.
  - `> 0` → debe. `= 0` → al día. `< 0` → crédito a favor.
- **Venta saldada:** una venta a crédito está "saldada" cuando la suma de sus
  `credit_payment` relacionados ≥ su `amount`. Estados: `pendiente` (0 pagos),
  `parcial` (pagos > 0 pero < total), `saldada` (≥ total).
- **Balance de caja (running):** suma de todos los movimientos **excepto** las
  ventas a crédito (`source=sale AND on_credit=true`). Las ventas de contado y
  los pagos de crédito sí suman.
- **Total cuentas por cobrar (dashboard):**
  `SUM(saldo pendiente)` para todos los clientes del usuario.
- **Ventas del mes:**
  `SUM(amount WHERE source=sale AND date IN [M.start, M.end])`
  (contado + crédito). Para "ventas cobradas en el mes":
  `source=sale AND on_credit=false` + `source=credit_payment` en el mes.

---

## 5. Fases del plan

Cada fase es autónoma y entregable: deja la app funcional hasta ese punto y
puede revisarse/commitearse por separado.

---

### Fase V2-0 — Fundamentos multi-modo

**Objetivo:** soportar tipo de cuenta al registrar y crear la tabla de clientes.

**Tareas**

1. Migración: añadir `account_type` enum (`personal`|`commercial`, default
   `personal`) a `users`.
2. Migración: crear tabla `customers`.
3. Modelo `Customer` con `$fillable`, casts y relaciones (`user`, `sales`,
   `creditPayments`).
4. Actualizar registro (Fortify `CreateNewUser`) para pedir `account_type`.
5. Composable `useAccountType` (o Inertia shared prop) que exponga el tipo al
   frontend; el layout oculta/muestra items de navegación comerciales.
6. Migración de usuarios existentes: todos quedan `account_type=personal`.
7. Tests: registro con cada tipo, visibilidad condicional del nav.

**Criterios de aceptación**

- [ ] Al registrarme puedo elegir `personal` o `commercial`.
- [ ] Un usuario `personal` no ve "Clientes" en el nav; uno `commercial` sí.
- [ ] Los usuarios existentes de v1 quedan como `personal`.

**Commits:** `feat: account type enum on users`,
`feat: customers table and model`,
`feat: registration with account type`,
`test: account type and conditional nav`.

---

### Fase V2-1 — CRUD de clientes

**Objetivo:** gestionar clientes (solo cuentas `commercial`).

**Tareas**

1. `CustomerController` resource (index, store, update, destroy).
2. Vista de lista con búsqueda por nombre o documento.
3. Formulario alta/edición: nombre, tipo doc, número, teléfono, email,
   dirección, notas.
4. Validación: `document_number` único por usuario (NULLs ignorados).
5. Restricción de borrado: no permitir borrar cliente con ventas/pagos
   asociados (ofrecer "archivar" vía soft-delete como alternativa).
6. Tests.

**Criterios de aceptación**

- [ ] Creo, edito y elimino clientes.
- [ ] Busco cliente por nombre o RUC/DNI.
- [ ] No puedo borrar un cliente con ventas (mensaje claro).

**Commits:** `feat: customer resource controller`,
`feat: customer list with search`,
`feat: customer create/edit form`,
`test: customer crud and validation`.

---

### Fase V2-2 — Ventas de contado

**Objetivo:** registrar ventas pagadas al instante, integradas a la línea
temporal de movimientos.

**Tareas**

1. Migración: añadir a `movements` las columnas `customer_id`, `on_credit`
   (default false), `related_movement_id`; ampliar enum `source` con `sale` y
   `credit_payment`.
2. Actualizar modelo `Movement` (`$fillable`, casts, relaciones `customer` y
   `relatedMovement`).
3. Formulario de venta: cliente (select buscable), importe, descripción, fecha.
   Crea `movement` con `source=sale`, `on_credit=false`, `customer_id`.
4. La venta aparece en la lista de movimientos como ingreso, con badge "Venta"
   y el cliente visible.
5. Filtro de movimientos por cliente.
6. Scopes: `Movement::forCustomer(C)`, `Movement::sales()`.
7. Tests.

**Criterios de aceptación**

- [ ] Registro una venta de contado y aparece en movimientos incrementando el
  balance de caja.
- [ ] Filtro movimientos por cliente.
- [ ] La venta se ve con badge "Venta" y nombre del cliente.

**Commits:** `feat: movements columns for customer and credit`,
`feat: cash sale form`,
`feat: customer filter on movements`,
`test: cash sales and movement relations`.

---

### Fase V2-3 — Ventas a crédito y cuentas por cobrar

**Objetivo:** ventas a crédito con seguimiento de saldos pendientes. Núcleo
comercial de v2.

**Tareas**

1. Formulario de venta: toggle "a crédito" → crea `movement` con `source=sale`,
   `on_credit=true`.
2. **Cálculo de balance ajustado:** el running balance excluye ventas a crédito
   (no son caja); incluye ventas de contado y pagos de crédito.
3. Registrar pago de crédito: seleccionas la venta a crédito → monto del pago →
   crea `movement` con `source=credit_payment`, `related_movement_id`,
   `customer_id`, `amount`.
4. Vista **Cuentas por cobrar:** lista de clientes con saldo pendiente
   (ordenado por monto descendente). Click → detalle del cliente.
5. Vista **Detalle de cliente:** historial de ventas + pagos + saldo actual +
   estado de cada venta (`pendiente`/`parcial`/`saldada`).
6. Badge de estado en ventas a crédito.
7. Tests: saldo del cliente, venta saldada tras pago total, pago parcial,
   overpayment (saldo negativo = crédito a favor).

**Criterios de aceptación**

- [ ] Registro una venta a crédito y **NO** incrementa el balance de caja;
  incrementa las cuentas por cobrar.
- [ ] Registro un pago parcial y el saldo del cliente baja.
- [ ] Tras pagar el total, la venta queda "Saldada" y el cliente en cero.
- [ ] La vista de cuentas por cobrar muestra solo clientes con saldo > 0.

**Commits:** `feat: credit sale form`,
`feat: cash-basis running balance excluding credit sales`,
`feat: credit payment with self-reference`,
`feat: accounts receivable view`,
`feat: customer detail with sales and payments`,
`test: credit sales, payments and balances`.

---

### Fase V2-4 — Dashboard comercial y reportes

**Objetivo:** dashboards y reportes que incorporen ventas y cuentas por cobrar.

**Tareas**

1. Dashboard condicional según `account_type`:
   - `personal`: idéntico a v1 (Fase 7).
   - `commercial`: añade tarjetas **Ventas del mes**, **Cuentas por cobrar**,
     **Top 5 clientes del mes**.
2. Reporte **Ventas por cliente** (mes seleccionable): tabla cliente · nº
   ventas · total vendido · cobrado · pendiente.
3. Reporte **Ventas por período:** total ventas por mes (contado vs crédito).
4. Integrar cuentas por cobrar en la conciliación: mostrar total por cobrar
   como info complementaria (no descuadre).
5. Tests.

**Criterios de aceptación**

- [ ] El dashboard `commercial` muestra ventas del mes y cuentas por cobrar.
- [ ] El reporte de ventas por cliente me dice quién debe y cuánto.
- [ ] El modo `personal` sigue mostrando el dashboard de v1 sin cambios.

**Commits:** `feat: commercial dashboard with sales and receivables`,
`feat: sales by customer report`,
`feat: sales by period report`,
`test: commercial dashboard and reports`.

---

### Fase V2-5 — Pulido y tests v2

**Objetivo:** dejar v2 lista para uso comercial local.

**Tareas**

1. Edge cases:
   - Borrar una venta a crédito con pagos (restringir o cascade con
     confirmación).
   - Pago que excede la deuda (saldo negativo del cliente = crédito a favor).
   - Cliente sin documento (`document_type=none`).
   - Venta a crédito con fecha futura (proyectada) — ¿cuenta como cuenta por
     cobrar futura?
2. Confirmaciones de borrado con dialog shadcn-vue.
3. Filtros combinados (mes + cliente + tipo).
4. README v2 con notas del modo comercial.
5. Verificar que el modo `personal` no sufre regresiones (suite de regresión).

**Criterios de aceptación**

- [ ] `php artisan test` en verde (suite v1 + v2).
- [ ] Modo `personal` funciona idéntico a v1.
- [ ] Modo `commercial` cubre ventas contado/crédito y reportes.

**Commits:** `test: v2 edge cases and regression`,
`feat: combined filters movements`,
`docs: v2 readme with commercial mode`.

---

## 6. Orden recomendado y dependencias

```
V2-0 (fundamentos: account_type + customers)
   │
   ▼
V2-1 (CRUD clientes) ──► V2-2 (ventas contado)
                              │
                              ▼
                        V2-3 (crédito / cuentas por cobrar)
                              │
                              ▼
                        V2-4 (dashboard + reportes)
                              │
                              ▼
                        V2-5 (pulido)
```

- V2-1 y V2-2 pueden solaparse parcialmente (V2-2 necesita `customers` creado
  en V2-0 y el CRUD de V2-1 para el select de cliente).
- V2-3 depende de V2-2 (ventas) y de las columnas añadidas en V2-2.
- V2-4 depende de V2-3 (necesita los saldos por cobrar calculados).

---

## 7. Estimación orientativa

| Fase     | Esfuerzo aprox. | Notas                                  |
| -------- | --------------- | -------------------------------------- |
| V2-0     | 1 día           | Migraciones + registro multi-modo      |
| V2-1     | 0.5–1 día       | CRUD clientes                          |
| V2-2     | 1 día           | Extender movements + ventas contado    |
| V2-3     | 1.5–2 días      | Núcleo comercial: crédito y por cobrar |
| V2-4     | 1 día           | Dashboard y reportes                   |
| V2-5     | 1 día           | Pulido + regresión modo personal       |
| **Total** | **~6–7 días**  | Secuencial                             |

---

## 8. Riesgos y mitigaciones

| Riesgo                                                        | Mitigación                                                                                          |
| ------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| Confusión balance caja vs devengado en usuarios comerciales  | UX clara: badge "a crédito" en la lista; tooltip explicando que el crédito no es caja               |
| Saldo de cliente incorrecto por doble conteo                 | Regla: `credit_payment` no suma al balance de caja; solo ventas contado y pagos de crédito suman    |
| Borrado de cliente con ventas rompe saldos                   | Restringir borrado si hay movimientos asociados; ofrecer "archivar" (soft-delete) como alternativa |
| Ampliar enum `source` rompe datos v1                         | Migración que preserva `manual`/`recurring`; `import` nunca existió en producción                  |
| Regresión en modo `personal`                                  | Suite de regresión que ejecuta los flujos v1 con `account_type=personal`                           |
| Overpayment deja saldos negativos confusos                   | Mostrar como "crédito a favor" (verde), no como error                                               |
| Cambiar `account_type` post-registro rompe aislamiento       | No permitir cambio en v2; documentar que se recrea la cuenta                                       |

---

## 9. Próximos pasos inmediatos

1. Completar v1 (Fase 8 personalización y Fase 9 pulido) antes de arrancar v2.
2. Ejecutar **V2-0** (fundamentos multi-modo).
3. Validar el flujo de crédito con un caso real antes de avanzar a V2-4.

---

## 10. Decisiones confirmadas

| Decisión              | Valor                                                                                  | Impacto                                                                       |
| --------------------- | -------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| Tipo de cuenta        | Enum `personal`|`commercial` en `users`                                                | Simple; multi-libro deferido a v3                                            |
| Modelo de venta       | `movement` con `source=sale`, `customer_id`                                            | Reutiliza la línea temporal de v1; sin catálogo de productos                 |
| Balance               | Cash basis; las ventas a crédito no incrementan caja                                   | Evita doble conteo; cuentas por cobrar como panel aparte                     |
| Pagos de crédito      | `movement` con `source=credit_payment`, auto-FK a la venta                             | Soporta pagos parciales; saldos por suma                                      |
| Facturación SUNAT     | Descartado en v2                                                                       | Solo tracking interno; comprobantes reales en v3+                            |
| IGV                   | No trackeado en v2                                                                     | Importes son totales; reportes de impuestos en v3                            |
| Multi-usuario         | No en v2                                                                               | Un operador por cuenta; equipos en v3                                         |

---

Con esto, v2 extiende Caja Diaria a uso comercial manteniendo la simplicidad
del modelo de v1. Las decisiones deferidas a v3 (multi-libro, multi-usuario,
IGV, SUNAT, catálogo de productos) quedan explícitas para no perderlas de
vista.
