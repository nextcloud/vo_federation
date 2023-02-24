<!--
	- @copyright 2022 Christopher Ng <chrng8@gmail.com>
	-
	- @author Christopher Ng <chrng8@gmail.com>
	- @author Sandro Mesterheide <sandro.mesterheide@extern.publicplan.de>
	-
	- @license AGPL-3.0-or-later
	-
	- This program is free software: you can redistribute it and/or modify
	- it under the terms of the GNU Affero General Public License as
	- published by the Free Software Foundation, either version 3 of the
	- License, or (at your option) any later version.
	-
	- This program is distributed in the hope that it will be useful,
	- but WITHOUT ANY WARRANTY; without even the implied warranty of
	- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	- GNU Affero General Public License for more details.
	-
	- You should have received a copy of the GNU Affero General Public License
	- along with this program. If not, see <http://www.gnu.org/licenses/>.
	-
-->

<template>
	<section>
		<div v-if="!showCropper" class="avatar__container">
			<div class="avatar__preview">
				<NcAvatar v-if="!loading"
					:aria-label="t('settings', 'Your profile picture')"
					:disabled-menu="true"
					:disabled-tooltip="true"
					:show-user-status="false"
					:display-name="avatarDisplayName"
					:url="avatarUrl ? avatarUrl : undefined"
					:size="180" />
				<div v-else class="icon-loading" />
			</div>
			<div class="avatar__buttons">
				<NcButton :aria-label="t('settings', 'Upload profile picture')"
					@click="activateLocalFilePicker">
					<template #icon>
						<Upload :size="20" />
					</template>
				</NcButton>
				<NcButton v-if="avatarUrl"
					:aria-label="t('settings', 'Remove profile picture')"
					@click="removeAvatar">
					<template #icon>
						<Delete :size="20" />
					</template>
				</NcButton>
			</div>
			<span>{{ t('settings', 'png or jpg, max. 20 MB') }}</span>
			<input ref="input"
				type="file"
				:accept="validMimeTypes.join(',')"
				@change="onChange">
		</div>

		<!-- Use v-show to ensure early cropper ref availability -->
		<div v-show="showCropper" class="avatar__container">
			<VueCropper ref="cropper"
				class="avatar__cropper"
				v-bind="cropperOptions" />
			<div class="avatar__cropper-buttons">
				<NcButton @click="cancel">
					{{ t('settings', 'Cancel') }}
				</NcButton>
				<NcButton type="primary"
					@click="saveAvatar">
					{{ t('settings', 'Set as profile picture') }}
				</NcButton>
			</div>
			<span>{{ t('settings', 'Please note that it can take up to 24 hours for your profile picture to be updated everywhere.') }}</span>
		</div>
	</section>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'

import { NcAvatar, NcButton } from '@nextcloud/vue'
import VueCropper from 'vue-cropperjs'
// eslint-disable-next-line
import 'cropperjs/dist/cropper.css'

import Upload from 'vue-material-design-icons/Upload.vue'
import Delete from 'vue-material-design-icons/Delete.vue'

const VALID_MIME_TYPES = ['image/png', 'image/jpeg']

export default {
	name: 'AvatarSection',

	components: {
		Delete,
		NcAvatar,
		NcButton,
		Upload,
		VueCropper,
	},

	props: ['initialAvatarUrl', 'avatarDisplayName', 'providerId'],

	data() {
		return {
			avatarUrl: this.initialAvatarUrl,
			showCropper: false,
			loading: false,
			validMimeTypes: VALID_MIME_TYPES,
			cropperOptions: {
				aspectRatio: 1 / 1,
				viewMode: 1,
				guides: false,
				center: false,
				highlight: false,
				autoCropArea: 1,
				minContainerWidth: 300,
				minContainerHeight: 300,
			},
		}
	},

	methods: {
		activateLocalFilePicker() {
			// Set to null so that selecting the same file will trigger the change event
			this.$refs.input.value = null
			this.$refs.input.click()
		},

		onChange(e) {
			this.loading = true
			const file = e.target.files[0]
			if (!this.validMimeTypes.includes(file.type)) {
				showError(t('settings', 'Please select a valid png or jpg file'))
				this.cancel()
				return
			}

			const reader = new FileReader()
			reader.onload = (e) => {
				this.$refs.cropper.replace(e.target.result)
				this.showCropper = true
			}
			reader.readAsDataURL(file)
		},

		saveAvatar() {
			this.showCropper = false
			this.loading = true

			this.$refs.cropper.getCroppedCanvas().toBlob(async (blob) => {
				if (blob === null) {
					showError(t('settings', 'Error cropping profile picture'))
					this.cancel()
					return
				}

				const formData = new FormData()
				formData.append('files[]', blob)
				try {
					const url = generateUrl(`/apps/vo_federation/avatar/${this.providerId}`)
					await axios.post(url, formData)
					this.avatarUrl = `${url}/32`
					this.handleAvatarUpdate(false)
				} catch (e) {
					showError(t('settings', 'Error saving profile picture'))
					this.handleAvatarUpdate(this.isGenerated)
				}
			})
		},

		async removeAvatar() {
			this.loading = true
			try {
				const url = generateUrl(`/apps/vo_federation/avatar/${this.providerId}`)
				await axios.delete(url)
				this.avatarUrl = null
				this.handleAvatarUpdate(true)
			} catch (e) {
				showError(t('settings', 'Error removing profile picture'))
				this.handleAvatarUpdate(this.isGenerated)
			}
		},

		cancel() {
			this.showCropper = false
			this.loading = false
		},

		handleAvatarUpdate(isGenerated) {
			// Update the avatar version so that avatar update handlers refresh correctly
			this.loading = false
			this.$emit('update-url', this.avatarUrl)
		},

	},
}
</script>

<style lang="scss" scoped>
.avatar {
	&__container {
		margin: 0 0;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
		gap: 16px 0;
		width: 450px;

		span {
			color: var(--color-text-lighter);
		}
	}

	&__preview {
		display: flex;
		justify-content: center;
		align-items: center;
		width: 180px;
		height: 180px;
	}

	&__buttons {
		display: flex;
		gap: 0 10px;
	}

	&__cropper {
		width: 300px;
		height: 300px;
		overflow: hidden;

		&-buttons {
			width: 100%;
			display: flex;
			justify-content: space-between;
		}

		&::v-deep .cropper-view-box {
			border-radius: 50%;
		}
	}
}

input[type='file'] {
	display: none;
}
</style>
