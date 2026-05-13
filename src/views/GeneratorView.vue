<template>
	<div class="generator-layout">
		<div class="generator-main">
			<QrForm
				ref="qrForm"
				:initial-data="formData"
				@generate="onGenerate" />

			<QrPreview
				v-if="qrResult.visible"
				ref="qrPreview"
				:show="qrResult.visible"
				:epc-string="qrResult.epcString"
				:form-data="formData"
				:options="styleOptions"
				:qr-service="qrService"
				@qr-rendered="onQrRendered" />

			<ExportActions
				v-if="qrResult.visible"
				:show="qrResult.visible"
				:qr-preview-ref="qrPreviewRefHandle"
				:qr-service="qrService"
				:form-data="formData"
				:options="styleOptions" />
		</div>
		<div class="generator-sidebar">
			<QrCustomizer
				:options="styleOptions"
				:presets="presetsMap"
				:logo-preview="qrService.logoDataUrl"
				@update:options="onStyleUpdate"
				@logo-upload="onLogoUpload"
				@logo-remove="onLogoRemove"
				@save-preset="onSavePreset"
				@load-preset="onLoadPreset"
				@delete-preset="onDeletePreset"
				@reset-styles="onResetStyles" />
		</div>
	</div>
</template>

<script>
import QrForm from '../components/QrForm.vue'
import QrCustomizer from '../components/QrCustomizer.vue'
import QrPreview from '../components/QrPreview.vue'
import ExportActions from '../components/ExportActions.vue'
import QRService from '../services/QRService.js'
import EPCGenerator from '../lib/epcGenerator.js'
import { useHistoryStore } from '../stores/history.js'
import { usePresetsStore } from '../stores/presets.js'

const DEFAULT_STYLES = {
	pixelShape: 'square',
	pixelColor: '#000000',
	bgColor: '#ffffff',
	cornersStyle: 'square',
	cornersFrameColor: '#000000',
	cornersDotColor: '#000000',
	logoSize: 25,
	logoShape: 'square',
	logoFit: 'deform',
	textEnabled: false,
	textFontFamily: 'Arial, sans-serif',
	textFontSize: 16,
	textColor: '#000000',
	qrResolution: 300,
}

