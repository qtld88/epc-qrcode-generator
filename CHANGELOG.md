# Changelog

## 1.0.4 — 2026-05-12
### Added
- Custom FolderPicker component replaces broken `@nextcloud/dialogs` file picker (silently hangs in NC31)
- Folder browsing via breadcrumb navigation in Save-to-Files dialog
- Default filename "QRC_{Remittance}" based on form data

### Fixed
- Logo rendering white page regression (CSP issue with `saveAsBlob`)
- Export PNG rounded corners and padding
- Preset overwrite detection (409 Conflict on duplicate name)
- Logo compression removed — PNG transparency preserved

## 1.0.3 — 2026-05-10
### Fixed
- App icon color for NC dark navigation bar (fill="#ffffff")

## 1.0.2 — 2026-05-10
### Fixed
- History Re-generate populates form fields correctly
- Preset Load applies styles immediately after JSON parse

## 1.0.1 — 2026-05-09
### Fixed
- Logo padding algorithm (dynamic margin formula)
- 2MB upload limit for logo images
- Internal handling of amount field format normalization

## 1.0.0 — 2026-05-08
### Added
- Initial release
- EPC QR code generation
- History management
- Preset management
- Export to PNG and PDF
