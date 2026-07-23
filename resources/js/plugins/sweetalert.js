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
  fire(options) {
    return Swal.fire(options);
  },
  success(title = 'Acción realizada con éxito') {
    Toast.fire({
      icon: 'success',
      title: title,
    })
  },
  error(title = 'Ha ocurrido un error', details = null) {
    // Si no se pasaron detalles, intentar obtener el último error de Axios global
    if (!details && window.lastAxiosError) {
      details = window.lastAxiosError;
    }

    let errorMessage = '';
    let displayTitle = title;
    
    if (title && typeof title === 'object') {
      details = title;
      displayTitle = 'Ha ocurrido un error';
    }

    if (details) {
      if (typeof details === 'string') {
        errorMessage = details;
      } else if (details.response?.data?.message) {
        errorMessage = details.response.data.message;
      } else if (details.response?.data?.error) {
        errorMessage = details.response.data.error;
      } else if (details.message) {
        errorMessage = details.message;
      } else {
        try {
          errorMessage = JSON.stringify(details);
        } catch(e) {
          errorMessage = String(details);
        }
      }
    }

    const hasDetails = errorMessage.length > 0;
    const cleanTitle = displayTitle.replace(/\.$/, ''); // Quitar punto final si existe
    
    Toast.fire({
      icon: 'error',
      title: cleanTitle,
      html: `
        <div style="font-size: 11px; color: rgba(var(--v-theme-on-surface), 0.6); margin-top: 3px; display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%;">
          <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 170px; text-align: left;">
            ${hasDetails ? errorMessage : 'Código de estado desconocido'}
          </span>
          <button class="copy-error-btn" style="background: rgba(var(--v-theme-error), 0.1); color: rgb(var(--v-theme-error)); border: none; padding: 2px 6px; border-radius: 3px; cursor: pointer; font-weight: 500; font-size: 10px; flex-shrink: 0;">Copiar</button>
        </div>
      `,
      timer: 8000, // Más tiempo para que el usuario pueda copiar
      didOpen: (toastEl) => {
        toastEl.addEventListener('mouseenter', Swal.stopTimer);
        toastEl.addEventListener('mouseleave', Swal.resumeTimer);
        
        const copyBtn = toastEl.querySelector('.copy-error-btn');
        if (copyBtn) {
          copyBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const textToCopy = `Error: ${cleanTitle}\nDetalles: ${errorMessage || 'Sin detalles adicionales.'}\nURL: ${window.location.href}`;
            navigator.clipboard.writeText(textToCopy);
            copyBtn.innerText = '¡Copiado!';
            copyBtn.style.background = 'rgba(var(--v-theme-success), 0.1)';
            copyBtn.style.color = 'rgb(var(--v-theme-success))';
          });
        }
      }
    });

    // Limpiar el error global después de consumirlo
    window.lastAxiosError = null;
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
  confirm(title, callback) {
    Swal.fire({
      title: title,
      text: "¿Está seguro de realizar esta acción?",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, confirmar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        callback();
      }
    });
  },
}
export { Swal };
export default Swal;