export default {
	name: 'GeneratorView',
	components: {
		QrForm,
		QrCustomizer,
		QrPreview,
		ExportActions,
	},
	data() {
		return {
			formData: {
				beneficiary: '',
				iban: '',
				amount: '',
				remittance: '',
			},
			styleOptions: { ...DEFAULT_STYLES },
			qrResult: {
				visible: false,
				epcString: '',
			},
			qrService: new QRService(),
			qrPreviewRefHandle: null,
		}
	},
	computed: {
		presetsMap() {
			const map = {}
			this.presetsStore.presetList.forEach(p => {
				map[p.name] = p.styleOptions
			})
			return map
		},
		shouldAutoGenerate() {
			return !!(this.$route.query.beneficiary && this.$route.query.iban)
		},
	},
	created() {
		this.epcGenerator = new EPCGenerator()
		this.historyStore = useHistoryStore()
		this.presetsStore = usePresetsStore()
		this.presetsStore.fetchPresets()

		// Populate form from history re-generate query params
		if (this.$route.query.beneficiary) {
			this.formData = {
				beneficiary: this.$route.query.beneficiary || '',
				iban: this.$route.query.iban || '',
				amount: this.$route.query.amount || '',
				remittance: this.$route.query.remittance || '',
			}
		}
	},
	mounted() {
		// Auto-generate QR when navigating from history re-generate
		if (this.shouldAutoGenerate) {
			this.$nextTick(() => {
				this.$refs.qrForm?.onSubmit()
			})
		}
	},
	methods: {
		onQrRendered() {
			this.qrPreviewRefHandle = this.$refs.qrPreview || null
		},

		onGenerate(formData) {
			this.formData = { ...formData }
			try {
				// Generate EPC string
				const epcString = this.epcGenerator.generate({
					beneficiary: formData.beneficiary,
					iban: formData.iban,
					amount: formData.amount,
					remittance: formData.remittance,
				})
				this.qrResult = {
					visible: true,
					epcString,
				}

				// Save to history via Pinia store
				this.historyStore.addHistory({
					beneficiary: formData.beneficiary,
					iban: formData.iban,
					amount: formData.amount || '',
					remittance: formData.remittance || '',
					epcString,
					createdAt: Date.now(),
				}).catch(e => console.error('Failed to save history:', e))

				// Scroll to result
				this.$nextTick(() => {
					this.qrPreviewRefHandle = this.$refs.qrPreview || null
					const el = this.$el?.querySelector('.generator-main')
					if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
				})
			} catch (error) {
				OC.Notification.showTemporary(`❌ ${error.message}`)
			}
		},

		/* === Style Management === */

		onStyleUpdate(newOptions) {
			this.styleOptions = { ...newOptions }
		},

		onLogoUpload(dataUrl) {
			this.qrService.setLogo(dataUrl)
			// qr-code-styling .update() blanks canvas when image changes — full re-render required
			if (this.qrResult.visible && this.$refs.qrPreview) {
				this.$refs.qrPreview.renderQR()
			}
		},

		onLogoRemove() {
			this.qrService.removeLogo()
			if (this.qrResult.visible && this.$refs.qrPreview) {
				this.$refs.qrPreview.renderQR()
			}
		},

		onResetStyles() {
			this.styleOptions = { ...DEFAULT_STYLES }
			this.qrService.removeLogo()
			if (this.qrResult.visible && this.$refs.qrPreview) {
				this.$refs.qrPreview.renderQR()
			}
		},

		/* === Presets === */

		async onSavePreset(name) {
			try {
				await this.presetsStore.savePreset(name, {
					styleOptions: this.styleOptions,
					logoFile: this.qrService.logoDataUrl || null,
				})
				OC.Notification.showTemporary(this.t('epc_qrcode_generator', 'Saved!'))
			} catch (e) {
				OC.Notification.showTemporary(`❌ ${e.message}`)
			}
		},

		async onLoadPreset(idOrName) {
			try {
				const preset = this.presetsStore.presetList.find(p => p.name === idOrName)
				if (!preset) {
					OC.Notification.showTemporary(this.t('epc_qrcode_generator', 'Preset not found'))
					return
				}

				let options = {}
				if (typeof preset.styleOptions === 'string') {
					try { options = JSON.parse(preset.styleOptions) } catch (e) {}
				} else if (typeof preset.styleOptions === 'object') {
					options = preset.styleOptions
				}
				this.styleOptions = {
					...this.styleOptions,
					...options,
				}

			if (preset.logoFile && this.qrService) {
				this.qrService.setLogo(preset.logoFile)
			}

			// Wait for Vue to flush reactive updates and propagate props to child components
			await this.$nextTick()

			if (this.qrResult.visible && this.$refs.qrPreview) {
				this.$refs.qrPreview.updateQR()
			}
			} catch (e) {
				OC.Notification.showTemporary(`❌ ${e.message}`)
			}
		},

		async onDeletePreset(name) {
			try {
				const preset = this.presetsStore.presetList.find(p => p.name === name)
				if (preset) {
					await this.presetsStore.deletePreset(preset.id)
				}
			} catch (e) {
				OC.Notification.showTemporary(`❌ ${e.message}`)
			}
		},
	},
}
</script>

<style scoped>
.generator-layout {
	display: grid;
	grid-template-columns: 1fr 320px;
	gap: 24px;
	padding: 16px;
	height: 100%;
}

.generator-main {
	min-width: 0;
}

.generator-sidebar {
	border-left: 1px solid var(--color-border);
	padding-left: 16px;
	overflow-y: auto;
	max-height: calc(100vh - 100px);
}

@media (max-width: 768px) {
	.generator-layout {
		grid-template-columns: 1fr;
	}
	.generator-sidebar {
		border-left: none;
		padding-left: 0;
		border-top: 1px solid var(--color-border);
		padding-top: 16px;
		max-height: none;
	}
}
</style>
