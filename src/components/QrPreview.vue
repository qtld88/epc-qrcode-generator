<template>
	<div v-if="show" class="qr-preview">
		<div class="qr-card" :style="{ background: options.bgColor || '#ffffff' }">
			<div ref="qrContainer" class="qr-container">
				<!-- qr-code-styling mounts canvas here -->
			</div>
			<div v-if="options.textEnabled && transactionLines.length > 0" class="qr-text">
				<div
					v-for="(line, i) in transactionLines"
					:key="i"
					class="qr-text-line"
					:style="textStyle">
					{{ line }}
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import QRService from '../services/QRService.js'

export default {
	name: 'QrPreview',
	props: {
		show: {
			type: Boolean,
			default: false,
		},
		epcString: {
			type: String,
			default: '',
		},
		formData: {
			type: Object,
			required: true,
		},
		options: {
			type: Object,
			default: () => ({
				pixelColor: '#000000',
				pixelShape: 'square',
				bgColor: '#ffffff',
				cornersStyle: 'square',
				cornersFrameColor: '#000000',
				cornersDotColor: '#000000',
				logoSize: 25,
				textEnabled: false,
				textFontFamily: 'Arial, sans-serif',
				textFontSize: 16,
				textColor: '#000000',
				qrResolution: 300,
			}),
		},
		qrService: {
			type: QRService,
			required: true,
		},
	},
	emits: ['qr-rendered'],
	data() {
		return {
			transactionLines: [],
		}
	},
	computed: {
		textStyle() {
			return {
				fontFamily: this.options.textFontFamily || 'Arial, sans-serif',
				fontSize: `${this.options.textFontSize || 16}px`,
				color: this.options.textColor || '#000000',
			}
		},
	},
	watch: {
		epcString: {
			immediate: true,
			handler(newVal) {
				if (newVal && this.show) {
					this.$nextTick(() => this.renderQR())
				}
			},
		},
		show(val) {
			if (val && this.epcString) {
				this.$nextTick(() => this.renderQR())
			} else if (!val) {
				this.qrService.destroy()
			}
		},
		options: {
			deep: true,
			handler() {
				this.updateTransactionLines()
				if (this.show && this.epcString) {
					this.$nextTick(() => this.updateQR())
				}
			},
		},
		formData: {
			deep: true,
			handler() {
				this.updateTransactionLines()
				if (this.show && this.epcString) {
					this.$nextTick(() => this.updateQR())
				}
			},
		},
	},
	mounted() {
		this.updateTransactionLines()
	},
	methods: {
		async renderQR() {
			if (!this.$refs.qrContainer) return

			this.$refs.qrContainer.innerHTML = ''

			const baseStyle = this.qrService.getStyleOptions(this.options)
			const fullStyle = { ...baseStyle }

			// Process logo and pass directly to qr-code-styling
			if (this.qrService.logoDataUrl) {
				const processed = await this.qrService.processLogo(this.options)
				if (processed) {
					const logoSize = parseInt(this.options?.logoSize || '25')
					fullStyle.image = processed
					fullStyle.imageOptions = {
						hideBackgroundDots: true,
						imageSize: logoSize / 100,
						margin: Math.round(10 - (logoSize - 10) * 0.2),
						saveAsBlob: false,
					}
				}
			}

			this.qrService.createQRCode(this.epcString, fullStyle, 300)
			this.qrService.getInstance().append(this.$refs.qrContainer)

			await this.qrService.waitForDraw()
			this.updateTransactionLines()
			this.$emit('qr-rendered')
		},

		async updateQR() {
			if (!this.qrService.getInstance()) return

			const baseStyle = this.qrService.getStyleOptions(this.options)
			const fullStyle = { ...baseStyle, width: 300, height: 300 }

			// Process logo and pass directly to qr-code-styling
			if (this.qrService.logoDataUrl) {
				const processed = await this.qrService.processLogo(this.options)
				if (processed) {
					const logoSize = parseInt(this.options?.logoSize || '25')
					fullStyle.image = processed
					fullStyle.imageOptions = {
						hideBackgroundDots: true,
						imageSize: logoSize / 100,
						margin: Math.round(10 - (logoSize - 10) * 0.2),
						saveAsBlob: false,
					}
				}
			}

			this.qrService.updateQRCode(fullStyle)

			await this.qrService.waitForDraw()
			this.updateTransactionLines()
		},

		updateTransactionLines() {
			this.transactionLines = this.qrService.getTransactionTextLines(
				this.formData,
				null, // default IBAN formatter
			)
		},

		/**
		 * Get the combined canvas for export (includes text and rounded corners)
		 * Used by ExportActions
		 */
		async getExportCanvas() {
			return await this.qrService.buildExportCanvas(
				this.epcString,
				this.options,
				this.formData,
				{
					enabled: this.options.textEnabled || false,
					fontFamily: this.options.textFontFamily || 'Arial, sans-serif',
					fontSize: this.options.textFontSize || 16,
					color: this.options.textColor || '#000000',
				},
			)
		},
	},
}
</script>

<style scoped>
.qr-preview {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 16px;
}

.qr-card {
	border-radius: 12px;
	padding: 16px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
	display: flex;
	flex-direction: column;
	align-items: center;
	max-width: 100%;
}

.qr-container {
	display: flex;
	justify-content: center;
	align-items: center;
	min-height: 200px;
}

.qr-container canvas {
	max-width: 100%;
	height: auto;
}

.qr-text {
	margin-top: 12px;
	text-align: center;
	width: 100%;
	max-width: 300px;
	overflow-wrap: break-word;
	word-wrap: break-word;
}

.qr-text-line {
	line-height: 1.4;
	overflow-wrap: break-word;
	word-wrap: break-word;
}
</style>
