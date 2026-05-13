import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { translate, translatePlural } from '@nextcloud/l10n'
import { getRequestToken } from '@nextcloud/auth'
import App from './App.vue'
import router from './router/index.js'

// CSP nonce for inline styles
__webpack_nonce__ = btoa(getRequestToken())

const app = createApp(App)
const pinia = createPinia()
const BUILD_MARKER = 'epc-qrcode-generator:frontend-build-2026-05-12-v16-logo-compress'

// Register global properties
app.config.globalProperties.t = translate
app.config.globalProperties.n = translatePlural

app.use(pinia)
app.use(router)

app.config.errorHandler = (error, instance, info) => {
	console.error('[EPC QR] Vue runtime error', { error, info, instance })
}

console.info(`[EPC QR] ${BUILD_MARKER}`)

try {
	app.mount('#app')
} catch (error) {
	console.error('[EPC QR] mount failed', error)
	const root = document.getElementById('app')
	if (root) {
		root.innerHTML = `
			<div style="padding:16px;color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;border-radius:8px;">
				<strong>EPC QR frontend failed to mount.</strong><br>
				Check browser console for details.
			</div>
		`
	}
}
