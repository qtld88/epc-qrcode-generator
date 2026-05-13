<template>
	<NcModal v-if="show" size="small" @close="$emit('close')">
		<div class="folder-picker">
			<h3 class="folder-picker-title">
				{{ t('epc_qrcode_generator', 'Save to Files') }}
			</h3>

			<NcBreadcrumbs>
				<NcBreadcrumb
					v-for="crumb in breadcrumbs"
					:key="crumb.path"
					:name="crumb.name"
					@click="navigateTo(crumb.path)" />
			</NcBreadcrumbs>

			<div class="folder-list">
				<div v-if="loading" class="folder-list-status">
					<NcLoadingIcon class="loading-icon" />
				</div>
				<template v-else>
					<NcListItem
						v-for="folder in folders"
						:key="folder.path"
						:name="folder.name"
						@click="navigateTo(folder.path)">
						<template #icon>
							<div class="folder-icon" />
						</template>
					</NcListItem>
					<p v-if="folders.length === 0" class="empty-state">
						{{ t('epc_qrcode_generator', 'No subfolders') }}
					</p>
				</template>
			</div>

			<div class="save-form">
				<p class="current-path">{{ currentPathDisplay }}</p>
				<NcTextField
					:model-value="filename"
					:label="t('epc_qrcode_generator', 'Filename')"
					:placeholder="t('epc_qrcode_generator', 'QRC_Remittance')"
					@update:model-value="filename = $event" />
				<NcButton
					class="save-button"
					:disabled="!filename.trim()"
					@click="onSave">
					{{ t('epc_qrcode_generator', 'Save') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcBreadcrumbs from '@nextcloud/vue/components/NcBreadcrumbs'
import NcBreadcrumb from '@nextcloud/vue/components/NcBreadcrumb'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcTextField from '@nextcloud/vue/components/NcTextField'

export default {
	name: 'FolderPicker',
	components: {
		NcButton,
		NcBreadcrumbs,
		NcBreadcrumb,
		NcListItem,
		NcLoadingIcon,
		NcModal,
		NcTextField,
	},
	props: {
		show: {
			type: Boolean,
			default: false,
		},
		initialPath: {
			type: String,
			default: '',
		},
		initialFilename: {
			type: String,
			default: 'qr-epc',
		},
	},
	emits: ['close', 'folder-selected'],
	data() {
		return {
			folders: [],
			currentPath: '',
			filename: this.initialFilename,
			loading: false,
		}
	},
	watch: {
		show(val) {
			if (val) {
				this.currentPath = this.initialPath || ''
				this.filename = this.initialFilename
				this.fetchFolders(this.currentPath)
			}
		},
	},
	computed: {
		breadcrumbs() {
			const crumbs = [{ name: '/', path: '' }]
			if (!this.currentPath || this.currentPath === '/') {
				return crumbs
			}
			const parts = this.currentPath.split('/').filter(Boolean)
			let accumulated = ''
			for (const part of parts) {
				accumulated += '/' + part
				crumbs.push({ name: part, path: accumulated })
			}
			return crumbs
		},
		currentPathDisplay() {
			return this.currentPath || '/'
		},
	},
	methods: {
		async fetchFolders(path) {
			this.loading = true
			this.folders = []
			try {
				const url = generateUrl('/apps/epc_qrcode_generator/folders')
				const { data } = await axios.get(url, {
					params: { path: path || '' },
				})
				this.folders = Array.isArray(data?.folders) ? data.folders : []
			} catch (error) {
				console.error('FolderPicker: failed to fetch folders:', error)
				this.folders = []
			} finally {
				this.loading = false
			}
		},
		navigateTo(path) {
			this.currentPath = path
			this.fetchFolders(path)
		},
		onSave() {
			const cleanName = this.filename.trim()
			if (!cleanName) return
			const filename = cleanName.endsWith('.png') ? cleanName : cleanName + '.png'
			this.$emit('folder-selected', {
				targetPath: this.currentPath || '/',
				filename,
			})
			this.$emit('close')
		},
	},
}
</script>

<style scoped>
.folder-picker {
	padding: 16px;
}

.folder-picker-title {
	font-size: 18px;
	font-weight: 600;
	margin: 0 0 16px;
	color: var(--color-main-text);
}

.folder-list {
	max-height: 300px;
	overflow-y: auto;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	margin: 12px 0;
}

.folder-list-status {
	display: flex;
	justify-content: center;
	align-items: center;
	padding: 24px;
}

.empty-state {
	text-align: center;
	padding: 24px;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
	margin: 0;
}

.folder-icon {
	width: 32px;
	height: 32px;
	background-color: var(--color-primary);
	mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z'/%3E%3C/svg%3E");
	-webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z'/%3E%3C/svg%3E");
	mask-size: contain;
	-webkit-mask-size: contain;
	mask-repeat: no-repeat;
	-webkit-mask-repeat: no-repeat;
	mask-position: center;
	-webkit-mask-position: center;
}

.save-form {
	margin-top: 12px;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.current-path {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	margin: 0;
	word-break: break-all;
}

.save-button {
	align-self: flex-end;
}
</style>
