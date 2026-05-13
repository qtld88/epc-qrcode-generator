# Save-to-Files Folder Picker — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the broken `getFilePickerBuilder` call with a custom in-app folder picker so "Save to Files" reliably opens a folder navigation dialog, prompts for a filename, and saves the QR PNG to the chosen Nextcloud folder.

**Architecture:** New `FolderPicker.vue` component (NcModal + NcBreadcrumbs + NcListItem) fetches folder listings from a new `FolderController` PHP endpoint (`GET /folders?path=`). `ExportActions.vue` shows the picker instead of calling `getFilePickerBuilder`. Backend `ExportController`/`ExportService` already work correctly — no changes needed there.

**Tech Stack:** Vue 3 (Options API, matching existing components), `@nextcloud/vue` (NcModal, NcBreadcrumbs, NcBreadcrumb, NcListItem, NcTextField, NcLoadingIcon, NcEmptyContent, NcButton), `@nextcloud/axios`, `@nextcloud/router` (`generateUrl`), PHP 8.1 OCP `IRootFolder`.

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `src/components/FolderPicker.vue` | Modal dialog: folder tree navigation + filename input |
| Modify | `src/components/ExportActions.vue` | Remove `getFilePickerBuilder`; add `<FolderPicker>` |
| Create | `lib/Controller/FolderController.php` | `GET /folders?path=` → JSON list of subdirs |
| Modify | `appinfo/routes.php` | Add `folder#listFolders` route |

---

## Task 1: PHP endpoint — list subfolders at path

**Files:**
- Create: `lib/Controller/FolderController.php`
- Modify: `appinfo/routes.php`

No test suite — verify manually via `curl` after deploy (lint-only project). Run `composer run lint` to catch syntax errors.

- [ ] **Step 1: Create `lib/Controller/FolderController.php`**

```php
<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;

class FolderController extends Controller {
    private IRootFolder $rootFolder;
    private IUserSession $userSession;

    public function __construct(
        IRequest $request,
        IRootFolder $rootFolder,
        IUserSession $userSession,
    ) {
        parent::__construct('epc_qrcode_generator', $request);
        $this->rootFolder = $rootFolder;
        $this->userSession = $userSession;
    }

    #[NoCSRFRequired]
    #[PublicPage]
    public function listFolders(string $path = '/'): JSONResponse {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }

        $userFolder = $this->rootFolder->getUserFolder($user->getUID());

        // Navigate to requested path
        $normalized = trim($path, '/');
        $current = $userFolder;
        if ($normalized !== '') {
            try {
                $node = $userFolder->get($normalized);
                if (!($node instanceof \OCP\Files\Folder)) {
                    return new JSONResponse(['error' => 'Not a folder'], 400);
                }
                $current = $node;
            } catch (\OCP\Files\NotFoundException) {
                return new JSONResponse(['error' => 'Folder not found'], 404);
            }
        }

        $folders = [];
        foreach ($current->getDirectoryListing() as $node) {
            if ($node instanceof \OCP\Files\Folder) {
                $folders[] = [
                    'name' => $node->getName(),
                    'path' => '/' . ltrim($node->getPath(), '/'),
                ];
            }
        }

        // Strip user-storage prefix so paths are relative to user root
        $prefix = $userFolder->getPath();
        $folders = array_map(function (array $f) use ($prefix): array {
            $f['path'] = '/' . ltrim(substr($f['path'], strlen($prefix)), '/');
            return $f;
        }, $folders);

        usort($folders, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return new JSONResponse(['folders' => $folders]);
    }
}
```

- [ ] **Step 2: Add route to `appinfo/routes.php`**

Open `appinfo/routes.php`. Add this line inside the `'routes'` array (after the last existing route, before the closing `]`):

```php
['name' => 'folder#listFolders', 'url' => '/folders', 'verb' => 'GET'],
```

Full file after edit:

