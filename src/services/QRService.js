import QRCodeStyling from 'qr-code-styling'

class QRService {
	constructor() {
		this.instance = null
		this.logoDataUrl = null
		this.logoProcessSize = 512
	}

	/**
	 * Get the current qr-code-styling instance
	 */
	getInstance() {
		return this.instance
	}

	/**
	 * Build style options from customization state.
	 * Logo is handled separately at call sites — passed directly to qr-code-styling
	 * after processing, so the library handles quiet zone and margin internally.
	 */
	getStyleOptions(state) {
		const cornersStyle = state.cornersStyle || 'square'
		const cornersSquareType = cornersStyle === 'rounded' ? 'extra-rounded' : cornersStyle === 'circle' ? 'dot' : 'square'
		const cornersDotType = cornersStyle === 'circle' || cornersStyle === 'rounded' ? 'dot' : 'square'

		return {
			dotsOptions: {
				color: state.pixelColor || '#000000',
				type: state.pixelShape || 'square',
			},
			cornersSquareOptions: {
				color: state.cornersFrameColor || '#000000',
				type: cornersSquareType,
			},
			cornersDotOptions: {
				color: state.cornersDotColor || '#000000',
				type: cornersDotType,
			},
			backgroundOptions: {
				color: state.bgColor || '#ffffff',
			},
		}
	}

	/**
	 * Wait for qr-code-styling canvas drawing to complete.
	 * getRawData() is the public API that awaits _canvasDrawingPromise internally.
	 * Avoids accessing private props through Vue's reactive proxy.
	 */
	waitForDraw() {
		if (!this.instance) return Promise.resolve()
		return this.instance.getRawData('png').catch(() => {})
	}

	/**
	 * Build a standalone export canvas at full qrResolution.
	 * Preview always renders at 300px; this creates a fresh high-res instance
	 * so resolution changes never affect preview size.
	 * Logo is processed and passed directly to qr-code-styling so the library
	 * handles quiet zone, white background, and margin internally.
	 */
	async buildExportCanvas(epcString, options, formData, textOptions) {
		const qrSize = options.qrResolution || 300
		const styleOptions = this.getStyleOptions(options)

		// Process logo and pass directly to qr-code-styling
		if (this.logoDataUrl) {
			const processed = await this.processLogo(options)
			if (processed) {
				const logoSize = parseInt(options?.logoSize || '25')
				styleOptions.image = processed
				styleOptions.imageOptions = {
					hideBackgroundDots: true,
					imageSize: logoSize / 100,
					margin: Math.round(10 - (logoSize - 10) * 0.2),
					saveAsBlob: false,
				}
			}
		}

		const tempDiv = document.createElement('div')
		tempDiv.style.cssText = 'position:fixed;left:-99999px;top:-99999px;pointer-events:none'
		document.body.appendChild(tempDiv)

		try {
			const instance = new QRCodeStyling({
				width: qrSize,
				height: qrSize,
				data: epcString,
				...styleOptions,
			})
			instance.append(tempDiv)
			await instance.getRawData('png').catch(() => {})

			return await this.getCombinedCanvas(tempDiv, formData, textOptions, options.bgColor || '#ffffff')
		} finally {
			document.body.removeChild(tempDiv)
		}
	}

	/**
	 * Create a new QR code instance
	 */
	createQRCode(epcString, styleOptions, qrSize) {
		this.instance = new QRCodeStyling({
			width: qrSize,
			height: qrSize,
			data: epcString,
			...styleOptions,
		})
		return this.instance
	}

	/**
	 * Update the existing QR code instance
	 */
	updateQRCode(styleOptions) {
		if (!this.instance) return
		this.instance.update(styleOptions)
	}

	/**
	 * Destroy the current instance
	 */
	destroy() {
		this.instance = null
	}

	/**
	 * Set logo data URL
	 */
	setLogo(dataUrl) {
		this.logoDataUrl = dataUrl
	}

	/**
	 * Remove logo
	 */
	removeLogo() {
		this.logoDataUrl = null
	}

	/* === Logo Processing === */

