# Group Sharing for History & Presets — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a user share an individual QR history entry or style preset with one Nextcloud group; group members see shared items read-only, owner manages them.

**Architecture:** Add a nullable `shared_group` column to both DB tables. Mappers gain a `findAllVisible(userId, groupIds)` query (own items OR items shared to a group the user belongs to). Controllers resolve group membership via `IGroupManager` and creator display names via `IUserManager`, enforce owner-only mutations, and expose a `/groups` endpoint. Frontend adds a per-row Share control in the History view and a group dropdown in the preset save row, with badges for items owned by others.

**Tech Stack:** Nextcloud PHP app framework (PHP 8.1), QBMapper ORM, Vue 3 + Pinia, `@nextcloud/vue`, `@nextcloud/axios`.

**No automated test suite** (per CLAUDE.md, `make test` = lint only). Each task ends with the relevant linter and a manual verification note. Final build at the end.

---

### Task 1: DB migration — add `shared_group` column

**Files:**
- Create: `lib/Migration/Version1004Date20260601000004.php`

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1004Date20260601000004 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		foreach (['epc_qr_history', 'epc_qr_presets'] as $tableName) {
			if (!$schema->hasTable($tableName)) {
				continue;
			}
			$table = $schema->getTable($tableName);
			if (!$table->hasColumn('shared_group')) {
				$table->addColumn('shared_group', Types::STRING, [
					'notnull' => false,
					'length' => 64,
					'default' => null,
				]);
			}
			if (!$table->hasIndex('epc_qr_' . ($tableName === 'epc_qr_history' ? 'h' : 'p') . '_shgrp_idx')) {
				$table->addIndex(['shared_group'], 'epc_qr_' . ($tableName === 'epc_qr_history' ? 'h' : 'p') . '_shgrp_idx');
			}
		}

		return $schema;
	}
}
```

- [ ] **Step 2: Lint**

Run: `composer run lint`
Expected: no errors (`php -l` passes on all files in `lib/`).

- [ ] **Step 3: Commit**

```bash
git add lib/Migration/Version1004Date20260601000004.php
git commit -m "feat(db): add shared_group column to history and presets"
```

---

### Task 2: Entities — add `sharedGroup` field + enriched `toArray()`

**Files:**
- Modify: `lib/Db/History.php`
- Modify: `lib/Db/Preset.php`

`toArray()` here only emits `sharedGroup`. The controller adds `ownerDisplayName` and
`isOwner` (it has the user services and current user id; the entity does not).

- [ ] **Step 1: Update `History.php`**

Add the property and type, and extend `toArray()`.

In the property block (after `protected int $createdAt = 0;`):
```php
	protected ?string $sharedGroup = null;
```

In the constructor (after the `createdAt` addType):
```php
		$this->addType('sharedGroup', 'string');
```

Replace `toArray()` body to include the new field:
```php
	public function toArray(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'beneficiary' => $this->getBeneficiary(),
			'iban' => $this->getIban(),
			'amount' => $this->getAmount(),
			'remittance' => $this->getRemittance(),
			'epcString' => $this->getEpcString(),
			'createdAt' => $this->getCreatedAt(),
			'sharedGroup' => $this->getSharedGroup(),
		];
	}
```

- [ ] **Step 2: Update `Preset.php`**

In the property block (after `protected int $updatedAt = 0;`):
```php
	protected ?string $sharedGroup = null;
```

In the constructor (after the `updatedAt` addType):
```php
		$this->addType('sharedGroup', 'string');
```

Add `'sharedGroup' => $this->getSharedGroup(),` to the returned array in `toArray()`
(after the `updatedAt` line):
```php
			'updatedAt' => $this->getUpdatedAt(),
			'sharedGroup' => $this->getSharedGroup(),
```

- [ ] **Step 3: Lint**

Run: `composer run lint`
Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add lib/Db/History.php lib/Db/Preset.php
git commit -m "feat(db): add sharedGroup to History and Preset entities"
```

---

### Task 3: Mappers — `findAllVisible`

**Files:**
- Modify: `lib/Db/HistoryMapper.php`
- Modify: `lib/Db/PresetMapper.php`

- [ ] **Step 1: Add `findAllVisible` to `HistoryMapper.php`**

