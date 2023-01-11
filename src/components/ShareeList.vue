<!--
  - @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
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
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<ul class="vo-sharing-sharee-list">
		<ShareeEntry v-for="(sharee, idx) in shareesWithShare"
			:key="`sharee-${idx}`"
			:is-shared="sharee.isShared"
			:sharee="sharee"
			:file-info="fileInfo"
			@sync:shares="syncShares" />
	</ul>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

import ShareeEntry from './ShareeEntry.vue'

const SHARE_TYPE_FEDERATED_GROUP = 14

export default {
	name: 'ShareeList',

	components: {
		ShareeEntry,
	},

	props: {
		fileInfo: {
			type: Object,
			default: () => {},
			required: true,
		},
	},

	data() {
		return {
			sharees: [],
		}
	},

	computed: {
		shares() {
			return this.$parent.shares
		},
		shareesWithShare() {
			return this.sharees.map(sharee => {
				const share = this.shares.find(share => {
					return share.shareWith === sharee.shareWith && share.type === SHARE_TYPE_FEDERATED_GROUP
				})
				if (share) {
					return Object.assign({ isShared: true, shareId: share.id }, sharee)
				}
				return Object.assign({ isShared: false, shareId: null }, sharee)
			})
		},
	},

	created() {
		this.getShareesSuggestions()
	},

	methods: {

		/**
		 * Get sharee suggestions
		 *
		 * @param {string} search the search query
		 * @param {boolean} [lookup=false] search on lookup server
		 */
		async getShareesSuggestions() {
			this.loading = true

			let request = null
			try {
				request = await axios.get(generateOcsUrl('apps/files_sharing/api/v1/sharees'), {
					params: {
						format: 'json',
						itemType: this.fileInfo.type === 'dir' ? 'folder' : 'file',
						search: '',
						lookup: false,
						perPage: this.getMaxAutocompleteResults(),
						shareType: [SHARE_TYPE_FEDERATED_GROUP],
					},
				})
			} catch (error) {
				console.error('Error fetching sharee suggestions', error)
				return
			}

			const data = request.data.ocs.data
			const exact = request.data.ocs.data.exact
			data.exact = [] // removing exact from general results

			// flatten array of arrays
			const rawExactSuggestions = Object.values(exact).reduce((arr, elem) => arr.concat(elem), [])
			const rawSuggestions = Object.values(data).reduce((arr, elem) => arr.concat(elem), [])

			// remove invalid data and format to user-select layout
			const exactSuggestions = this.filterOutExistingShares(rawExactSuggestions)
				.map(share => this.formatForMultiselect(share))
				// sort by type so we can get user&groups first...
				.sort((a, b) => a.shareType - b.shareType)
			const suggestions = this.filterOutExistingShares(rawSuggestions)
				.map(share => this.formatForMultiselect(share))
				// sort by type so we can get user&groups first...
				.sort((a, b) => a.shareType - b.shareType)

			const allSuggestions = exactSuggestions.concat(suggestions)

			// Count occurrences of display names in order to provide a distinguishable description if needed
			const nameCounts = allSuggestions.reduce((nameCounts, result) => {
				if (!result.displayName) {
					return nameCounts
				}
				if (!nameCounts[result.displayName]) {
					nameCounts[result.displayName] = 0
				}
				nameCounts[result.displayName]++
				return nameCounts
			}, {})

			this.sharees = allSuggestions.map(item => {
				// Make sure that items with duplicate displayName get the shareWith applied as a description
				if (nameCounts[item.displayName] > 1 && !item.desc) {
					return { ...item, desc: item.shareWithDisplayNameUnique }
				}
				return item
			})

			this.loading = false
			console.info('sharees', this.sharees)
		},

		/**
		 * Format shares for the multiselect options
		 *
		 * @param {object} result select entry item
		 * @return {object}
		 */
		formatForMultiselect(result) {
			return {
				id: `${result.value.shareType}-${result.value.shareWith}`,
				shareWith: result.value.shareWith,
				shareType: result.value.shareType,
				user: result.uuid || result.value.shareWith,
				displayName: result.name || result.label,
				server: result.value.server,
				shareWithDisplayNameUnique: result.shareWithDisplayNameUnique || '',
				shareWithDescription: result.shareWithDescription,
				shareWithAvatar: result.shareWithAvatar,
			}
		},

		filterOutExistingShares(shares) {
			return shares.reduce((arr, share) => {
				// only check proper objects
				if (typeof share !== 'object') {
					return arr
				}
				arr.push(share)
				return arr
			}, [])
		},

		getMaxAutocompleteResults() {
			return parseInt(OC.config['sharing.maxAutocompleteResults'], 10) || 25
		},

		syncShares() {
			this.$parent.getShares()
		},

	},
}
</script>
