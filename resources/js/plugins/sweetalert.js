// src/plugins/sweetalert.js

import Swal from 'sweetalert2';
import 'sweetalert2/src/sweetalert2.scss';

const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
  didOpen: (toast) => {
    toast.addEventListener('mouseenter', Swal.stopTimer)
    toast.addEventListener('mouseleave', Swal.resumeTimer)
  },
})

export const toast = {
  success(title = 'Acción realizada con éxito') {
    Toast.fire({
      icon: 'success',
      title: title,
    })
  },
  error(title = 'Ha ocurrido un error') {
    Toast.fire({
      icon: 'error',
      title: title,
    })
  },
  warning(title = 'Atención') {
    Toast.fire({
      icon: 'warning',
      title: title,
    })
  },
  info(title = 'Información') {
    Toast.fire({
      icon: 'info',
      title: title,
    })
  },
}
