<template>
	<div id="vo_federation-personal-settings" class="section">
		<h2>
			<a class="icon icon-vo" />
			{{ t('vo_federation', 'VO Federation') }}
		</h2>
		<div class="vo-content">
			<div v-if="!state.displayName">
				<button id="aai-oidc" @click="onOAuthClick">
					<span class="icon icon-external" />
					{{ t('vo_federation', 'Connect to Community AAI') }}
				</button>
			</div>
			<div v-else class="vo-grid-form">
				<label>
					<a class="icon icon-checkmark-color" />
					{{ t('vo_federation', 'Connected as {user}', { user: state.displayName }) }}
				</label>
				<button id="vo-rm-cred" @click="onLogoutClick">
					<span class="icon icon-close" />
					{{ t('vo_federation', 'Disconnect from AAI') }}
				</button>
				<div style="grid-column: 1/-1">
					<label for="vo-groups">VO-Groups</label>
					<textarea id="vo-groups"
						v-model="state.groups"
						readonly
						style="width: 100%"
						rows="10" />
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'

export default {
	name: 'PersonalSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: Object.assign({
				displayName: '',
				groups: '',
			}, loadState('vo_federation', 'user-config')),
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
		onLogoutClick() {
			this.state.displayName = ''
			this.state.groups = ''
			this.saveOptions({ displayName: this.state.displayName, groups: this.state.groups })
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/vo_federation/config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('vo_federation', 'VO Federation options saved'))
				})
				.catch((error) => {
					showError(
						t('vo_federation', 'Failed to save VO Federation options')
						+ ': ' + error.response.request.responseText
					)
				})
		},
		onOAuthClick() {
			const url = generateUrl('/apps/vo_federation/login')
			window.location.replace(url)
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
