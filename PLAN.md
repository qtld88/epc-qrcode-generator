---
status: not-started
phase: 1
updated: 2026-05-09
---

# Plan : Application Nextcloud `epc_qrcode_generator`

## Goal
Converter le générateur EPC QR code en application Nextcloud complète avec Vue 3,
stockage serveur (historique individuel, presets partagés avec logos), et export vers Files.

## Context & Decisions
| Decision                                         | Rationale                                          | Source               |
| ------------------------------------------------ | -------------------------------------------------- | -------------------- |
| Build webpack (Vue 3)                            | Standard NC, CSP-compatible, bundle optimisé       | docs.nextcloud.com   |
| qr-code-styling via npm                          | Pas de CDN dans NC, respect CSP                    | docs.nextcloud.com   |
| Historique individuel en DB                      | Pas besoin de partage, léger                       | Décision utilisateur |
| Presets partagés avec logos serveur              | Logos accessibles par tous les users               | Décision utilisateur |
| Logos stockés via OCIAppData                     | Partage inter-utilisateur, pas de limite de taille | docs.nextcloud.com   |
| Export dossier "EPC QR Codes"                    | Nom choisi par l'utilisateur                       | Décision utilisateur |
| @nextcloud/l10n pour i18n                        | Standard NC, fichiers .po                          | docs.nextcloud.com   |
| Migration localStorage → DB                      | Éviter perte de données utilisateurs existants     | Revue de code        |
| Erreurs API → JSON {status, message} + toasts UI | Fail Fast, Fail Loud                               | Règle philosophie    |

## Phase 1: Squelette Nextcloud + build pipeline [PENDING]
- [ ] 1.1 Créer appinfo/info.xml (id=epc_qrcode_generator, nc28+, AGPL)
- [ ] 1.2 Créer appinfo/routes.php (route web → PageController)
- [ ] 1.3 Créer lib/AppInfo/Application.php (IBootstrap, navigation, sidebar)
- [ ] 1.4 Créer lib/Controller/PageController.php (TemplateResponse)
- [ ] 1.5 Créer templates/main.php (shell #app-navigation + #app-content)
- [ ] 1.6 Créer package.json (vue, @nextcloud/vue, qr-code-styling, pinia)
- [ ] 1.7 Créer webpack.config.js (@nextcloud/webpack-vue-config)
- [ ] 1.8 Créer composer.json
- [ ] 1.9 Créer Makefile (dev-setup, build-js, watch-js)
- [ ] 1.10 Créer src/main.js + App.vue basique, valider build ← CURRENT
- [ ] 1.11 Vérifier CSP pour qr-code-styling (canvas)

## Phase 2: Vue — page Generator [PENDING]
- [ ] 2.1 Router → `/` → GeneratorView
- [ ] 2.2 QrForm.vue (bénéficiaire, IBAN validation, montant, comm)
- [ ] 2.3 QrCustomizer.vue (logo upload, pixels, finders, couleurs, résolution)
- [ ] 2.4 QrPreview.vue (qr-code-styling, canvas combiné QR+texte)
- [ ] 2.5 ExportActions.vue (download PNG, copy presse-papier)
- [ ] 2.6 Chaque composant : états loading, empty, error + toasts
- [ ] 2.7 Composants @nextcloud/vue (NcButton, NcInputField, NcAppNavigation...)

## Phase 3: History DB + API [PENDING]
- [ ] 3.1 Migration via NC framework : table oc_epc_history
  - user_id VARCHAR(64), beneficiary VARCHAR(70), iban VARCHAR(34),
    amount DECIMAL(11,2) NULL, remittance VARCHAR(140) NULL,
    epc_string TEXT, created_at DATETIME
  - Index: (user_id), (user_id, created_at)
- [ ] 3.2 lib/Db/History.php + HistoryMapper.php
- [ ] 3.3 lib/Controller/HistoryController.php
  - GET/POST/DELETE /apps/epc_qrcode_generator/api/history
  - DELETE /apps/epc_qrcode_generator/api/history/{id}
  - Réponses JSON {status, message}
- [ ] 3.4 src/services/HistoryService.js + store/history.js (Pinia)
- [ ] 3.5 Auto-save à la génération du QR
- [ ] 3.6 registerEventListener(UserDeletedEvent) → nettoyage historique
- [ ] 3.7 appinfo/uninstall.php → suppression tables

## Phase 4: Presets DB + API avec logos partagés [PENDING]
- [ ] 4.1 Migration : tables oc_epc_presets + oc_epc_preset_images
  - presets : id, user_id, name(50), style_data(JSON), logo_image_id(FK NULL),
    is_shared(BOOL DEFAULT 0), created_at, updated_at
  - preset_images : id, filename(UUID), mime_type, created_at
- [ ] 4.2 lib/Db/Preset.php + PresetMapper.php (CRUD + findByUser + findShared)
- [ ] 4.3 lib/Db/PresetImage.php + PresetImageMapper.php
- [ ] 4.4 lib/Controller/PresetController.php
  - GET/POST /api/presets, GET /api/presets/shared
  - PUT/DELETE /api/presets/{id}
  - Validation upload : MIME image/png|jpeg, max 1MB, max 500×500
- [ ] 4.5 lib/Controller/LogoController.php
  - GET /api/presets/{id}/logo → sert image via OCIAppData
- [ ] 4.6 src/services/PresetService.js + store/presets.js (Pinia)
- [ ] 4.7 PresetManager.vue : sauver/charger/partager + logo

## Phase 5: Migration localStorage → DB [PENDING]
- [ ] 5.1 Détection données existantes (clés epc_qr_history, epcQrPresets)
- [ ] 5.2 Import via API POST /history + POST /presets
- [ ] 5.3 Flag migration faite (user_prefs NC)
- [ ] 5.4 Toast confirmation / erreur + retry

## Phase 6: Export vers Files NC [PENDING]
- [ ] 6.1 lib/Controller/FileController.php
  - POST /api/export
  - Créer dossier "EPC QR Codes", écrire PNG
- [ ] 6.2 src/services/ExportService.js
- [ ] 6.3 Bouton UI + toasts

## Phase 7: Vue — page Historique [PENDING]
- [ ] 7.1 Router → /history → HistoryView
- [ ] 7.2 HistoryList.vue (tableau, recharger, supprimer)
- [ ] 7.3 États loading/empty/error
- [ ] 7.4 Navigation Generator/History dans sidebar

## Phase 8: i18n [PENDING]
- [ ] 8.1 @nextcloud/l10n (t() centralisé, v-t dans templates)
- [ ] 8.2 .pot + traductions FR/EN/DE/ES/IT/NL

## Phase 9: Tests + packaging [PENDING]
- [ ] 9.1 Tests PHPUnit : mappers + controllers
- [ ] 9.2 Tests vitest : composants Vue
- [ ] 9.3 Mode sombre natif NC (variables CSS)
- [ ] 9.4 README, AGPL, CI (GitHub Actions)
- [ ] 9.5 Publication apps.nextcloud.com

## Notes
- 2026-05-09: Plan initial approuvé. Revue technique ajoutée :
  migration localStorage, gestion d'erreurs, nettoyage uninstall,
  contraintes upload, états UI.
