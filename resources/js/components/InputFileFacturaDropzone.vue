<script setup lang="js">
import Dropzone from 'dropzone'
import 'dropzone/dist/dropzone.css'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

// Evita la auto-inicialización (por si acaso)
// Nota: En v6 ya viene deshabilitado, pero lo dejamos explícito
if ('autoDiscover' in Dropzone)
  Dropzone.autoDiscover = false

const props = defineProps({
  // Archivo seleccionado desde el padre (File | null)
  file_factura: { type: [File, Object, null], default: null },
  // Mensaje de error a mostrar bajo el input
  error_input_file: { type: [String, Array], default: '' },
})

// Emite cambios al padre cuando se agrega/borra el archivo
const emit = defineEmits(['update:file_factura'])

const dzEl = ref(null)
let dz = null

onMounted(() => {
  if (!dzEl.value) return

  dz = new Dropzone(dzEl.value, {
    url: '#', // No subimos aquí, solo obtenemos el File
    autoProcessQueue: false,
    uploadMultiple: false,
    maxFiles: 1,
    addRemoveLinks: true,
    dictDefaultMessage: 'Arrastra y suelta la factura aquí o haz clic',
    dictRemoveFile: 'Quitar',
    acceptedFiles: '.pdf,.jpg,.jpeg,.png,.gif,.xml,application/pdf,image/*,text/xml,application/xml',
    previewTemplate: document.getElementById('dz-preview-template')?.innerHTML ?? undefined,
  })

  dz.on('addedfile', file => {
    // Mantener solo un archivo
    if (dz.files.length > 1) {
      // Eliminar el anterior y dejar el último
      const [first] = dz.files
      if (first && first !== file)
        dz.removeFile(first)
    }
    emit('update:file_factura', file)
  })

  dz.on('removedfile', () => {
    // Si no queda archivo, notificar null
    if (dz.files.length === 0)
      emit('update:file_factura', null)
  })

  dz.on('error', (file, message) => {
    // Solo para evitar logs silenciosos; el padre maneja el mensaje
    // console.warn('Dropzone error:', message)
  })

  // Si el padre ya tiene un archivo, no podemos inyectarlo en Dropzone (no hay API para eso)
  // pero si lo limpia, sincronizamos quitando previews.
})

onBeforeUnmount(() => {
  if (dz) {
    try { dz.destroy() } catch {}
    dz = null
  }
})

// Si desde el padre ponen null, limpiamos la zona
watch(
  () => props.file_factura,
  newVal => {
    if (!dz) return
    if (newVal == null && dz.files.length) dz.removeAllFiles(true)
  }
)
</script>

<template>
  <div>
    <!-- Zona Dropzone -->
    <div ref="dzEl" class="dropzone" />

    <!-- Error (texto simple o array de mensajes) -->
    <div
      v-if="
        (typeof props.error_input_file === 'string' &&
          props.error_input_file.length) ||
        Array.isArray(props.error_input_file)
      "
      class="mt-2 error-text"
    >
      <template v-if="Array.isArray(props.error_input_file)">
        <div v-for="(err, idx) in props.error_input_file" :key="idx">
          {{ err }}
        </div>
      </template>
      <template v-else>
        {{ props.error_input_file }}
      </template>
    </div>

    <!-- Template de preview (oculto) -->
    <div id="dz-preview-template" style="display: none">
      <div class="dz-preview dz-file-preview">
        <div class="dz-details">
          <div class="dz-filename"><span data-dz-name></span></div>
          <div class="dz-size" data-dz-size></div>
          <img data-dz-thumbnail />
        </div>
        <div class="dz-progress">
          <span class="dz-upload" data-dz-uploadprogress></span>
        </div>
        <div class="dz-success-mark"><span>✔</span></div>
        <div class="dz-error-mark"><span>✘</span></div>
        <div class="dz-error-message"><span data-dz-errormessage></span></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dropzone {
  border: 2px dashed var(--v-theme-primary, #7367f0);
  border-radius: 8px;
  background-color: rgba(115, 103, 240, 0.05);
  min-height: 160px;
  padding: 16px;
  color: #c2c6dc;
}

.dropzone .dz-preview {
  display: inline-block;
  margin: 8px;
  vertical-align: top;
}

.dz-preview .dz-image {
  width: 120px;
  height: 120px;
  overflow: hidden;
  border-radius: 10px;
}

.dz-preview .dz-image img {
  width: 100% !important;
  height: 100% !important;
  object-fit: cover;
}

.dropzone .dz-message {
  text-align: center;
  width: 100%;
  margin: 20px 0;
}

.error-text {
  color: #ff4d4f;
  font-size: 0.875rem;
}
</style>
