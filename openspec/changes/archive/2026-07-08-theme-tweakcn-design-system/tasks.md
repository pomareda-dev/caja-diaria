# Tasks: Theme Tweakcn Design-System Selector

## Summary
- Total tasks: 12
- Estimated lines: ~1200 (960 CSS + 50 JS/PHP + 90 tests + 10 migration)
- 400-line budget risk: HIGH — orchestrator must trigger chained-PR conversation

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1200 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 → PR 2a → PR 2b → PR 3 |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Backend: migration + validation + tests | PR 1 | ~150 lines; tests first (TDD red→green) |
| 2a | CSS: first 4 themes (bold-tech, claude, default, pastel-dreams) | PR 2a | ~480 lines; builds on PR 1 or independent |
| 2b | CSS: last 4 themes (quantum-rose, sunny-sprout, twitter, violet-bloom) + app.css wiring | PR 2b | ~520 lines; builds on PR 2a |
| 3 | Frontend: composables + Preferences.vue | PR 3 | ~80 lines; builds on PR 2b |

---

## Workstream A: Backend (migration + validation)

### Task A1: Update theme validation tests (TDD — tests first)
- [x] Update `tests/Feature/Settings/PreferencesTest.php`:
  - Change valid dataset `'theme' => ['theme', 'rose']` → `['theme', 'default']`
  - Add `['theme', 'slate']` to invalid dataset (legacy key rejected post-migration)
  - Keep `['theme', 'pink']` in invalid dataset
  - Replace `'rose'` references in lines 49, 56, 64 with `'default'` or `'claude'`
  - Add new dataset test asserting all 8 new keys are accepted (200)
- **Files**: `tests/Feature/Settings/PreferencesTest.php`
- **Test command**: `php artisan test --compact --filter=Preferences`
- **Expected**: tests FAIL until A2 lands — that's the TDD red phase

### Task A2: Update backend validation rule
- [x] Change `app/Http/Requests/Settings/UpdateSettingsRequest.php` line 26 theme rule to:
  `in:default,bold-tech,claude,pastel-dreams,quantum-rose,sunny-sprout,twitter,violet-bloom`
- **Files**: `app/Http/Requests/Settings/UpdateSettingsRequest.php`
- **Test command**: `php artisan test --compact --filter=Preferences`
- **Expected**: A1 tests now GREEN

### Task A3: Create theme key migration
- [x] New migration `database/migrations/*_remigrate_theme_keys_to_default.php`:
  - Remap old keys `{slate, rose, blue, green, amber, violet, teal, red}` → `'default'` in `users.settings` JSON
  - Idempotent (safe to re-run)
  - `down()` is no-op
- **Files**: `database/migrations/*_remigrate_theme_keys_to_default.php` (NEW)
- **Test command**: `php artisan test --compact --filter=Preferences` or `php artisan migrate --pretend`
- **Expected**: migration runs without error; re-run yields 0 rows affected

---

## Workstream B: CSS (themes.css + app.css)

### Task B1: Create themes.css with 8 scoped theme blocks
- [x] Extract first 4 themes (bold-tech, claude, default, pastel-dreams) into scoped `.theme-xxx` / `.theme-xxx.dark` blocks in `resources/css/themes.css` — PR 2a
- [x] Extract remaining 4 themes (quantum-rose, sunny-sprout, twitter, violet-bloom) — PR 2b
  - Strip `--font-sans`, `--font-serif`, `--font-mono`
  - Strip `@import`, `@theme inline`, `@layer base`, `:root`, `.dark` selectors
  - Include all tokens: colors (19), chart (5), sidebar (8), radius (1), shadows (14), spacing (1), tracking (1)
  - Add header comment pointing back to `resources/themes/*.css` as source-of-truth
- **Files**: `resources/css/themes.css` (NEW)
- **Verify**: `npm run build` succeeds, no Tailwind errors
- **Estimated lines**: ~960

### Task B2: Update app.css @theme inline + import themes.css + remove old blocks
- [x] Modify `resources/css/app.css`:
  - Add `@import './themes.css'` near top (after `tw-animate-css`)
  - Remove `--font-sans` from `@theme inline` (lines 11–14)
  - Change line 54 `--color-sidebar: var(--sidebar-background)` → `--color-sidebar: var(--sidebar)`
  - Add bridge tokens after `--color-sidebar-ring`:
    - `--shadow-2xs` through `--shadow-2xl` mapped to `var(--shadow-*)`
    - `--tracking-tighter/tight/normal/wide/wider/widest` with `calc()` from `--tracking-normal`
    - `--spacing: var(--spacing)`
    - `--radius-xl: calc(var(--radius) + 4px)`
  - Remove old `.theme-slate` through `.theme-red` blocks (lines 165–333)
- **Files**: `resources/css/app.css`
- **Verify**: `npm run build`, visual check of default theme

---

## Workstream C: Frontend (composables + UI)

### Task C1: Update useAppearance.ts
- [x] Modify `resources/js/composables/useAppearance.ts`:
  - Add `const VALID_THEMES = new Set([...])` with 8 keys
  - Change line 18 `themeKey` default from `'slate'` to `'default'`
  - Change line 148 `initialTheme` fallback from `'slate'` to `'default'`
  - Add unknown-key fallback in `initializeTheme()`: if `pageTheme` not in `VALID_THEMES`, use `'default'`
- **Files**: `resources/js/composables/useAppearance.ts`
- **Verify**: `npx vue-tsc --noEmit`

### Task C2: Update useSettings.ts type
- [x] Modify `resources/js/composables/useSettings.ts`:
  - Update `UserSettings.theme` type union to 8 new keys
  - Change `defaults.theme` from `'slate'` to `'default'`
- **Files**: `resources/js/composables/useSettings.ts`
- **Verify**: `npx vue-tsc --noEmit`

### Task C3: Update Preferences.vue
- [x] Modify `resources/js/pages/settings/Preferences.vue`:
  - Replace `palettes[]` with 8 entries (keys + labels: Default, Bold Tech, Claude, Pastel Dreams, Quantum Rose, Sunny Sprout, Twitter, Violet Bloom)
  - Change Card title from `"Paleta de colores"` to `"Diseño"`
  - Update Card description to reflect full design-system selection
- **Files**: `resources/js/pages/settings/Preferences.vue`
- **Verify**: `npx vue-tsc --noEmit`, visual check

---

## Workstream D: Quality + Final Verification

### Task D1: Run Pint formatting
- [x] Run `vendor/bin/pint --dirty --format agent` on modified PHP files
- **Command**: `vendor/bin/pint --dirty --format agent`

### Task D2: Run full test suite + type checks
- [x] Run `php artisan test --compact`
- [x] Run `npx vue-tsc --noEmit`
- [x] Run `npm run build`
- **Expected**: all green

### Task D3: Visual QA checklist
- [x] Manual visual check across 8 themes × light/dark on key pages (Dashboard, Preferences, a dialog, sidebar)
- **Note**: not automatable — apply agent flags this for user
