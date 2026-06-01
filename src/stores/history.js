import { defineStore } from 'pinia'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export const useHistoryStore = defineStore('history', {
	state: () => ({
		items: [],
		loading: false,
		error: null,
	}),

	getters: {
		historyCount: (state) => state.items.length,
		recentHistory: (state) => (limit = 10) => state.items.slice(0, limit),
	},

	actions: {
		async fetchHistory() {
			this.loading = true
			this.error = null
			try {
				const response = await axios.get(generateUrl('/apps/epc_qrcode_generator/history'))
				this.items = response.data
			} catch (e) {
				console.error('Failed to fetch history:', e)
				this.error = e.message || 'Failed to fetch history'
			} finally {
				this.loading = false
			}
		},

		async addHistory(entry) {
			try {
				const response = await axios.post(generateUrl('/apps/epc_qrcode_generator/history'), entry)
				this.items.unshift(response.data)
				return response.data
			} catch (e) {
				console.error('Failed to save history:', e)
				throw e
			}
		},

		async removeHistory(id) {
			try {
				await axios.delete(generateUrl(`/apps/epc_qrcode_generator/history/${id}`))
				this.items = this.items.filter(item => item.id !== id)
			} catch (e) {
				console.error('Failed to delete history entry:', e)
				throw e
			}
		},

		async shareHistory(id, group) {
			try {
				const response = await axios.post(
					generateUrl(`/apps/epc_qrcode_generator/history/${id}/share`),
					{ group: group || null },
				)
				const idx = this.items.findIndex(item => item.id === id)
				if (idx !== -1) {
					this.items.splice(idx, 1, response.data)
				}
				return response.data
			} catch (e) {
				console.error('Failed to share history entry:', e)
				throw e
			}
		},

		async importFromLocalStorage() {
			try {
				const stored = localStorage.getItem('epcQrHistory')
				if (!stored) return 0

				const localItems = JSON.parse(stored)
				let imported = 0

				for (const item of localItems) {
					try {
						await this.addHistory({
							beneficiary: item.formData?.beneficiary || '',
							iban: item.formData?.iban || '',
							amount: item.formData?.amount || '',
							remittance: item.formData?.remittance || '',
							epcString: item.epcString || '',
							createdAt: item.timestamp || Date.now(),
						})
						imported++
					} catch (e) {
						console.error('Failed to import history item:', e)
					}
				}

				localStorage.removeItem('epcQrHistory')
				return imported
			} catch (e) {
				console.error('Failed to import from localStorage:', e)
				throw e
			}
		},
	},
})
