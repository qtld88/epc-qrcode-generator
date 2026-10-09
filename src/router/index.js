import { createRouter, createWebHistory } from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import GeneratorView from '../views/GeneratorView.vue'
import HistoryView from '../views/HistoryView.vue'

// Take the base from the current URL: generateUrl() returns either /index.php/apps/... or /apps/...
// depending on the server's pretty-URL setting, and both URL forms reach this page.
const currentBase = window.location.pathname.match(/^.*?\/apps\/epc_qrcode_generator/)
const base = currentBase ? currentBase[0] : generateUrl('/apps/epc_qrcode_generator')

const router = createRouter({
	history: createWebHistory(base),
	routes: [
		{
			path: '/',
			name: 'generator',
			component: GeneratorView,
		},
		{
			path: '/history',
			name: 'history',
			component: HistoryView,
		},
	],
})

export default router
