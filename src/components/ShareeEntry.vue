<template>
	<NcListItem class="vo-sharing-entry"
		:title="sharee.displayName"
		:bold="false"
		counter-type="highlighted"
		force-display-actions>
		<template #icon>
			<NcAvatar :size="32" :display-name="sharee.shareWithDescription" :url="sharee.shareWithAvatar ? sharee.shareWithAvatar : undefined" />
		</template>
		<template #subtitle>
			{{ sharee.shareWithDescription }}
		</template>
		<template #actions>
			<NcActionButton :disabled="loading" @click.prevent="actionCheck">
				<template #icon>
					<CheckboxMarkedIcon v-if="isShared" :size="20" />
					<CheckboxBlankOutlineIcon v-else :size="20" />
				</template>
			</NcActionButton>
		</template>
	</NcListItem>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { NcListItem, NcActionButton, NcAvatar } from '@nextcloud/vue'
import CheckboxBlankOutlineIcon from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import CheckboxMarkedIcon from 'vue-material-design-icons/CheckboxMarked.vue'

const SHARE_TYPE_FEDERATED_GROUP = 14

export default {
	name: 'ShareeEntry',

	components: {
		NcListItem,
		NcActionButton,
		NcAvatar,
		CheckboxBlankOutlineIcon,
		CheckboxMarkedIcon,
	},

	props: {
		sharee: {
			type: Object,
			default: () => {},
			required: true,
		},
		fileInfo: {
			type: Object,
			default: () => {},
			required: true,
		},
		isShared: {
			type: Boolean,
			default: false,
			required: true,
		},
	},

	data() {
		return {
			loading: false,
		}
	},

	methods: {

		actionCheck() {
			if (this.isShared) {
				this.actionUnShare()
			} else {
				this.actionShare()
			}
		},

		async actionUnShare() {
			try {
				this.loading = true
				const shareUrl = generateOcsUrl('apps/files_sharing/api/v1/shares')
				const request = await axios.delete(shareUrl + `/${this.sharee.shareId}`)
				if (!request?.data?.ocs) {
					throw request
				}

				console.debug('Share deleted', this.sharee.shareId)
				this.$emit('sync:shares')
			} catch (error) {
				console.error('Error while deleting share', error)
				const errorMessage = error?.response?.data?.ocs?.meta?.message
				OC.Notification.showTemporary(
					errorMessage ? t('files_sharing', 'Error deleting the share: {errorMessage}', { errorMessage }) : t('files_sharing', 'Error deleting the share'),
					{ type: 'error' }
				)
			} finally {
				this.loading = false
			}
		},

		async actionShare() {
			try {
				this.loading = true
				const shareUrl = generateOcsUrl('apps/files_sharing/api/v1/shares')
				const path = (this.fileInfo.path + '/' + this.fileInfo.name).replace('//', '/')
				const request = await axios.post(shareUrl, {
					path,
					shareType: SHARE_TYPE_FEDERATED_GROUP,
					shareWith: this.sharee.shareWith,
					password: null,
					permissions: this.fileInfo.sharePermissions & OC.getCapabilities().files_sharing.default_permissions,
					attributes: JSON.stringify(this.fileInfo.shareAttributes),
				})
				if (!request?.data?.ocs) {
					throw request
				}

				console.debug('Share added', request.data.ocs.data.id)
				this.$emit('sync:shares')
			} catch (error) {
				console.error('Error while creating share', error)
				const errorMessage = error?.response?.data?.ocs?.meta?.message
				OC.Notification.showTemporary(
					errorMessage ? t('files_sharing', 'Error creating the share: {errorMessage}', { errorMessage }) : t('files_sharing', 'Error creating the share'),
					{ type: 'error' }
				)
			} finally {
				this.loading = false
			}
		},

	},
}
</script>
