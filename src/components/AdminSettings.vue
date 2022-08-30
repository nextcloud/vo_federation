<!--
  - @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
  -
  - @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<div class="section">
		<h2>
			{{ t('vo_federation', 'Registered Community AAIs') }}
			<Actions>
				<ActionButton icon="icon-add" @click="addNewProvider">
					{{ t('vo_federation', 'Register new AAI') }}
				</ActionButton>
			</Actions>
		</h2>

		<div class="oidcproviders">
			<p v-if="providers.length === 0">
				{{ t('vo_federation', 'No AIIs registered.') }}
			</p>
			<div v-for="provider in providers"
				v-else
				:key="provider.providerId"
				class="oidcproviders__provider">
				<div class="oidcproviders__details">
					<b>{{ provider.identifier }}</b><br>
					{{ t('vo_federation', 'Client ID') }}: {{ provider.clientId }}<br>
					{{ t('vo_federation', 'Authorization endpoint') }}: {{ provider.authorizationEndpoint }}
				</div>
				<Actions>
					<ActionButton icon="icon-rename" @click="updateProvider(provider.providerId)">
						{{ t('vo_federation', 'Update') }}
					</ActionButton>
				</Actions>
				<Actions>
					<ActionButton icon="icon-delete" @click="onRemove(provider.providerId)">
						{{ t('vo_federation', 'Remove') }}
					</ActionButton>
				</Actions>
			</div>
		</div>

		<Modal v-if="editProvider !== null" :can-close="false">
			<div class="providermodal__wrapper">
				<h3 v-if="editProvider.providerId == null">
					{{ t('user_oids', 'Register a new provider') }}
				</h3>
				<h3 v-else v-once>
					Edit {{ editProvider.clientId }}
				</h3>
				<form ref="providerForm" class="provider-edit" @submit.prevent="onSubmit">
					<p>
						<label for="oidc-client-id">{{ t('vo_federation', 'Client ID') }}</label>
						<input id="oidc-client-id"
							v-model="editProvider.clientId"
							type="text">
					</p>
					<p>
						<label for="oidc-client-secret">{{ t('vo_federation', 'Client secret') }}</label>
						<input id="oidc-client-secret"
							v-model="editProvider.clientSecret"
							type="password"
							autocomplete="off">
					</p>
					<p>
						<label for="oidc-authorization-endpoint">{{ t('vo_federation', 'Authorization endpoint') }}</label>
						<input id="oidc-authorization-endpoint"
							v-model="editProvider.authorizationEndpoint"
							type="text">
					</p>
					<p>
						<label for="oidc-token-endpoint">{{ t('vo_federation', 'Token endpoint') }}</label>
						<input id="oidc-token-endpoint"
							v-model="editProvider.tokenEndpoint"
							type="text">
					</p>
					<p>
						<label for="oidc-jwks-endpoint">{{ t('vo_federation', 'JWKS endpoint') }}</label>
						<input id="oidc-jwks-endpoint"
							v-model="editProvider.jwksEndpoint"
							type="text">
					</p>
					<p>
						<label for="oidc-userinfo-endpoint">{{ t('vo_federation', 'Userinfo endpoint') }}</label>
						<input id="oidc-userinfo-endpoint"
							v-model="editProvider.userinfoEndpoint"
							type="text">
					</p>
					<p>
						<label for="oidc-scope">{{ t('vo_federation', 'Scope') }}</label>
						<input id="oidc-scope"
							v-model="editProvider.scope"
							type="text"
							placeholder="openid email profile">
					</p>
					<p>
						<label for="oidc-extra-claims">{{ t('vo_federation', 'Extra claims') }}</label>
						<input id="oidc-extra-claims"
							v-model="editProvider.extraClaims"
							type="text"
							placeholder="claim1 claim2 claim3">
					</p>
					<h3 style="font-weight: bold">
						{{ t('vo_federation', 'Attribute mapping') }}
					</h3>
					<p>
						<label for="mapping-uid">{{ t('vo_federation', 'User ID mapping') }}</label>
						<input id="mapping-uid"
							v-model="editProvider.mappingUid"
							type="text"
							placeholder="sub">
					</p>
					<p>
						<label for="mapping-displayName">{{ t('vo_federation', 'Display name mapping') }}</label>
						<input id="mapping-displayName"
							v-model="editProvider.mappingDisplayName"
							type="text"
							placeholder="name">
					</p>
					<p>
						<label for="mapping-group">{{ t('vo_federation', 'Groups mapping') }}</label>
						<input id="mapping-group"
							v-model="editProvider.mappingGroups"
							type="text"
							placeholder="groups">
					</p>
					<fieldset style="margin-top: 10px">
						<input type="button" :value="t('vo_federation', 'Cancel')" @click="editProvider = null">
						<input type="submit" :value="t('vo_federation', 'Update')">
					</fieldset>
				</form>
			</div>
		</Modal>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Modal from '@nextcloud/vue/dist/Components/Modal'

