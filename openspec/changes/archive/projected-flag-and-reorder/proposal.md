# Proposal: Projected Flag and Reorder for Movements

## Intent

Movements are auto-flagged "projected" by `date > today` (no persistent control) and within a day sort only by creation order. This change adds an explicit `is_projected` flag and per-group `sort_order`, surfaced as two drag-and-droppable UI sections ("Reales"/"Proyectados"), and excludes projected movements from any real-balance computation so the split stays financially consistent.

## Scope

### In Scope
- `is_projected` bool (NOT NULL, default 0) on `movements`
- `sort_order` int (NOT NULL, default 0) + index `[user_id, date, is_projected, sort_order]`
- Controller `index` returns `realMovements` + `projectedMovements`; running_balance only on reales
- Compat `is_projected = is_projected || date > today` (keeps 3 tests green)
- `PATCH /movimientos/reorder` `{ ids: number[] }`, transaction, route BEFORE `{movement}`
- `MovementRequest`: `is_projected` rule + `prepareForValidation` (copy `AccountRequest`)
- `Movement::openingBalance`/`realBalance` filter `is_projected=false`
- `Movimientos/Index.vue`: two `vuedraggable` tables, `GripVertical` handle
- `MovementDialog`: "Marcar como proyectado" switch; flip→real auto-assigns `MAX(sort_order)+1`
- Wayfinder regen for new route

### Out of Scope
- Forecasts/report graphs, cross-day drag, recurring reordering, custom mobile gestures

## Capabilities

### New Capabilities
- `movements`: lifecycle — create/edit/delete, projection flag, ordering, balances, reorder

### Modified Capabilities
- None (`openspec/specs/` empty)

## Approach

Single migration adds columns + index. `MovementRequest::prepareForValidation` mirrors `AccountRequest`. Controller splits the month query into two arrays, accumulates running balance only on `is_projected=false`; compat rule keeps prior assertions. Reorder validates all `ids` share (date, is_projected, user_id), reassigns `sort_order` 1..N in `DB::transaction`. Frontend renders two `<Table>` sections wrapped in `draggable` with a `GripVertical` handle so edit/delete buttons stay clickable.

## Affected Areas

| Area | Impact |
|------|--------|
| `database/migrations/*_add_*movements*` | New — bool + int + index |
| `app/Models/Movement.php` | Modified — casts/fillable; balances exclude projected |
| `app/Http/Controllers/MovementController.php` | Modified — split index; `reorder()` |
| `app/Http/Requests/MovementRequest.php` | Modified — `is_projected` rule + coercion |
| `routes/web.php` | Modified — `PATCH movimientos/reorder` before `{movement}` |
| `resources/js/pages/Movimientos/Index.vue` | Modified — two draggable tables |
| `resources/js/components/movements/MovementDialog.vue` | Modified — switch + sort auto-assign |
| `tests/Feature/MovementTest.php` | Modified — 3 compat + new reorder tests |
| `resources/js/routes/movimientos.*` | Regenerated — Wayfinder |

## Risks

| Risk | Mitigation |
|------|------------|
| `reorder` shadowed by `{movement}` (Med) | Register before bound route; arch test |
| Balance diverges between sections (Low) | Single source: only reales accumulate |
| `sort_order` collision on concurrent insert (Low) | `MAX+1` per (user, date, group) |
| Review exceeds 400-line budget (Med) | Forecast ~350–450; slice PRs if needed |

## Rollback Plan

Migration `down()` drops columns + index. Revert controller to single array with `is_projected = date > today` (3 tests restored). Remove reorder route + Wayfinder regen; revert Vue files. No data loss — `date`/`amount`/`source` unchanged.

## Dependencies

- `vuedraggable@^4.1.0` (installed); `@lucide/vue` `GripVertical` (in icon set)

## Success Criteria

- [ ] 3 existing projected tests pass unchanged
- [ ] Reorder test asserts `sort_order` 1..N after `PATCH movimientos/reorder`
- [ ] Server rejects reorder with mixed dates / groups / foreign user
- [ ] `openingBalance`/`realBalance` exclude `is_projected=true`
- [ ] Two draggable tables; only reales carry running balance
- [ ] `php artisan test --compact` + `npm run build` pass

## Open Questions

None — decisions confirmed in preflight. Spec-phase edge cases:
- Projected→real flip preserves date (assumption: yes; only reassigns sort_order)
- Drag of sole section row: client no-op, skip empty payload (assumption: yes)