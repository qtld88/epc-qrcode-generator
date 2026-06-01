<template>
	<div class="history-page">
		<div class="history-header">
			<h2>{{ t('epc_qrcode_generator', 'History') }}</h2>
			<div v-if="showImportBanner" class="import-banner">
				<p>
					{{ t('epc_qrcode_generator', 'Import history from browser') }}
					({{ localStorageCount }} {{ t('epc_qrcode_generator', 'items') }})
				</p>
				<NcButton @click="importFromStorage">
					{{ importing ? t('epc_qrcode_generator', 'Importing...') : t('epc_qrcode_generator', 'Import') }}
				</NcButton>
			</div>
		</div>

		<NcTextField
			v-model="searchQuery"
			:placeholder="t('epc_qrcode_generator', 'Search history')"
			class="search-field" />

		<div v-if="store.loading" class="loading">
			{{ t('epc_qrcode_generator', 'Loading...') }}
		</div>

		<div v-else-if="filteredItems.length === 0" class="empty-state">
			<p>{{ t('epc_qrcode_generator', 'No history yet') }}</p>
		</div>

		<HistoryList
			v-else
			:items="filteredItems"
			:groups="groupsStore.items"
			@delete="onDelete"
			@regenerate="onRegenerate"
			@share="onShare" />
	</div>
</template>

<script>
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcButton from '@nextcloud/vue/components/NcButton'

import HistoryList from '../components/HistoryList.vue'
import { useHistoryStore } from '../stores/history.js'
import { useGroupsStore } from '../stores/groups.js'
import { hasLocalStorageData, getLocalStorageCount, importHistoryFromLocalStorage } from '../utils/migration.js'

export default {
	name: 'HistoryView',
	components: {
		NcTextField,
		NcButton,
		HistoryList,
	},
	data() {
		return {
			searchQuery: '',
			importing: false,
			showImportBanner: false,
			localStorageCount: 0,
		}
	},
	computed: {
		store() {
			return this.historyStore
		},
		filteredItems() {
			if (!this.searchQuery.trim()) return this.store.items
			const q = this.searchQuery.toLowerCase()
			return this.store.items.filter(item =>
				item.beneficiary.toLowerCase().includes(q) ||
				item.iban.includes(q) ||
				item.amount.includes(q) ||
				item.remittance.toLowerCase().includes(q)
			)
		},
	},
	created() {
		this.historyStore = useHistoryStore()
		this.historyStore.fetchHistory()
		this.groupsStore = useGroupsStore()
		this.groupsStore.fetchGroups()
		this.showImportBanner = hasLocalStorageData()
		this.localStorageCount = getLocalStorageCount()
	},
	methods: {
		async importFromStorage() {
			this.importing = true
			try {
				const count = await importHistoryFromLocalStorage(this.historyStore)
				if (count > 0) {
					OC.Notification.showTemporary(`${this.t('epc_qrcode_generator', 'History imported!')} (${count})`)
				}
				this.showImportBanner = false
				this.historyStore.fetchHistory()
			} catch (e) {
				OC.Notification.showTemporary(`❌ ${e.message}`)
			} finally {
				this.importing = false
			}
		},
		onDelete(id) {
			if (confirm(this.t('epc_qrcode_generator', 'Are you sure you want to delete this entry?'))) {
				this.historyStore.removeHistory(id)
			}
		},
		onRegenerate(item) {
			// Navigate to generator with form data
			this.$router.push({
				name: 'generator',
				query: {
					beneficiary: item.beneficiary,
					iban: item.iban,
					amount: item.amount,
					remittance: item.remittance,
				},
			})
		},
		async onShare({ id, group }) {
			try {
				await this.store.shareHistory(id, group)
			} catch (e) {
				OC.Notification.showTemporary(`❌ ${e.message}`)
			}
		},
	},
}
</script>

<style scoped>
.history-page {
	padding: 16px;
	max-width: 800px;
	margin: 0 auto;
}

.history-header {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin-bottom: 16px;
}

.history-header h2 {
	font-size: 20px;
	font-weight: 600;
	margin: 0;
}

.import-banner {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px;
	background: var(--color-info-light);
	border-radius: 8px;
	font-size: 14px;
}

.import-banner p {
	margin: 0;
	flex: 1;
}

.search-field {
	margin-bottom: 16px;
}

.loading,
.empty-state {
	text-align: center;
	padding: 40px;
	color: var(--color-text-maxcontrast);
}
</style>