const provider = {
	providerId: null,
	clientId: '',
	clientSecret: '',
	authorizationEndpoint: '',
	tokenEndpoint: '',
	userinfoEndpoint: '',
	jwksEndpoint: '',
	scope: '',
	extraClaims: '',
	mappingUid: '',
	mappingDisplayName: '',
	mappingGroups: '',
}

export default {
	name: 'AdminSettings',
	components: {
		Actions,
		ActionButton,
		Modal,
	},
	data() {
		return {
			providers: loadState('vo_federation', 'providers'),
			editProvider: null,
		}
	},
	methods: {
		async onSubmit() {
			if (this.editProvider.providerId == null) {
				const url = generateUrl('/apps/vo_federation/provider')
				try {
					const response = await axios.post(url, { values: this.editProvider })
					this.providers.push({
						...this.editProvider,
						providerId: response.data.providerId,
					})
					this.editProvider = null
					showSuccess(t('vo_federation', 'Provider created successfully'))
				} catch (error) {
					console.error('Could not create the provider: ' + error.message, { error })
					showError(t('vo_federation', 'Could not create the provider:') + ' ' + (error.response?.data?.message ?? error.message))
				}
			} else {
				const url = generateUrl(`/apps/vo_federation/provider/${this.editProvider.providerId}`)
				try {
					await axios.put(url, { values: this.editProvider })
					const providerIndex = this.provider.findIndex(provider => provider.providerId === this.editProvider.providerId)
					if (providerIndex > -1) {
						this.$set(this.providers, providerIndex, this.editProvider)
					}
					this.editProvider = null
					showSuccess(t('vo_federation', 'Provider updated successfully'))
				} catch (error) {
					console.error('Could not update the provider: ' + error.message, { error })
					showError(t('vo_federation', 'Could not update the provider:') + ' ' + (error.response?.data?.message ?? error.message))
				}
			}
		},
		async onRemove(providerId) {
			const url = generateUrl(`/apps/vo_federation/provider/${providerId}`)
			try {
				await axios.delete(url)
				this.providers = this.providers.filter(provider => provider.providerId !== providerId)
			} catch (error) {
				showError(t('vo_federation', 'Could not remove provider: ' + error.message))
			}
		},
		updateProvider(providerId) {
			const providerIndex = this.providers.findIndex(provider => provider.providerId === providerId)
			if (providerIndex > -1) {
				this.editProvider = { ...this.providers[providerIndex] }
			}
		},
		addNewProvider() {
			this.editProvider = { ...provider }
		},
	},
}
</script>
<style lang="scss" scoped>
h2 .action-item {
	vertical-align: middle;
	margin-top: -2px;
}

.provider-edit p label {
	width: 160px;
	display: inline-block;
}

.provider-edit p input[type=text],
.provider-edit p input[type=password] {
	width: 100%;
	min-width: 200px;
	max-width: 400px;
}

h3 {
	font-weight: bold;
	padding-bottom: 12px;
}

.oidcproviders {
	margin-top: 20px;
	border-top: 1px solid var(--color-border);
	max-width: 900px;
}

.oidcproviders__provider {
	border-bottom: 1px solid var(--color-border);
	padding: 10px;
	display: flex;

	&:hover {
		background-color: var(--color-background-hover);
	}
	.oidcproviders__details {
		flex-grow: 1;
	}
}

.providermodal__wrapper {
	min-width: 320px;
	width: 50vw;
	max-width: 800px;
	height: calc(80vh - 40px);
	margin: 20px;
	overflow: scroll;
}
</style>
