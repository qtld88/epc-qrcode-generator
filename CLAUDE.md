# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Nextcloud app (v28–31) that generates EPC/SEPA QR codes. PHP backend + Vue 3 frontend.

## Commands

```bash
# Install all dependencies
make dev-setup          # npm ci + composer install

# Frontend
npm run dev             # dev build (once)
npm run watch           # dev build, watch mode
npm run build           # production build → js/
npm run lint            # eslint src/**/*.{vue,js}
npm run lint:fix        # eslint --fix

# PHP
composer run lint       # php -l lib/
composer run cs-fix     # php-cs-fixer fix

# Combined
make lint               # npm run lint + composer run lint
make clean              # rm node_modules, js/*, vendor
```

No test suite beyond lint (`make test` = `make lint`).

## Architecture

### Frontend (`src/`)

Vue 3 SPA, mounted on `#app` via `src/main.js`. Stack: Vue 3 + Pinia + vue-router + `@nextcloud/vue` components.

**Data flow:**
```
QrForm.vue (user input)
  → src/lib/epcGenerator.js   — builds EPC/BCD string (pure, no deps)
  → src/lib/ibanValidator.js  — modulo-97 IBAN check (pure)
  → src/services/QRService.js — wraps qr-code-styling; handles logo processing + canvas export
  → Pinia stores              — persist to PHP REST API
```

**Stores (`src/stores/`):**
- `history.js` — CRUD for generation history; also handles localStorage→DB migration via `importFromLocalStorage()`
- `presets.js` — CRUD for style presets

**`src/utils/migration.js`** — one-time migration path from the old localStorage-only version to the DB-backed version. Called on app boot if `localStorage.epcQrHistory` or `epcQrPresets` exist.

**Router:** Two routes — `generator` (`/`) → `GeneratorView.vue`, `history` (`/history`) → `HistoryView.vue`. Uses `createWebHistory` with Nextcloud base URL.

**Build:** `webpack.config.js` extends `@nextcloud/webpack-vue-config`; single entry `src/main.js` → `js/epc_qrcode_generator-main.js`.

### Backend (`lib/`)

PHP 8.1, namespace `OCA\EPCQRCodeGenerator`, PSR-4 from `lib/`.

**REST API (from `appinfo/routes.php`):**
| Route | Controller |
|-------|-----------|
| `GET/POST/DELETE /history[/{id}]` | `HistoryController` |
| `GET/POST/PUT/DELETE /presets[/{id}]` | `PresetController` |
| `POST /export/save` | `ExportController` |
| `GET /` | `PageController` (renders `templates/main.php`) |

**DB layer:** Mapper pattern — `HistoryMapper` / `PresetMapper` extend Nextcloud's ORM. Entities: `History`, `Preset`. DB migrations in `lib/Migration/` (versioned by date).

**`ExportService`** — saves QR PNG to user's Nextcloud Files.

All controllers use `#[NoCSRFRequired]` + `#[PublicPage]` but manually check `IUserSession` and return 401 if unauthenticated.

### Localization

Translation files in `l10n/` (en, fr, de, es, it, nl). Use `t('epc_qrcode_generator', 'string')` in Vue (registered as `app.config.globalProperties.t`).

### EPC QR format

`EPCGenerator.generate()` produces a newline-delimited string per the EPC/GIR standard:
```
BCD\n002\n1\nSCT\n\n<beneficiary>\n<IBAN>\n<amount> EUR\n\n<remittance>\n
```
Amount field format: `"EUR12.34"` → stored as `"12.34 EUR"` (the generator normalizes this).