```php
<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'history#index', 'url' => '/history', 'verb' => 'GET'],
		['name' => 'history#show', 'url' => '/history/{id}', 'verb' => 'GET'],
		['name' => 'history#create', 'url' => '/history', 'verb' => 'POST'],
		['name' => 'history#destroy', 'url' => '/history/{id}', 'verb' => 'DELETE'],
		['name' => 'preset#index', 'url' => '/presets', 'verb' => 'GET'],
		['name' => 'preset#show', 'url' => '/presets/{id}', 'verb' => 'GET'],
		['name' => 'preset#create', 'url' => '/presets', 'verb' => 'POST'],
		['name' => 'preset#update', 'url' => '/presets/{id}', 'verb' => 'PUT'],
		['name' => 'preset#destroy', 'url' => '/presets/{id}', 'verb' => 'DELETE'],
		['name' => 'export#saveToFiles', 'url' => '/export/save', 'verb' => 'POST'],
		['name' => 'folder#listFolders', 'url' => '/folders', 'verb' => 'GET'],
	],
];
```

- [ ] **Step 3: Lint PHP**

```bash
composer run lint
```

Expected: no errors. Fix any syntax issues before continuing.

- [ ] **Step 4: Commit**

```bash
git add lib/Controller/FolderController.php appinfo/routes.php
git commit -m "feat: add FolderController endpoint GET /folders?path="
```

---

## Task 2: `FolderPicker.vue` component

**Files:**
- Create: `src/components/FolderPicker.vue`

This component is self-contained. It opens as an NcModal, navigates folders, collects a filename, and emits `pick({ folder, filename })` or `close`.

- [ ] **Step 1: Create `src/components/FolderPicker.vue`**

```vue
<template>
	<NcModal
		v-if="show"
		:name="t('epc_qrcode_generator', 'Save to Files')"
		size="normal"
		@close="$emit('close')">
		<div class="folder-picker">
			<div class="folder-picker__breadcrumbs">
				<NcBreadcrumbs>
					<NcBreadcrumb
						:name="t('epc_qrcode_generator', 'Home')"
						:to="null"
						@click="navigateTo('/')" />
					<NcBreadcrumb
						v-for="(segment, index) in pathSegments"
						:key="index"
						:name="segment.name"
						:to="null"
						@click="navigateTo(segment.path)" />
				</NcBreadcrumbs>
			</div>

			<div class="folder-picker__list">
				<NcLoadingIcon v-if="loading" :size="32" />
				<NcEmptyContent
					v-else-if="!loading && folders.length === 0"
					:name="t('epc_qrcode_generator', 'No subfolders')"
					:description="t('epc_qrcode_generator', 'This folder has no subfolders. Save here or navigate up.')" />
				<ul v-else>
					<NcListItem
						v-for="folder in folders"
						:key="folder.path"
						:name="folder.name"
						:bold="false"
						@click="navigateTo(folder.path)">
						<template #icon>
							<span class="folder-icon">📁</span>
						</template>
					</NcListItem>
				</ul>
				<div v-if="error" class="folder-picker__error">{{ error }}</div>
			</div>

			<div class="folder-picker__filename">
				<NcTextField
					:value.sync="filename"
					:label="t('epc_qrcode_generator', 'Filename')"
					:placeholder="'qr-epc.png'" />
			</div>

			<div class="folder-picker__actions">
				<NcButton @click="$emit('close')">
					{{ t('epc_qrcode_generator', 'Cancel') }}
				</NcButton>
				<NcButton type="primary" :disabled="!resolvedFilename" @click="onSaveHere">
					{{ t('epc_qrcode_generator', 'Save here') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcBreadcrumbs from '@nextcloud/vue/components/NcBreadcrumbs'
import NcBreadcrumb from '@nextcloud/vue/components/NcBreadcrumb'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'

export default {
	name: 'FolderPicker',
	components: {
		NcModal,
		NcBreadcrumbs,
		NcBreadcrumb,
		NcListItem,
		NcLoadingIcon,
		NcEmptyContent,
		NcTextField,
		NcButton,
	},
	props: {
		show: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['close', 'pick'],
	data() {
		return {
			currentPath: '/',
			folders: [],
			loading: false,
			error: '',
			filename: 'qr-epc.png',
		}
	},
	computed: {
		pathSegments() {
			if (this.currentPath === '/') return []
			const parts = this.currentPath.replace(/^\//, '').split('/')
			const segments = []
			let accumulated = ''
			for (const part of parts) {
				accumulated += '/' + part
				segments.push({ name: part, path: accumulated })
			}
			return segments
		},
		resolvedFilename() {
			const name = this.filename.trim()
			if (!name) return ''
			return name.endsWith('.png') ? name : name + '.png'
		},
	},
	watch: {
		show(val) {
			if (val) {
				this.currentPath = '/'
				this.filename = 'qr-epc.png'
				this.fetchFolders('/')
			}
		},
	},
	methods: {
		async fetchFolders(path) {
			this.loading = true
			this.error = ''
			this.folders = []
			try {
				const url = generateUrl('/apps/epc_qrcode_generator/folders')
				const response = await axios.get(url, { params: { path } })
				this.folders = response.data.folders || []
			} catch (err) {
				this.error = err?.response?.data?.error || this.t('epc_qrcode_generator', 'Failed to load folders')
			} finally {
				this.loading = false
			}
		},
		navigateTo(path) {
			this.currentPath = path
			this.fetchFolders(path)
		},
		onSaveHere() {
			this.$emit('pick', {
				folder: this.currentPath,
				filename: this.resolvedFilename,
			})
		},
	},
}
</script>

<style scoped>
.folder-picker {
	padding: 16px;
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-height: 320px;
}

.folder-picker__list {
	flex: 1;
	min-height: 180px;
	overflow-y: auto;
	border: 1px solid var(--color-border);
	border-radius: 6px;
	padding: 4px;
}

.folder-picker__list ul {
	list-style: none;
	margin: 0;
	padding: 0;
}

.folder-icon {
	font-size: 18px;
	line-height: 1;
}

.folder-picker__error {
	color: var(--color-error);
	font-size: 13px;
	padding: 4px 8px;
}

.folder-picker__actions {
	display: flex;
	gap: 8px;
	justify-content: flex-end;
}
</style>
```