Add this method (after `findAll`):
```php
	/**
	 * Own entries plus entries shared to any of the given groups.
	 *
	 * @param string $userId
	 * @param string[] $groupIds
	 * @return History[]
	 */
	public function findAllVisible(string $userId, array $groupIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('epc_qr_history');

		$ownExpr = $qb->expr()->eq('user_id', $qb->createNamedParameter($userId));
		if (count($groupIds) > 0) {
			$sharedExpr = $qb->expr()->andX(
				$qb->expr()->isNotNull('shared_group'),
				$qb->expr()->in('shared_group', $qb->createNamedParameter($groupIds, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR_ARRAY)),
			);
			$qb->where($qb->expr()->orX($ownExpr, $sharedExpr));
		} else {
			$qb->where($ownExpr);
		}

		$qb->orderBy('created_at', 'DESC');

		return $this->findEntities($qb);
	}
```

- [ ] **Step 2: Add `findAllVisible` to `PresetMapper.php`**

Add this method (after `findAll`):
```php
	/**
	 * Own presets plus presets shared to any of the given groups.
	 *
	 * @param string $userId
	 * @param string[] $groupIds
	 * @return Preset[]
	 */
	public function findAllVisible(string $userId, array $groupIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('epc_qr_presets');

		$ownExpr = $qb->expr()->eq('user_id', $qb->createNamedParameter($userId));
		if (count($groupIds) > 0) {
			$sharedExpr = $qb->expr()->andX(
				$qb->expr()->isNotNull('shared_group'),
				$qb->expr()->in('shared_group', $qb->createNamedParameter($groupIds, IQueryBuilder::PARAM_STR_ARRAY)),
			);
			$qb->where($qb->expr()->orX($ownExpr, $sharedExpr));
		} else {
			$qb->where($ownExpr);
		}

		$qb->orderBy('name', 'ASC');

		return $this->findEntities($qb);
	}
```

(`PresetMapper` already imports `IQueryBuilder`; `HistoryMapper` references it via FQN above to avoid touching imports.)

- [ ] **Step 3: Lint**

Run: `composer run lint`
Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add lib/Db/HistoryMapper.php lib/Db/PresetMapper.php
git commit -m "feat(db): add findAllVisible to mappers for group-shared items"
```

---

### Task 4: HistoryController — group-aware index, share endpoint, owner enrichment

**Files:**
- Modify: `lib/Controller/HistoryController.php`

- [ ] **Step 1: Inject group + user managers**

Replace the `use` block and constructor. New `use` lines (add to existing):
```php
use OCP\IGroupManager;
use OCP\IUserManager;
```

Replace the class properties + constructor:
```php
	private HistoryMapper $mapper;
	private IUserSession $userSession;
	private IGroupManager $groupManager;
	private IUserManager $userManager;

	public function __construct(
		IRequest $request,
		HistoryMapper $mapper,
		IUserSession $userSession,
		IGroupManager $groupManager,
		IUserManager $userManager
	) {
		parent::__construct('epc_qrcode_generator', $request);
		$this->mapper = $mapper;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
	}
```

- [ ] **Step 2: Add helpers (after `getUserId`)**

```php
	private function getUserGroupIds(string $userId): array {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return [];
		}
		return $this->groupManager->getUserGroupIds($user);
	}

	private function enrich(History $entry, string $currentUserId): array {
		$data = $entry->toArray();
		$ownerId = $entry->getUserId();
		$owner = $this->userManager->get($ownerId);
		$data['ownerDisplayName'] = $owner !== null ? $owner->getDisplayName() : $ownerId;
		$data['isOwner'] = ($ownerId === $currentUserId);
		return $data;
	}
```

- [ ] **Step 3: Rewrite `index()` to use visible scope**

```php
	#[PublicPage]
	public function index(): JSONResponse {
		$userId = $this->getUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		$groupIds = $this->getUserGroupIds($userId);
		$entries = $this->mapper->findAllVisible($userId, $groupIds);
		$result = array_map(fn(History $entry) => $this->enrich($entry, $userId), $entries);

		return new JSONResponse($result);
	}
