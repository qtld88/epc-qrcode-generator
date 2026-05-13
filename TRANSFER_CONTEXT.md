# Context pour l'application Nextcloud `epc_qrcode_generator`

Ce document contient tout le code source de la version web standalone, à porter en Vue 3 dans Nextcloud.

## Architecture actuelle (standalone web)

```
index.html          → Tailwind UI + qr-code-styling CDN
js/
  app.js            → Classe EPCQRApp (1279 lignes) : tout le métier
  i18n.js           → Module i18n maison (6 langues, data-i18n, t())
  epcGenerator.js   → Génération chaîne EPC norme ISO 20022
  ibanValidator.js  → Validation IBAN (modulo 97, ISO 13616)
  storage.js        → localStorage (history 10 items, presets)
  darkMode.js       → Toggle dark/light + localStorage
```

**Pas de build.** ES modules natifs avec `<script type="module">`. Tailwind via CDN. qr-code-styling via CDN.

---

## `app.js` — Classe principale (à migrer en Vue 3 + composants)

```js
import EPCGenerator from './epcGenerator.js';
import IBANValidator from './ibanValidator.js';
import QRHistory from './storage.js';
import DarkMode from './darkMode.js';
import { t, getLang, setLang, translatePage } from './i18n.js';

class EPCQRApp {
    constructor() {
        this.epcGenerator = new EPCGenerator();
        this.ibanValidator = new IBANValidator();
        this.qrHistory = new QRHistory();
        this.darkMode = new DarkMode();
        this.currentQR = null;
        this.qrStylingInstance = null;
        this.logoDataUrl = null;
        this.styleDefaults = {};

        this.initializeElements();
        this.attachEventListeners();
        this.applyDarkMode();
        this.saveStyleDefaults();
        this.initLanguage();
        this.loadPresets();
    }

    saveStyleDefaults() {
        this.styleDefaults = {
            pixelShape: 'square',
            pixelColor: '#000000',
            bgColor: '#ffffff',
            cornersStyle: 'square',
            cornersFrameColor: '#000000',
            cornersDotColor: '#000000',
            logoSize: 25,
            logoShape: 'square',
            logoFit: 'deform',
            textInfoEnabled: false,
            textFontFamily: 'Arial, sans-serif',
            textFontSize: 16,
            textColor: '#000000',
            qrResolution: 600,
        };
    }

    initializeElements() {
        this.form = document.getElementById('epcForm');
        this.beneficiaryInput = document.getElementById('beneficiary');
        this.ibanInput = document.getElementById('iban');
        this.amountInput = document.getElementById('amount');
        this.remittanceInput = document.getElementById('remittance');
        this.beneficiaryCount = document.getElementById('beneficiaryCount');
        this.remittanceCount = document.getElementById('remittanceCount');
        this.ibanFeedback = document.getElementById('ibanFeedback');
        this.formSection = document.getElementById('formSection');
        this.qrResult = document.getElementById('qrResult');
        this.qrcodeDiv = document.getElementById('qrcode');
        this.qrcodeWrapper = document.getElementById('qrcodeWrapper');
        this.qrCodeBg = document.getElementById('qrCodeBg');
        this.displayBeneficiary = document.getElementById('displayBeneficiary');
        this.displayIban = document.getElementById('displayIban');
        this.displayAmount = document.getElementById('displayAmount');
        this.displayRemittance = document.getElementById('displayRemittance');
        this.displayRemittanceContainer = document.getElementById('displayRemittanceContainer');
        this.downloadBtn = document.getElementById('downloadBtn');
        this.copyBtn = document.getElementById('copyBtn');
        this.newQrBtn = document.getElementById('newQrBtn');
        this.darkModeToggle = document.getElementById('darkModeToggle');
        this.langSelect = document.getElementById('langSelect');
        this.historyBtn = document.getElementById('historyBtn');
        this.historyModal = document.getElementById('historyModal');
        this.historyContent = document.getElementById('historyContent');
        this.closeHistoryBtn = document.getElementById('closeHistoryBtn');
        this.clearHistoryBtn = document.getElementById('clearHistoryBtn');
        this.historyClearSection = document.getElementById('historyClearSection');
        this.logoFileInput = document.getElementById('logoFileInput');
        this.logoRemoveBtn = document.getElementById('logoRemoveBtn');
        this.logoShapeRadios = document.querySelectorAll('input[name="logoShape"]');
        this.logoFitRadios = document.querySelectorAll('input[name="logoFit"]');
        this.logoSizeSlider = document.getElementById('logoSizeSlider');
        this.logoSizeValue = document.getElementById('logoSizeValue');
        this.pixelShapeSelect = document.getElementById('pixelShapeSelect');
        this.pixelColorInput = document.getElementById('pixelColorInput');
        this.pixelColorText = document.getElementById('pixelColorText');
        this.bgColorInput = document.getElementById('bgColorInput');
        this.bgColorText = document.getElementById('bgColorText');
        this.cornersStyleSelect = document.getElementById('cornersStyleSelect');
        this.cornersFrameColorInput = document.getElementById('cornersFrameColorInput');
        this.cornersFrameColorText = document.getElementById('cornersFrameColorText');
        this.cornersDotColorInput = document.getElementById('cornersDotColorInput');
        this.cornersDotColorText = document.getElementById('cornersDotColorText');
        this.resetStylesBtn = document.getElementById('resetStylesBtn');
        this.customizationAccordion = document.getElementById('customizationAccordion');
        this.textInfoToggle = document.getElementById('textInfoToggle');
        this.textFontSelect = document.getElementById('textFontSelect');
        this.textFontSizeSlider = document.getElementById('textFontSizeSlider');
        this.textFontSizeValue = document.getElementById('textFontSizeValue');
        this.textColorInput = document.getElementById('textColorInput');
        this.textColorText = document.getElementById('textColorText');
        this.qrTextAbove = document.getElementById('qrTextAbove');
        this.qrTextBelow = document.getElementById('qrTextBelow');
        this.qrResolutionSelect = document.getElementById('qrResolutionSelect');
        this.presetNameInput = document.getElementById('presetNameInput');
        this.savePresetBtn = document.getElementById('savePresetBtn');
        this.presetSelect = document.getElementById('presetSelect');
        this.deletePresetBtn = document.getElementById('deletePresetBtn');
    }

    attachEventListeners() {
        this.form.addEventListener('submit', (e) => { e.preventDefault(); this.generateQRCode(); });
        this.form.addEventListener('reset', () => { this.resetForm(); });
        this.beneficiaryInput.addEventListener('input', () => { this.beneficiaryCount.textContent = this.beneficiaryInput.value.length; });
        this.remittanceInput.addEventListener('input', () => { this.remittanceCount.textContent = this.remittanceInput.value.length; });
        this.ibanInput.addEventListener('input', () => { this.validateIbanRealtime(); });
        this.ibanInput.addEventListener('blur', () => {
            if (this.ibanInput.value) this.ibanInput.value = this.ibanValidator.format(this.ibanInput.value);
        });
        this.downloadBtn.addEventListener('click', () => this.downloadQRCode());
        this.copyBtn.addEventListener('click', () => this.copyQRCode());
        this.newQrBtn.addEventListener('click', () => this.resetApp());
        this.darkModeToggle.addEventListener('click', () => this.toggleDarkMode());
        this.langSelect.addEventListener('change', () => { setLang(this.langSelect.value); this.onLanguageChange(); });
        this.historyBtn.addEventListener('click', () => this.showHistory());
        this.closeHistoryBtn.addEventListener('click', () => this.hideHistory());
        this.clearHistoryBtn.addEventListener('click', () => this.clearHistory());
        this.historyModal.addEventListener('click', (e) => { if (e.target === this.historyModal) this.hideHistory(); });
        this.logoFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) { const reader = new FileReader(); reader.onload = (event) => { this.logoDataUrl = event.target.result; this.updateQRCode(); }; reader.readAsDataURL(file); }
        });
        this.logoRemoveBtn.addEventListener('click', () => { this.logoDataUrl = null; this.logoFileInput.value = ''; this.updateQRCode(); });
        this.logoShapeRadios.forEach(radio => radio.addEventListener('change', () => this.updateQRCode()));
        this.logoFitRadios.forEach(radio => radio.addEventListener('change', () => this.updateQRCode()));
        this.logoSizeSlider.addEventListener('input', () => { this.logoSizeValue.textContent = this.logoSizeSlider.value; this.updateQRCode(); });
        this.pixelShapeSelect.addEventListener('change', () => this.updateQRCode());
        this.pixelColorInput.addEventListener('input', () => { this.pixelColorText.value = this.pixelColorInput.value; this.updateQRCode(); });
        this.pixelColorText.addEventListener('input', () => { const val = this.pixelColorText.value; if (/^#[0-9a-f]{6}$/i.test(val)) { this.pixelColorInput.value = val; this.updateQRCode(); } });
        this.bgColorInput.addEventListener('input', () => { this.bgColorText.value = this.bgColorInput.value; this.updateQRCode(); this.updateQRBackground(); });
        this.bgColorText.addEventListener('input', () => { const val = this.bgColorText.value; if (/^#[0-9a-f]{6}$/i.test(val)) { this.bgColorInput.value = val; this.updateQRCode(); this.updateQRBackground(); } });
        this.cornersStyleSelect.addEventListener('change', () => this.updateQRCode());
        this.cornersFrameColorInput.addEventListener('input', () => { this.cornersFrameColorText.value = this.cornersFrameColorInput.value; this.updateQRCode(); });
        this.cornersFrameColorText.addEventListener('input', () => { const val = this.cornersFrameColorText.value; if (/^#[0-9a-f]{6}$/i.test(val)) { this.cornersFrameColorInput.value = val; this.updateQRCode(); } });
        this.cornersDotColorInput.addEventListener('input', () => { this.cornersDotColorText.value = this.cornersDotColorInput.value; this.updateQRCode(); });
        this.cornersDotColorText.addEventListener('input', () => { const val = this.cornersDotColorText.value; if (/^#[0-9a-f]{6}$/i.test(val)) { this.cornersDotColorInput.value = val; this.updateQRCode(); } });
        this.textInfoToggle.addEventListener('change', () => this.updateTransactionDisplay());
        this.textFontSelect.addEventListener('change', () => this.updateTransactionDisplay());
        this.textFontSizeSlider.addEventListener('input', () => { this.textFontSizeValue.textContent = this.textFontSizeSlider.value; this.updateTransactionDisplay(); });
        this.textColorInput.addEventListener('input', () => { this.textColorText.value = this.textColorInput.value; this.updateTransactionDisplay(); });
        this.textColorText.addEventListener('input', () => { const val = this.textColorText.value; if (/^#[0-9a-f]{6}$/i.test(val)) { this.textColorInput.value = val; this.updateTransactionDisplay(); } });
        this.resetStylesBtn.addEventListener('click', () => this.resetStyles());
        this.qrResolutionSelect.addEventListener('change', () => this.rerenderQRCode());
        this.savePresetBtn.addEventListener('click', () => this.savePreset());
        this.presetSelect.addEventListener('change', () => this.loadPreset());
        this.deletePresetBtn.addEventListener('click', () => this.deletePreset());
    }

    getStyleOptions() {
        const logoSize = parseInt(this.logoSizeSlider?.value || '25');
        return {
            image: this.logoDataUrl,
            imageOptions: { crossOrigin: 'anonymous', margin: Math.round(10 - (logoSize - 10) * 0.2), imageSize: logoSize / 100, hideBackgroundDots: true },
            dotsOptions: { color: this.pixelColorInput?.value || '#000000', type: this.pixelShapeSelect?.value || 'square' },
            cornersSquareOptions: { color: this.cornersFrameColorInput?.value || '#000000', type: this.cornersStyleSelect?.value || 'square' },
            cornersDotOptions: { color: this.cornersDotColorInput?.value || '#000000', type: this.cornersStyleSelect?.value || 'square' },
            backgroundOptions: { color: this.bgColorInput?.value || '#ffffff' }
        };
    }

    async processLogo() {
        if (!this.logoDataUrl) return null;
        try {
            const logoFit = document.querySelector('input[name="logoFit"]:checked')?.value || 'deform';
            const logoShape = document.querySelector('input[name="logoShape"]:checked')?.value || 'square';
            let image = this.logoDataUrl;
            if (logoShape !== 'original') {
                if (logoFit === 'deform') image = await this.deformImage(image);
                else image = await this.fitContain(image);
            }
            if (logoShape === 'round') image = await this.cropToCircle(image);
            return image;
        } catch (error) {
            console.error('Logo processing failed:', error);
            return null;
        }
    }

    fitCover(dataUrl) {
        const img = new Image();
        const canvas = document.createElement('canvas');
        const size = 600;
        canvas.width = size; canvas.height = size;
        const ctx = canvas.getContext('2d');
        return new Promise((resolve) => {
            img.onload = () => {
                const sRatio = Math.max(size / img.width, size / img.height);
                const dx = (size - img.width * sRatio) / 2;
                const dy = (size - img.height * sRatio) / 2;
                ctx.drawImage(img, dx, dy, img.width * sRatio, img.height * sRatio);
                resolve(canvas.toDataURL('image/png'));
            };
            img.onerror = () => resolve(dataUrl);
            img.src = dataUrl;
        });
    }

    fitContain(dataUrl) {
        const img = new Image();
        return new Promise((resolve) => {
            img.onload = () => {
                const size = Math.max(img.width, img.height);
                const canvas = document.createElement('canvas');
                canvas.width = size; canvas.height = size;
                const ctx = canvas.getContext('2d');
                const scale = Math.min(size / img.width, size / img.height);
                const w = img.width * scale; const h = img.height * scale;
                const dx = (size - w) / 2; const dy = (size - h) / 2;
                ctx.drawImage(img, dx, dy, w, h);
                resolve(canvas.toDataURL('image/png'));
            };
            img.onerror = () => resolve(dataUrl);
            img.src = dataUrl;
        });
    }

    cropToCircle(dataUrl) {
        const img = new Image();
        return new Promise((resolve) => {
            img.onload = () => {
                const size = Math.max(img.width, img.height);
                const canvas = document.createElement('canvas');
                canvas.width = size; canvas.height = size;
                const ctx = canvas.getContext('2d');
                ctx.save();
                ctx.beginPath();
                ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
                ctx.closePath(); ctx.clip();
                ctx.drawImage(img, 0, 0, size, size);
                ctx.restore();
                resolve(canvas.toDataURL('image/png'));
            };
            img.onerror = () => resolve(dataUrl);
            img.src = dataUrl;
        });
    }

    deformImage(dataUrl) {
        const img = new Image();
        return new Promise((resolve) => {
            img.onload = () => {
                const size = Math.max(img.width, img.height);
                const canvas = document.createElement('canvas');
                canvas.width = size; canvas.height = size;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, size, size);
                resolve(canvas.toDataURL('image/png'));
            };
            img.onerror = () => resolve(dataUrl);
            img.src = dataUrl;
        });
    }

    getTransactionTextLines() {
        if (!this.currentQR) return [];
        const data = this.currentQR.data;
        const lines = [`${data.beneficiary}`, `${this.ibanValidator.format(data.iban)}`];
        if (data.amount) lines.push(`${parseFloat(data.amount).toFixed(2)} EUR`);
        if (data.remittance) lines.push(`${data.remittance}`);
        return lines;
    }

    displayTransactionDetails(data) {
        if (!data) return;
        this.displayBeneficiary.textContent = data.beneficiary;
        this.displayIban.textContent = this.ibanValidator.format(data.iban);
        this.displayAmount.textContent = data.amount ? `${parseFloat(data.amount).toFixed(2)} EUR` : t('result.amountFree');
        if (data.remittance) {
            this.displayRemittance.textContent = data.remittance;
            this.displayRemittanceContainer.classList.remove('hidden');
        } else {
            this.displayRemittanceContainer.classList.add('hidden');
        }
    }

    updateTransactionDisplay() {
        const show = this.textInfoToggle?.checked || false;
        const fontFamily = this.textFontSelect?.value || 'Arial, sans-serif';
        const fontSize = parseInt(this.textFontSizeSlider?.value || '16');
        const color = this.textColorInput?.value || '#000000';
        const lines = this.getTransactionTextLines();
        const html = lines.map(line => `<div>${this.escapeHtml(line)}</div>`).join('');
        const style = `font-family: ${fontFamily}; font-size: ${fontSize}px; color: ${color}; overflow-wrap: break-word; word-wrap: break-word;`;
        this.qrTextAbove.classList.add('hidden');
        this.qrTextBelow.classList.add('hidden');
        this.qrTextAbove.innerHTML = '';
        this.qrTextBelow.innerHTML = '';
        if (!show || lines.length === 0) return;
        const container = 'below' === 'above' ? this.qrTextAbove : this.qrTextBelow;
        container.innerHTML = html;
        container.style.cssText = style;
        container.classList.remove('hidden');
    }

    updateQRBackground() {
        const bgColor = this.bgColorInput?.value || '#ffffff';
        if (this.qrCodeBg) this.qrCodeBg.style.background = bgColor;
    }

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    getCombinedCanvas() {
        const canvas = this.qrcodeDiv.querySelector('canvas');
        if (!canvas) return null;
        const show = this.textInfoToggle?.checked || false;
        if (!show) return canvas;
        const fontFamily = this.textFontSelect?.value || 'Arial, sans-serif';
        const fontSize = parseInt(this.textFontSizeSlider?.value || '16');
        const color = this.textColorInput?.value || '#000000';
        const lines = this.getTransactionTextLines();
        if (lines.length === 0) return canvas;
        const padding = 14;
        const lineHeight = fontSize * 1.4;
        const tempCtx = document.createElement('canvas').getContext('2d');
        tempCtx.font = `${fontSize}px ${fontFamily}`;
        const maxTextWidth = canvas.width - padding * 2;
        const wrappedLines = [];
        lines.forEach(line => {
            if (tempCtx.measureText(line).width <= maxTextWidth) {
                wrappedLines.push(line);
            } else {
                const words = line.split(' ');
                let currentLine = '';
                for (const word of words) {
                    const testLine = currentLine ? currentLine + ' ' + word : word;
                    if (tempCtx.measureText(testLine).width <= maxTextWidth) {
                        currentLine = testLine;
                    } else {
                        if (currentLine) wrappedLines.push(currentLine);
                        currentLine = word;
                    }
                }
                if (currentLine) wrappedLines.push(currentLine);
            }
        });
        const totalLines = wrappedLines.length;
        const textBlockHeight = totalLines * lineHeight + padding * 2;
        const combinedCanvas = document.createElement('canvas');
        const ctx = combinedCanvas.getContext('2d');
        combinedCanvas.width = canvas.width;
        combinedCanvas.height = canvas.height + textBlockHeight;
        const qrY = 'below' === 'above' ? textBlockHeight : 0;
        const textY = 'below' === 'above' ? padding + fontSize : canvas.height + padding + fontSize;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, combinedCanvas.width, combinedCanvas.height);
        ctx.drawImage(canvas, 0, qrY);
        ctx.font = `${fontSize}px ${fontFamily}`;
        ctx.textAlign = 'left';
        ctx.fillStyle = color;
        wrappedLines.forEach((line, i) => { ctx.fillText(line, padding, textY + i * lineHeight); });
        return combinedCanvas;
    }

    applyDarkMode() { this.darkMode.apply(); }
    toggleDarkMode() { this.darkMode.toggle(); }

    validateIbanRealtime() {
        const iban = this.ibanInput.value;
        if (iban.length < 15) {
            this.ibanFeedback.innerHTML = '';
            this.ibanInput.classList.remove('border-green-300', 'dark:border-green-600', 'border-red-300', 'dark:border-red-600');
            return;
        }
        const validation = this.ibanValidator.validate(iban);
        if (validation.valid) {
            this.ibanFeedback.innerHTML = `<span class="text-green-600 dark:text-green-400 text-sm flex items-center">${t('iban.valid', { country: validation.country })}</span>`;
            this.ibanInput.classList.remove('border-red-300', 'dark:border-red-600');
            this.ibanInput.classList.add('border-green-300', 'dark:border-green-600');
        } else {
            this.ibanFeedback.innerHTML = `<span class="text-red-600 dark:text-red-400 text-sm">${t('iban.invalid', { error: validation.error })}</span>`;
            this.ibanInput.classList.remove('border-green-300', 'dark:border-green-600');
            this.ibanInput.classList.add('border-red-300', 'dark:border-red-600');
        }
    }

    generateQRCode() {
        try {
            const data = { beneficiary: this.beneficiaryInput.value, iban: this.ibanInput.value, amount: this.amountInput.value, remittance: this.remittanceInput.value };
            const ibanValidation = this.ibanValidator.validate(data.iban);
            if (!ibanValidation.valid) { alert(`❌ ${ibanValidation.error}`); return; }
            const epcString = this.epcGenerator.generate(data);
            this.qrHistory.save({ epcString, formData: data });
            this.renderQRCode(epcString, data);
        } catch (error) { alert(t('error.generic', { message: error.message })); }
    }

    async renderQRCode(epcString, data) {
        this.qrcodeDiv.innerHTML = '';
        const styleOptions = this.getStyleOptions();
        const image = await this.processLogo();
        const qrSize = this.getQrSize();
        const options = {
            width: qrSize, height: qrSize, data: epcString, image: image,
            dotsOptions: styleOptions.dotsOptions,
            cornersSquareOptions: styleOptions.cornersSquareOptions,
            cornersDotOptions: styleOptions.cornersDotOptions,
            backgroundOptions: styleOptions.backgroundOptions,
            imageOptions: styleOptions.imageOptions
        };
        this.qrStylingInstance = new QRCodeStyling(options);
        this.qrStylingInstance.append(this.qrcodeDiv);
        this.displayTransactionDetails(data);
        this.currentQR = { epcString, data };
        this.updateTransactionDisplay();
        this.qrResult.classList.remove('hidden');
        this.qrResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        this.updateQRBackground();
    }

    async updateQRCode() {
        if (!this.qrStylingInstance || !this.currentQR) return;
        try {
            const styleOptions = this.getStyleOptions();
            const image = await this.processLogo();
            this.qrStylingInstance.update({
                image: image,
                dotsOptions: styleOptions.dotsOptions,
                cornersSquareOptions: styleOptions.cornersSquareOptions,
                cornersDotOptions: styleOptions.cornersDotOptions,
                backgroundOptions: styleOptions.backgroundOptions,
                imageOptions: styleOptions.imageOptions
            });
        } catch (error) {
            console.error('QR update failed, retrying without logo:', error);
            this.logoDataUrl = null;
            const styleOptions = this.getStyleOptions();
            this.qrStylingInstance.update({
                image: null,
                dotsOptions: styleOptions.dotsOptions,
                cornersSquareOptions: styleOptions.cornersSquareOptions,
                cornersDotOptions: styleOptions.cornersDotOptions,
                backgroundOptions: styleOptions.backgroundOptions,
                imageOptions: styleOptions.imageOptions
            });
        }
    }

    getQrSize() { return parseInt(this.qrResolutionSelect?.value || '300'); }

    rerenderQRCode() { if (this.currentQR) this.renderQRCode(this.currentQR.epcString, this.currentQR.data); }

    loadPresets() {
        try { const stored = localStorage.getItem('epcQrPresets'); this.presets = stored ? JSON.parse(stored) : {}; }
        catch { this.presets = {}; }
        this.populatePresetSelect();
    }

    storePresets() {
        try { localStorage.setItem('epcQrPresets', JSON.stringify(this.presets)); } catch {}
        this.populatePresetSelect();
    }

    populatePresetSelect() {
        const currentValue = this.presetSelect.value;
        this.presetSelect.innerHTML = '<option value="">Presets...</option>';
        Object.keys(this.presets).sort().forEach(name => {
            const option = document.createElement('option');
            option.value = name;
            option.textContent = name;
            this.presetSelect.appendChild(option);
        });
        if (currentValue && this.presets[currentValue]) this.presetSelect.value = currentValue;
    }

    readCurrentStyles() {
        const logoShape = document.querySelector('input[name="logoShape"]:checked');
        const logoFit = document.querySelector('input[name="logoFit"]:checked');
        return {
            pixelShape: this.pixelShapeSelect?.value || 'square',
            pixelColor: this.pixelColorInput?.value || '#000000',
            bgColor: this.bgColorInput?.value || '#ffffff',
            cornersStyle: this.cornersStyleSelect?.value || 'square',
            cornersFrameColor: this.cornersFrameColorInput?.value || '#000000',
            cornersDotColor: this.cornersDotColorInput?.value || '#000000',
            logoSize: parseInt(this.logoSizeSlider?.value || '25'),
            logoShape: logoShape?.value || 'square',
            logoFit: logoFit?.value || 'deform',
            logoDataUrl: this.logoDataUrl,
            textInfoEnabled: this.textInfoToggle?.checked || false,
            textInfoPosition: 'below',
            textFontFamily: this.textFontSelect?.value || 'Arial, sans-serif',
            textFontSize: parseInt(this.textFontSizeSlider?.value || '16'),
            textColor: this.textColorInput?.value || '#000000',
            qrResolution: parseInt(this.qrResolutionSelect?.value || '300')
        };
    }

    applyStyles(styles) {
        if (!styles) return;
        this.pixelShapeSelect.value = styles.pixelShape || this.styleDefaults.pixelShape;
        this.pixelColorInput.value = styles.pixelColor || this.styleDefaults.pixelColor;
        this.pixelColorText.value = styles.pixelColor || this.styleDefaults.pixelColor;
        this.bgColorInput.value = styles.bgColor || this.styleDefaults.bgColor;
        this.bgColorText.value = styles.bgColor || this.styleDefaults.bgColor;
        this.cornersStyleSelect.value = styles.cornersStyle || this.styleDefaults.cornersStyle;
        this.cornersFrameColorInput.value = styles.cornersFrameColor || this.styleDefaults.cornersFrameColor;
        this.cornersFrameColorText.value = styles.cornersFrameColor || this.styleDefaults.cornersFrameColor;
        this.cornersDotColorInput.value = styles.cornersDotColor || this.styleDefaults.cornersDotColor;
        this.cornersDotColorText.value = styles.cornersDotColor || this.styleDefaults.cornersDotColor;
        this.logoSizeSlider.value = styles.logoSize || this.styleDefaults.logoSize;
        this.logoSizeValue.textContent = styles.logoSize || this.styleDefaults.logoSize;
        this.logoShapeRadios.forEach(radio => { radio.checked = radio.value === (styles.logoShape || this.styleDefaults.logoShape); });
        this.logoFitRadios.forEach(radio => { radio.checked = radio.value === (styles.logoFit || this.styleDefaults.logoFit); });
        this.textInfoToggle.checked = styles.textInfoEnabled ?? this.styleDefaults.textInfoEnabled;
        this.textFontSelect.value = styles.textFontFamily || this.styleDefaults.textFontFamily;
        this.textFontSizeSlider.value = styles.textFontSize || this.styleDefaults.textFontSize;
        this.textFontSizeValue.textContent = styles.textFontSize || this.styleDefaults.textFontSize;
        this.textColorInput.value = styles.textColor || this.styleDefaults.textColor;
        this.textColorText.value = styles.textColor || this.styleDefaults.textColor;
        this.qrResolutionSelect.value = String(styles.qrResolution || this.styleDefaults.qrResolution);
        if (styles.logoDataUrl !== undefined) {
            this.logoDataUrl = styles.logoDataUrl;
            if (!this.logoDataUrl) this.logoFileInput.value = '';
        }
        this.rerenderQRCode();
        this.updateTransactionDisplay();
        this.updateQRBackground();
    }

    savePreset() {
        const name = this.presetNameInput.value.trim();
        if (!name) { alert(t('presets.noName')); return; }
        if (name.length > 50) { alert(t('presets.nameTooLong')); return; }
        this.presets[name] = this.readCurrentStyles();
        this.storePresets();
        this.presetSelect.value = name;
        this.presetNameInput.value = '';
        const btn = this.savePresetBtn;
        btn.textContent = '✓';
        setTimeout(() => { btn.textContent = '💾'; }, 1500);
    }

    loadPreset() {
        const name = this.presetSelect.value;
        if (!name) return;
        if (!this.presets[name]) { alert(t('presets.notFound')); this.populatePresetSelect(); return; }
        this.applyStyles(this.presets[name]);
    }

    deletePreset() {
        const name = this.presetSelect.value;
        if (!name) return;
        if (!confirm(t('presets.confirmDelete', { name }))) return;
        delete this.presets[name];
        this.storePresets();
        this.presetSelect.value = '';
    }

    initLanguage() {
        const lang = getLang();
        if (this.langSelect) this.langSelect.value = lang;
        translatePage();
        document.querySelectorAll('select[data-i18n] option[data-i18n]').forEach(option => {
            const key = option.getAttribute('data-i18n');
            if (key) option.textContent = t(key);
        });
    }

    onLanguageChange() {
        translatePage();
        document.querySelectorAll('select[data-i18n] option[data-i18n]').forEach(option => {
            const key = option.getAttribute('data-i18n');
            if (key) option.textContent = t(key);
        });
        this.updateTransactionDisplay();
        if (this.currentQR) this.displayTransactionDetails(this.currentQR.data);
    }

    resetStyles() {
        this.pixelShapeSelect.value = this.styleDefaults.pixelShape;
        this.pixelColorInput.value = this.styleDefaults.pixelColor;
        this.pixelColorText.value = this.styleDefaults.pixelColor;
        this.bgColorInput.value = this.styleDefaults.bgColor;
        this.bgColorText.value = this.styleDefaults.bgColor;
        this.cornersStyleSelect.value = this.styleDefaults.cornersStyle;
        this.cornersFrameColorInput.value = this.styleDefaults.cornersFrameColor;
        this.cornersFrameColorText.value = this.styleDefaults.cornersFrameColor;
        this.cornersDotColorInput.value = this.styleDefaults.cornersDotColor;
        this.cornersDotColorText.value = this.styleDefaults.cornersDotColor;
        this.logoSizeSlider.value = this.styleDefaults.logoSize;
        this.logoSizeValue.textContent = this.styleDefaults.logoSize;
        this.logoDataUrl = null;
        this.logoFileInput.value = '';
        this.logoShapeRadios.forEach(radio => { radio.checked = radio.value === this.styleDefaults.logoShape; });
        this.logoFitRadios.forEach(radio => { radio.checked = radio.value === this.styleDefaults.logoFit; });
        this.textInfoToggle.checked = this.styleDefaults.textInfoEnabled;
        this.textFontSelect.value = this.styleDefaults.textFontFamily;
        this.textFontSizeSlider.value = this.styleDefaults.textFontSize;
        this.textFontSizeValue.textContent = this.styleDefaults.textFontSize;
        this.textColorInput.value = this.styleDefaults.textColor;
        this.textColorText.value = this.styleDefaults.textColor;
        this.qrResolutionSelect.value = this.styleDefaults.qrResolution;
        this.rerenderQRCode();
        this.updateTransactionDisplay();
        this.updateQRBackground();
    }

    downloadQRCode() {
        if (!this.qrStylingInstance) return;
        const show = this.textInfoToggle?.checked || false;
        if (show) {
            const combinedCanvas = this.getCombinedCanvas();
            if (!combinedCanvas) return;
            const link = document.createElement('a');
            link.download = 'qr-epc.png';
            link.href = combinedCanvas.toDataURL('image/png');
            link.click();
        } else {
            this.qrStylingInstance.download({ name: 'qr-epc', extension: 'png' });
        }
    }

    async copyQRCode() {
        try {
            const show = this.textInfoToggle?.checked || false;
            let canvas;
            if (show) { canvas = this.getCombinedCanvas(); }
            else { canvas = this.qrcodeDiv.querySelector('canvas'); }
            if (!canvas) return;
            const blob = await new Promise(resolve => canvas.toBlob(resolve));
            await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
            const originalText = this.copyBtn.textContent;
            this.copyBtn.textContent = t('action.copied');
            this.copyBtn.classList.add('bg-green-600', 'hover:bg-green-700');
            this.copyBtn.classList.remove('bg-gray-600', 'hover:bg-gray-700');
            setTimeout(() => {
                this.copyBtn.textContent = originalText;
                this.copyBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                this.copyBtn.classList.add('bg-gray-600', 'hover:bg-gray-700');
            }, 2000);
        } catch (error) { alert(t('action.copyError')); }
    }

    resetForm() {
        this.beneficiaryCount.textContent = '0';
        this.remittanceCount.textContent = '0';
        this.ibanFeedback.innerHTML = '';
        this.ibanInput.classList.remove('border-green-300', 'dark:border-green-600', 'border-red-300', 'dark:border-red-600');
    }

    resetApp() {
        this.form.reset();
        this.resetForm();
        this.qrResult.classList.add('hidden');
        this.currentQR = null;
        this.qrStylingInstance = null;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    showHistory() {
        const history = this.qrHistory.getAll();
        if (history.length === 0) {
            this.historyContent.innerHTML = `<p class="text-center text-gray-500 dark:text-gray-400 py-8">${t('history.empty')}</p>`;
            this.historyClearSection.classList.add('hidden');
        } else {
            this.renderHistory(history);
            this.historyClearSection.classList.remove('hidden');
        }
        this.historyModal.classList.remove('hidden');
        this.historyModal.classList.add('flex');
    }

    renderHistory(history) {
        this.historyContent.innerHTML = history.map((item, index) => {
            const date = new Date(item.timestamp);
            const formattedDate = date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            return `<div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition cursor-pointer mb-3" data-index="${index}">
              <div class="flex justify-between items-start mb-2">
                <div class="flex-1">
                  <h3 class="font-medium text-gray-900 dark:text-white">${item.formData.beneficiary}</h3>
                  <p class="text-sm text-gray-600 dark:text-gray-400">${this.ibanValidator.format(item.formData.iban)}</p>
                </div>
                <div class="text-right">
                  <p class="font-medium text-gray-900 dark:text-white">${item.formData.amount ? `${parseFloat(item.formData.amount).toFixed(2)} EUR` : t('history.free')}</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">${formattedDate}</p>
                </div>
              </div>
              ${item.formData.remittance ? `<p class="text-sm text-gray-600 dark:text-gray-400 mt-2">💬 ${item.formData.remittance}</p>` : ''}
            </div>`;
        }).join('');
        this.historyContent.querySelectorAll('[data-index]').forEach(element => {
            element.addEventListener('click', () => {
                const index = parseInt(element.dataset.index);
                this.loadFromHistory(index);
            });
        });
    }

    loadFromHistory(index) {
        const item = this.qrHistory.getByIndex(index);
        if (!item) return;
        this.beneficiaryInput.value = item.formData.beneficiary;
        this.ibanInput.value = item.formData.iban;
        this.amountInput.value = item.formData.amount || '';
        this.remittanceInput.value = item.formData.remittance || '';
        this.beneficiaryCount.textContent = item.formData.beneficiary.length;
        this.remittanceCount.textContent = (item.formData.remittance || '').length;
        this.renderQRCode(item.epcString, item.formData);
        this.hideHistory();
    }

    hideHistory() {
        this.historyModal.classList.add('hidden');
        this.historyModal.classList.remove('flex');
    }

    clearHistory() {
        if (confirm(t('history.confirmClear'))) { this.qrHistory.clear(); this.showHistory(); }
    }
}

document.addEventListener('DOMContentLoaded', () => { new EPCQRApp(); });
```

