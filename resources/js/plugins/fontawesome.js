// resources/js/plugins/fontawesome.js

import { library } from '@fortawesome/fontawesome-svg-core'
import { faBoxesStacked } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

// Importa los iconos que necesites para mantener la aplicación ligera.
// ¡Puedes añadir todos los que quieras aquí!
import {
    faCog,
    faEye,
    faPencilAlt,
    faPlus,
    faSave,
    faTrash,
    faUser
} from '@fortawesome/free-solid-svg-icons'

// Añade los iconos importados a la librería global de Font Awesome.
library.add(
  faUser, 
  faCog, 
  faSave, 
  faTrash, 
  faPlus, 
  faPencilAlt,
  faEye,
  faBoxesStacked
)

// Exporta la función por defecto que será leída por el cargador de plugins.
export default function (app) {
  // Registra el componente <font-awesome-icon> de forma global.
  app.component('font-awesome-icon', FontAwesomeIcon)
}
