<script setup>
import { ref, onUnmounted, watch, nextTick } from 'vue';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  imageSource: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'confirm']);

const imageElement = ref(null);
const cropper     = ref(null);
const loading     = ref(false);
const ratioMode   = ref('1'); // 1:1 = proporción exacta del ecommerce

const ratios = [
  { label: '1:1 · Ecommerce', value: '1'    },
  { label: 'Libre',           value: 'free' },
  { label: '4:3',             value: '4/3'  },
  { label: '16:9',            value: '16/9' },
];

const getAspectRatio = () => {
  if (ratioMode.value === '1')   return 1;
  if (ratioMode.value === '4/3') return 4 / 3;
  if (ratioMode.value === '16/9') return 16 / 9;
  return NaN;
};

const initCropper = () => {
  if (cropper.value) { cropper.value.destroy(); cropper.value = null; }
  if (!imageElement.value) return;
  cropper.value = new Cropper(imageElement.value, {
    aspectRatio: getAspectRatio(),
    viewMode: 1,
    dragMode: 'move',
    autoCropArea: 0.85,
    restore: false,
    guides: true,
    center: true,
    highlight: false,
    cropBoxMovable: true,
    cropBoxResizable: true,
    toggleDragModeOnDblclick: false,
    responsive: true,
  });
};

watch(() => props.modelValue, async (val) => {
  if (val) {
    await nextTick();
    setTimeout(initCropper, 300);
  } else if (cropper.value) {
    cropper.value.destroy();
    cropper.value = null;
  }
});

watch(ratioMode, () => {
  cropper.value?.setAspectRatio(getAspectRatio());
});

onUnmounted(() => { if (cropper.value) cropper.value.destroy(); });

const rotateLeft  = () => cropper.value?.rotate(-90);
const rotateRight = () => cropper.value?.rotate(90);
const zoomIn      = () => cropper.value?.zoom(0.1);
const zoomOut     = () => cropper.value?.zoom(-0.1);
const reset       = () => cropper.value?.reset();

const handleConfirm = () => {
  if (!cropper.value) return;
  loading.value = true;
  const canvas = cropper.value.getCroppedCanvas({
    maxWidth: 1400, maxHeight: 1400,
    fillColor: '#fff',
    imageSmoothingEnabled: true,
    imageSmoothingQuality: 'high',
  });
  canvas.toBlob((blob) => {
    loading.value = false;
    const file = new File([blob], 'product-image.jpg', { type: 'image/jpeg', lastModified: Date.now() });
    emit('confirm', file);
    emit('update:modelValue', false);
  }, 'image/jpeg', 0.92);
};

const closeDialog = () => emit('update:modelValue', false);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="600"
    persistent
    scrollable
    transition="scale-transition"
  >
    <VCard class="cropper-card">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center">
          <VAvatar color="white" variant="flat" size="32" class="me-3 rounded">
            <VIcon icon="tabler-crop" color="primary" size="20" />
          </VAvatar>
          <span class="text-subtitle-1 font-weight-black text-white">RECORTAR FOTOGRAFÍA</span>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" @click="closeDialog" />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 bg-light">
        <div class="cropper-container rounded-lg border overflow-hidden shadow-inner">
          <img 
            ref="imageElement" 
            :src="props.imageSource" 
            class="max-width-100" 
            style="display: block; max-width: 100%;"
          />
        </div>

        <!-- Selector de relación de aspecto -->
        <div class="d-flex justify-center gap-2 mt-4 flex-wrap">
          <VChip
            v-for="r in ratios"
            :key="r.value"
            :color="ratioMode === r.value ? 'primary' : 'default'"
            size="small"
            density="comfortable"
            class="font-weight-bold cursor-pointer"
            @click="ratioMode = r.value"
          >{{ r.label }}</VChip>
        </div>

        <!-- Controles de transformación -->
        <div class="d-flex justify-center gap-2 mt-3 flex-wrap">
          <VBtn icon="tabler-zoom-in"              variant="tonal" color="primary"   size="small" @click="zoomIn"      title="Acercar" />
          <VBtn icon="tabler-zoom-out"             variant="tonal" color="primary"   size="small" @click="zoomOut"     title="Alejar" />
          <VBtn icon="tabler-rotate-2"             variant="tonal" color="secondary" size="small" @click="rotateLeft"  title="Rotar izquierda" />
          <VBtn icon="tabler-rotate-clockwise"     variant="tonal" color="secondary" size="small" @click="rotateRight" title="Rotar derecha" />
          <VBtn icon="tabler-refresh"              variant="tonal" color="warning"   size="small" @click="reset"       title="Restablecer" />
        </div>

        <div class="text-center mt-3 text-caption text-disabled font-weight-bold uppercase letter-spacing-1">
          Arrastra para mover · Ajusta las esquinas para recortar
        </div>
      </VCardText>

      <VCardActions class="pa-4 bg-white border-t">
        <VRow no-gutters>
          <VCol cols="6" class="pe-2">
            <VBtn
              block
              variant="tonal"
              color="secondary"
              height="44"
              class="font-weight-black rounded-lg"
              @click="closeDialog"
            >
              CANCELAR
            </VBtn>
          </VCol>
          <VCol cols="6" class="ps-2">
            <VBtn
              block
              color="primary"
              height="44"
              class="font-weight-black rounded-lg shadow-primary"
              :loading="loading"
              @click="handleConfirm"
            >
              CONFIRMAR RECORTE
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.cropper-card {
  border-radius: 8px !important;
}

.cropper-container {
  background-color: #000;
  min-height: 300px;
  max-height: 400px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.bg-light { background-color: #f8fafc !important; }

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.3) !important;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

/* Personalización de Cropper.js */
:deep(.cropper-view-box),
:deep(.cropper-face) {
  border-radius: 8px; /* O 50% para ver preview circular */
}

/* Si el usuario prefiere ver el área de recorte como círculo */
:deep(.cropper-view-box) {
  outline: 0;
  box-shadow: 0 0 0 1px #39f;
}
</style>