---

## `i18n.js` — Traductions complètes (6 langues)

```js
const STORAGE_KEY = 'epcQrLang';

const TRANSLATIONS = {
    fr: {
        'app.title': 'Générateur QR Code EPC',
        'app.subtitle': 'QR codes pour virements SEPA',
        'lang.label': 'Langue',
        'form.beneficiary': 'Nom du bénéficiaire *',
        'form.beneficiary.placeholder': 'Wikimedia Foundation',
        'form.beneficiary.count': '{count}/70 caractères',
        'form.iban': 'IBAN *',
        'form.iban.placeholder': 'BE68 5390 0754 7034',
        'form.amount': 'Montant (EUR)',
        'form.amount.placeholder': '123.45',
        'form.amount.optional': 'Laissez vide si le montant est libre',
        'form.remittance': 'Communication / Référence',
        'form.remittance.placeholder': 'Donation pour Wikipedia',
        'form.remittance.count': '{count}/140 caractères',
        'form.submit': 'Générer le QR Code',
        'form.reset': 'Réinitialiser',
        'customize.title': '🎨 Personnalisation du QR Code',
        'customize.resolution': 'Résolution d\'export',
        'customize.resolution.standard': 'Standard (300px)',
        'customize.resolution.high': 'Haute (600px)',
        'customize.resolution.veryhigh': 'Très haute (900px)',
        'customize.resolution.ultra': 'Ultra (1200px)',
        'logo.title': '📷 Logo',
        'logo.image': 'Image',
        'logo.remove': 'Supprimer le logo',
        'logo.shape': 'Forme',
        'logo.square': 'Carré',
        'logo.round': 'Rond',
        'logo.original': 'Original',
        'logo.size': 'Taille',
        'logo.fit': 'Ajustement',
        'logo.deform': 'Déformer',
        'logo.crop': 'Recadrer',
        'pixels.title': '🎨 Pixels',
        'pixels.shape': 'Forme',
        'pixels.square': 'Carré',
        'pixels.rounded': 'Arrondi',
        'pixels.dots': 'Points',
        'pixels.color': 'Couleur',
        'pixels.background': 'Fond',
        'finders.title': '🔲 Finder Patterns (3 coins)',
        'finders.style': 'Style des coins',
        'finders.square': 'Carré',
        'finders.rounded': 'Arrondi',
        'finders.circle': 'Cercle',
        'finders.frameColor': 'Couleur cadre',
        'finders.dotColor': 'Couleur point',
        'transaction.title': '📝 Infos Transaction',
        'transaction.show': 'Afficher',
        'transaction.showLabel': 'Afficher les infos',
        'transaction.font': 'Police',
        'transaction.size': 'Taille',
        'transaction.color': 'Couleur du texte',
        'presets.title': '💾 Presets',
        'presets.namePlaceholder': 'Nom...',
        'presets.save': 'Sauvegarder',
        'presets.selectPlaceholder': 'Presets...',
        'presets.delete': 'Supprimer',
        'presets.noName': 'Veuillez entrer un nom pour le preset.',
        'presets.nameTooLong': 'Le nom est trop long (50 caractères max).',
        'presets.notFound': 'Ce preset n\'existe plus.',
        'presets.confirmDelete': 'Supprimer le preset « {name} » ?',
        'reset.button': '🔄 Réinitialiser les styles',
        'result.title': 'QR Code',
        'result.details': 'Détails du virement',
        'result.beneficiary': 'Bénéficiaire:',
        'result.iban': 'IBAN:',
        'result.amount': 'Montant:',
        'result.remittance': 'Communication:',
        'result.amountFree': 'Montant libre',
        'result.download': '📥 Télécharger PNG',
        'result.copy': '📋 Copier l\'image',
        'result.new': 'Nouveau',
        'iban.valid': '✓ IBAN valide ({country})',
        'iban.invalid': '✗ {error}',
        'action.copied': '✓ Copié !',
        'action.copyError': '❌ Impossible de copier l\'image. Utilisez le bouton Télécharger.',
        'history.title': '📋 Historique des QR Codes',
        'history.empty': 'Aucun QR code dans l\'historique',
        'history.clear': '🗑️ Effacer l\'historique',
        'history.confirmClear': '⚠️ Êtes-vous sûr de vouloir effacer tout l\'historique ?',
        'history.free': 'Libre',
        'error.generic': '❌ Erreur : {message}',
    },
    en: {
        'app.title': 'EPC QR Code Generator',
        'app.subtitle': 'QR codes for SEPA transfers',
        'lang.label': 'Language',
        'form.beneficiary': 'Beneficiary name *',
        'form.beneficiary.placeholder': 'Wikimedia Foundation',
        'form.beneficiary.count': '{count}/70 characters',
        'form.iban': 'IBAN *',
        'form.iban.placeholder': 'BE68 5390 0754 7034',
        'form.amount': 'Amount (EUR)',
        'form.amount.placeholder': '123.45',
        'form.amount.optional': 'Leave empty for free amount',
        'form.remittance': 'Remittance / Reference',
        'form.remittance.placeholder': 'Donation for Wikipedia',
        'form.remittance.count': '{count}/140 characters',
        'form.submit': 'Generate QR Code',
        'form.reset': 'Reset',
        'customize.title': '🎨 QR Code Customization',
        'customize.resolution': 'Export resolution',
        'customize.resolution.standard': 'Standard (300px)',
        'customize.resolution.high': 'High (600px)',
        'customize.resolution.veryhigh': 'Very high (900px)',
        'customize.resolution.ultra': 'Ultra (1200px)',
        'logo.title': '📷 Logo',
        'logo.image': 'Image',
        'logo.remove': 'Remove logo',
        'logo.shape': 'Shape',
        'logo.square': 'Square',
        'logo.round': 'Round',
        'logo.original': 'Original',
        'logo.size': 'Size',
        'logo.fit': 'Fit',
        'logo.deform': 'Stretch',
        'logo.crop': 'Crop',
        'pixels.title': '🎨 Pixels',
        'pixels.shape': 'Shape',
        'pixels.square': 'Square',
        'pixels.rounded': 'Rounded',
        'pixels.dots': 'Dots',
        'pixels.color': 'Color',
        'pixels.background': 'Background',
        'finders.title': '🔲 Finder Patterns (3 corners)',
        'finders.style': 'Corner style',
        'finders.square': 'Square',
        'finders.rounded': 'Rounded',
        'finders.circle': 'Circle',
        'finders.frameColor': 'Frame color',
        'finders.dotColor': 'Dot color',
        'transaction.title': '📝 Transaction Info',
        'transaction.show': 'Show',
        'transaction.showLabel': 'Show transaction info',
        'transaction.font': 'Font',
        'transaction.size': 'Size',
        'transaction.color': 'Text color',
        'presets.title': '💾 Presets',
        'presets.namePlaceholder': 'Name...',
        'presets.save': 'Save',
        'presets.selectPlaceholder': 'Presets...',
        'presets.delete': 'Delete',
        'presets.noName': 'Please enter a name for the preset.',
        'presets.nameTooLong': 'Name is too long (50 chars max).',
        'presets.notFound': 'This preset no longer exists.',
        'presets.confirmDelete': 'Delete preset « {name} » ?',
        'reset.button': '🔄 Reset styles',
        'result.title': 'QR Code',
        'result.details': 'Transfer details',
        'result.beneficiary': 'Beneficiary:',
        'result.iban': 'IBAN:',
        'result.amount': 'Amount:',
        'result.remittance': 'Remittance:',
        'result.amountFree': 'Free amount',
        'result.download': '📥 Download PNG',
        'result.copy': '📋 Copy image',
        'result.new': 'New',
        'iban.valid': '✓ Valid IBAN ({country})',
        'iban.invalid': '✗ {error}',
        'action.copied': '✓ Copied!',
        'action.copyError': '❌ Cannot copy image. Please use the Download button.',
        'history.title': '📋 QR Code History',
        'history.empty': 'No QR codes in history',
        'history.clear': '🗑️ Clear history',
        'history.confirmClear': '⚠️ Are you sure you want to clear all history?',
        'history.free': 'Free',
        'error.generic': '❌ Error: {message}',
    },
    de: {
        'app.title': 'EPC QR-Code Generator',
        'app.subtitle': 'QR-Codes für SEPA-Überweisungen',
        'lang.label': 'Sprache',
        'form.beneficiary': 'Name des Begünstigten *',
        'form.beneficiary.placeholder': 'Wikimedia Foundation',
        'form.beneficiary.count': '{count}/70 Zeichen',
        'form.iban': 'IBAN *',
        'form.iban.placeholder': 'BE68 5390 0754 7034',
        'form.amount': 'Betrag (EUR)',
        'form.amount.placeholder': '123.45',
        'form.amount.optional': 'Leer lassen für freien Betrag',
        'form.remittance': 'Verwendungszweck',
        'form.remittance.placeholder': 'Spende für Wikipedia',
        'form.remittance.count': '{count}/140 Zeichen',
        'form.submit': 'QR-Code generieren',
        'form.reset': 'Zurücksetzen',
        'customize.title': '🎨 QR-Code anpassen',
        'customize.resolution': 'Export-Auflösung',
        'customize.resolution.standard': 'Standard (300px)',
        'customize.resolution.high': 'Hoch (600px)',
        'customize.resolution.veryhigh': 'Sehr hoch (900px)',
        'customize.resolution.ultra': 'Ultra (1200px)',
        'logo.title': '📷 Logo',
        'logo.image': 'Bild',
        'logo.remove': 'Logo entfernen',
        'logo.shape': 'Form',
        'logo.square': 'Quadrat',
        'logo.round': 'Rund',
        'logo.original': 'Original',
        'logo.size': 'Größe',
        'logo.fit': 'Anpassung',
        'logo.deform': 'Strecken',
        'logo.crop': 'Zuschneiden',
        'pixels.title': '🎨 Pixel',
        'pixels.shape': 'Form',
        'pixels.square': 'Quadrat',
        'pixels.rounded': 'Abgerundet',
        'pixels.dots': 'Punkte',
        'pixels.color': 'Farbe',
        'pixels.background': 'Hintergrund',
        'finders.title': '🔲 Eckmuster (3 Ecken)',
        'finders.style': 'Ecken-Stil',
        'finders.square': 'Quadrat',
        'finders.rounded': 'Abgerundet',
        'finders.circle': 'Kreis',
        'finders.frameColor': 'Rahmenfarbe',
        'finders.dotColor': 'Punktfarbe',
        'transaction.title': '📝 Transaktionsinfo',
        'transaction.show': 'Anzeigen',
        'transaction.showLabel': 'Transaktionsinfo anzeigen',
        'transaction.font': 'Schriftart',
        'transaction.size': 'Größe',
        'transaction.color': 'Schriftfarbe',
        'presets.title': '💾 Presets',
        'presets.namePlaceholder': 'Name...',
        'presets.save': 'Speichern',
        'presets.selectPlaceholder': 'Presets...',
        'presets.delete': 'Löschen',
        'presets.noName': 'Bitte Namen für das Preset eingeben.',
        'presets.nameTooLong': 'Name zu lang (max. 50 Zeichen).',
        'presets.notFound': 'Dieses Preset existiert nicht mehr.',
        'presets.confirmDelete': 'Preset « {name} » löschen?',
        'reset.button': '🔄 Stile zurücksetzen',
        'result.title': 'QR-Code',
        'result.details': 'Überweisungsdetails',
        'result.beneficiary': 'Begünstigter:',
        'result.iban': 'IBAN:',
        'result.amount': 'Betrag:',
        'result.remittance': 'Verwendungszweck:',
        'result.amountFree': 'Freier Betrag',
        'result.download': '📥 PNG herunterladen',
        'result.copy': '📋 Bild kopieren',
        'result.new': 'Neu',
        'iban.valid': '✓ Gültige IBAN ({country})',
        'iban.invalid': '✗ {error}',
        'action.copied': '✓ Kopiert!',
        'action.copyError': '❌ Bild kann nicht kopiert werden. Bitte Download-Button verwenden.',
        'history.title': '📋 QR-Code-Verlauf',
        'history.empty': 'Keine QR-Codes im Verlauf',
        'history.clear': '🗑️ Verlauf löschen',
        'history.confirmClear': '⚠️ Wirklich den gesamten Verlauf löschen?',
        'history.free': 'Frei',
        'error.generic': '❌ Fehler: {message}',
    },
    es: {
        'app.title': 'Generador de QR EPC',
        'app.subtitle': 'Códigos QR para transferencias SEPA',
        'lang.label': 'Idioma',
        'form.beneficiary': 'Nombre del beneficiario *',
        'form.beneficiary.placeholder': 'Wikimedia Foundation',
        'form.beneficiary.count': '{count}/70 caracteres',
        'form.iban': 'IBAN *',
        'form.iban.placeholder': 'BE68 5390 0754 7034',
        'form.amount': 'Importe (EUR)',
        'form.amount.placeholder': '123.45',
        'form.amount.optional': 'Dejar vacío para importe libre',
        'form.remittance': 'Concepto / Referencia',
        'form.remittance.placeholder': 'Donación para Wikipedia',
        'form.remittance.count': '{count}/140 caracteres',
        'form.submit': 'Generar QR',
        'form.reset': 'Reiniciar',
        'customize.title': '🎨 Personalizar QR',
        'customize.resolution': 'Resolución de exportación',
        'customize.resolution.standard': 'Estándar (300px)',
        'customize.resolution.high': 'Alta (600px)',
        'customize.resolution.veryhigh': 'Muy alta (900px)',
        'customize.resolution.ultra': 'Ultra (1200px)',
        'logo.title': '📷 Logo',
        'logo.image': 'Imagen',
        'logo.remove': 'Eliminar logo',
        'logo.shape': 'Forma',
        'logo.square': 'Cuadrado',
        'logo.round': 'Redondo',
        'logo.original': 'Original',
        'logo.size': 'Tamaño',
        'logo.fit': 'Ajuste',
        'logo.deform': 'Estirar',
        'logo.crop': 'Recortar',
        'pixels.title': '🎨 Píxeles',
        'pixels.shape': 'Forma',
        'pixels.square': 'Cuadrado',
        'pixels.rounded': 'Redondeado',
        'pixels.dots': 'Puntos',
        'pixels.color': 'Color',
        'pixels.background': 'Fondo',
        'finders.title': '🔲 Patrones de esquina (3)',
        'finders.style': 'Estilo de esquina',
        'finders.square': 'Cuadrado',
        'finders.rounded': 'Redondeado',
        'finders.circle': 'Círculo',
        'finders.frameColor': 'Color del marco',
        'finders.dotColor': 'Color del punto',
        'transaction.title': '📝 Info de transacción',
        'transaction.show': 'Mostrar',
        'transaction.showLabel': 'Mostrar info de transacción',
        'transaction.font': 'Fuente',
        'transaction.size': 'Tamaño',
        'transaction.color': 'Color del texto',
        'presets.title': '💾 Presets',
        'presets.namePlaceholder': 'Nombre...',
        'presets.save': 'Guardar',
        'presets.selectPlaceholder': 'Presets...',
        'presets.delete': 'Eliminar',
        'presets.noName': 'Por favor ingrese un nombre para el preset.',
        'presets.nameTooLong': 'El nombre es demasiado largo (máx. 50 caracteres).',
        'presets.notFound': 'Este preset ya no existe.',
        'presets.confirmDelete': '¿Eliminar preset « {name} »?',
        'reset.button': '🔄 Restablecer estilos',
        'result.title': 'Código QR',
        'result.details': 'Detalles de la transferencia',
        'result.beneficiary': 'Beneficiario:',
        'result.iban': 'IBAN:',
        'result.amount': 'Importe:',
        'result.remittance': 'Concepto:',
        'result.amountFree': 'Importe libre',
        'result.download': '📥 Descargar PNG',
        'result.copy': '📋 Copiar imagen',
        'result.new': 'Nuevo',
        'iban.valid': '✓ IBAN válido ({country})',
        'iban.invalid': '✗ {error}',
        'action.copied': '✓ ¡Copiado!',
        'action.copyError': '❌ No se puede copiar la imagen. Use el botón Descargar.',
        'history.title': '📋 Historial de QR',
        'history.empty': 'No hay QR en el historial',
        'history.clear': '🗑️ Limpiar historial',
        'history.confirmClear': '⚠️ ¿Está seguro de borrar todo el historial?',
        'history.free': 'Libre',
        'error.generic': '❌ Error: {message}',
    },
    it: {
        'app.title': 'Generatore QR EPC',
        'app.subtitle': 'Codici QR per bonifici SEPA',
        'lang.label': 'Lingua',
        'form.beneficiary': 'Nome del beneficiario *',
        'form.beneficiary.placeholder': 'Wikimedia Foundation',
        'form.beneficiary.count': '{count}/70 caratteri',
        'form.iban': 'IBAN *',
        'form.iban.placeholder': 'BE68 5390 0754 7034',
        'form.amount': 'Importo (EUR)',
        'form.amount.placeholder': '123.45',
        'form.amount.optional': 'Lasciare vuoto per importo libero',
        'form.remittance': 'Causale / Riferimento',
        'form.remittance.placeholder': 'Donazione per Wikipedia',
        'form.remittance.count': '{count}/140 caratteri',
        'form.submit': 'Genera QR Code',
        'form.reset': 'Reimposta',
        'customize.title': '🎨 Personalizza QR',
        'customize.resolution': 'Risoluzione export',
        'customize.resolution.standard': 'Standard (300px)',
        'customize.resolution.high': 'Alta (600px)',
        'customize.resolution.veryhigh': 'Molto alta (900px)',
        'customize.resolution.ultra': 'Ultra (1200px)',
        'logo.title': '📷 Logo',
        'logo.image': 'Immagine',
        'logo.remove': 'Rimuovi logo',
        'logo.shape': 'Forma',
        'logo.square': 'Quadrato',
        'logo.round': 'Tondo',
        'logo.original': 'Originale',
        'logo.size': 'Dimensione',
        'logo.fit': 'Adattamento',
        'logo.deform': 'Deforma',
        'logo.crop': 'Ritaglia',
        'pixels.title': '🎨 Pixel',
        'pixels.shape': 'Forma',
        'pixels.square': 'Quadrato',
        'pixels.rounded': 'Arrotondato',
        'pixels.dots': 'Punti',
        'pixels.color': 'Colore',
        'pixels.background': 'Sfondo',
        'finders.title': '🔲 Pattern angolari (3)',
        'finders.style': 'Stile angoli',
        'finders.square': 'Quadrato',
        'finders.rounded': 'Arrotondato',
        'finders.circle': 'Cerchio',
        'finders.frameColor': 'Colore cornice',
        'finders.dotColor': 'Colore punto',
        'transaction.title': '📝 Info transazione',
        'transaction.show': 'Mostra',
        'transaction.showLabel': 'Mostra info transazione',
        'transaction.font': 'Carattere',
        'transaction.size': 'Dimensione',
        'transaction.color': 'Colore testo',
        'presets.title': '💾 Preset',
        'presets.namePlaceholder': 'Nome...',
        'presets.save': 'Salva',
        'presets.selectPlaceholder': 'Preset...',
        'presets.delete': 'Elimina',
        'presets.noName': 'Inserire un nome per il preset.',
        'presets.nameTooLong': 'Nome troppo lungo (max 50 caratteri).',
        'presets.notFound': 'Questo preset non esiste più.',
        'presets.confirmDelete': 'Eliminare il preset « {name} »?',
        'reset.button': '🔄 Reimposta stili',
        'result.title': 'QR Code',
        'result.details': 'Dettagli bonifico',
        'result.beneficiary': 'Beneficiario:',
        'result.iban': 'IBAN:',
        'result.amount': 'Importo:',
        'result.remittance': 'Causale:',
        'result.amountFree': 'Importo libero',
        'result.download': '📥 Scarica PNG',
        'result.copy': '📋 Copia immagine',
        'result.new': 'Nuovo',
        'iban.valid': '✓ IBAN valido ({country})',
        'iban.invalid': '✗ {error}',
        'action.copied': '✓ Copiato!',
        'action.copyError': '❌ Impossibile copiare l\'immagine. Usa il pulsante Scarica.',
        'history.title': '📋 Cronologia QR',
        'history.empty': 'Nessun QR nella cronologia',
        'history.clear': '🗑️ Cancella cronologia',
        'history.confirmClear': '⚠️ Sei sicuro di voler cancellare tutta la cronologia?',
        'history.free': 'Libero',
        'error.generic': '❌ Errore: {message}',
    },
    nl: {
        'app.title': 'EPC QR-code Generator',
        'app.subtitle': 'QR-codes voor SEPA-overboekingen',
        'lang.label': 'Taal',
        'form.beneficiary': 'Naam begunstigde *',
        'form.beneficiary.placeholder': 'Wikimedia Foundation',
        'form.beneficiary.count': '{count}/70 tekens',
        'form.iban': 'IBAN *',
        'form.iban.placeholder': 'BE68 5390 0754 7034',
        'form.amount': 'Bedrag (EUR)',
        'form.amount.placeholder': '123.45',
        'form.amount.optional': 'Leeg laten voor vrij bedrag',
        'form.remittance': 'Mededeling / Referentie',
        'form.remittance.placeholder': 'Donatie voor Wikipedia',
        'form.remittance.count': '{count}/140 tekens',
        'form.submit': 'QR-code genereren',
        'form.reset': 'Herstellen',
        'customize.title': '🎨 QR-code aanpassen',
        'customize.resolution': 'Exportresolutie',
        'customize.resolution.standard': 'Standaard (300px)',
        'customize.resolution.high': 'Hoog (600px)',
        'customize.resolution.veryhigh': 'Zeer hoog (900px)',
        'customize.resolution.ultra': 'Ultra (1200px)',
        'logo.title': '📷 Logo',
        'logo.image': 'Afbeelding',
        'logo.remove': 'Logo verwijderen',
        'logo.shape': 'Vorm',
        'logo.square': 'Vierkant',
        'logo.round': 'Rond',
        'logo.original': 'Origineel',
        'logo.size': 'Grootte',
        'logo.fit': 'Aanpassing',
        'logo.deform': 'Rekken',
        'logo.crop': 'Bijsnijden',
        'pixels.title': '🎨 Pixels',
        'pixels.shape': 'Vorm',
        'pixels.square': 'Vierkant',
        'pixels.rounded': 'Afgerond',
        'pixels.dots': 'Stippen',
        'pixels.color': 'Kleur',
        'pixels.background': 'Achtergrond',
        'finders.title': '🔲 Hoekpatronen (3)',
        'finders.style': 'Hoekstijl',
        'finders.square': 'Vierkant',
        'finders.rounded': 'Afgerond',
        'finders.circle': 'Cirkel',
        'finders.frameColor': 'Kaderkleur',
        'finders.dotColor': 'Stipkleur',
        'transaction.title': '📝 Transactie-info',
        'transaction.show': 'Toon',
        'transaction.showLabel': 'Transactie-info tonen',
        'transaction.font': 'Lettertype',
        'transaction.size': 'Grootte',
        'transaction.color': 'Tekstkleur',
        'presets.title': '💾 Presets',
        'presets.namePlaceholder': 'Naam...',
        'presets.save': 'Opslaan',
        'presets.selectPlaceholder': 'Presets...',
        'presets.delete': 'Verwijderen',
        'presets.noName': 'Voer een naam in voor het preset.',
        'presets.nameTooLong': 'Naam is te lang (max 50 tekens).',
        'presets.notFound': 'Dit preset bestaat niet meer.',
        'presets.confirmDelete': 'Preset « {name} » verwijderen?',
        'reset.button': '🔄 Stijlen herstellen',
        'result.title': 'QR-code',
        'result.details': 'Overboekingsgegevens',
        'result.beneficiary': 'Begunstigde:',
        'result.iban': 'IBAN:',
        'result.amount': 'Bedrag:',
        'result.remittance': 'Mededeling:',
        'result.amountFree': 'Vrij bedrag',
        'result.download': '📥 PNG downloaden',
        'result.copy': '📋 Afbeelding kopiëren',
        'result.new': 'Nieuw',
        'iban.valid': '✓ Geldig IBAN ({country})',
        'iban.invalid': '✗ {error}',
        'action.copied': '✓ Gekopieerd!',
        'action.copyError': '❌ Kan afbeelding niet kopiëren. Gebruik de downloadknop.',
        'history.title': '📋 QR-code geschiedenis',
        'history.empty': 'Geen QR-codes in geschiedenis',
        'history.clear': '🗑️ Geschiedenis wissen',
        'history.confirmClear': '⚠️ Weet u zeker dat u de hele geschiedenis wilt wissen?',
        'history.free': 'Vrij',
        'error.generic': '❌ Fout: {message}',
    }
};

export function getLang() {
    try { const stored = localStorage.getItem(STORAGE_KEY); if (stored && TRANSLATIONS[stored]) return stored; } catch {}
    const browserLang = (navigator.language || 'fr').slice(0, 2).toLowerCase();
    return TRANSLATIONS[browserLang] ? browserLang : 'fr';
}

export function setLang(lang) {
    if (!TRANSLATIONS[lang]) lang = 'fr';
    try { localStorage.setItem(STORAGE_KEY, lang); } catch {}
    translatePage();
    window.dispatchEvent(new CustomEvent('languagechange', { detail: { lang } }));
}

export function t(key, vars = {}) {
    const lang = getLang();
    let text = TRANSLATIONS[lang]?.[key];
    if (text === undefined) text = TRANSLATIONS['fr']?.[key] || key;
    if (vars) { Object.entries(vars).forEach(([k, v]) => { text = text.replace(`{${k}}`, v); }); }
    return text;
}

export function translatePage() {
    document.querySelectorAll('[data-i18n]').forEach(el => { const key = el.getAttribute('data-i18n'); el.textContent = t(key); });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => { const key = el.getAttribute('data-i18n-placeholder'); el.placeholder = t(key); });
    document.querySelectorAll('[data-i18n-title]').forEach(el => { const key = el.getAttribute('data-i18n-title'); el.title = t(key); });
    document.documentElement.lang = getLang();
}
```