	fitCover(dataUrl) {
		return new Promise((resolve) => {
			const img = new Image()
			img.onload = () => {
				const canvas = document.createElement('canvas')
				const size = this.logoProcessSize
				canvas.width = size
				canvas.height = size
				const ctx = canvas.getContext('2d')
				const sRatio = Math.max(size / img.width, size / img.height)
				const dx = (size - img.width * sRatio) / 2
				const dy = (size - img.height * sRatio) / 2
				ctx.drawImage(img, dx, dy, img.width * sRatio, img.height * sRatio)
				resolve(canvas.toDataURL('image/png'))
			}
			img.onerror = () => resolve(null)
			img.src = dataUrl
		})
	}

	fitContain(dataUrl) {
		return new Promise((resolve) => {
			const img = new Image()
			img.onload = () => {
				const size = this.logoProcessSize
				const canvas = document.createElement('canvas')
				canvas.width = size
				canvas.height = size
				const ctx = canvas.getContext('2d')
				const scale = Math.min(size / img.width, size / img.height, 1)
				const w = img.width * scale
				const h = img.height * scale
				const dx = (size - w) / 2
				const dy = (size - h) / 2
				ctx.drawImage(img, dx, dy, w, h)
				resolve(canvas.toDataURL('image/png'))
			}
			img.onerror = () => resolve(null)
			img.src = dataUrl
		})
	}

