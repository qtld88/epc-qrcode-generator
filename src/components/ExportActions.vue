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
			@click="onSaveToFiles">
			{{ t('epc_qrcode_generator', 'Save to Files') }}
		</NcButton>
		<FolderPicker :show.sync="showFolderPicker" :initial-filename="defaultFilename" @folder-selected="handleFolderSelected" />
		<div v-if="feedback" class="feedback" :class="feedbackType">
			{{ feedback }}
		</div>
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
	computed: {
		defaultFilename() {
			const remittance = this.formData?.remittance?.trim()
			if (!remittance) {
				return 'qr-epc'
			}
			const sanitized = remittance
				.replace(/[/\\:*?"<>|]/g, '_')
				.replace(/\s+/g, '_')
				.substring(0, 80)
			return `QRC_${sanitized}`
		},
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

				// Use QrPreview's export method if available
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
				// Fallback: use QRService directly
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

		onSaveToFiles() {
			this.showFolderPicker = true
		},

		async handleFolderSelected(payload) {
			const { targetPath, filename } = payload
			try {
				const mountElement = this.getQrContainer()
				if (!mountElement) {
					this.showFeedback(this.t('epc_qrcode_generator', 'QR preview not ready'), 'error')
					return
				}

				if (this.qrPreviewRef?.getExportCanvas) {
					const canvas = await this.qrPreviewRef.getExportCanvas()
					if (canvas) {
						const pngData = canvas.toDataURL('image/png')
						const cleanFilename = filename.replace(/\.png$/i, '')
						const response = await axios.post(generateUrl('/apps/epc_qrcode_generator/export/save'), {
							pngData,
							filename: cleanFilename,
							targetFolder: targetPath || '/',
						})

						if (response.data?.success) {
							const savedPath = response.data?.path
							const message = savedPath
								? `${this.t('epc_qrcode_generator', 'Saved in')}: ${savedPath}`
								: this.t('epc_qrcode_generator', 'Saved!')
							this.showFeedback(message, 'success', 10000)
						} else {
							const errMsg = response.data?.error || this.t('epc_qrcode_generator', 'Save failed')
							console.error('Save to Files server error:', errMsg)
							this.showFeedback(`❌ ${errMsg}`, 'error')
						}
						return
					}
				}
				this.showFeedback(this.t('epc_qrcode_generator', 'Save failed'), 'error')
			} catch (error) {
				const errMsg = error?.response?.data?.error || error?.message || this.t('epc_qrcode_generator', 'Save failed')
				console.error('Save to Files error:', error?.response?.data || error)
				this.showFeedback(`❌ ${errMsg}`, 'error')
			} finally {
				this.showFolderPicker = false
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
				// Fallback
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
