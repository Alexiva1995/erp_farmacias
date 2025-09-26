import { library } from '@fortawesome/fontawesome-svg-core'
import { faBoxesStacked } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

import {
    faAddressBook,
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
    faUsers
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
  faBarcode,
  faFileLines,
  faAddressBook,
  faChartSimple
)

export default function (app) {
  app.component('font-awesome-icon', FontAwesomeIcon)
}
