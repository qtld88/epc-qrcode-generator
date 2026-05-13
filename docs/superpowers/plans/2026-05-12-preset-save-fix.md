# Preset Save Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix HTTP 500 on preset save caused by logo base64 data URL exceeding the 65535-byte MySQL TEXT column limit for `logo_file`.

**Architecture:** Two-layer fix — (1) compress logo to JPEG ≤256px before preset storage so data always fits within TEXT column, (2) force-alter the column to LONGBLOB via direct SQL so future large values never hit this again regardless of compression. The migration system is bypassed because `occ upgrade` refuses to fire when the app version in the NC DB already matches info.xml (1.0.4).

**Tech Stack:** Vue 3, PHP 8.3, Nextcloud 31, MariaDB, Podman containers.

---

## File Map

| File | Change |
|---|---|
| `src/services/QRService.js` | `compressLogoForStorage()` — resize + JPEG-encode logo before preset save |
| `src/views/GeneratorView.vue` | `onSavePreset()` — call compress before store |
| `src/main.js` | BUILD_MARKER bump |
| DB: `oc_epc_qr_presets.logo_file` | ALTER column TEXT → LONGBLOB via direct SQL |
| `lib/Migration/Version1003Date20260512000003.php` | Already exists, records migration after manual SQL |

---

### Task 1: Verify the frontend compression code is correct

**Files:**
- Read: `src/services/QRService.js` (compressLogoForStorage method)
- Read: `src/views/GeneratorView.vue` (onSavePreset method)

- [ ] **Step 1: Confirm `compressLogoForStorage` exists and is correct**

Open `src/services/QRService.js` and verify this method exists:

```js
compressLogoForStorage(dataUrl, maxDim = 256) {
    if (!dataUrl) return Promise.resolve(null)
    return new Promise((resolve) => {
        const img = new Image()
        img.onload = () => {
            const scale = Math.min(maxDim / img.width, maxDim / img.height, 1)
            const w = Math.round(img.width * scale)
            const h = Math.round(img.height * scale)
            const canvas = document.createElement('canvas')
            canvas.width = w
            canvas.height = h
            canvas.getContext('2d').drawImage(img, 0, 0, w, h)
            resolve(canvas.toDataURL('image/jpeg', 0.82))
        }
        img.onerror = () => resolve(null)
        img.src = dataUrl
    })
}
```

If missing, add it before `createQRCode()`.

- [ ] **Step 2: Confirm `onSavePreset` in `GeneratorView.vue` calls compress**

Verify this exact pattern exists at line ~181:

```js
async onSavePreset(name) {
    try {
        const logoFile = await this.qrService.compressLogoForStorage(this.qrService.logoDataUrl)
        await this.presetsStore.savePreset(name, {
            styleOptions: this.styleOptions,
            logoFile,
        })
```

If it still passes `this.qrService.logoDataUrl` directly, fix it to the pattern above.

- [ ] **Step 3: Confirm BUILD_MARKER is v16 or higher in `src/main.js`**

```js
const BUILD_MARKER = 'epc-qrcode-generator:frontend-build-2026-05-12-v16-logo-compress'
```

If not, bump it.

---

### Task 2: Build and deploy frontend

**Files:**
- Built output: `js/epc_qrcode_generator-main.js`

- [ ] **Step 1: Run production build**

```bash
cd /Users/Shared/SHELF/PROJECTS/SANDBOX/epc-qrcode-generator
npm run build
```

Expected: no errors, `js/epc_qrcode_generator-main.js` updated.

- [ ] **Step 2: Rsync ALL files to server (PHP + JS)**

```bash
# Adjust user@host and paths to match your rsync setup
rsync -av --delete \
  /Users/Shared/SHELF/PROJECTS/SANDBOX/epc-qrcode-generator/ \
  user@shelf.hopto.org:/path/to/custom_apps/epc_qrcode_generator/ \
  --exclude=node_modules --exclude=.git
```

- [ ] **Step 3: Verify v16 build loaded in browser**

Open browser console on the app page. Confirm:
```
[EPC QR] epc-qrcode-generator:frontend-build-2026-05-12-v16-logo-compress
```

