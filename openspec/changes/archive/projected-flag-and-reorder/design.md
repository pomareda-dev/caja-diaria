# Design: Projected Flag and Reorder for Movements

## Technical Approach

Add `is_projected` (bool) and `sort_order` (int) columns to `movements`. Controller splits index into two arrays; running_balance computed only over reales. New `PATCH reorder` endpoint validates same-group ids and reassigns sort_order 1..N in a transaction. Frontend renders two `vuedraggable` table sections. Compat rule: `is_projected = is_projected || date > today`.

## Architecture Decisions

| Decision | Choice | Alternatives | Rationale |
|----------|--------|-------------|-----------|
| sort_order scope | Per `(user_id, date, is_projected)` | Global per `(user_id, date)` | Each section gets independent 1..N; avoids interleaved numbers when flipping projected→real |
| Balance exclusion | `openingBalance`/`realBalance` add `is_projected=false` filter | Keep current filter, let frontend ignore | Financial consistency — projected manual movements must not inflate realized balances |
| Route registration | `reorder` BEFORE `{movement}` in `routes/web.php` | After (would shadow) | Laravel matches routes top-down; `movimientos/{movement}` would capture `reorder` as the model parameter |
| Drag lib | Use existing `vuedraggable@^4.1.0` | Install new dep | Already in `package.json`; Vue 3 compatible via `@next` tag |
| Wayfinder | Vite plugin auto-generates; run `php artisan wayfinder:generate` for dev | Manual TS file | Project uses `@laravel/vite-plugin-wayfinder` — plugin handles generation on build |
| is_projected in fillable | Yes — add to `$fillable` | Set explicitly in controller | User controls the flag via UI; follows Category pattern with `sort_order` in fillable |

## Data Flow

```
User toggles "Marcar como proyectado" + saves
  → MovementRequest::prepareForValidation coerces empty→0
  → Controller store(): compute sort_order = MAX+1 for (user, date, is_projected)
  → Movement::create([...validated, 'source'=>'manual', 'sort_order'=>N])

User drags row in Reales section
  → Frontend PATCH /movimientos/reorder { ids: [3,1,2] }
  → Controller reorder(): validate all ids same (user, date, is_projected)
  → DB::transaction: reassign sort_order 1..N

Controller index():
  → Query movements for month, ordered by sort_order
  → Split into realMovements (is_projected=false OR date<=today→false)
  → Compute running_balance only on realMovements (opening→reales)
  → Return { realMovements, projectedMovements, openingBalance, ... }
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `database/migrations/*_add_projection_and_order_to_movements.php` | Create | Add `is_projected` bool, `sort_order` int, composite index |
| `app/Models/Movement.php` | Modify | Fillable + casts additions; exclude `is_projected=true` from `openingBalance`/`realBalance` |
| `app/Http/Requests/MovementRequest.php` | Modify | Add `is_projected` rule + `prepareForValidation` (copy `AccountRequest` pattern) |
| `app/Http/Controllers/MovementController.php` | Modify | Split index; add `sort_order` to store; handle flip in update; new `reorder()` |
| `routes/web.php` | Modify | Add `PATCH movimientos/reorder` BEFORE `{movement}` route |
| `resources/js/pages/Movimientos/Index.vue` | Modify | Two `vuedraggable` tables; summary from realMovements only |
| `resources/js/components/movements/MovementDialog.vue` | Modify | Add `is_projected` switch to form |
| `tests/Feature/MovementTest.php` | Modify | 3 compat tests + 10 new tests |
| `database/factories/MovementFactory.php` | Modify | Add `is_projected` and `sort_order` defaults |

## Interfaces / Contracts

**MovementRequest::prepareForValidation** (mirrors `AccountRequest`):
```php
protected function prepareForValidation(): void
{
    $this->merge([
        'is_projected' => $this->input('is_projected') === null || $this->input('is_projected') === ''
            ? 0
            : (int) $this->input('is_projected'),
    ]);
}
```

**Movement::nextSortOrder** helper:
```php
public static function nextSortOrder(int $userId, string $date, bool $isProjected): int
{
    return (int) static::where('user_id', $userId)
        ->where('date', $date)
        ->where('is_projected', $isProjected)
        ->max('sort_order') + 1;
}
```

**reorder() responses**: 200 success, 422 mixed dates/groups, 403 cross-user.

## Testing Strategy

All Pest feature tests. 3 existing projected tests stay green via compat rule.

| What | Approach |
|------|----------|
| `is_projected` defaults false | Post without field, assert DB |
| Manual `is_projected=true` shows projected | Create with flag, check index |
| Index splits `realMovements`/`projectedMovements` | assertInertia on new keys |
| `running_balance` only on reales | Assert projected lack it |
| Balances exclude `is_projected=true` | Projected manual before month, assert sum |
| Store auto-assigns `sort_order` | Create two reales, assert 1,2 |
| Flip projected→real reassigns sort_order | Update flag, assert new value |
| Flip preserves date | Assert date unchanged |
| Reorder happy path | Patch reordered ids, assert sort_order |
| Reorder rejects mixed dates (422) | Cross-date ids |
| Reorder rejects cross-user (403) | Other user's movement |
| Reorder single row idempotent | Patch 1 id, assert unchanged |
| Dialog switch integration | Pest post with `is_projected=1` |

## Migration / Rollout

Single reversible migration (`down()` drops columns + index). Existing rows get defaults (`is_projected=0, sort_order=0`). No feature flag. Run `php artisan wayfinder:generate` for dev after route addition.

## Sequence Diagrams

**Reorder:** `PATCH {ids:[3,1,2]}` → validate same (user,date,is_projected) → `DB::transaction` reassign 1..N → 200
**Flip projected→real:** `PUT {is_projected:0}` → detect true→false → `nextSortOrder()` = MAX+1 → update sort_order

## Open Questions

- [ ] None — all decisions confirmed in exploration and proposal phases.
