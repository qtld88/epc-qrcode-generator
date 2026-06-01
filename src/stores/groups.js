import { defineStore } from 'pinia'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export const useGroupsStore = defineStore('groups', {
	state: () => ({
		items: [], // [{ id, displayName }]
		loaded: false,
	}),
	actions: {
		async fetchGroups() {
			if (this.loaded) return
			try {
				const response = await axios.get(generateUrl('/apps/epc_qrcode_generator/groups'))
				this.items = response.data
				this.loaded = true
			} catch (e) {
				console.error('Failed to fetch groups:', e)
			}
		},
	},
})