---

## `epcGenerator.js` — Génération chaîne EPC

```js
class EPCGenerator {
    constructor() {
        this.version = '002';
        this.charset = '1';
        this.identification = 'SCT';
    }

    generate(data) {
        const { beneficiary, iban, amount, remittance, bic = '' } = data;
        this.validate(data);
        const lines = [
            'BCD', this.version, this.charset, this.identification,
            bic.toUpperCase(), this.sanitize(beneficiary, 70),
            this.formatIBAN(iban), this.formatAmount(amount),
            '', '', this.sanitize(remittance, 140), ''
        ];
        return lines.join('\n');
    }

    validate(data) {
        if (!data.beneficiary || data.beneficiary.trim().length === 0) throw new Error('Le nom du bénéficiaire est requis');
        if (!data.iban) throw new Error('L\'IBAN est requis');
        if (data.amount !== undefined && data.amount !== null && data.amount !== '') {
            const amount = parseFloat(data.amount);
            if (isNaN(amount) || amount < 0.01 || amount > 999999999.99) throw new Error('Le montant doit être entre 0.01 et 999999999.99');
        }
        if (data.beneficiary.length > 70) throw new Error('Le nom du bénéficiaire ne peut pas dépasser 70 caractères');
        if (data.remittance && data.remittance.length > 140) throw new Error('La communication ne peut pas dépasser 140 caractères');
    }

    sanitize(text, maxLength) {
        if (!text) return '';
        return text.trim().replace(/[^\x20-\x7E]/g, '').substring(0, maxLength);
    }

    formatIBAN(iban) { return iban.replace(/\s/g, '').toUpperCase(); }

    formatAmount(amount) {
        if (amount === undefined || amount === null || amount === '') return '';
        return `EUR${parseFloat(amount).toFixed(2)}`;
    }
}
export default EPCGenerator;
```

