# Design: Theme Tweakcn Design-System Selector

## Overview

Upgrades the 8-color-palette selector into a full tweakcn design-system selector. Each theme swaps colors + radius + shadows + spacing + tracking via scoped `.theme-xxx` CSS blocks. The app's `@theme inline` block acts as a bridge so Tailwind utilities consume per-theme tokens at runtime.

## Architecture

### CSS Architecture

```
app.css
├── @import 'tailwindcss'
├── @import './themes.css'          ← NEW
├── @theme inline                   ← CHANGED: add shadow/tracking/spacing bridges, fix --sidebar
│   ├── --color-*: var(--*)         (existing — colors)
│   ├── --radius-*: calc(...)       (existing — radius)
│   ├── --shadow-*: var(--*)        ← NEW (8 composite shadows)
│   ├── --tracking-*: var(--*)      ← NEW (6 tracking tokens)
│   └── --spacing: var(--spacing)   ← NEW
├── :root { ... }                   (base light tokens — UNCHANGED)
├── .dark { ... }                   (base dark tokens — UNCHANGED)
└── @layer base { ... }             (border/body defaults — UNCHANGED)

themes.css                          ← NEW ~960 lines
├── .theme-bold-tech { ...light tokens... }
├── .theme-bold-tech.dark { ...dark tokens... }
├── .theme-claude { ... }
├── .theme-claude.dark { ... }
├── ... (8 themes × 2 blocks = 16 blocks)
```

When `.theme-bold-tech` is on `<html>`, its CSS custom properties override `:root` values. The `@theme inline` bridge mappings (`--shadow-sm: var(--shadow-sm)`) tell Tailwind to resolve at runtime, so utilities like `shadow-sm`, `tracking-normal`, `rounded-lg` all pick up the theme's values.

### @theme inline Bridge Token Mappings

**Line 54 fix** (sidebar):
```css
/* BEFORE */ --color-sidebar: var(--sidebar-background);
/* AFTER  */ --color-sidebar: var(--sidebar);
```
Rationale: tweakcn themes define `--sidebar`, not `--sidebar-background`. Both vars exist in `:root`/`.dark` with identical values, so non-themed behavior is unchanged.

**New bridge tokens to add after line 61** (after `--color-sidebar-ring`):

```css
/* Shadow bridge — composite tokens consumed by shadow-* utilities */
--shadow-2xs: var(--shadow-2xs);
--shadow-xs: var(--shadow-xs);
--shadow-sm: var(--shadow-sm);
--shadow: var(--shadow);
--shadow-md: var(--shadow-md);
--shadow-lg: var(--shadow-lg);
--shadow-xl: var(--shadow-xl);
--shadow-2xl: var(--shadow-2xl);

/* Tracking bridge — consumed by tracking-* utilities */
--tracking-tighter: calc(var(--tracking-normal) - 0.05em);
--tracking-tight: calc(var(--tracking-normal) - 0.025em);
--tracking-normal: var(--tracking-normal);
--tracking-wide: calc(var(--tracking-normal) + 0.025em);
--tracking-wider: calc(var(--tracking-normal) + 0.05em);
--tracking-widest: calc(var(--tracking-normal) + 0.1em);

/* Spacing bridge — consumed by spacing utilities */
--spacing: var(--spacing);
```

**NOT registered as bridge tokens** (intermediate vars, not consumed by utilities):
- `--shadow-x`, `--shadow-y`, `--shadow-blur`, `--shadow-spread`, `--shadow-opacity`, `--shadow-color`
- These exist in theme blocks for reference/composition but have no Tailwind utility.

**Existing bridge tokens** (already present, no change needed):
- All `--color-*` mappings (lines 20–52)
- `--radius-lg/md/sm` (lines 16–18)

**Font bridge tokens to REMOVE** (lines 11–14 in `@theme inline`):
```css
/* REMOVE — app keeps Instrument Sans via @layer utilities, not theme */
--font-sans: Instrument Sans, ...;
```
The font declaration in `@layer utilities` (lines 82–90) already sets `--font-sans` on `body, html`. Removing from `@theme inline` prevents theme font overrides (we strip `--font-*` from themes).

**Also add `--radius-xl`** (not currently in app.css):
```css
--radius-xl: calc(var(--radius) + 4px);
```

### Theme Block Structure

Each `.theme-xxx` block contains ALL tokens from the source theme EXCEPT `--font-*`. Example using `bold-tech`:

