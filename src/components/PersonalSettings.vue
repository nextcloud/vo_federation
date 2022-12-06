<template>
	<div id="vo_federation-personal-settings" class="section">
		<h2>
			<a class="icon icon-vo" />
			{{ t('vo_federation', 'VO Federation') }}
		</h2>
		<div v-for="provider in state" :key="provider.providerId" class="vo-content">
			<div v-if="!provider.active">
				<button id="aai-oidc" @click="() => onOAuthClick(provider.providerId)">
					<span class="icon icon-external" />
					{{ t('vo_federation', 'Connect with {name}', { name: provider.identifier }) }}
				</button>
			</div>
			<div v-else class="vo-grid-form">
				<label>
					<a class="icon icon-checkmark-color" />
					{{ t('vo_federation', 'Connected as {user}', { user: provider.displayName }) }}
				</label>
				<button id="vo-rm-cred" @click="() => onLogoutClick(provider.providerId)">
					<span class="icon icon-close" />
					{{ t('vo_federation', 'Disconnect from AAI') }}
				</button>
				<span style="grid-column: 1/-1">
					<b>{{ t('vo_federation', 'Last synchronization') }}:</b>
					{{ provider.timestamp ? formatSyncTimestamp(provider.timestamp) : 'None' }}
				</span>
			</div>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import '@nextcloud/dialogs/styles/toast.scss'

export default {
	name: 'PersonalSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('vo_federation', 'user-config'),
		}
	},

	mounted() {
		const paramString = window.location.search.substr(1)
		// eslint-disable-next-line
		const urlParams = new URLSearchParams(paramString)
		const twToken = urlParams.get('aaiToken')
		if (twToken === 'success') {
			showSuccess(t('vo_federation', 'Successfully connected to AAI!'))
		} else if (twToken === 'error') {
			showError(t('vo_federation', 'AAI OIDC error:') + ' ' + urlParams.get('message'))
		}
	},

	methods: {
		onLogoutClick(providerId) {
			const url = generateUrl(`/apps/vo_federation/provider/${providerId}/logout`)
			axios.post(url)
				.then((response) => {
					showSuccess(t('vo_federation', 'Logged out successfully'))
					const providerIndex = this.state.findIndex(provider => provider.providerId === providerId)
					if (providerIndex > -1) {
						this.state[providerIndex].active = false
					}
				})
				.catch((error) => {
					showError(
						t('vo_federation', 'Failed logging out')
						+ ': ' + error.response.request.responseText
					)
				})
		},
		onOAuthClick(providerId) {
			const url = generateUrl(`/apps/vo_federation/login/${providerId}`)
			window.location.replace(url)
		},
		formatSyncTimestamp(timestamp) {
			return moment.unix(timestamp).format('LLL')
		},
	},
}
</script>

<style scoped lang="scss">
#vo_federation-personal-settings .icon {
	display: inline-block;
	width: 32px;
}

.icon-vo {
	background-image: url(./../../img/app-dark.svg);
	background-size: 23px 23px;
	height: 23px;
	margin-bottom: -4px;
}

body.theme--dark .icon-vo {
	background-image: url(./../../img/app.svg);
}

.vo-content {
	margin-left: 40px;
}

.vo-grid-form {
	max-width: 600px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
	button .icon {
		margin-bottom: -1px;
	}
	input {
		width: 100%;
	}

}

.vo-grid-form label {
	line-height: 38px;
}

#vo-rm-cred {
	max-height: 34px;
}

</style>