```

- [ ] **Step 4: Add `share()` endpoint (after `create`)**

```php
	/**
	 * Share / un-share a history entry with one group. Owner only.
	 * Pass group = null (or empty) to make it private again.
	 */
	#[PublicPage]
	public function share(int $id, ?string $group = null): JSONResponse {
		$userId = $this->getUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		$entry = $this->mapper->find($id);
		if ($entry === null) {
			return new JSONResponse(['error' => 'Entry not found'], 404);
		}
		if ($entry->getUserId() !== $userId) {
			return new JSONResponse(['error' => 'Only the owner can change sharing'], 403);
		}

		$target = ($group === null || $group === '') ? null : $group;
		if ($target !== null) {
			$groupIds = $this->getUserGroupIds($userId);
			if (!in_array($target, $groupIds, true)) {
				return new JSONResponse(['error' => 'You are not a member of that group'], 403);
			}
		}

		$entry->setSharedGroup($target);
		$updated = $this->mapper->update($entry);

		return new JSONResponse($this->enrich($updated, $userId));
	}
```

- [ ] **Step 5: Lint**

Run: `composer run lint`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add lib/Controller/HistoryController.php
git commit -m "feat(api): group-aware history index + owner-only share endpoint"
```

---

### Task 5: PresetController — group-aware index, sharedGroup on create/update, owner enforcement

**Files:**
- Modify: `lib/Controller/PresetController.php`

- [ ] **Step 1: Inject group + user managers**

Add to `use` block:
```php
use OCP\IGroupManager;
use OCP\IUserManager;
```

Replace properties + constructor:
```php
	private PresetMapper $mapper;
	private IUserSession $userSession;
	private IGroupManager $groupManager;
	private IUserManager $userManager;

	public function __construct(
		IRequest $request,
		PresetMapper $mapper,
		IUserSession $userSession,
		IGroupManager $groupManager,
		IUserManager $userManager
	) {
		parent::__construct('epc_qrcode_generator', $request);
		$this->mapper = $mapper;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
	}
```

- [ ] **Step 2: Add helpers (after `getUserId`)**

```php
	private function getUserGroupIds(string $userId): array {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return [];
		}
		return $this->groupManager->getUserGroupIds($user);
	}

	private function normalizeSharedGroup(?string $group, string $userId): ?string {
		if ($group === null || $group === '') {
			return null;
		}
		return in_array($group, $this->getUserGroupIds($userId), true) ? $group : null;
	}

	private function enrich(Preset $entry, string $currentUserId): array {
		$data = $entry->toArray();
		$ownerId = $entry->getUserId();
		$owner = $this->userManager->get($ownerId);
		$data['ownerDisplayName'] = $owner !== null ? $owner->getDisplayName() : $ownerId;
		$data['isOwner'] = ($ownerId === $currentUserId);
		return $data;
	}
```

- [ ] **Step 3: Rewrite `index()`**

```php
	#[PublicPage]
	public function index(): JSONResponse {
		$userId = $this->getUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		$groupIds = $this->getUserGroupIds($userId);
		$entries = $this->mapper->findAllVisible($userId, $groupIds);
		$result = array_map(fn(Preset $entry) => $this->enrich($entry, $userId), $entries);

		return new JSONResponse($result);
	}
```

- [ ] **Step 4: Add `sharedGroup` to `create()`**

Change the signature:
```php
	public function create(string $name, string $styleOptions, ?string $logoFile = null, ?string $sharedGroup = null): JSONResponse {
```

After `$entry->setUpdatedAt($now);` add:
```php
		$entry->setSharedGroup($this->normalizeSharedGroup($sharedGroup, $userId));
```

Change the success return to enrich:
```php
		return new JSONResponse($this->enrich($inserted, $userId));
```

- [ ] **Step 5: Add owner check + `sharedGroup` to `update()`**

Change signature:
```php
	public function update(int $id, string $name, string $styleOptions, ?string $logoFile = null, ?string $sharedGroup = null): JSONResponse {
```

After the `if ($entry === null)` 404 block, add an owner check:
```php
		if ($entry->getUserId() !== $userId) {
			return new JSONResponse(['error' => 'Only the owner can edit this preset'], 403);
		}
```

After `$entry->setUpdatedAt(time());` add:
```php
		$entry->setSharedGroup($this->normalizeSharedGroup($sharedGroup, $userId));
```

Change the return to enrich:
```php
		return new JSONResponse($this->enrich($updated, $userId));
```

- [ ] **Step 6: Lint**