- [ ] **Step 2: Lint frontend**

```bash
npm run lint
```

Expected: no errors. Fix any reported issues.

- [ ] **Step 3: Commit**

```bash
git add src/components/FolderPicker.vue
git commit -m "feat: add FolderPicker component with NcModal folder tree navigation"
```

---

## Task 3: Wire `FolderPicker` into `ExportActions.vue`

**Files:**
- Modify: `src/components/ExportActions.vue`

Replace `getFilePickerBuilder` flow with `FolderPicker` component. The save logic (canvas export → POST `/export/save`) stays identical.

- [ ] **Step 1: Update `src/components/ExportActions.vue`**

Replace the entire file content with:

```vue
<template>
	<div v-if="show" class="export-actions">
		<NcButton
			class="export-btn"
			@click="onDownload">
			{{ t('epc_qrcode_generator', 'Download PNG') }}
		</NcButton>
		<NcButton
			class="export-btn"
			@click="onCopy">
			{{ t('epc_qrcode_generator', 'Copy image') }}
		</NcButton>
		<NcButton
			class="export-btn"
			@click="showFolderPicker = true">
			{{ t('epc_qrcode_generator', 'Save to Files') }}
		</NcButton>
		<div v-if="feedback" class="feedback" :class="feedbackType">
			{{ feedback }}
		</div>

		<FolderPicker
			:show="showFolderPicker"
			@close="showFolderPicker = false"
			@pick="onFolderPicked" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import FolderPicker from './FolderPicker.vue'

export default {
	name: 'ExportActions',
	components: {
		NcButton,
		FolderPicker,
	},
	props: {
		show: {
			type: Boolean,
			default: false,
		},
		qrPreviewRef: {
			type: Object,
			default: null,
		},
		qrService: {
			type: Object,
			default: null,
		},
		formData: {
			type: Object,
			default: null,
		},
		options: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			feedback: '',
			feedbackType: 'success',
			feedbackTimeout: null,
			showFolderPicker: false,
		}
	},
	methods: {
		getQrContainer() {
			return this.qrPreviewRef?.$refs?.qrContainer || document.querySelector('.qr-container')
		},

		showFeedback(msg, type = 'success', duration = 2500) {
			this.feedback = msg
			this.feedbackType = type
			if (this.feedbackTimeout) clearTimeout(this.feedbackTimeout)
			this.feedbackTimeout = setTimeout(() => {
				this.feedback = ''
			}, duration)
		},

		async onDownload() {
			try {
				const mountElement = this.getQrContainer()
				if (!mountElement) {
					this.showFeedback(this.t('epc_qrcode_generator', 'QR preview not ready'), 'error')
					return
				}

				if (this.qrPreviewRef?.getExportCanvas) {
					const canvas = await this.qrPreviewRef.getExportCanvas()
					if (canvas) {
						const link = document.createElement('a')
						link.download = 'qr-epc.png'
						link.href = canvas.toDataURL('image/png')
						link.click()
						this.showFeedback(this.t('epc_qrcode_generator', 'Downloaded!'))
						return
					}
				}
				if (this.qrService) {
					await this.qrService.downloadQR(
						mountElement,
						this.formData || {},
						{
							enabled: this.options?.textEnabled || false,
							fontFamily: this.options?.textFontFamily || 'Arial, sans-serif',
							fontSize: this.options?.textFontSize || 16,
							color: this.options?.textColor || '#000000',
						},
						this.options?.bgColor || '#ffffff',
					)
					this.showFeedback(this.t('epc_qrcode_generator', 'Downloaded!'))
				}
			} catch (error) {
				console.error('Download failed:', error)
				this.showFeedback(this.t('epc_qrcode_generator', 'Download failed'), 'error')
			}
		},

		async onFolderPicked({ folder, filename }) {
			this.showFolderPicker = false
			try {
				if (!this.qrPreviewRef?.getExportCanvas) {
					this.showFeedback(this.t('epc_qrcode_generator', 'QR preview not ready'), 'error')
					return
				}
				const canvas = await this.qrPreviewRef.getExportCanvas()
				if (!canvas) {
					this.showFeedback(this.t('epc_qrcode_generator', 'QR preview not ready'), 'error')
					return
				}

				const pngData = canvas.toDataURL('image/png')
				const baseName = filename.replace(/\.png$/i, '')
				const response = await axios.post(generateUrl('/apps/epc_qrcode_generator/export/save'), {
					pngData,
					filename: baseName,
					targetFolder: folder,
				})

				if (response.data?.success) {
					const savedPath = response.data?.path
					const message = savedPath
						? `${this.t('epc_qrcode_generator', 'Saved in')}: ${savedPath}`
						: this.t('epc_qrcode_generator', 'Saved!')
					this.showFeedback(message, 'success', 10000)
				} else {
					const errMsg = response.data?.error || this.t('epc_qrcode_generator', 'Save failed')
					this.showFeedback(`❌ ${errMsg}`, 'error')
				}
			} catch (error) {
				const errMsg = error?.response?.data?.error || error?.message || this.t('epc_qrcode_generator', 'Save failed')
				console.error('Save to Files error:', error?.response?.data || error)
				this.showFeedback(`❌ ${errMsg}`, 'error')
			}
		},

		async onCopy() {
			try {
				const mountElement = this.getQrContainer()
				if (!mountElement) {
					this.showFeedback(this.t('epc_qrcode_generator', 'QR preview not ready'), 'error')
					return
				}

				if (this.qrPreviewRef?.getExportCanvas) {
					const canvas = await this.qrPreviewRef.getExportCanvas()
					if (canvas) {
						const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'))
						await navigator.clipboard.write([
							new ClipboardItem({ 'image/png': blob }),
						])
						this.showFeedback(this.t('epc_qrcode_generator', 'Copied!'))
						return
					}
				}
				if (this.qrService) {
					await this.qrService.copyQR(
						mountElement,
						this.formData || {},
						{
							enabled: this.options?.textEnabled || false,
							fontFamily: this.options?.textFontFamily || 'Arial, sans-serif',
							fontSize: this.options?.textFontSize || 16,
							color: this.options?.textColor || '#000000',
						},
						this.options?.bgColor || '#ffffff',
					)
					this.showFeedback(this.t('epc_qrcode_generator', 'Copied!'))
				}
			} catch (error) {
				console.error('Copy failed:', error)
				this.showFeedback(
					this.t('epc_qrcode_generator', 'Cannot copy image. Use the Download button instead.'),
					'error',
				)
			}
		},
	},
}
</script>

<style scoped>
.export-actions {
	display: flex;
	gap: 8px;
	align-items: center;
	flex-wrap: wrap;
	margin-top: 16px;
	justify-content: center;
}

.feedback {
	font-size: 13px;
	padding: 4px 12px;
	border-radius: 4px;
	width: 100%;
	text-align: center;
}

.feedback.success {
	color: var(--color-success);
	background: var(--color-success-light);
}

.feedback.error {
	color: var(--color-error);
	background: var(--color-error-light);
}
</style>
```

