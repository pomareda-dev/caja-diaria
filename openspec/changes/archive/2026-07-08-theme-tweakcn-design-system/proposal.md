# Change: Theme Tweakcn Design-System Selector

## Why
The current palette selector only swaps four tokens (`--primary`, `--ring`, `--accent`, `--sidebar-primary`) across 8 simple `.theme-*` blocks, so every "theme" looks identical except hue. Users want a real **design-system** selector where each theme feels distinct — swapping colors **plus** radius, shadows, spacing, and letter tracking. This change upgrades the selector to 8 tweakcn themes (bold-tech, claude, default, pastel-dreams, quantum-rose, sunny-sprout, twitter, violet-bloom), each carrying a full design-token set rather than a single hue rotation.

## What Changes

**CSS**
- New `resources/css/themes.css` with 8 scoped blocks `.theme-xxx { …light… }` / `.theme-xxx.dark { …dark… }`, stripped of `@import`, `@theme inline`, `@layer base`, and `--font-*` tokens.
- `app.css`: remove the 8 old color-only `.theme-*` blocks; add `@import './themes.css'`; fix `@theme inline` line 54 `--color-sidebar: var(--sidebar)` (currently `var(--sidebar-background)`).
- Keep `resources/themes/*.css` as downloaded source-of-truth; a header comment in `themes.css` points back to them.

**Frontend JS**
- `useAppearance.ts`: default `themeKey` and `initializeTheme()` fallback → `'default'`; add unknown-key fallback in `initializeTheme()` so stale browser state maps to `'default'`.
- `Preferences.vue`: new `palettes[]` with 8 entries (key + Spanish label); rename card title/description from "Paleta de colores" to "Diseño"/"Tema".

**Backend**
- `UpdateSettingsRequest.php`: `in:default,bold-tech,claude,pastel-dreams,quantum-rose,sunny-sprout,twitter,violet-bloom`.
- New migration remaps old keys (`slate,rose,blue,green,amber,violet,teal,red`) → `'default'`. Old keys become INVALID; no aliases.

**Tests**
- `PreferencesTest.php`: valid dataset → `'default'`/`'claude'`; invalid dataset → `'pink'` still invalid. Assert old keys (`'slate'`) are rejected post-migration.

## Scope
### In Scope
- 8 scoped tweakcn theme blocks (colors + radius + shadows + spacing + tracking; NO fonts in v1)
- Backend validation enum + DB migration of old keys → `default`
- Frontend defaults + UI labels (Spanish)
- Test updates for the new enum

### Out of Scope
- Per-theme font loading (Roboto, Playfair, etc.) — future phase; `--font-*` stripped, app keeps `Instrument Sans`
- `app/Models/` changes — existing `settings` JSON column already stores `theme`
- Layout/shadcn component rewrites — they inherit tokens automatically
- `BalanceLineChart.vue` — already safe with oklch (resolves to rgb)

## Impact
| File | Rationale |
|------|-----------|
| `resources/css/themes.css` (NEW ~960 lines) | 8 scoped theme blocks |
| `resources/css/app.css` | Wiring + `--sidebar` token fix + remove old blocks |
| `useAppearance.ts`, `useSettings.ts` | Defaults → `'default'`, unknown-key fallback |
| `Preferences.vue` | 8-entry palette array + Spanish labels |
| `UpdateSettingsRequest.php` | New `in:` enum |
| `database/migrations/xxxx_remigrate_theme_keys_to_default.php` | Old keys → `default` |
| `PreferencesTest.php` | Updated valid/invalid datasets |

**Token model**: Option A — full design-system swap (colors, radius, shadows, spacing, tracking). `--spacing` coexists with the existing density utility-class feature (independent). `--sidebar` aligns with tweakcn canonical token name; both `--sidebar` and `--sidebar-background` exist in `:root`/`.dark` so behavior is unchanged for non-themed state.

## Approach
Extract each `resources/themes/*.css` into a scoped `.theme-xxx` / `.theme-xxx.dark` pair, dropping `@import`, `@theme inline`, `@layer base`, and all `--font-*` lines. Consolidate into one `themes.css` imported by `app.css`. Update the backend enum and add a one-shot migration that remaps the 8 legacy keys to `'default'`. Update composables and the preferences UI with Spanish labels. Tests follow strict TDD: update `PreferencesTest.php` datasets first, watch them fail, then implement.

## Risks
- **Visual blast radius**: radius/shadow/spacing shifts make tables, dialogs, sidebar look markedly different per theme — requires visual QA across each of the 8 themes in light/dark.
- **Migration irreversibility**: old keys become INVALID post-migration; any user with stale client cache sends an old key → 422. Mitigated by frontend fallback to `'default'` + migration to `default`.
- **CSS volume ~960 lines**: triggers chained-PR protocol at sdd-tasks (review budget 400 lines); split into backend/validation, CSS extraction, and UI PRs.
- **`--sidebar` token rename**: single-line blast radius; both base vars already equal, so non-themed behavior is identical.
- **Stripped fonts**: themes that visually relied on a contrast font (e.g. bold-tech) lose that distinction in v1 — acceptable tradeoff to avoid silent fallback to system fonts.

## Rollback Plan
1. Revert `themes.css` (delete file) and `app.css` `@import` removal — old `.theme-*` color blocks return.
2. Revert `UpdateSettingsRequest.php` enum to old keys; revert `useAppearance.ts`/`useSettings.ts` default to `'slate'`.
3. Revert `Preferences.vue` palette array and Spanish labels.
4. The migration remaps old keys → `default`; a compensating migration is unnecessary since column already stores JSON — restore old keys manually only if user data recovery is required (document key list).

## Success Criteria
- [ ] All 8 themes apply distinct colors + radius + shadows + spacing + tracking in light and dark mode.
- [ ] `php artisan test --compact` passes with updated `PreferencesTest.php` datasets.
- [ ] Existing users with old theme keys load `'default'` after migration with no 422.
- [ ] New users default to `'default'`.
- [ ] `npm run build` succeeds; no Tailwind duplicate-`@import` build error.

## Dependencies
- 8 tweakcn theme CSS files already present in `resources/themes/`.
- `laravel-vite-plugin/fonts` already loads `Instrument Sans` (unchanged).

## Capabilities

### New Capabilities
- `design-system-themes`: Full design-system theme selector (8 tweakcn themes swapping colors, radius, shadows, spacing, tracking) with backend validation, theme-key migration, and Spanish-labeled preferences UI.

### Modified Capabilities
_None — no existing specs in `openspec/specs/`._

## Open Questions
_None — all product decisions locked by the user answer round (theme count, token scope, migration strategy, default theme)._