Run: `composer run lint`
Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add lib/Controller/PresetController.php
git commit -m "feat(api): group-aware preset index + sharedGroup on create/update"
```

---

### Task 6: `/groups` endpoint

**Files:**
- Modify: `lib/Controller/PageController.php`
- Modify: `appinfo/routes.php`

- [ ] **Step 1: Inspect PageController constructor**

Run: `sed -n '1,60p' lib/Controller/PageController.php`
Expected: see existing `use` lines + constructor so the new dependencies are added consistently.

- [ ] **Step 2: Add a `groups()` method to `PageController`**

Add these `use` lines if missing:
```php
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;
```

Inject `IUserSession`, `IGroupManager`, `IUserManager` into the constructor (append as
parameters and assign to private properties `$userSession`, `$groupManager`,
`$userManager`, matching the style already in the file).

Add the method:
```php
	/**
	 * Return the current user's groups for the share dropdowns.
	 */
	#[PublicPage]
	public function groups(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}
		$ids = $this->groupManager->getUserGroupIds($user);
		$result = [];
		foreach ($ids as $id) {
			$group = $this->groupManager->get($id);
			$result[] = [
				'id' => $id,
				'displayName' => $group !== null ? $group->getDisplayName() : $id,
			];
		}
		return new JSONResponse($result);
	}
```

- [ ] **Step 3: Register the route**

In `appinfo/routes.php`, add inside the `routes` array (after the `page#index` line):
```php
		['name' => 'page#groups', 'url' => '/groups', 'verb' => 'GET'],
```

- [ ] **Step 4: Lint**

Run: `composer run lint`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add lib/Controller/PageController.php appinfo/routes.php
git commit -m "feat(api): add /groups endpoint listing current user's groups"
```

---

### Task 7: Add history#share route

**Files:**
- Modify: `appinfo/routes.php`

- [ ] **Step 1: Register the share route**

In `appinfo/routes.php`, after the `history#create` line, add:
```php
		['name' => 'history#share', 'url' => '/history/{id}/share', 'verb' => 'POST'],
```

- [ ] **Step 2: Lint**

Run: `composer run lint`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add appinfo/routes.php
git commit -m "feat(api): register history share route"
```

---

### Task 8: Frontend — groups store

**Files:**
- Create: `src/stores/groups.js`

- [ ] **Step 1: Write the store**

```js
import { defineStore } from 'pinia'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export const useGroupsStore = defineStore('groups', {
	state: () => ({
		items: [], // [{ id, displayName }]
		loaded: false,
	}),
	actions: {
		async fetchGroups() {
			if (this.loaded) return
			try {
				const response = await axios.get(generateUrl('/apps/epc_qrcode_generator/groups'))
				this.items = response.data
				this.loaded = true
			} catch (e) {
				console.error('Failed to fetch groups:', e)
			}
		},
	},
})
```

- [ ] **Step 2: Lint**

Run: `npm run lint`
Expected: no errors on `src/stores/groups.js`.

- [ ] **Step 3: Commit**

```bash
git add src/stores/groups.js
git commit -m "feat(ui): add groups Pinia store"
```

---

### Task 9: Frontend — history store `shareHistory`

**Files:**
- Modify: `src/stores/history.js`

- [ ] **Step 1: Add `shareHistory` action**

Add this action (after `removeHistory`):
```js
		async shareHistory(id, group) {
			try {
				const response = await axios.post(
					generateUrl(`/apps/epc_qrcode_generator/history/${id}/share`),
					{ group: group || null },
				)
				const idx = this.items.findIndex(item => item.id === id)
				if (idx !== -1) {
					this.items.splice(idx, 1, response.data)
				}
				return response.data
			} catch (e) {
				console.error('Failed to share history entry:', e)
				throw e
			}
		},
```

- [ ] **Step 2: Lint**

Run: `npm run lint`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/stores/history.js
git commit -m "feat(ui): add shareHistory action to history store"
```

---

### Task 10: Frontend — presets store passes `sharedGroup`

**Files:**
- Modify: `src/stores/presets.js`

- [ ] **Step 1: Extend `savePreset` to accept and send `sharedGroup`**