- [ ] **Step 2: Lint**

```bash
npm run lint
```

Expected: no errors.

- [ ] **Step 3: Build**

```bash
npm run build
```

Expected: exits 0, produces `js/epc_qrcode_generator-main.js`.

- [ ] **Step 4: Commit**

```bash
git add src/components/ExportActions.vue
git commit -m "feat: wire FolderPicker into ExportActions, remove getFilePickerBuilder"
```

---

## Task 4: Deploy and manual smoke test

No automated tests — lint-only project. Manual verification required after deploy.

- [ ] **Step 1: Copy built assets + PHP to Nextcloud apps folder**

```bash
# Adjust NCAPPS path to match your Nextcloud install
NCAPPS=/path/to/nextcloud/apps/epc_qrcode_generator
cp js/epc_qrcode_generator-main.js "$NCAPPS/js/"
cp lib/Controller/FolderController.php "$NCAPPS/lib/Controller/"
cp appinfo/routes.php "$NCAPPS/appinfo/"
```

Or sync entire repo if symlinked.

- [ ] **Step 2: Verify endpoint responds**

```bash
# Replace YOUR_NC_URL and SESSION_COOKIE with real values
curl -s -b "nc_session_id=..." \
  "https://YOUR_NC_URL/apps/epc_qrcode_generator/folders?path=/" \
  | python3 -m json.tool
```