```css
/* === bold-tech === */
.theme-bold-tech {
  /* Colors (19 tokens) */
  --background: oklch(1.0000 0 0);
  --foreground: oklch(0.3588 0.1354 278.6973);
  --card: oklch(1.0000 0 0);
  --card-foreground: oklch(0.3588 0.1354 278.6973);
  --popover: oklch(1.0000 0 0);
  --popover-foreground: oklch(0.3588 0.1354 278.6973);
  --primary: oklch(0.6056 0.2189 292.7172);
  --primary-foreground: oklch(1.0000 0 0);
  --secondary: oklch(0.9618 0.0202 295.1913);
  --secondary-foreground: oklch(0.4568 0.2146 277.0229);
  --muted: oklch(0.9691 0.0161 293.7558);
  --muted-foreground: oklch(0.5413 0.2466 293.0090);
  --accent: oklch(0.9319 0.0316 255.5855);
  --accent-foreground: oklch(0.4244 0.1809 265.6377);
  --destructive: oklch(0.6368 0.2078 25.3313);
  --destructive-foreground: oklch(1.0000 0 0);
  --border: oklch(0.9299 0.0334 272.7879);
  --input: oklch(0.9299 0.0334 272.7879);
  --ring: oklch(0.6056 0.2189 292.7172);
  /* Chart (5 tokens) */
  --chart-1: oklch(0.6056 0.2189 292.7172);
  --chart-2: oklch(0.5413 0.2466 293.0090);
  --chart-3: oklch(0.4907 0.2412 292.5809);
  --chart-4: oklch(0.4320 0.2106 292.7591);
  --chart-5: oklch(0.3796 0.1783 293.7446);
  /* Sidebar (8 tokens) */
  --sidebar: oklch(0.9691 0.0161 293.7558);
  --sidebar-foreground: oklch(0.3588 0.1354 278.6973);
  --sidebar-primary: oklch(0.6056 0.2189 292.7172);
  --sidebar-primary-foreground: oklch(1.0000 0 0);
  --sidebar-accent: oklch(0.9319 0.0316 255.5855);
  --sidebar-accent-foreground: oklch(0.4244 0.1809 265.6377);
  --sidebar-border: oklch(0.9299 0.0334 272.7879);
  --sidebar-ring: oklch(0.6056 0.2189 292.7172);
  /* Radius (1 token) */
  --radius: 0.625rem;
  /* Shadows (8 composite + 6 intermediate) */
  --shadow-x: 2px;
  --shadow-y: 2px;
  --shadow-blur: 4px;
  --shadow-spread: 0px;
  --shadow-opacity: 0.2;
  --shadow-color: hsl(255 86% 66%);
  --shadow-2xs: 2px 2px 4px 0px hsl(255 86% 66% / 0.10);
  --shadow-xs: 2px 2px 4px 0px hsl(255 86% 66% / 0.10);
  --shadow-sm: 2px 2px 4px 0px hsl(255 86% 66% / 0.20), 2px 1px 2px -1px hsl(255 86% 66% / 0.20);
  --shadow: 2px 2px 4px 0px hsl(255 86% 66% / 0.20), 2px 1px 2px -1px hsl(255 86% 66% / 0.20);
  --shadow-md: 2px 2px 4px 0px hsl(255 86% 66% / 0.20), 2px 2px 4px -1px hsl(255 86% 66% / 0.20);
  --shadow-lg: 2px 2px 4px 0px hsl(255 86% 66% / 0.20), 2px 4px 6px -1px hsl(255 86% 66% / 0.20);
  --shadow-xl: 2px 2px 4px 0px hsl(255 86% 66% / 0.20), 2px 8px 10px -1px hsl(255 86% 66% / 0.20);
  --shadow-2xl: 2px 2px 4px 0px hsl(255 86% 66% / 0.50);
  /* Spacing */
  --spacing: 0.25rem;
  /* Tracking */
  --tracking-normal: 0em;
  /* NOTE: --font-sans, --font-serif, --font-mono STRIPPED */
}

.theme-bold-tech.dark {
  /* Same token set, dark values from .dark block */
  --background: oklch(0.2077 0.0398 265.7549);
  --foreground: oklch(0.9299 0.0334 272.7879);
  /* ... (same 44 tokens, dark values) ... */
}
```

**Token count per theme block**: ~44 tokens (19 colors + 5 chart + 8 sidebar + 1 radius + 14 shadow + 1 tracking + 1 spacing). Two blocks per theme (light + dark) = ~88 lines per theme, ~704 total + comments ≈ ~960 lines.

### themes.css File Layout

```css
/*
 * Design-system themes — sourced from resources/themes/*.css
 * Each block is a scoped extraction: :root → .theme-xxx, .dark → .theme-xxx.dark
 * Font tokens (--font-sans/serif/mono) stripped; app keeps Instrument Sans.
 * Source-of-truth: resources/themes/*.css (tweakcn originals)
 */

/* === bold-tech === */
.theme-bold-tech { ... }
.theme-bold-tech.dark { ... }

/* === claude === */
.theme-claude { ... }
.theme-claude.dark { ... }

/* === default === */
.theme-default { ... }
.theme-default.dark { ... }

/* === pastel-dreams === */
.theme-pastel-dreams { ... }
.theme-pastel-dreams.dark { ... }

/* === quantum-rose === */
.theme-quantum-rose { ... }
.theme-quantum-rose.dark { ... }

/* === sunny-sprout === */
.theme-sunny-sprout { ... }
.theme-sunny-sprout.dark { ... }

/* === twitter === */
.theme-twitter { ... }
.theme-twitter.dark { ... }

/* === violet-bloom === */
.theme-violet-bloom { ... }
.theme-violet-bloom.dark { ... }
```