---

## `ibanValidator.js` — Validation IBAN

```js
class IBANValidator {
    constructor() {
        this.ibanLengths = {
            'AD': 24, 'AT': 20, 'BE': 16, 'BG': 22, 'CH': 21,
            'CY': 28, 'CZ': 24, 'DE': 22, 'DK': 18, 'EE': 20,
            'ES': 24, 'FI': 18, 'FR': 27, 'GB': 22, 'GI': 23,
            'GR': 27, 'HR': 21, 'HU': 28, 'IE': 22, 'IS': 26,
            'IT': 27, 'LI': 21, 'LT': 20, 'LU': 20, 'LV': 21,
            'MC': 27, 'MT': 31, 'NL': 18, 'NO': 15, 'PL': 28,
            'PT': 25, 'RO': 24, 'SE': 24, 'SI': 19, 'SK': 24,
            'SM': 27, 'VA': 22
        };
    }

    validate(iban) {
        const cleanIban = iban.replace(/\s/g, '').toUpperCase();
        if (!/^[A-Z]{2}\d{2}[A-Z0-9]+$/.test(cleanIban)) return { valid: false, error: 'Format IBAN invalide' };
        const countryCode = cleanIban.substring(0, 2);
        const expectedLength = this.ibanLengths[countryCode];
        if (!expectedLength) return { valid: false, error: `Code pays ${countryCode} non supporté dans l'EEE` };
        if (cleanIban.length !== expectedLength) return { valid: false, error: `Longueur incorrecte pour ${countryCode} (attendu: ${expectedLength}, reçu: ${cleanIban.length})` };
        const rearranged = cleanIban.substring(4) + cleanIban.substring(0, 4);
        const numericString = this.toNumericString(rearranged);
        const remainder = this.mod97(numericString);
        if (remainder !== 1) return { valid: false, error: 'Somme de contrôle invalide' };
        return { valid: true, country: countryCode };
    }

    toNumericString(str) { return str.replace(/[A-Z]/g, char => char.charCodeAt(0) - 55); }

    mod97(str) {
        let remainder = 0;
        for (let i = 0; i < str.length; i++) remainder = (remainder * 10 + parseInt(str[i])) % 97;
        return remainder;
    }

    format(iban) {
        const clean = iban.replace(/\s/g, '').toUpperCase();
        return clean.match(/.{1,4}/g)?.join(' ') || clean;
    }
}
export default IBANValidator;
```

---

## `storage.js` — Historique localStorage

```js
class QRHistory {
    constructor() {
        this.storageKey = 'epc_qr_history';
        this.maxItems = 10;
    }

