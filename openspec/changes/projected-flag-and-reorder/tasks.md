# Tasks: Projected Flag and Reorder for Movements

Change: `projected-flag-and-reorder`

Strict TDD is active. Test runner: `php artisan test --compact`.
Every implementation task has a preceding test task (red-green-refactor).

## Legend

- Type: `test` | `implementation` | `migration` | `tooling`
- Deps: task IDs that must be completed first
- Est: estimated changed lines

---

## Phase 1: Migration + Model

### T001 — Test: is_projected defaults false, sort_order defaults 0
- Type: test
- Deps: —
- Files: `tests/Feature/MovementTest.php`, `database/factories/MovementFactory.php` (temporary inline, not yet state-based)
- Description: Write Pest test: create a Movement via factory without `is_projected`/`sort_order` — assert DB row has `is_projected=0` and `sort_order=0`. Also assert `$movement->is_projected === false` (boolean cast). This test will FAIL until the migration + model changes land (red phase).
- Verification: `php artisan test --compact --filter=is_projected_defaults_to_false` → expect FAIL (red).
- Est: 15 lines
- Status: ✅ Done (TDD: RED → GREEN after T003)

### T002 — Migration: add is_projected + sort_order to movements
- Type: migration
- Deps: T001
- Files: `database/migrations/2026_07_04_000001_add_is_projected_and_sort_order_to_movements_table.php`
- Description: Add `$table->boolean('is_projected')->default(false)` and `$table->integer('sort_order')->default(0)`. Add composite index `$table->index(['user_id', 'date', 'is_projected', 'sort_order'])`. `down()` drops index + columns. Reversible.
- Verification: `php artisan migrate` on testing DB succeeds; `php artisan test --compact --filter=is_projected_defaults` → still red (model not updated yet) but migration no longer fails.
- Est: 20 lines
- Status: ✅ Done

### T003 — Model: Movement fillable + casts + scope changes
- Type: implementation
- Deps: T002
- Files: `app/Models/Movement.php`
- Description: Add `is_projected` and `sort_order` to `$fillable`. Add casts: `'is_projected' => 'boolean'`, `'sort_order' => 'integer'`. Modify `openingBalance()` and `realBalance()` to add `->where('is_projected', false)` to their sum queries. Add static `nextSortOrder(int $userId, string $date, bool $isProjected): int` returning `(int) static::where(...)->max('sort_order') + 1`.
- Verification: `php artisan test --compact --filter=is_projected_defaults` → GREEN. `php artisan test --compact` → existing 139 tests still green.
- Est: 25 lines
- Status: ✅ Done

### T004 — Test: balances exclude is_projected=true
- Type: test
- Deps: T003
- Files: `tests/Feature/MovementTest.php`
- Description: Write Pest test: create a manual movement before month-start with `is_projected=true`, assert `openingBalance` does NOT include its amount. Create a manual movement date<=today with `is_projected=true`, assert `realBalance` excludes it. Red until T003 already green (should pass immediately if T003 done right — regression guard).
- Verification: `php artisan test --compact --filter=balance_excludes_projected` → GREEN.
- Est: 30 lines
- Status: ✅ Done

---

## Phase 2: Request + Controller backend

### T005 — Test: MovementRequest is_projected boolean + prepareForValidation
- Type: test
- Deps: T003
- Files: `tests/Feature/MovementTest.php`
- Description: Write Pest test: POST to `movimientos.store` WITHOUT `is_projected` field — assert DB row has `is_projected=0`. POST with `is_projected=1` — assert DB has `is_projected=1`. Verify validation accepts boolean. Red until T007 implementation.
- Verification: `php artisan test --compact --filter=movement_request_is_projected` → expect FAIL (red).
- Est: 25 lines
- Status: ✅ Done (TDD: RED → GREEN after T007)

### T006 — Test: controller index splits realMovements/projectedMovements
- Type: test
- Deps: T003
- Files: `tests/Feature/MovementTest.php`
- Description: Write Pest test: create 3 real (is_projected=false, date<=today) and 2 projected movements (is_projected=true, date<=today OR date>today). GET `movimientos.index?month=YYYY-MM`. AssertInertia: `realMovements` has 3 items with `running_balance`, `projectedMovements` has 2 items WITHOUT `running_balance` (or running_balance null). Red until T008 implementation.
- Verification: `php artisan test --compact --filter=index_splits_real_projected` → expect FAIL (red).
- Est: 35 lines
- Status: ✅ Done (TDD: RED → GREEN after T008)

### T007 — Implementation: MovementRequest is_projected rule + prepareForValidation
- Type: implementation
- Deps: T005
- Files: `app/Http/Requests/MovementRequest.php`
- Description: Add `'is_projected' => ['nullable', 'boolean']` to rules. Add `prepareForValidation()` coerces `$this->input('is_projected')` null/empty → `0`, else `(int)` — copy `AccountRequest` pattern exactly. Add `'sort_order' => ['nullable', 'integer']` rule for completeness.
- Verification: `php artisan test --compact --filter=movement_request_is_projected` → GREEN.
- Est: 20 lines
- Status: ✅ Done

