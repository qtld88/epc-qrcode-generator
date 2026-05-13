<template>
	<NcContent app-name="epc_qrcode_generator">
		<NcAppNavigation>
			<NcAppNavigationItem
				:name="t('epc_qrcode_generator', 'Generator')"
				:to="{ name: 'generator' }" />
			<NcAppNavigationItem
				:name="t('epc_qrcode_generator', 'History')"
				:to="{ name: 'history' }" />
		</NcAppNavigation>
		<NcAppContent>
			<router-view />
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'

export default {
	name: 'App',
	components: {
		NcContent,
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppContent,
	},
}
</script>
