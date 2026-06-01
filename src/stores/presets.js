import { defineStore } from 'pinia'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export const usePresetsStore = defineStore('presets', {
	state: () => ({
		items: [],
		loading: false,
		error: null,
	}),

	getters: {
		presetList: (state) => state.items,
		presetNames: (state) => state.items.map(p => p.name),
	},

	actions: {
		async fetchPresets() {
			this.loading = true
			this.error = null
			try {
				const response = await axios.get(generateUrl('/apps/epc_qrcode_generator/presets'))
				this.items = response.data
			} catch (e) {
				console.error('Failed to fetch presets:', e)
				this.error = e.message || 'Failed to fetch presets'
			} finally {
				this.loading = false
			}
		},

		async savePreset(name, data) {
			try {
				const existing = this.items.find(p => p.name === name && p.isOwner !== false)
				const payload = {
					name,
					styleOptions: JSON.stringify(data.styleOptions || data),
					logoFile: data.logoFile || null,
					sharedGroup: data.sharedGroup || null,
				}
				if (existing) {
					await axios.put(generateUrl(`/apps/epc_qrcode_generator/presets/${existing.id}`), payload)
				} else {
					await axios.post(generateUrl('/apps/epc_qrcode_generator/presets'), payload)
				}
				// Refresh list
				await this.fetchPresets()
			} catch (e) {
				console.error('Failed to save preset:', e)
				throw e
			}
		},

		async loadPreset(id) {
			try {
				const response = await axios.get(generateUrl(`/apps/epc_qrcode_generator/presets/${id}`))
				return response.data
			} catch (e) {
				console.error('Failed to load preset:', e)
				throw e
			}
		},

		async deletePreset(id) {
			try {
				await axios.delete(generateUrl(`/apps/epc_qrcode_generator/presets/${id}`))
				this.items = this.items.filter(item => item.id !== id)
			} catch (e) {
				console.error('Failed to delete preset:', e)
				throw e
			}
		},
	},
})
