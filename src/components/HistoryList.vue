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
				</div>
			</div>
			<div class="history-item-actions">
				<NcButton
					type="tertiary"
					@click="$emit('regenerate', item)">
					{{ t('epc_qrcode_generator', 'Re-generate') }}
				</NcButton>
				<NcButton
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
	},
	emits: ['delete', 'regenerate'],
	methods: {
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
</style>
