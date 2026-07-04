# Movements Specification

## Purpose

Defines the lifecycle (CRUD), projection flagging, per-group ordering, reorder endpoint, and balance
computation rules for Movements. This is the initial spec for the `movements` domain.

## Requirements

### REQ-001: `is_projected` Flag

| Field | Value |
|-------|-------|
| Type | `boolean`, NOT NULL, default `false` |
| Control | Manual via UI; auto-derive compat rule |

The system MUST persist `is_projected` as a database column. A movement SHALL be displayed
as projected when `is_projected = true OR date > today` (compat rule).

#### Scenario: Manual projected flag set via dialog

- GIVEN a movement with `date <= today` and `is_projected = false`
- WHEN the user toggles "Marcar como proyectado" in MovementDialog and saves
- THEN `is_projected` becomes `true`

#### Scenario: Future-dated movement displayed as projected

- GIVEN a movement with `date > today` and `is_projected = false`
- WHEN the movements index is rendered
- THEN the movement appears in the Proyectados section

### REQ-002: `sort_order` Column

| Field | Value |
|-------|-------|
| Type | `integer`, NOT NULL, default `0` |
| Scope | `(user_id, date, is_projected)` |
| Auto-assign | `MAX(sort_order) + 1` for scope on create |

The system MUST scope `sort_order` per user, date, and projection group.
New movements SHALL receive `sort_order = MAX(sort_order) + 1` within their scope.

#### Scenario: First movement of the day gets sort_order 1

- GIVEN no movements exist for (user, 2026-07-15, is_projected=false)
- WHEN the user creates a new real movement on that date
- THEN `sort_order` is set to `1`

### REQ-003: Reorder Endpoint

`PATCH /movimientos/reorder` — registered BEFORE `{movement}` route.

The endpoint MUST validate all `ids` share the same user, date, and `is_projected` group.
It MUST reassign `sort_order` 1..N atomically within a `DB::transaction`.

#### Scenario: Reorder three real movements

- GIVEN month movements [A(sort=1), B(sort=2), C(sort=3)] for same user/date/is_projected=false
- WHEN `PATCH /movimientos/reorder` with `{ "ids": [3, 1, 2] }`
- THEN sort_order becomes [C=1, A=2, B=3]

#### Scenario: Reorder with mixed dates is rejected

- GIVEN two movements on different dates for the same user
- WHEN `PATCH /movimientos/reorder` includes both `ids`
- THEN server returns 422 validation error

#### Scenario: Single-row reorder is idempotent

- GIVEN only one movement in the Reales section
- WHEN `PATCH /movimientos/reorder` with `{ "ids": [1] }`
- THEN sort_order remains unchanged

### REQ-004: Two-Section UI

The movements index MUST render two `vuedraggable` sections: "Reales" and "Proyectados".
Only the Reales section SHALL display a running balance column.
Each row MUST include a drag-handle column (`GripVertical`).

#### Scenario: Running balance only in reales

- GIVEN realMovements [A(100), B(-50)] and projectedMovements [C(200)]
- WHEN the index page is rendered
- THEN Reales shows running_balance [100, 50]; Proyectados shows no running_balance

### REQ-005: Flip Projected to Real

When `is_projected` changes from `true` to `false`, the system MUST auto-assign
`sort_order = MAX(sort_order) + 1` for scope `(user, date, is_projected=false)`.
The date MUST NOT change during the flip.

#### Scenario: Flip preserves date, reassigns sort_order

- GIVEN a projected movement (date=2026-07-20, is_projected=true, sort_order=2)
  AND the Reales of that date have MAX sort_order=5
- WHEN user unchecks "Marcar como proyectado" and saves
- THEN is_projected becomes false, date stays 2026-07-20, sort_order becomes 6

### REQ-006: MovementDialog Projected Switch

The MovementDialog component SHALL include a `"Marcar como proyectado"` toggle (boolean switch).
It SHALL be visible on both create and edit forms.

#### Scenario: Create a projected movement

- GIVEN user opens MovementDialog
- WHEN user fills date/description/amount and toggles "Marcar como proyectado" ON
- THEN the movement is saved with `is_projected = true`

### REQ-007: MovementRequest Validation

`MovementRequest` MUST include `is_projected` as a `boolean` validation rule.
`prepareForValidation` SHALL coerce empty string to `0`, following the `AccountRequest` pattern.

#### Scenario: Empty is_projected defaults to false

- GIVEN a POST to `/movimientos` without the `is_projected` field
- WHEN the request is validated
- THEN `is_projected` is coerced to `false`

### REQ-008: Balance Exclusion

`Movement::openingBalance()` and `Movement::realBalance()` MUST exclude movements where `is_projected = true`.
The filter SHALL be: `source IN ['manual','import'] AND is_projected = false`.

#### Scenario: Projected movements excluded from opening balance

- GIVEN a manual movement before month-start with `is_projected = true`
- WHEN `openingBalance($monthStart, $userId)` is called
- THEN that movement's amount is NOT included in the sum

### REQ-009: Controller Index Split

`MovementController::index` MUST return two separate arrays: `realMovements` and `projectedMovements`.
Only `realMovements` SHALL include `running_balance`. Both arrays SHALL be ordered by `sort_order`.

#### Scenario: Index returns split arrays

- GIVEN 3 real and 2 projected movements for the selected month
- WHEN `GET /movimientos?month=2026-07` is called
- THEN response contains `realMovements` with 3 items and `projectedMovements` with 2 items
