import { library } from '@fortawesome/fontawesome-svg-core'
import { faBoxesStacked , faFileInvoice} from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

import {
    faAddressBook,
    faArrowsRotate,
    faBarcode,
    faChartSimple,
    faCog,
    faEye,
    faFileLines,
    faPencilAlt,
    faPlus,
    faSave,
    faTrash,
    faUser,
    faUsers,
    faChartBar
} from '@fortawesome/free-solid-svg-icons'

library.add(
  faUser, 
  faUsers,
  faCog, 
  faSave, 
  faTrash, 
  faPlus, 
  faPencilAlt,
  faEye,
  faBoxesStacked,
  faFileInvoice,
  faBarcode,
  faFileLines,
  faAddressBook,
  faChartSimple,
  faArrowsRotate,
  faChartBar
)

export default function (app) {
  app.component('font-awesome-icon', FontAwesomeIcon)
}
