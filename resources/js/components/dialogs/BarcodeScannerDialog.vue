<script setup>
import { Html5Qrcode } from 'html5-qrcode'
import { ref, onMounted, onUnmounted, watch } from 'vue'

const props = defineProps({
  modelValue: Boolean,
})

const emit = defineEmits(['update:modelValue', 'scan'])

const isScanning = ref(false)
const error = ref('')
const hasSupport = ref(true)

let html5QrCode = null

const startCamera = async () => {
  error.value = ''
  try {
    if (!html5QrCode) {
        html5QrCode = new Html5Qrcode('barcode-reader')
    }
    
    isScanning.value = true
    
    // Configuración optimizada para códigos de barras
    const config = {
      fps: 10,
      qrbox: (viewfinderWidth, viewfinderHeight) => {
        return { 
          width: Math.min(viewfinderWidth * 0.8, 300), 
          height: Math.min(viewfinderHeight * 0.4, 150) 
        }
      },
      aspectRatio: 1.333334,
    }

    await html5QrCode.start(
      { facingMode: 'environment' },
      config,
      (decodedText) => {
        emit('scan', decodedText)
        close()
      },
      (errorMessage) => {
        // Errores de escaneo individuales se ignoran para no saturar
      }
    )
  } catch (err) {
    error.value = 'No se pudo acceder a la cámara. Asegúrate de dar permisos.'
    console.error('Error starting scanner:', err)
    isScanning.value = false
  }
}

const stopCamera = async () => {
  if (html5QrCode && html5QrCode.isScanning) {
    try {
      await html5QrCode.stop()
    } catch (err) {
      console.error('Error stopping scanner:', err)
    }
  }
  isScanning.value = false
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
        <div class="scanner-container">
          <div id="barcode-reader" class="scanner-video"></div>
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

.scanner-video :deep(video) {
  block-size: 100% !important;
  inline-size: 100% !important;
  object-fit: cover !important;
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
