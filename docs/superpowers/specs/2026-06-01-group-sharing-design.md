# Group Sharing for History & Presets — Design

## Goal

Let a user share an individual QR history entry or style preset with **one** Nextcloud
group. Group members see shared items **read-only**. Each item is private by default.

## Decisions (from brainstorming)

- **Granularity:** one precise group per item (not all-groups, not multi-group).
- **Permissions:** read-only for non-owners. Only the creator can edit / delete / change sharing.
- **History UX:** items auto-save private. A per-row "Share" control in the History view
  assigns a group after creation.
- **Preset UX:** group chosen in the save dialog (dropdown).
- **Display:** shared items appear mixed into the user's own list, marked with a badge
  showing the group name and the creator's display name.

## Storage (approach A)

Single nullable column `shared_group` on each table. `NULL` = private; otherwise holds the
Nextcloud group id the item is shared with.

- Tables: `epc_qr_history`, `epc_qr_presets`
- New migration `Version1004Date20260601000004` adds `shared_group` (string, length 64,
  nullable, default null) to both tables. Idempotent (`hasColumn` guard).

## Backend

### Entities
`History` and `Preset` gain `protected ?string $sharedGroup = null;` with
`addType('sharedGroup', 'string')`. `toArray()` adds:
- `sharedGroup` — group id or null
- `ownerDisplayName` — resolved from `userId` via `IUserManager` (fallback to userId)
- `isOwner` — `userId === currentUserId`

### Mappers
Add to both mappers:
```
findAllVisible(string $userId, array $groupIds): array
// WHERE user_id = :userId OR (shared_group IS NOT NULL AND shared_group IN (:groupIds))
// history: ORDER BY created_at DESC ; presets: ORDER BY name ASC
```
When `$groupIds` is empty, the `IN` clause is omitted (only own items).

Add `setSharedGroup` handling via existing entity setters (auto from Entity).

### Controllers
Inject `IGroupManager` + `IUserManager` into `HistoryController` and `PresetController`.
Helper `getUserGroupIds()` → `$this->groupManager->getUserGroupIds($user)`.

`HistoryController`:
- `index()` → `findAllVisible(uid, groupIds)`; map with `ownerDisplayName` + `isOwner`.
- new `share(int $id, ?string $group)` → owner-only (403 otherwise). Validates the group
  exists and the **owner** is a member of it before assigning. `group = null` un-shares.
- `destroy()` stays owner-scoped (already filters by `user_id`).

`PresetController`:
- `index()` → `findAllVisible`.
- `create` / `update` accept `?string $sharedGroup`. On set, validate owner membership of
  the group (ignore/clear invalid). `update` is owner-only (already `find` then check;
  add explicit `userId` ownership check returning 403).

### Routes
Add `['name' => 'history#share', 'url' => '/history/{id}/share', 'verb' => 'POST']`.
Add `['name' => 'page#groups', 'url' => '/groups', 'verb' => 'GET']` (or on an existing
controller) returning the current user's groups: `[{ id, displayName }]` for dropdowns.

### Ownership enforcement
All mutating ops (history destroy/share, preset update/destroy) verify `userId` ownership
server-side and return 403 if not owner. Frontend hiding is cosmetic only.

## Frontend

### Stores
- `history.js`: `fetchHistory` already loads the list (now includes shared). Add
  `shareHistory(id, group)` → `POST /history/{id}/share`, then refresh.
- `presets.js`: `savePreset` payload gains `sharedGroup`. Add nothing else.
- New small store or inline fetch for `/groups` (cache in component).

### History view
- Per row: show badge when `!isOwner` → `{group} · {ownerDisplayName}`.
- Owner rows: a "Share" button → small group dropdown (own groups + "Private"). Calls
  `shareHistory`. Show current share state.
- Hide delete button when `!isOwner`.

### Preset UI (save dialog + list)
- Save dialog: add a group dropdown ("Private" default + own groups). Pass `sharedGroup`.
- Preset list: badge for shared (`{group} · {owner}`); hide delete/overwrite for non-owners.
- Loading a shared preset (read-only) still works — it only applies styles locally.

### Group dropdown source
`GET /groups` → current user's group memberships. Empty list → only "Private" shown.

## Out of scope (YAGNI)

- Multi-group sharing, public/server-wide sharing, per-user (non-group) sharing.
- Letting non-owners edit or re-share. Notifications. Share expiry.

## Testing

- Lint only (no test suite). Manual: two users in a shared group — user A shares a history
  row and a preset; user B sees them with badge, read-only, cannot delete; user C (not in
  group) does not see them. Un-share hides them from B.