    save(qrData) {
        const history = this.getAll();
        history.unshift({ ...qrData, timestamp: new Date().toISOString() });
        const trimmed = history.slice(0, this.maxItems);
        try { localStorage.setItem(this.storageKey, JSON.stringify(trimmed)); return true; }
        catch (error) { console.error('Erreur lors de la sauvegarde dans localStorage:', error); return false; }
    }

    getAll() {
        try { const data = localStorage.getItem(this.storageKey); return data ? JSON.parse(data) : []; }
        catch (error) { console.error('Erreur lors de la lecture de localStorage:', error); return []; }
    }

    clear() {
        try { localStorage.removeItem(this.storageKey); return true; }
        catch (error) { console.error('Erreur lors de l\'effacement de localStorage:', error); return false; }
    }

    getByIndex(index) {
        const history = this.getAll();
        return history[index] || null;
    }
}
export default QRHistory;
```

---

## `darkMode.js`

```js
class DarkMode {
    constructor() {
        this.storageKey = 'darkMode';
        this.isDark = this.loadPreference();
    }

    loadPreference() {
        try { return localStorage.getItem(this.storageKey) === 'true'; }
        catch { return false; }
    }

    savePreference() {
        try { localStorage.setItem(this.storageKey, this.isDark.toString()); } catch {}
    }

