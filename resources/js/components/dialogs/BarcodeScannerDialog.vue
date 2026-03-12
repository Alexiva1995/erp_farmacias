<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  modelValue: Boolean,
})

const emit = defineEmits(['update:modelValue', 'scan'])

const video = ref(null)
const canvas = ref(null)
const isScanning = ref(false)
const error = ref('')
const hasSupport = ref('BarcodeDetector' in window)

let stream = null
let animationId = null

const startCamera = async () => {
  error.value = ''
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment' }
    })
    if (video.value) {
      video.value.srcObject = stream
      isScanning.value = true
      scanBarcode()
    }
  } catch (err) {
    error.value = 'No se pudo acceder a la cámara. Asegúrate de dar permisos.'
    console.error('Error accessing camera:', err)
  }
}

const stopCamera = () => {
  if (stream) {
    stream.getTracks().forEach(track => track.stop())
    stream = null
  }
  if (animationId) {
    cancelAnimationFrame(animationId)
    animationId = null
  }
  isScanning.value = false
}

const scanBarcode = async () => {
  if (!isScanning.value || !hasSupport.value) return

  const barcodeDetector = new BarcodeDetector()

  const detect = async () => {
    if (!isScanning.value) return

    try {
      const barcodes = await barcodeDetector.detect(video.value)
      if (barcodes.length > 0) {
        emit('scan', barcodes[0].rawValue)
        close()
        return
      }
    } catch (err) {
      console.error('Detection error:', err)
    }

    animationId = requestAnimationFrame(detect)
  }

  detect()
}

const close = () => {
  stopCamera()
  emit('update:modelValue', false)
}

onMounted(() => {
  if (props.modelValue) {
    startCamera()
  }
})

onUnmounted(() => {
  stopCamera()
})

watch(() => props.modelValue, (newVal) => {
  if (newVal) {
    startCamera()
  } else {
    stopCamera()
  }
})
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="500"
    persistent
    @update:model-value="close"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>Escanear Código de Barras</span>
        <VBtn icon variant="text" @click="close">
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-0 relative overflow-hidden">
        <div v-if="!hasSupport" class="pa-4 text-center">
          <VAlert type="error" variant="tonal">
            Tu navegador no soporta el escaneo nativo de códigos de barras.
          </VAlert>
        </div>
        
        <div v-else class="scanner-container">
          <video
            ref="video"
            autoplay
            playsinline
            class="scanner-video"
          ></video>
          <div class="scanner-overlay">
            <div class="scanner-laser"></div>
            <div class="scanner-frame"></div>
          </div>
          
          <VAlert
            v-if="error"
            type="error"
            variant="elevated"
            class="scanner-error"
          >
            {{ error }}
          </VAlert>
        </div>
      </VCardText>

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn variant="tonal" color="secondary" @click="close">
          Cancelar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.scanner-container {
  position: relative;
  overflow: hidden;
  aspect-ratio: 4 / 3;
  background: #000;
  inline-size: 100%;
}

.scanner-video {
  block-size: 100%;
  inline-size: 100%;
  object-fit: cover;
}

.scanner-overlay {
  position: absolute;
  display: flex;
  align-items: center;
  justify-content: center;
  inset: 0;
}

.scanner-frame {
  position: relative;
  border: 2px solid rgba(255, 255, 255, 50%);
  border-radius: 8px;
  block-size: 40%;
  box-shadow: 0 0 0 1000px rgba(0, 0, 0, 50%);
  inline-size: 70%;
}

.scanner-laser {
  position: absolute;
  z-index: 2;
  animation: scan 2s infinite;
  background: #f00;
  block-size: 2px;
  box-shadow: 0 0 10px #f00;
  inline-size: 100%;
}

@keyframes scan {
  0% { inset-block-start: 30%; }
  50% { inset-block-start: 70%; }
  100% { inset-block-start: 30%; }
}

.scanner-error {
  position: absolute;
  z-index: 10;
  inset-block-end: 20px;
  inset-inline: 20px;
}
</style>
