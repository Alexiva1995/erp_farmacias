import { library } from '@fortawesome/fontawesome-svg-core'
import { faBoxesStacked } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

import {
  faBarcode,
  faCog,
  faEye,
  faFileLines,
  faPencilAlt,
  faPlus,
  faSave,
  faTrash,
  faUser,
  faAddressBook
} from '@fortawesome/free-solid-svg-icons'

library.add(
  faUser, 
  faCog, 
  faSave, 
  faTrash, 
  faPlus, 
  faPencilAlt,
  faEye,
  faBoxesStacked,
  faBarcode,
  faFileLines,
  faAddressBook
)

export default function (app) {
  app.component('font-awesome-icon', FontAwesomeIcon)
}