---

### Task 3: Fix the database column directly

The `occ upgrade` system won't fire because Nextcloud already records the app at v1.0.4. Bypass it with direct SQL.

**Files:**
- DB column: `oc_epc_qr_presets.logo_file`
- `lib/Migration/Version1003Date20260512000003.php` (mark as applied after)

- [ ] **Step 1: Get DB credentials from Nextcloud config**

```bash
sudo podman exec nextcloud grep -E 'dbtype|dbname|dbuser|dbpassword|dbhost' /var/www/html/config/config.php
```

Note the values. Typical output for MariaDB setup:
```
'dbtype' => 'mysql',
'dbname' => 'nextcloud',
'dbuser' => 'nextcloud',
'dbpassword' => 'somepassword',
'dbhost' => 'mariadb',   ← this is the container name or IP
```

- [ ] **Step 2: Find the MariaDB container**

```bash
sudo podman ps --format "{{.Names}}"
```

Look for a container named `mariadb`, `db`, or similar.

- [ ] **Step 3: Alter the column to LONGBLOB**

Replace `<dbuser>`, `<dbpassword>`, `<dbname>` with values from Step 1. Replace `<mariadb-container>` with the container name from Step 2:

```bash
sudo podman exec <mariadb-container> mysql \
  -u <dbuser> \
  -p<dbpassword> \
  <dbname> \
  -e "ALTER TABLE oc_epc_qr_presets MODIFY COLUMN logo_file LONGBLOB DEFAULT NULL;"
```

Expected: no output = success.

If MariaDB is inside the Nextcloud container (uncommon but possible):
```bash
sudo podman exec nextcloud mysql \
  -u <dbuser> \
  -p<dbpassword> \
  <dbname> \
  -e "ALTER TABLE oc_epc_qr_presets MODIFY COLUMN logo_file LONGBLOB DEFAULT NULL;"
```

- [ ] **Step 4: Verify column type changed**

```bash
sudo podman exec <mariadb-container> mysql \
  -u <dbuser> -p<dbpassword> <dbname> \
  -e "SHOW COLUMNS FROM oc_epc_qr_presets LIKE 'logo_file';"
```

Expected output shows `Type: longblob`.

- [ ] **Step 5: Record migration as applied so occ upgrade doesn't conflict later**

```bash
sudo podman exec <mariadb-container> mysql \
  -u <dbuser> -p<dbpassword> <dbname> \
  -e "INSERT IGNORE INTO oc_migrations (app, version) VALUES ('epc_qrcode_generator', 'Version1003Date20260512000003');"
```

Expected: no output = success.

---

### Task 4: End-to-end verification

- [ ] **Step 1: Test preset save without logo**

In the app: fill form → generate QR → do NOT upload logo → enter preset name → click Save.

Expected: "Saved!" notification, preset appears in dropdown.

- [ ] **Step 2: Test preset save with a small logo (< 50KB original file)**

Upload a small PNG logo → generate QR → save preset with a name.

Expected: "Saved!" notification, no 500 error in console.

- [ ] **Step 3: Test preset save with a large logo (> 100KB original file)**

Upload a large PNG/JPG logo → generate QR → save preset.

Expected: "Saved!" notification. Verify in browser Network tab that the POST body `logoFile` field is a JPEG data URL and its length is under 65535 characters.

- [ ] **Step 4: Test preset load**

Select the saved preset in dropdown → click Load.

Expected: style options restore, logo restores in QR preview, QR re-renders with logo.

- [ ] **Step 5: Test preset load restores compressed logo correctly**

After loading a preset with a logo: verify the logo still appears in the QR center and the shape/fit options apply correctly.

---

### Task 5: Cleanup (optional)

- [ ] **Step 1: Remove the Version1002 migration (it was always a no-op)**

`lib/Migration/Version1002Date20260512000002.php` does nothing (condition `$type !== 'text'` never fires since column was already text). Safe to delete if desired — but only if no clean-install scenario matters. Leave it if unsure.

- [ ] **Step 2: Verify lint passes**

```bash
npm run lint
composer run lint
```

Expected: no errors.
