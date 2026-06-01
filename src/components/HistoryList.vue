<template>
	<div class="history-list">
		<div
			v-for="item in items"
			:key="item.id"
			class="history-item">
			<div class="history-item-main">
				<div class="history-item-primary">
					<span class="history-beneficiary">{{ item.beneficiary }}</span>
					<span class="history-iban">{{ formatIban(item.iban) }}</span>
				</div>
				<div class="history-item-secondary">
					<span v-if="item.amount" class="history-amount">{{ item.amount }} EUR</span>
					<span v-if="item.remittance" class="history-remittance">{{ item.remittance }}</span>
					<span class="history-date">{{ formatDate(item.createdAt) }}</span>
					<span v-if="!item.isOwner" class="history-badge">
						{{ groupLabel(item.sharedGroup) }} · {{ item.ownerDisplayName }}
					</span>
					<span v-else-if="item.sharedGroup" class="history-badge">
						{{ t('epc_qrcode_generator', 'Shared with') }} {{ groupLabel(item.sharedGroup) }}
					</span>
				</div>
			</div>
			<div class="history-item-actions">
				<NcButton
					type="tertiary"
					@click="$emit('regenerate', item)">
					{{ t('epc_qrcode_generator', 'Re-generate') }}
				</NcButton>
				<select
					v-if="item.isOwner"
					class="history-share-select"
					:value="item.sharedGroup || ''"
					@change="$emit('share', { id: item.id, group: $event.target.value })">
					<option value="">{{ t('epc_qrcode_generator', 'Private') }}</option>
					<option v-for="g in groups" :key="g.id" :value="g.id">
						{{ g.displayName }}
					</option>
				</select>
				<NcButton
					v-if="item.isOwner"
					type="tertiary"
					@click="$emit('delete', item.id)">
					{{ t('epc_qrcode_generator', 'Delete') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'

export default {
	name: 'HistoryList',
	components: {
		NcButton,
	},
	props: {
		items: {
			type: Array,
			required: true,
		},
		groups: {
			type: Array,
			default: () => [],
		},
	},
	emits: ['delete', 'regenerate', 'share'],
	methods: {
		groupLabel(id) {
			const g = this.groups.find(x => x.id === id)
			return g ? g.displayName : id
		},
		formatIban(iban) {
			return (iban || '').replace(/\s+/g, '').toUpperCase().replace(/(.{4})/g, '$1 ').trim()
		},
		formatDate(timestamp) {
			if (!timestamp) return ''
			const d = new Date(timestamp * 1000)
			return d.toLocaleDateString(undefined, {
				day: 'numeric',
				month: 'short',
				year: 'numeric',
				hour: '2-digit',
				minute: '2-digit',
			})
		},
	},
}
</script>

<style scoped>
.history-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.history-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	gap: 12px;
}

.history-item:hover {
	background: var(--color-background-hover);
}

.history-item-main {
	flex: 1;
	min-width: 0;
}

.history-item-primary {
	display: flex;
	gap: 8px;
	align-items: baseline;
	flex-wrap: wrap;
}

.history-beneficiary {
	font-weight: 600;
	font-size: 14px;
}

.history-iban {
	font-family: monospace;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.history-item-secondary {
	display: flex;
	gap: 12px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	margin-top: 4px;
	flex-wrap: wrap;
}

.history-item-actions {
	display: flex;
	gap: 4px;
	flex-shrink: 0;
}

@media (max-width: 600px) {
	.history-item {
		flex-direction: column;
		align-items: flex-start;
	}
	.history-item-actions {
		width: 100%;
		justify-content: flex-end;
	}
}

.history-badge {
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	border-radius: 12px;
	padding: 1px 8px;
	font-size: 11px;
}

.history-share-select {
	max-width: 140px;
}
</style>