    enable() { this.isDark = true; document.documentElement.classList.add('dark'); this.savePreference(); }
    disable() { this.isDark = false; document.documentElement.classList.remove('dark'); this.savePreference(); }
    toggle() { this.isDark ? this.disable() : this.enable(); }
    apply() { this.isDark ? document.documentElement.classList.add('dark') : document.documentElement.classList.remove('dark'); }
    isEnabled() { return this.isDark; }
}
export default DarkMode;
```

---

## Architecture HTML (index.html)

Le HTML de la version web utilise Tailwind CSS via CDN et une structure de layout en accordéon. Les sections clés :

1. **Header** : logo, titre, sélecteur de langue
2. **Formulaire EPC** : champs bénéficiaire, IBAN, montant, communication
3. **Accordéon personnalisation** (pliable) :
   - Logo : upload, forme (carré/rond/original), ajustement (déformer/recadrer), taille (slider)
   - Pixels : forme (carré/arrondi/points), couleur, fond
   - Finder Patterns : style (carré/arrondi/cercle), couleur cadre, couleur point
   - Infos transaction : afficher, police, taille (slider), couleur texte
   - Résolution export : 300/600/900/1200px
4. **Presets** : sauvegarder, charger, supprimer
5. **QR Result** : canvas QR + infos transaction + boutons télécharger/copier/nouveau
6. **Historique modal** : liste des 10 derniers QR générés
