import './bootstrap'
import VOSharingList from './components/VOSharingList.vue'

OCA.Sharing.ShareTabSections.registerSection((parentRef, fileInfo) => VOSharingList)