	cropToCircle(dataUrl) {
		return new Promise((resolve) => {
			const img = new Image()
			img.onload = () => {
				const size = this.logoProcessSize
				const canvas = document.createElement('canvas')
				canvas.width = size
				canvas.height = size
				const ctx = canvas.getContext('2d')
				const scale = Math.min(size / img.width, size / img.height, 1)
				const w = img.width * scale
				const h = img.height * scale
				const dx = (size - w) / 2
				const dy = (size - h) / 2
				ctx.save()
				ctx.beginPath()
				ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2)
				ctx.closePath()
				ctx.clip()
				ctx.drawImage(img, dx, dy, w, h)
				ctx.restore()
				resolve(canvas.toDataURL('image/png'))
			}
			img.onerror = () => resolve(null)
			img.src = dataUrl
		})
	}

	deformImage(dataUrl) {
		return new Promise((resolve) => {
			const img = new Image()
			img.onload = () => {
				const size = this.logoProcessSize
				const canvas = document.createElement('canvas')
				canvas.width = size
				canvas.height = size
				const ctx = canvas.getContext('2d')
				ctx.drawImage(img, 0, 0, size, size)
				resolve(canvas.toDataURL('image/png'))
			}
			img.onerror = () => resolve(null)
			img.src = dataUrl
		})
	}

	async processLogo(logoOptions) {
		if (!this.logoDataUrl) return null
		try {
			const logoFit = logoOptions?.logoFit || 'deform'
			const logoShape = logoOptions?.logoShape || 'square'
			let image = this.logoDataUrl
			if (logoShape !== 'original') {
				if (logoFit === 'deform') {
					image = await this.deformImage(image)
				} else if (logoFit === 'crop') {
					image = await this.fitCover(image)
				} else {
					image = await this.fitContain(image)
				}
			}
			if (!image) return null
			if (logoShape === 'round') {
				image = await this.cropToCircle(image)
			}
			return image || null
		} catch (error) {
			console.error('Logo processing failed:', error)
			return null
		}
	}

	/* === Combined Canvas Export (with the 3 fixes) === */

	/**
	 * Get the transaction text lines to display on the QR
	 */
	getTransactionTextLines(formData, ibanFormatter) {
		const lines = [formData.beneficiary]
		if (ibanFormatter) {
			lines.push(ibanFormatter(formData.iban))
		} else {
			lines.push(
				formData.iban
					.replace(/\s+/g, '')
					.toUpperCase()
					.replace(/(.{4})/g, '$1 ')
					.trim()
			)
		}
		if (formData.amount) {
			lines.push(`${parseFloat(formData.amount).toFixed(2)} EUR`)
		} else {
			lines.push('')
		}
		if (formData.remittance) {
			lines.push(formData.remittance)
		}
		// Filter out empty lines
		return lines.filter(line => line !== '')
	}

	/**
	 * Get the QR canvas element from the instance's DOM
	 */
	getQrCanvas(mountElement) {
		if (!mountElement) return null
		const canvas = mountElement.querySelector('canvas')
		return canvas
	}

	/**
	 * Create a combined canvas with QR code + transaction text
	 *
	 * Adds outer padding around the QR code so rounded corners don't clip content.
	 * Text is centered below the QR area with proportional spacing.
	 */
	async getCombinedCanvas(mountElement, formData, textOptions, bgColor) {
		const qrCanvas = this.getQrCanvas(mountElement)
		if (!qrCanvas) return null

		const canvas = qrCanvas

		const scale = canvas.width / 300
		const fontSize = parseInt(textOptions?.fontSize || '16') * scale
		const lineHeight = fontSize * 1.4
		// Outer padding around the entire combined canvas
		const outerPadding = 16 * scale

		const show = textOptions?.enabled || false
		const lines = this.getTransactionTextLines(formData)
		const fontFamily = textOptions?.fontFamily || 'Arial, sans-serif'
		const color = textOptions?.color || '#000000'
		const borderRadius = 8 * scale

		// Measure text for wrapping
		const tempCtx = document.createElement('canvas').getContext('2d')
		tempCtx.font = `${fontSize}px ${fontFamily}`
		const maxTextWidth = canvas.width - outerPadding * 2
		const wrappedLines = []

		if (show && lines.length > 0) {
			for (const line of lines) {
				if (tempCtx.measureText(line).width <= maxTextWidth) {
					wrappedLines.push(line)
				} else {
					const words = line.split(' ')
					let currentLine = ''
					for (const word of words) {
						const testLine = currentLine ? currentLine + ' ' + word : word
						if (tempCtx.measureText(testLine).width <= maxTextWidth) {
							currentLine = testLine
						} else {
							if (currentLine) wrappedLines.push(currentLine)
							currentLine = word
						}
					}
					if (currentLine) wrappedLines.push(currentLine)
				}
			}
		}

		const totalLines = wrappedLines.length
		const textContentHeight =
			show && totalLines > 0
				? totalLines * lineHeight + outerPadding * 2  // gap above + lines + gap below
				: 0

		// Combined canvas: larger than QR to add outer padding all around
		const combinedCanvas = document.createElement('canvas')
		const ctx = combinedCanvas.getContext('2d')

		combinedCanvas.width = canvas.width + outerPadding * 2
		combinedCanvas.height = canvas.height + textContentHeight + outerPadding * 2

		const bg = bgColor || '#ffffff'

		// Fill entire canvas with bg + rounded corners
		ctx.fillStyle = bg
		if (typeof ctx.roundRect === 'function') {
			ctx.beginPath()
			ctx.roundRect(0, 0, combinedCanvas.width, combinedCanvas.height, borderRadius)
			ctx.fill()
		} else {
			ctx.fillRect(0, 0, combinedCanvas.width, combinedCanvas.height)
		}

		// Draw QR code offset by outerPadding (so rounded corners don't clip it)
		ctx.drawImage(canvas, outerPadding, outerPadding)

		// Draw transaction text below QR
		if (show && wrappedLines.length > 0) {
			ctx.font = `${fontSize}px ${fontFamily}`
			ctx.textAlign = 'center'
			ctx.textBaseline = 'middle'
			ctx.fillStyle = color

			// Text starts after: top outer padding + QR height + gap (first outerPadding of textContentHeight)
			const textY = outerPadding + canvas.height + outerPadding + fontSize / 2
			for (let i = 0; i < wrappedLines.length; i++) {
				ctx.fillText(wrappedLines[i], combinedCanvas.width / 2, textY + i * lineHeight)
			}
		}

		return combinedCanvas
	}

	/**
	 * Download the QR code as PNG
	 * FIX 3: Always uses getCombinedCanvas (even without text)
	 */
	async downloadQR(mountElement, formData, textOptions, bgColor) {
		const combinedCanvas = await this.getCombinedCanvas(
			mountElement,
			formData,
			textOptions,
			bgColor
		)
		if (!combinedCanvas) return

		const link = document.createElement('a')
		link.download = 'qr-epc.png'
		link.href = combinedCanvas.toDataURL('image/png')
		link.click()
	}

	/**
	 * Copy the QR code image to clipboard
	 * FIX 3: Always uses getCombinedCanvas (even without text)
	 */
	async copyQR(mountElement, formData, textOptions, bgColor) {
		const combinedCanvas = await this.getCombinedCanvas(
			mountElement,
			formData,
			textOptions,
			bgColor
		)
		if (!combinedCanvas) return

		const blob = await new Promise(resolve =>
			combinedCanvas.toBlob(resolve, 'image/png')
		)
		await navigator.clipboard.write([
			new ClipboardItem({ 'image/png': blob }),
		])
	}
}

export default QRService
