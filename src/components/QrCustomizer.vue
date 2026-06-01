<template>
	<div class="customizer-root">
		<!-- Logo Section -->
		<div class="customizer-section">
			<h3>{{ t('epc_qrcode_generator', 'Logo') }}</h3>
			<div class="customizer-row">
				<NcButton @click="$refs.logoInput.click()">
					{{ t('epc_qrcode_generator', 'Upload logo') }}
				</NcButton>
				<input
					ref="logoInput"
					type="file"
					accept="image/*"
					style="display: none"
					@change="onLogoUpload" />
				<NcButton
					v-if="logoPreview"
					type="tertiary"
					@click="removeLogo">
					{{ t('epc_qrcode_generator', 'Remove') }}
				</NcButton>
			</div>
			<div v-if="logoPreview" class="logo-preview">
				<img :src="logoPreview" alt="Logo preview" class="logo-preview-img" />
			</div>

			<div class="customizer-row">
				<label>{{ t('epc_qrcode_generator', 'Shape') }}</label>
				<div class="radio-group">
					<label v-for="opt in logoShapeOptions" :key="opt.value" class="radio-label">
						<input
							type="radio"
							name="logoShape"
							:value="opt.value"
							:checked="options.logoShape === opt.value"
							@change="updateOption('logoShape', opt.value)" />
						{{ opt.label }}
					</label>
				</div>
			</div>

			<div class="customizer-row">
				<label>{{ t('epc_qrcode_generator', 'Fit') }}</label>
				<div class="radio-group">
					<label v-for="opt in logoFitOptions" :key="opt.value" class="radio-label">
						<input
							type="radio"
							name="logoFit"
							:value="opt.value"
							:checked="options.logoFit === opt.value"
							@change="updateOption('logoFit', opt.value)" />
						{{ opt.label }}
					</label>
				</div>
			</div>

			<div class="customizer-row">
				<label>{{ t('epc_qrcode_generator', 'Size') }}: {{ options.logoSize }}%</label>
				<input
					type="range"
					min="10"
					max="40"
					:value="options.logoSize"
					@input="updateOption('logoSize', parseInt($event.target.value))" />
			</div>
		</div>

		<!-- Pixels Section -->
		<div class="customizer-section">
			<h3>{{ t('epc_qrcode_generator', 'Pixels') }}</h3>
			<div class="customizer-row">
				<label>{{ t('epc_qrcode_generator', 'Shape') }}</label>
				<select :value="options.pixelShape" @change="updateOption('pixelShape', $event.target.value)">
					<option value="square">{{ t('epc_qrcode_generator', 'Square') }}</option>
					<option value="rounded">{{ t('epc_qrcode_generator', 'Rounded') }}</option>
					<option value="dots">{{ t('epc_qrcode_generator', 'Dots') }}</option>
				</select>
			</div>
			<div class="customizer-row">
				<label>{{ t('epc_qrcode_generator', 'Color') }}</label>
				<input
					type="color"
					:value="options.pixelColor"
					@input="updateOption('pixelColor', $event.target.value)" />
				<input
					type="text"
					:value="options.pixelColor"
					@input="onColorTextInput('pixelColor', $event.target.value)"
					maxlength="7" />
			</div>
			<div class="customizer-row">
				<label>{{ t('epc_qrcode_generator', 'Background') }}</label>
				<input
					type="color"
					:value="options.bgColor"
					@input="updateOption('bgColor', $event.target.value)" />
				<input
					type="text"
					:value="options.bgColor"
					@input="onColorTextInput('bgColor', $event.target.value)"
					maxlength="7" />
			</div>
		</div>

		<!-- Finder Patterns Section -->
		<div class="customizer-section">
			<h3>{{ t('epc_qrcode_generator', 'Finder Patterns') }}</h3>
			<div class="customizer-row">
				<label>{{ t('epc_qrcode_generator', 'Style') }}</label>
				<select :value="options.cornersStyle" @change="updateOption('cornersStyle', $event.target.value)">
					<option value="square">{{ t('epc_qrcode_generator', 'Square') }}</option>
					<option value="rounded">{{ t('epc_qrcode_generator', 'Rounded') }}</option>
					<option value="circle">{{ t('epc_qrcode_generator', 'Circle') }}</option>
				</select>
			</div>
			<div class="customizer-row">
				<label>{{ t('epc_qrcode_generator', 'Frame color') }}</label>
				<input
					type="color"
					:value="options.cornersFrameColor"
					@input="updateOption('cornersFrameColor', $event.target.value)" />
				<input
					type="text"
					:value="options.cornersFrameColor"
					@input="onColorTextInput('cornersFrameColor', $event.target.value)"
					maxlength="7" />
			</div>
			<div class="customizer-row">
				<label>{{ t('epc_qrcode_generator', 'Dot color') }}</label>
				<input
					type="color"
					:value="options.cornersDotColor"
					@input="updateOption('cornersDotColor', $event.target.value)" />
				<input
					type="text"
					:value="options.cornersDotColor"
					@input="onColorTextInput('cornersDotColor', $event.target.value)"
					maxlength="7" />
			</div>
		</div>

		<!-- Transaction Text Section -->
		<div class="customizer-section">
			<h3>{{ t('epc_qrcode_generator', 'Transaction text') }}</h3>
			<div class="customizer-row">
				<label class="radio-label">
					<input
						type="checkbox"
						:checked="options.textEnabled"
						@change="updateOption('textEnabled', $event.target.checked)" />
					{{ t('epc_qrcode_generator', 'Show transaction info') }}
				</label>
			</div>
			<div v-if="options.textEnabled">
				<div class="customizer-row">
					<label>{{ t('epc_qrcode_generator', 'Font') }}</label>
					<select :value="options.textFontFamily" @change="updateOption('textFontFamily', $event.target.value)">
						<option value="Arial, sans-serif">Arial</option>
						<option value="'Courier New', monospace">Courier New</option>
						<option value="Georgia, serif">Georgia</option>
						<option value="'Times New Roman', serif">Times New Roman</option>
						<option value="'Trebuchet MS', sans-serif">Trebuchet MS</option>
						<option value="Verdana, sans-serif">Verdana</option>
					</select>
				</div>
				<div class="customizer-row">
					<label>{{ t('epc_qrcode_generator', 'Size') }}: {{ options.textFontSize }}px</label>
					<input
						type="range"
						min="10"
						max="32"
						:value="options.textFontSize"
						@input="updateOption('textFontSize', parseInt($event.target.value))" />
				</div>
				<div class="customizer-row">
					<label>{{ t('epc_qrcode_generator', 'Color') }}</label>
					<input
						type="color"
						:value="options.textColor"
						@input="updateOption('textColor', $event.target.value)" />
					<input
						type="text"
						:value="options.textColor"
						@input="onColorTextInput('textColor', $event.target.value)"
						maxlength="7" />
				</div>
			</div>
		</div>

		<!-- Resolution Section -->
		<div class="customizer-section">
			<h3>{{ t('epc_qrcode_generator', 'Export resolution') }}</h3>
			<div class="customizer-row">
				<select :value="options.qrResolution" @change="updateOption('qrResolution', parseInt($event.target.value))">
					<option :value="300">300px</option>
					<option :value="600">600px</option>
					<option :value="900">900px</option>
					<option :value="1200">1200px</option>
				</select>
			</div>
		</div>

		<!-- Presets Section -->
		<div class="customizer-section">
			<h3>{{ t('epc_qrcode_generator', 'Presets') }}</h3>
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
		</div>

		<!-- Reset Styles -->
		<div class="customizer-section">
			<NcButton type="tertiary" @click="resetStyles">
				{{ t('epc_qrcode_generator', 'Reset styles') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'

export default {
	name: 'QrCustomizer',
	components: {
		NcButton,
	},
	props: {
		options: {
			type: Object,
			required: true,
		},
		presets: {
			type: Object,
			default: () => ({}),
		},
		logoPreview: {
			type: String,
			default: null,
		},
		groups: {
			type: Array,
			default: () => [],
		},
		presetMeta: {
			type: Object,
			default: () => ({}), // name -> { isOwner, sharedGroup, ownerDisplayName }
		},
	},
	emits: ['update:options', 'save-preset', 'load-preset', 'delete-preset', 'reset-styles', 'logo-upload', 'logo-remove'],
	data() {
		return {
			presetName: '',
			selectedPreset: '',
			presetShareGroup: '',
			logoShapeOptions: [
				{ value: 'square', label: 'Square' },
				{ value: 'round', label: 'Round' },
				{ value: 'original', label: 'Original' },
			],
			logoFitOptions: [
				{ value: 'deform', label: 'Stretch' },
				{ value: 'crop', label: 'Crop' },
			],
		}
	},
	methods: {
		onColorTextInput(key, value) {
			if (/^#[0-9a-f]{6}$/i.test(value)) {
				this.$emit('update:options', { ...this.options, [key]: value })
			}
		},
		updateOption(key, value) {
			this.$emit('update:options', { ...this.options, [key]: value })
		},
		onLogoUpload(event) {
			const file = event.target.files[0]
			if (!file) return
			const maxSize = 2 * 1024 * 1024 // 2MB
			if (file.size > maxSize) {
				OC.Notification.showTemporary(
					this.t('epc_qrcode_generator', 'Logo too large — max 2MB')
				)
				event.target.value = ''
				return
			}
			const reader = new FileReader()
			reader.onload = (e) => {
				this.$emit('logo-upload', e.target.result)
			}
			reader.readAsDataURL(file)
		},
		removeLogo() {
			this.$emit('logo-remove')
		},
		savePreset() {
			if (this.presetName.trim()) {
				this.$emit('save-preset', { name: this.presetName.trim(), sharedGroup: this.presetShareGroup || null })
				this.presetName = ''
				this.presetShareGroup = ''
			}
		},
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
		loadPreset() {
			if (this.selectedPreset) {
				this.$emit('load-preset', this.selectedPreset)
			}
		},
		deletePreset() {
			if (this.selectedPreset && confirm(`Delete preset "${this.selectedPreset}"?`)) {
				this.$emit('delete-preset', this.selectedPreset)
				this.selectedPreset = ''
			}
		},
		resetStyles() {
			this.$emit('reset-styles')
		},
	},
}
</script>

<style scoped>
.customizer-section {
	margin-bottom: 24px;
	padding-bottom: 16px;
	border-bottom: 1px solid var(--color-border);
}

.customizer-section h3 {
	font-size: 14px;
	font-weight: 600;
	margin-bottom: 12px;
	color: var(--color-text-maxcontrast);
}

.customizer-row {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 8px;
	flex-wrap: wrap;
}

.customizer-row label {
	font-size: 13px;
	min-width: 80px;
	color: var(--color-text-maxcontrast);
}

.radio-group {
	display: flex;
	gap: 12px;
}

.radio-label {
	display: flex;
	align-items: center;
	gap: 4px;
	font-size: 13px;
	cursor: pointer;
}

.logo-preview {
	margin: 8px 0;
}

.logo-preview-img {
	max-width: 80px;
	max-height: 80px;
	border-radius: 8px;
	border: 1px solid var(--color-border);
}

.preset-input {
	flex: 1;
	min-width: 120px;
}

.preset-select {
	flex: 1;
	min-width: 120px;
}

input[type="range"] {
	width: 120px;
}

input[type="color"] {
	width: 32px;
	height: 32px;
	padding: 0;
	border: 1px solid var(--color-border);
	border-radius: 4px;
	cursor: pointer;
}

input[type="text"][maxlength="7"] {
	width: 72px;
	font-family: monospace;
	font-size: 12px;
}

select {
	padding: 4px 8px;
	border: 1px solid var(--color-border);
	border-radius: 4px;
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 13px;
}
</style>
