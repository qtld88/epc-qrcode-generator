/**
 * Migration utility — detects and imports localStorage data to DB via API
 */

/**
 * Check if there is localStorage data to migrate
 */
export function hasLocalStorageData() {
	try {
		const history = localStorage.getItem('epcQrHistory')
		const presets = localStorage.getItem('epcQrPresets')
		return (history && JSON.parse(history).length > 0) || (presets && Object.keys(JSON.parse(presets)).length > 0)
	} catch {
		return false
	}
}

/**
 * Get count of items available for migration
 */
export function getLocalStorageCount() {
	try {
		const history = localStorage.getItem('epcQrHistory')
		const parsed = history ? JSON.parse(history) : []
		return Array.isArray(parsed) ? parsed.length : 0
	} catch {
		return 0
	}
}

/**
 * Import history from localStorage to server via Pinia store
 * Returns the number of items imported
 */
export async function importHistoryFromLocalStorage(historyStore) {
	try {
		const stored = localStorage.getItem('epcQrHistory')
		if (!stored) return 0

		const items = JSON.parse(stored)
		if (!Array.isArray(items) || items.length === 0) return 0

		let imported = 0
		const total = items.length

		for (let i = 0; i < total; i++) {
			const item = items[i]
			try {
				await historyStore.addHistory({
					beneficiary: item.formData?.beneficiary || '',
					iban: item.formData?.iban || '',
					amount: item.formData?.amount || '',
					remittance: item.formData?.remittance || '',
					epcString: item.epcString || '',
					createdAt: item.timestamp || Date.now(),
				})
				imported++
			} catch (e) {
				console.error('Failed to import item', i, e)
			}
		}

		// Clear localStorage after successful import
		localStorage.removeItem('epcQrHistory')
		return imported
	} catch (e) {
		console.error('Migration failed:', e)
		throw e
	}
}

/**
 * Import presets from localStorage to server via Pinia store
 * Returns the number of presets imported
 */
export async function importPresetsFromLocalStorage(presetsStore) {
	try {
		const stored = localStorage.getItem('epcQrPresets')
		if (!stored) return 0

		const presets = JSON.parse(stored)
		const names = Object.keys(presets)
		if (names.length === 0) return 0

		let imported = 0
		for (const name of names) {
			try {
				await presetsStore.savePreset(name, {
					...presets[name],
				})
				imported++
			} catch (e) {
				console.error('Failed to import preset', name, e)
			}
		}

		localStorage.removeItem('epcQrPresets')
		return imported
	} catch (e) {
		console.error('Preset migration failed:', e)
		throw e
	}
}
