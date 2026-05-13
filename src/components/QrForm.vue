<template>
	<div class="qr-form">
		<h2 class="form-title">
			{{ t('epc_qrcode_generator', 'SEPA Transfer Details') }}
		</h2>

		<!-- Beneficiary -->
		<div class="form-field">
			<NcTextField
				:model-value="formData.beneficiary"
				:label="t('epc_qrcode_generator', 'Beneficiary name *')"
				:placeholder="t('epc_qrcode_generator', 'Wikimedia Foundation')"
				:maxlength="70"
				@update:model-value="updateField('beneficiary', $event)" />
			<span class="char-count">{{ formData.beneficiary.length }}/70</span>
		</div>

		<!-- IBAN -->
		<div class="form-field">
			<NcTextField
				:model-value="formData.iban"
				:label="t('epc_qrcode_generator', 'IBAN *')"
				:placeholder="t('epc_qrcode_generator', 'BE68 5390 0754 7034')"
				:success="ibanValid === true"
				:error="ibanValid === false"
				:helper-text="ibanFeedback"
				@update:model-value="onIbanInput"
				@blur="onIbanBlur" />
		</div>

		<!-- Amount -->
		<div class="form-field">
			<NcTextField
				:model-value="formData.amount"
				:label="t('epc_qrcode_generator', 'Amount (EUR)')"
				:placeholder="t('epc_qrcode_generator', '123.45')"
				type="number"
				step="0.01"
				min="0"
				@update:model-value="updateField('amount', $event)" />
			<span class="field-hint">{{ t('epc_qrcode_generator', 'Leave empty for free amount') }}</span>
		</div>

		<!-- Remittance -->
		<div class="form-field">
			<NcTextField
				:model-value="formData.remittance"
				:label="t('epc_qrcode_generator', 'Remittance / Reference')"
				:placeholder="t('epc_qrcode_generator', 'Donation for Wikipedia')"
				:maxlength="140"
				@update:model-value="updateField('remittance', $event)" />
			<span class="char-count">{{ formData.remittance.length }}/140</span>
		</div>

		<!-- Actions -->
		<div class="form-actions">
			<NcButton variant="primary" type="button" @click="onSubmit">
				{{ t('epc_qrcode_generator', 'Generate QR Code') }}
			</NcButton>
			<NcButton type="button" @click="onReset">
				{{ t('epc_qrcode_generator', 'Reset') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'
import IBANValidator from '../lib/ibanValidator.js'

export default {
	name: 'QrForm',
	components: {
		NcTextField,
		NcButton,
	},
	props: {
		/** Initial form data */
		initialData: {
			type: Object,
			default: () => ({
				beneficiary: '',
				iban: '',
				amount: '',
				remittance: '',
			}),
		},
		/** Disable the form (e.g. while generating) */
		disabled: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['generate', 'reset-form'],
	data() {
		return {
			formData: { ...this.initialData },
			ibanValid: null, // null = not validated, true = valid, false = invalid
			ibanFeedback: '',
		}
	},
	created() {
		this.ibanValidator = new IBANValidator()
	},
	methods: {
		updateField(key, value) {
			this.formData = { ...this.formData, [key]: value }
		},
		onIbanInput(value) {
			this.formData = { ...this.formData, iban: value }
			this.validateIban()
		},
		onIbanBlur(value) {
			// Format IBAN on blur (add spaces every 4 chars)
			if (this.formData.iban) {
				const formatted = this.ibanValidator.format(this.formData.iban)
				this.formData = { ...this.formData, iban: formatted }
			}
		},
		validateIban() {
			const iban = this.formData.iban
			if (iban.length < 15) {
				this.ibanValid = null
				this.ibanFeedback = ''
				return
			}
			const result = this.ibanValidator.validate(iban)
			if (result.valid) {
				this.ibanValid = true
				this.ibanFeedback = `✓ ${result.country}`
			} else {
				this.ibanValid = false
				this.ibanFeedback = `✗ ${result.error}`
			}
		},
		onSubmit() {
			// Validate required fields
			if (!this.formData.beneficiary.trim()) {
				OC.Notification.showTemporary(this.t('epc_qrcode_generator', 'Beneficiary name is required'))
				return
			}
			if (!this.formData.iban.trim()) {
				OC.Notification.showTemporary(this.t('epc_qrcode_generator', 'IBAN is required'))
				return
			}

			// Validate IBAN format
			const ibanResult = this.ibanValidator.validate(this.formData.iban)
			if (!ibanResult.valid) {
				OC.Notification.showTemporary(`❌ ${ibanResult.error}`)
				return
			}

			this.$emit('generate', { ...this.formData })
		},
		onReset() {
			this.formData = {
				beneficiary: '',
				iban: '',
				amount: '',
				remittance: '',
			}
			this.ibanValid = null
			this.ibanFeedback = ''
			this.$emit('reset-form')
		},
	},
}
</script>

<style scoped>
.qr-form {
	padding: 16px;
}

.form-title {
	font-size: 18px;
	font-weight: 600;
	margin-bottom: 20px;
	color: var(--color-main-text);
}

.form-field {
	margin-bottom: 16px;
	position: relative;
}

.char-count {
	position: absolute;
	right: 8px;
	bottom: -4px;
	font-size: 11px;
	color: var(--color-text-maxcontrast);
}

.field-hint {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	margin-top: 2px;
	display: block;
}

.form-actions {
	display: flex;
	gap: 8px;
	margin-top: 24px;
}
</style>