### T008 — Implementation: controller index splits + running balance on reales only
- Type: implementation
- Deps: T006, T007
- Files: `app/Http/Controllers/MovementController.php`
- Description: Modify `index()`: query movements for month ordered by `date, sort_order`. Partition into realMovements (where `is_projected=false AND date<=today`) and projectedMovements (where `is_projected=true OR date>today`). Compute `running_balance` only over realMovements in order (opening → reales). projectedMovements carry all fields except `running_balance` (set null or omit). Return Inertia props `realMovements`, `projectedMovements`, `openingBalance`, `categories`, `selectedMonth`, `currentMonth`. Keep existing compat assertion shape for `is_projected` on each row.
- Verification: `php artisan test --compact --filter=index_splits_real_projected` → GREEN.
- Est: 40 lines
- Status: ✅ Done

### T009 — Test: store auto-assigns sort_order per (user, date, is_projected)
- Type: test
- Deps: T007
- Files: `tests/Feature/MovementTest.php`
- Description: Write Pest test: user creates two real movements on same date — assert DB sort_order 1 and 2 respectively. Create a third projected movement same date — assert its sort_order is 1 in the projected group (independent sequence). Red until T010 implementation.
- Verification: `php artisan test --compact --filter=store_auto_sort_order` → expect FAIL (red).
- Est: 35 lines
- Status: ✅ Done (TDD: RED → GREEN after T010)

### T010 — Implementation: store auto-assigns sort_order
- Type: implementation
- Deps: T009, T007
- Files: `app/Http/Controllers/MovementController.php`
- Description: In `store()`: before `$user->movements()->create(...)`, compute `nextSortOrder($user->id, $date, $isProjected)` and merge into create payload alongside `source => 'manual'`. Use the validated `is_projected` (already coerced to 0/1 by request) casted to bool.
- Verification: `php artisan test --compact --filter=store_auto_sort_order` → GREEN.
- Est: 15 lines
- Status: ✅ Done

### T011 — Test: update flip projected→real auto-reassigns sort_order + preserves date
- Type: test
- Deps: T010
- Files: `tests/Feature/MovementTest.php`
- Description: Write Pest test: create a projected movement (date=2026-07-20, is_projected=true, sort_order=2). Create 5 real movements on same date with sort_order 1..5. PUT `movimientos.update` with `is_projected=0` (unchanged date). Assert DB: `is_projected=0`, `date` still 2026-07-20 (EDGE-1), `sort_order=6` (MAX+1). Red until T012 implementation.
- Verification: `php artisan test --compact --filter=flip_preserves_date_reassigns_sort` → expect FAIL (red).
- Est: 35 lines
- Status: ✅ Done (TDD: RED → GREEN after T012)

### T012 — Implementation: update handles flip + sort_order reassign
- type: implementation
- Deps: T011, T010
- Files: `app/Http/Controllers/MovementController.php`
- Description: In `update()`: detect if `is_projected` changed from true→false (compare validated vs original). If so, OR if `date` changed, recompute `sort_order = nextSortOrder(...)` for new (user, date, is_projected) scope and merge into update payload. If only `is_projected` changes (EDGE-1), do NOT change date — only is_projected and sort_order. Otherwise keep existing sort_order.
- Verification: `php artisan test --compact --filter=flip_preserves_date_reassigns_sort` → GREEN.
- Est: 25 lines
- Status: ✅ Done

---

## Phase 3: Reorder endpoint

### T013 — Test: reorder happy path + edge cases
- Type: test
- Deps: T007
- Files: `tests/Feature/MovementTest.php`
- Description: Write Pest tests:
  - Happy path: 3 real movements same date [A=s1,B=s2,C=s3]. PATCH reorder `{ids:[3,1,2]}` → assert C=s1, A=s2, B=s3 (200).
  - Mixed dates: same two movements on different dates. PATCH with both ids → 422.
  - Cross-user: PATCH with ids containing another user's movement → 403 or 422.
  - Single row idempotent (EDGE-2): PATCH `{ids:[1]}` → sort_order unchanged.
  Red until T014 implementation.
- Verification: `php artisan test --compact --filter=reorder` → expect FAIL (red).
- Est: 60 lines
- Status: ✅ Done (TDD: RED → GREEN after T014)

### T014 — Implementation: reorder route + controller + validation
- Type: implementation
- Deps: T013, T012
- Files: `routes/web.php`, `app/Http/Controllers/MovementController.php`
- Description: Add `Route::patch('movimientos/reorder', [MovementController::class, 'reorder'])->name('movimientos.reorder')` BEFORE the `{movement}` route. Implement `reorder(Request $request)`: validate `ids` is a non-empty array of integers. Fetch all movements by ids where `user_id = auth->id` (cross-user → 403 if count mismatch). Validate all share same `date` and same `is_projected` (else 422). In `DB::transaction`, iterate ids in order, update `sort_order = index+1`. Flash success toast. Return back.
- Verification: `php artisan test --compact --filter=reorder` → GREEN.
- Est: 45 lines
- Status: ✅ Done

