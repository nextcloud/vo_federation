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
			{{ t('vo_federation', 'Community AAI settings') }}
		</h2>

		<form ref="providerForm" class="provider-edit" @submit.prevent="onSubmit">
			<p>
				<label for="oidc-client-id">{{ t('vo_federation', 'Client ID') }}</label>
				<input id="oidc-client-id"
					v-model="localProvider.clientId"
					type="text">
			</p>
			<p>
				<label for="oidc-client-secret">{{ t('vo_federation', 'Client secret') }}</label>
				<input id="oidc-client-secret"
					v-model="localProvider.clientSecret"
					type="password"
					autocomplete="off">
			</p>
			<p>
				<label for="oidc-authorization-endpoint">{{ t('vo_federation', 'Authorization endpoint') }}</label>
				<input id="oidc-authorization-endpoint"
					v-model="localProvider.authorizationEndpoint"
					type="text">
			</p>
			<p>
				<label for="oidc-token-endpoint">{{ t('vo_federation', 'Token endpoint') }}</label>
				<input id="oidc-token-endpoint"
					v-model="localProvider.tokenEndpoint"
					type="text">
			</p>
			<p>
				<label for="oidc-jwks-endpoint">{{ t('vo_federation', 'JWKS endpoint') }}</label>
				<input id="oidc-jwks-endpoint"
					v-model="localProvider.jwksEndpoint"
					type="text">
			</p>
			<p>
				<label for="oidc-userinfo-endpoint">{{ t('vo_federation', 'Userinfo endpoint') }}</label>
				<input id="oidc-userinfo-endpoint"
					v-model="localProvider.userinfoEndpoint"
					type="text">
			</p>
			<p>
				<label for="oidc-scope">{{ t('vo_federation', 'Scope') }}</label>
				<input id="oidc-scope"
					v-model="localProvider.scope"
					type="text"
					placeholder="openid email profile">
			</p>
			<p>
				<label for="oidc-extra-claims">{{ t('vo_federation', 'Extra claims') }}</label>
				<input id="oidc-extra-claims"
					v-model="localProvider.extraClaims"
					type="text"
					placeholder="claim1 claim2 claim3">
			</p>
			<h3 style="font-weight: bold">
				{{ t('vo_federation', 'Attribute mapping') }}
			</h3>
			<p>
				<label for="mapping-uid">{{ t('vo_federation', 'User ID mapping') }}</label>
				<input id="mapping-uid"
					v-model="localProvider.mappingUid"
					type="text"
					placeholder="sub">
			</p>
			<p>
				<label for="mapping-displayName">{{ t('vo_federation', 'Display name mapping') }}</label>
				<input id="mapping-displayName"
					v-model="localProvider.mappingDisplayName"
					type="text"
					placeholder="name">
			</p>
			<p>
				<label for="mapping-group">{{ t('vo_federation', 'Groups mapping') }}</label>
				<input id="mapping-group"
					v-model="localProvider.mappingGroups"
					type="text"
					placeholder="groups">
			</p>
			<fieldset style="margin-top: 10px">
				<input type="submit" :value="t('vo_federation', 'Update')">
			</fieldset>
		</form>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'AdminSettings',
	components: {
	},
	data() {
		return {
			localProvider: Object.assign({
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
			}, loadState('vo_federation', 'provider')),
		}
	},
	methods: {
		async onSubmit() {
			const url = generateUrl('/apps/vo_federation/provider')
			try {
				await axios.put(url, { values: this.localProvider })
				showSuccess(t('vo_federation', 'Provider updated successfully'))
			} catch (error) {
				console.error('Could not update the provider: ' + error.message, { error })
				showError(t('vo_federation', 'Could not update the provider:') + ' ' + (error.response?.data?.message ?? error.message))
			}
		},
	},
}
</script>
<style lang="scss" scoped>
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
</style>