Replace the `savePreset` action with:
```js
		async savePreset(name, data) {
			try {
				const existing = this.items.find(p => p.name === name && p.isOwner !== false)
				const payload = {
					name,
					styleOptions: JSON.stringify(data.styleOptions || data),
					logoFile: data.logoFile || null,
					sharedGroup: data.sharedGroup || null,
				}
				if (existing) {
					await axios.put(generateUrl(`/apps/epc_qrcode_generator/presets/${existing.id}`), payload)
				} else {
					await axios.post(generateUrl('/apps/epc_qrcode_generator/presets'), payload)
				}
				// Refresh list
				await this.fetchPresets()
			} catch (e) {
				console.error('Failed to save preset:', e)
				throw e
			}
		},
```

- [ ] **Step 2: Lint**

Run: `npm run lint`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/stores/presets.js
git commit -m "feat(ui): send sharedGroup when saving presets"
```

---

### Task 11: Frontend — HistoryList share control + badge + read-only

**Files:**
- Modify: `src/components/HistoryList.vue`

The component receives `groups` (array of `{id, displayName}`) and emits a new `share`
event `{ id, group }`. Owner rows get a group `<select>`; non-owner rows get a read-only
badge and no Delete button.

- [ ] **Step 1: Update the template**

Replace the `history-item-secondary` block and the `history-item-actions` block:

```html
				<div class="history-item-secondary">
					<span v-if="item.amount" class="history-amount">{{ item.amount }} EUR</span>
					<span v-if="item.remittance" class="history-remittance">{{ item.remittance }}</span>
					<span class="history-date">{{ formatDate(item.createdAt) }}</span>
					<span v-if="!item.isOwner" class="history-badge">
						{{ groupLabel(item.sharedGroup) }} · {{ item.ownerDisplayName }}
					</span>
					<span v-else-if="item.sharedGroup" class="history-badge">
						{{ t('epc_qrcode_generator', 'Shared with') }} {{ groupLabel(item.sharedGroup) }}
					</span>
				</div>
			</div>
			<div class="history-item-actions">
				<NcButton
					type="tertiary"
					@click="$emit('regenerate', item)">
					{{ t('epc_qrcode_generator', 'Re-generate') }}
				</NcButton>
				<select
					v-if="item.isOwner"
					class="history-share-select"
					:value="item.sharedGroup || ''"
					@change="$emit('share', { id: item.id, group: $event.target.value })">
					<option value="">{{ t('epc_qrcode_generator', 'Private') }}</option>
					<option v-for="g in groups" :key="g.id" :value="g.id">
						{{ g.displayName }}
					</option>
				</select>
				<NcButton
					v-if="item.isOwner"
					type="tertiary"
					@click="$emit('delete', item.id)">
					{{ t('epc_qrcode_generator', 'Delete') }}
				</NcButton>
			</div>
```

- [ ] **Step 2: Update the script (props, emits, helper)**

Replace the `props` and `emits` and add a `groupLabel` method:
```js
	props: {
		items: {
			type: Array,
			required: true,
		},
		groups: {
			type: Array,
			default: () => [],
		},
	},
	emits: ['delete', 'regenerate', 'share'],
	methods: {
		groupLabel(id) {
			const g = this.groups.find(x => x.id === id)
			return g ? g.displayName : id
		},
		formatIban(iban) {
			return (iban || '').replace(/\s+/g, '').toUpperCase().replace(/(.{4})/g, '$1 ').trim()
		},
		formatDate(timestamp) {
			if (!timestamp) return ''
			const d = new Date(timestamp * 1000)
			return d.toLocaleDateString(undefined, {
				day: 'numeric',
				month: 'short',
				year: 'numeric',
				hour: '2-digit',
				minute: '2-digit',
			})
		},
	},
```

- [ ] **Step 3: Add badge + select styling**

Add to the `<style scoped>` block:
```css
.history-badge {
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	border-radius: 12px;
	padding: 1px 8px;
	font-size: 11px;
}

.history-share-select {
	max-width: 140px;
}
```

- [ ] **Step 4: Lint**

Run: `npm run lint`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add src/components/HistoryList.vue
git commit -m "feat(ui): history share dropdown, owner badge, read-only for shared rows"
```

---

### Task 12: Frontend — HistoryView wires groups + share

**Files:**
- Modify: `src/views/HistoryView.vue`

- [ ] **Step 1: Import + use the groups store and pass props/handlers**

