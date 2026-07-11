# Caja Diaria

Personal expense tracker and financial projection app. Replaces the
`proyeccion-2026.xlsx` Google Sheets workflow with a local-first Laravel app
that records daily movements, projects future balances, tracks budgets per
category, and reconciles account snapshots.

Single-user, single-currency (PEN), SQLite, local-only.

## Quick start

Requirements: PHP 8.3+, Node 22+, npm.

```bash
git clone <repo-url> caja-diaria && cd caja-diaria
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run dev          # terminal 1 — Vite dev server
php artisan serve    # terminal 2 — Laravel dev server
```

Open http://localhost:8000, register an account, and start tracking.

## Features

| Section | What it does |
|---------|-------------|
| Dashboard | Monthly summary cards (balance, income, expense, end-of-month projection), budget overview, reconciliation status, upcoming 7-day projections, balance chart |
| Movimientos | CRUD for income/expense movements with running balance per row, month navigation, real vs. projected split, drag-to-reorder, keyboard shortcuts (`N` = new, `←/→` = change month) |
| Categorías | CRUD categories with kind (expense/income/transfer), monthly limit, color, balance (income − expenses), budget progress bars |
| Cuentas | CRUD account snapshots (bank/wallet/cash/credit), reconciliation panel (sum of accounts vs. real balance), Liquidación exclusion |
| Recurrentes | CRUD recurring transaction templates (name, amount, category, day of month, start/end month) that generate projected movements via `app:generate-projections` |
| Proyección | Timeline of all movements (real + projected + recurring) with running balance into the future |
| Preferences | 8 tweakcn design-system themes (light/dark), profile photo, table density, start section, week start day, projection horizon (1–24 months) |

## Tech stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.3 |
| Auth | Laravel Fortify |
| Frontend | Inertia 3, Vue 3 (Composition API + TypeScript) |
| Styling | Tailwind CSS v4, shadcn-vue, Reka UI |
| Build | Vite |
| Database | SQLite (local), MySQL-ready for production |
| Testing | Pest v4, PHPUnit 12 |
| Locale | es_PE, timezone America/Lima |

## Keyboard shortcuts

| Key | Action | Page |
|-----|--------|------|
| `N` | Open new-movement dialog | Movimientos |
| `←` | Previous month | Movimientos, Categorías, Dashboard |
| `→` | Next month | Movimientos, Categorías, Dashboard |

Shortcuts are suppressed while typing in inputs or when a dialog is open.

## Testing

```bash
php artisan test --compact                    # full suite
php artisan test --compact --filter=Movement  # filter by name
```

Test suite: 242 tests covering models, scopes, CRUD flows, edge cases
(running balance recalculation after delete, prior-month opening balance,
category cascade to null), projections, dashboard, settings, and auth.

## Useful commands

```bash
php artisan app:generate-projections         # generate projected movements from active templates
php artisan tinker                           # REPL in app context
php artisan route:list --except-vendor       # list all routes
npm run dev                                  # Vite dev server (HMR + SSR)
npm run build                                # production build
npm run types:check                          # vue-tsc type checking
vendor/bin/pint --format agent               # PHP formatting
```

## Project structure

```
app/
  Console/Commands/         Artisan commands (generate-projections)
  Http/Controllers/         Resource controllers + settings
  Http/Requests/            Form requests (validation)
  Http/Responses/           Fortify login response (start_section redirect)
  Models/                   Eloquent models with scopes
database/
  migrations/               Schema migrations
  factories/                Model factories for testing
docs/
  plan-de-trabajo.md        Phase-by-phase implementation plan
  analisis-sistema-actual.md  How the original spreadsheet works
resources/js/
  pages/                    Inertia Vue pages (Dashboard, Movimientos, etc.)
  components/               Reusable Vue + shadcn-vue components
  composables/              useAppearance, useSettings, useKeyboardShortcuts
  css/                      Tailwind + theme CSS (8 tweakcn palettes)
routes/
  web.php                   App routes
  settings.php              Settings routes
```

## Documentation

See `docs/` for the full design and analysis:

- `docs/plan-de-trabajo.md` — architecture decisions, data model, 10-phase plan
- `docs/analisis-sistema-actual.md` — how the original spreadsheet works
- `DEPLOY.md` — notes for future shared-hosting deployment

## License

Private project for personal use.