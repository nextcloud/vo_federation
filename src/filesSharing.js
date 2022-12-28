import './bootstrap'
import ShareeList from './components/ShareeList.vue'

OCA.Sharing.ShareTabSections.registerSection((parentRef, fileInfo) => ShareeList)