In the template, update the `<HistoryList>` usage:
```html
		<HistoryList
			v-else
			:items="filteredItems"
			:groups="groupsStore.items"
			@delete="onDelete"
			@regenerate="onRegenerate"
			@share="onShare" />
```

In `<script>`, add the import:
```js
import { useGroupsStore } from '../stores/groups.js'
```

Add `groupsStore` via a `created()` hook (or extend the existing one). If a `created()`
hook already exists, add the two lines inside it; otherwise add:
```js
	created() {
		this.groupsStore = useGroupsStore()
		this.groupsStore.fetchGroups()
	},
```

Add the `onShare` method to `methods`:
```js
		async onShare({ id, group }) {
			try {
				await this.store.shareHistory(id, group)
			} catch (e) {
				OC.Notification.showTemporary(`❌ ${e.message}`)
			}
		},
```

- [ ] **Step 2: Verify `store` + `groupsStore` references**

Run: `grep -n "this.store\|groupsStore\|useHistoryStore" src/views/HistoryView.vue`
Expected: `store` is already defined (existing code uses `store.loading`); `groupsStore`
now defined in `created()`. If `store` is a computed/data alias, mirror that pattern for
`groupsStore` instead of `created()`.

- [ ] **Step 3: Lint**

Run: `npm run lint`
Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add src/views/HistoryView.vue
git commit -m "feat(ui): wire groups + share handler into HistoryView"
```

---

### Task 13: Frontend — preset save group dropdown + non-owner guards

**Files:**
- Modify: `src/components/QrCustomizer.vue`
- Modify: `src/views/GeneratorView.vue`

The customizer gets a `groups` prop and a group `<select>` next to the preset name input,
and emits `save-preset` with `{ name, sharedGroup }`. Non-owned presets can't be
overwritten/deleted (Delete hidden for them).

- [ ] **Step 1: Add `groups` + `presetMeta` props to QrCustomizer**

In `props`, add:
```js
		groups: {
			type: Array,
			default: () => [],
		},
		presetMeta: {
			type: Object,
			default: () => ({}), // name -> { isOwner, sharedGroup, ownerDisplayName }
		},
```

In `data`, add a field for the chosen share group:
```js
			presetShareGroup: '',
```

- [ ] **Step 2: Add the group select to the save row (template)**

Replace the preset save `customizer-row` (the one containing `presetName`) with:
```html
			<div class="customizer-row">
				<input
					v-model="presetName"
					type="text"
					:placeholder="t('epc_qrcode_generator', 'Preset name...')"
					maxlength="50"
					class="preset-input" />
				<select v-model="presetShareGroup" class="preset-select">
					<option value="">{{ t('epc_qrcode_generator', 'Private') }}</option>
					<option v-for="g in groups" :key="g.id" :value="g.id">
						{{ g.displayName }}
					</option>
				</select>
				<NcButton @click="savePreset">
					{{ t('epc_qrcode_generator', 'Save') }}
				</NcButton>
			</div>
```

- [ ] **Step 3: Show a badge in the preset dropdown + guard Delete**

Replace the load/delete `customizer-row` with:
```html
			<div class="customizer-row">
				<select v-model="selectedPreset" class="preset-select">
					<option value="">{{ t('epc_qrcode_generator', 'Select preset...') }}</option>
					<option v-for="(preset, name) in presets" :key="name" :value="name">
						{{ name }}{{ presetBadge(name) }}
					</option>
				</select>
				<NcButton
					v-if="selectedPreset"
					type="tertiary"
					@click="loadPreset">
					{{ t('epc_qrcode_generator', 'Load') }}
				</NcButton>
				<NcButton
					v-if="selectedPreset && isOwnPreset(selectedPreset)"
					type="tertiary"
					@click="deletePreset">
					{{ t('epc_qrcode_generator', 'Delete') }}
				</NcButton>
			</div>
```

- [ ] **Step 4: Update script — emit object, helpers**

Change `savePreset` to emit name + group:
```js
		savePreset() {
			if (this.presetName.trim()) {
				this.$emit('save-preset', { name: this.presetName.trim(), sharedGroup: this.presetShareGroup || null })
				this.presetName = ''
				this.presetShareGroup = ''
			}
		},