---

## Phase 4: Frontend

### T015 — MovementDialog: is_projected switch
- Type: implementation
- Deps: T014
- Files: `resources/js/components/movements/MovementDialog.vue`
- Description: Import shadcn Switch component. Add "Marcar como proyectado" label + Switch below the Fecha field, wired to `form.is_projected`. On `resetForm`, default to `false`. On `populateFormForEdit`, read `movement.is_projected`. Transform to `0` or `1` in submit payload. Export `is_projected` in `MovementData` interface.
- Verification: `npm run build` succeeds; manual page smoke check.
- Est: 25 lines

### T016 — Movimientos/Index: split two sections + vuedraggable + drag handle + summary from reales
- Type: implementation
- Deps: T015
- Files: `resources/js/pages/Movimientos/Index.vue`
- Description: Replace single Table with two sections (cards), each containing a Table:
  - **Reales**: header "Reales" + `<draggable :list="realMovements" item-key="id" :handle="'.drag-handle'" @end="onReorderReales">` wrapping `<tr>` rows. Show columns: Fecha, Movimiento, Tipo, Cantidad, Balance (running_balance). Drag handle column with `<GripVertical>` icon (lucide-vue-next), class `drag-handle cursor-grab`. Edit/delete buttons as before.
  - **Proyectados**: header "Proyectados" + same draggable pattern. No running_balance column (show only Cantidad).
  - Summary cards (Ingresos/Gastos/Neto/Balance final) computed from `realMovements` only.
  - `onReorderReales`/`onReorderProjected`: emit `router.patch(movimientos.reorder.url(), { ids: realMovements.map(m => m.id) })` after drag end. Use `preserveScroll: true`.
  - Opening balance row only at top of Reales table.
  Update `defineProps` to accept `realMovements` and `projectedMovements` arrays (rename `movements` prop).
- Verification: `npm run build` succeeds; `npm run dev` smoke test drag row in each section.
- Est: 120 lines

### T017 — Wayfinder regenerate
- Type: tooling
- Deps: T014
- Files: `resources/js/routes/movimientos/index.ts`
- Description: Run `php artisan wayfinder:generate` to regenerate TypeScript route functions. Verify `reorder` builder now exported.
- Verification: `grep reorder resources/js/routes/movimientos/index.ts` returns the builder.
- Est: auto-generated (0 manual lines)

---

## Phase 5: Factory + Seeder

### T018 — Factory: is_projected + sort_order defaults and states
- Type: implementation
- Deps: T003
- Files: `database/factories/MovementFactory.php`
- Description: Add `'is_projected' => false` and `'sort_order' => 0` to definition(). Add `projected()` state (sets `is_projected => true`) and `sortOrder(int $n)` state. Update any broken calls.
- Verification: `php artisan test --compact` → full suite green.
- Est: 15 lines
- Status: ✅ Done

### T019 — Seeder: backfill is_projected from date>today for existing demo rows
- Type: implementation
- Deps: T018
- Files: `database/seeders/CajaDiariaDemoSeeder.php`
- Description: In seeder, set `is_projected` explicitly when creating movements. Recurring-seeded movements should keep `is_projected = false` (compat rule derives from date at display time). Add at least one manual projected movement for demo.
- Verification: `php artisan db:seed --class=CajaDiariaDemoSeeder` succeeds; manual smoke check on seeded DB.
- Est: 15 lines
- Status: ✅ Done

---

## Review Workload Forecast

| Metric | Value |
|--------|-------|
| Total tasks | 19 (9 test + 8 implementation + 1 migration + 1 tooling) |
| Total estimated changed lines | ~540 |
| Chained PRs recommended | **Yes** |
| 400-line budget risk | **High** |
| Decision needed before apply | **Yes** |

### PR slicing recommendation

Forecast (~540 lines) exceeds the 400-line review budget. Recommended chained PRs:

- **PR #1 (backend, ~275 lines)**: T001–T014 — migration, model, request, controller index/store/update/reorder, routes, Wayfinder regen, factory, seeder. Backend is independently testable (Pest feature tests prove the API before UI lands).
- **PR #2 (frontend, ~145 lines)**: T015–T016 — MovementDialog switch + Movimientos/Index split with vuedraggable. Builds on PR #1's API.

PR #1 can merge to main independently. PR #2 targets main after PR #1 merges. Reviewer sees clean separation: backend contract first, UI second.

### Chain strategy

If the user chooses chained PRs, also choose chain strategy:
- `stacked-to-main`: PR #1 merges to main, PR #2 targets main after. Simplest for this case.
- `feature-branch-chain`: not needed — only 2 PRs and both target main.