Expected: `{"folders":[{"name":"Documents","path":"/Documents"}, ...]}` (array may be empty if no subfolders at root).

- [ ] **Step 3: Browser smoke test**

1. Open app in NC, generate a QR code.
2. Click **Save to Files** → NcModal should open showing root folders.
3. Navigate into a subfolder → breadcrumbs update, subfolder list updates.
4. Edit filename field → default `qr-epc.png`, change to `test.png`.
5. Click **Save here** → modal closes → success feedback shows path.
6. Open NC Files → verify file exists at chosen path with correct name.
7. Click **Cancel** → modal closes, no file saved, no error shown.

- [ ] **Step 4: Final commit (if any fixups needed)**

```bash
git add -p
git commit -m "fix: <describe fixup>"
```

---

## Self-Review

### Spec coverage

| Requirement | Task |
|-------------|------|
| Dialog opens on button click | Task 3 — `showFolderPicker = true` on click |
| Folder tree navigation | Task 2 — `navigateTo()` + `NcListItem` list |
| Breadcrumb path display | Task 2 — `NcBreadcrumbs` + `pathSegments` computed |
| Filename prompt (default qr-epc.png) | Task 2 — `NcTextField` with `filename` data |
| Canvas export → POST /export/save | Task 3 — `onFolderPicked` method |
| Cancel → silent close | Task 2 — `@close="showFolderPicker = false"` |
| Error feedback | Task 3 — `showFeedback('❌ ...', 'error')` |

### Placeholder scan

No TBD/TODO in plan. All code blocks complete.

### Type consistency

- `FolderPicker` emits `pick({ folder, filename })` → `ExportActions.onFolderPicked({ folder, filename })` — consistent.
- `fetchFolders(path)` → `navigateTo(path)` calls `fetchFolders(path)` — consistent.
- `FolderController::listFolders(string $path)` → route `GET /folders?path=` — consistent.
- `ExportService::savePngToFiles($pngData, $filename, $targetFolder)` — called from `onFolderPicked` with matching param names — consistent.