```

Add helpers to `methods`:
```js
		isOwnPreset(name) {
			const meta = this.presetMeta[name]
			return !meta || meta.isOwner !== false
		},
		presetBadge(name) {
			const meta = this.presetMeta[name]
			if (meta && meta.isOwner === false) {
				return ` (${meta.ownerDisplayName})`
			}
			return ''
		},
```

- [ ] **Step 5: Update GeneratorView — pass props, adapt `onSavePreset`**

In the `<QrCustomizer>` usage, add:
```html
			:groups="groupsStore.items"
			:preset-meta="presetMetaMap"
```

Add the groups store. In `<script>` import:
```js
import { useGroupsStore } from '../stores/groups.js'
```

In `created()` (after `this.presetsStore = usePresetsStore()`):
```js
		this.groupsStore = useGroupsStore()
		this.groupsStore.fetchGroups()
```

Add a computed `presetMetaMap` (next to `presetsMap`):
```js
		presetMetaMap() {
			const map = {}
			this.presetsStore.presetList.forEach(p => {
				map[p.name] = {
					isOwner: p.isOwner,
					sharedGroup: p.sharedGroup,
					ownerDisplayName: p.ownerDisplayName,
				}
			})
			return map
		},
```

Change `onSavePreset` to accept the object payload:
```js
		async onSavePreset(payload) {
			const name = typeof payload === 'string' ? payload : payload.name
			const sharedGroup = typeof payload === 'string' ? null : payload.sharedGroup
			try {
				await this.presetsStore.savePreset(name, {
					styleOptions: this.styleOptions,
					logoFile: this.qrService.logoDataUrl || null,
					sharedGroup,
				})
				OC.Notification.showTemporary(this.t('epc_qrcode_generator', 'Saved!'))
			} catch (e) {
				OC.Notification.showTemporary(`❌ ${e.message}`)
			}
		},
```

- [ ] **Step 6: Lint**

Run: `npm run lint`
Expected: no errors.

- [ ] **Step 7: Commit**

```bash
git add src/components/QrCustomizer.vue src/views/GeneratorView.vue
git commit -m "feat(ui): preset share group dropdown + non-owner guards"
```

---

### Task 14: Build, version bump, manual verification

**Files:**
- Modify: `appinfo/info.xml`

- [ ] **Step 1: Production build**

Run: `npm run build`
Expected: build succeeds, `js/` updated.

- [ ] **Step 2: Bump version to 1.1.0**

In `appinfo/info.xml`, change `<version>1.0.6</version>` to `<version>1.1.0</version>`
(minor bump — new feature).

- [ ] **Step 3: Lint everything**

Run: `make lint`
Expected: `npm run lint` + `composer run lint` both pass.

- [ ] **Step 4: Commit**

```bash
git add js/ appinfo/info.xml
git commit -m "build: group sharing feature, bump to 1.1.0"
```

- [ ] **Step 5: Manual verification (deploy to a test NC, two users in a shared group)**

Deploy the app to the test Nextcloud (rsync as in prior workflow) and verify:
1. User A generates a QR → history row appears, no badge, has a group `<select>` defaulting to "Private".
2. User A sets the select to a shared group → row now shows "Shared with <group>".
3. User B (member of that group) opens History → sees A's row with badge `<group> · <A's name>`, no Delete button, no share select.
4. User C (not in the group) does not see the row.
5. User A sets the select back to "Private" → row disappears from B's list on refresh.
6. Presets: User A saves a preset with a group selected → User B sees it in the preset dropdown with `(A's name)` suffix, can Load but has no Delete.
7. Owner-only enforcement: a direct `POST /history/{id}/share` or `PUT /presets/{id}` from a non-owner returns 403 (optional spot-check via browser devtools).

This step has no automated assertion; record the outcome in the PR/commit message or session notes.

---

## Notes for the implementer

- Nextcloud auto-discovers migrations on `occ upgrade` / app version bump. The version bump
  in Task 14 (1.0.6 → 1.1.0) is what triggers `Version1004...` to run on install/upgrade.
- All controllers already use `#[PublicPage]` + manual `IUserSession` checks — keep that
  pattern; do not add CSRF requirements.
- `PARAM_STR_ARRAY` is required for the `IN (:groupIds)` binding; without it the array
  binds incorrectly.
- Keep caveman mode out of code/comments — code and commit messages are written normally.