### Extraction Algorithm

For each `resources/themes/{name}.css`:

1. **Read `:root { ... }`** block → extract ALL CSS custom property declarations
2. **Exclude** `--font-sans`, `--font-serif`, `--font-mono` (stripped; app keeps Instrument Sans)
3. **Include** all other tokens: colors, chart, sidebar, radius, shadow (composite + intermediate), spacing, tracking-normal
4. **Prefix** with `.theme-{name} { ... }`
5. **Read `.dark { ... }`** block → extract same token set
6. **Prefix** with `.theme-{name}.dark { ... }`
7. **Skip entirely**: `@import`, `@custom-variant`, `@theme inline`, `@layer base`, `body { letter-spacing: ... }` (the `letter-spacing` application is handled by the tracking bridge in `app.css`)
8. **Keep intermediate shadow vars** (`--shadow-x/y/blur/spread/opacity/color`) — they're referenced by composite values and useful for debugging
9. **Preserve `--tracking-normal`** from all themes (even if `0em` — it's the base for derived tracking tokens in the bridge)

**Themes with extra tracking tokens** (sunny-sprout, violet-bloom): These source files define `--tracking-tighter/tight/wide/wider/widest` in their `@theme inline` blocks. These are DERIVED from `--tracking-normal` via the bridge in `app.css`, so they do NOT need to be in the theme blocks. Only `--tracking-normal` goes in the theme block.

## Component Design

### useAppearance.ts changes

```typescript
// NEW: valid theme keys constant
const VALID_THEMES = new Set([
    'default', 'bold-tech', 'claude', 'pastel-dreams',
    'quantum-rose', 'sunny-sprout', 'twitter', 'violet-bloom',
]);

// Line 18: change default
export const themeKey: Ref<string> = ref('default');

// initializeTheme(): add fallback after reading pageTheme
export function initializeTheme(): void {
    // ... (existing code up to line 163) ...

    // FALLBACK: unknown key → 'default'
    if (!VALID_THEMES.has(initialTheme)) {
        initialTheme = 'default';
    }

    themeKey.value = initialTheme;
    applyPalette(initialTheme);
}
```

### useSettings.ts changes

```typescript
// Line 10: update type union
export interface UserSettings {
    theme: 'default' | 'bold-tech' | 'claude' | 'pastel-dreams' | 'quantum-rose' | 'sunny-sprout' | 'twitter' | 'violet-bloom';
    // ... rest unchanged
}

// Line 18: update default
const defaults: UserSettings = {
    theme: 'default',
    // ... rest unchanged
};
```

### Preferences.vue changes

```typescript
// Replace palettes array (lines 40-49)
const palettes: { key: string; label: string }[] = [
    { key: 'default', label: 'Default' },
    { key: 'bold-tech', label: 'Bold Tech' },
    { key: 'claude', label: 'Claude' },
    { key: 'pastel-dreams', label: 'Pastel Dreams' },
    { key: 'quantum-rose', label: 'Quantum Rose' },
    { key: 'sunny-sprout', label: 'Sunny Sprout' },
    { key: 'twitter', label: 'Twitter' },
    { key: 'violet-bloom', label: 'Violet Bloom' },
];
```

**Card title** (line 212): `"Paleta de colores"` → `"Diseño"`
**Card description** (line 213-215): `"Elige el color principal..."` → `"Elige el tema de diseño de la aplicación"`

**Spanish label rationale**: Theme names are proper names (tweakcn brand names), not common nouns. Keeping them in English is standard UX practice (like "Dark Mode" in Spanish UIs). Hispanicizing "Bold Tech" → "Tecnología Audaz" would be confusing. "Default" is universally understood. The card title/description ARE in Spanish per project convention.

### UpdateSettingsRequest.php changes

```php
// Line 26: update validation rule
'theme' => ['nullable', 'in:default,bold-tech,claude,pastel-dreams,quantum-rose,sunny-sprout,twitter,violet-bloom'],
```

### Migration

**Class**: `database/migrations/{timestamp}_remigrate_theme_keys_to_default.php`

```php
return new class extends Migration
{
    public function up(): void
    {
        $legacyKeys = ['slate', 'rose', 'blue', 'green', 'amber', 'violet', 'teal', 'red'];

        DB::table('users')
            ->whereNotNull('settings')
            ->whereIn('settings->theme', $legacyKeys)
            ->update(['settings' => DB::raw(
                "JSON_SET(settings, '$.theme', 'default')"
            )]);
    }

    public function down(): void
    {
        // No-op: old keys are intentionally invalidated.
        // Restore manually from backup if needed.
    }
};
```

**Edge cases handled**:
- `settings` is NULL → excluded by `whereNotNull`
- `settings->theme` key absent → `whereIn` returns NULL → excluded
- Theme already valid (new key) → not in `$legacyKeys` → excluded
- Theme already `'default'` → not in `$legacyKeys` → excluded (idempotent)
- Re-run safe → all legacy keys already migrated → 0 rows affected

**Eloquent vs raw SQL**: Uses `DB::table()` + `DB::raw()` because Eloquent would require loading all users into memory. The JSON_SET approach is a single UPDATE statement.

**Deployment order**: Migration runs BEFORE the `UpdateSettingsRequest.php` validation change. This ensures no user hits the new validation with an old key.

### Test Updates

```php
// Valid dataset (line 22-27): change 'rose' → 'default', add all-8-keys test
->with([
    'theme' => ['theme', 'default'],      // was 'rose'
    'density' => ['density', 'compact'],
    'start_section' => ['start_section', 'movements'],
    'projection_horizon' => ['projection_horizon', 6],
]);

// Invalid dataset (line 35-42): add legacy key
->with([
    'theme pink' => ['theme', 'pink'],
    'theme slate (legacy)' => ['theme', 'slate'],   // NEW
    'density cozy' => ['density', 'cozy'],
    // ... rest unchanged
]);

// New test: all 8 keys accepted
test('settings update accepts all 8 design-system theme keys', function (string $key) {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->put(route('settings.update'), ['theme' => $key])
        ->assertNoContent();
    $user->refresh();
    expect($user->settings['theme'])->toBe($key);
})->with([
    'default' => 'default',
    'bold-tech' => 'bold-tech',
    'claude' => 'claude',
    'pastel-dreams' => 'pastel-dreams',
    'quantum-rose' => 'quantum-rose',
    'sunny-sprout' => 'sunny-sprout',
    'twitter' => 'twitter',
    'violet-bloom' => 'violet-bloom',
]);

// Update references to 'rose' in other tests (lines 49, 56, 64) → 'claude'
```

## Decisions

| Decision | Choice | Alternatives | Rationale |
|----------|--------|-------------|-----------|
| Spanish labels | English proper names, Spanish card title | Hispanicized names | Theme names are brand names; "Bold Tech" → "Tecnología Audaz" is confusing |
| Extraction | Manual copy-paste per theme | Scripted extraction | 8 themes is manageable; script adds maintenance burden for one-time operation |
| resources/themes/*.css | Keep as source-of-truth | Delete after extraction | Header comment in themes.css points back; useful for regeneration |
| Shadow bridge | Explicit `@theme inline` mappings | Rely on Tailwind auto-registration | Tailwind v4 auto-registers shadows with hardcoded values, not `var()` references; explicit bridge is required for per-theme override |
| Tracking bridge | All 6 derived tokens in app.css | Per-theme bridge blocks | Single bridge in `@theme inline` is cleaner; derived tokens use `calc()` from `--tracking-normal` |
| Font bridge | Remove from `@theme inline` | Keep and let themes override | Themes strip `--font-*`; removing bridge prevents accidental override from residual theme font tokens |
| Migration SQL | `DB::table()->whereIn()->update()` | Eloquent loop | Single UPDATE statement; no N+1; handles all rows atomically |
| Deployment order | Migration BEFORE validation change | Simultaneous | Prevents 422 for users with stale keys during deployment window |

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|-----------|
| Visual blast radius per theme | Tables/dialogs/sidebar look markedly different | Visual QA across all 8 themes in light/dark after implementation |
| Stale client sends old key → 422 | User gets error on settings save | Frontend `initializeTheme()` fallback to `'default'`; migration remaps DB |
| CSS volume ~960 lines | Hard to review in single PR | Split into chained PRs (backend + CSS + UI) |
| Shadow bridge circular reference | Potential CSS infinite loop | Not actually circular: `@theme inline` registers Tailwind tokens, `var(--shadow-sm)` resolves to CSS custom property at runtime — different namespaces |
| Stripped fonts lose visual distinction | Bold Tech without Roboto looks less bold | Acceptable v1 tradeoff; font loading is a future phase |

## Out of Scope

- Per-theme font loading (Roboto, Playfair, etc.) — future phase
- Changes to `app/Models/User` — `settings` JSON column already exists
- Changes to shadcn/ui components — they inherit tokens automatically
- `BalanceLineChart.vue` — already oklch-safe (resolves to rgb via `getComputedStyle`)
- Layout rewrites
