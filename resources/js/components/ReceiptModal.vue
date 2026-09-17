<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  receiptUrl: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const isVisible = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

const isZoomed = ref(false);

const openOriginal = () => {
  if (props.receiptUrl) {
    window.open(props.receiptUrl, "_blank");
  }
};

const downloadReceipt = async () => {
  if (!props.receiptUrl) return;
  try {
    const response = await fetch(props.receiptUrl);
    const blob = await response.blob();
    const blobUrl = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = blobUrl;
    link.download = `comprobante_${Date.now()}.${props.receiptUrl.split(".").pop()?.split("?")[0] || "jpg"}`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(blobUrl);
  } catch {
    openOriginal();
  }
};
</script>

<template>
  <VDialog
    v-model="isVisible"
    max-width="900"
    :fullscreen="$vuetify.display.smAndDown"
    :transition="$vuetify.display.smAndDown ? 'dialog-bottom-transition' : 'scale-transition'"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface d-flex flex-column">
      <VCardTitle class="pa-0 flex-shrink-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            size="40"
            color="white"
            variant="flat"
            class="me-3 shadow-sm rounded-lg elevation-1"
          >
            <VIcon icon="tabler-file-dollar" color="primary" size="24" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h3 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Comprobante Digital
            </h3>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Soporte de Transferencia / Pago
              </span>
            </div>
          </div>
          <VSpacer />
          <IconBtn
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg me-1"
            @click="downloadReceipt"
          >
            <VIcon icon="tabler-download" size="18" />
            <VTooltip activator="parent" location="top">Descargar comprobante</VTooltip>
          </IconBtn>
          <IconBtn
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg me-1"
            @click="openOriginal"
          >
            <VIcon icon="tabler-external-link" size="18" />
            <VTooltip activator="parent" location="top">Abrir en pestaña nueva</VTooltip>
          </IconBtn>
          <IconBtn
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="isVisible = false"
          >
            <VIcon icon="tabler-x" size="20" />
            <VTooltip activator="parent" location="top">Cerrar</VTooltip>
          </IconBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-3 pa-sm-4 bg-light d-flex justify-center align-center receipt-body-wrapper">
        <div v-if="props.receiptUrl" class="receipt-preview-box rounded-xl border shadow-sm position-relative overflow-hidden">
          <img
            :src="props.receiptUrl"
            alt="Comprobante de Pago"
            class="receipt-fit-image cursor-pointer"
            @click="isZoomed = true"
          />
          <div class="zoom-overlay d-flex align-center justify-center cursor-pointer" @click="isZoomed = true">
            <VChip size="small" color="surface" variant="flat" class="font-weight-bold shadow-sm">
              <VIcon icon="tabler-zoom-in" size="16" class="me-1" />
              Clic para ampliar
            </VChip>
          </div>
        </div>
        <div v-else class="pa-10 text-medium-emphasis text-center">
          <VIcon icon="tabler-file-off" size="48" color="disabled" class="mb-2" />
          <p class="text-sm font-weight-medium mb-0">No se ha encontrado el archivo del comprobante.</p>
        </div>
      </VCardText>

      <VCardActions class="pa-4 px-6 bg-white border-t">
        <VRow class="ma-0 w-100" no-gutters>
          <VCol cols="6" class="pe-2">
            <VBtn
              block
              height="44"
              variant="outlined"
              color="secondary"
              class="rounded-lg font-weight-bold"
              prepend-icon="tabler-download"
              @click="downloadReceipt"
            >
              Descargar
            </VBtn>
          </VCol>
          <VCol cols="6" class="ps-2">
            <VBtn
              block
              height="44"
              color="primary"
              variant="flat"
              class="rounded-lg font-weight-bold shadow-sm"
              @click="isVisible = false"
            >
              Cerrar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>

    <!-- Diálogo para ampliar / zoom de imagen a pantalla completa -->
    <VDialog v-model="isZoomed" max-width="1200" scrollable>
      <VCard class="rounded-xl overflow-hidden bg-surface">
        <VCardTitle class="pa-3 bg-surface border-b d-flex align-center justify-space-between">
          <span class="text-subtitle-1 font-weight-bold">Vista Ampliada</span>
          <div class="d-flex align-center gap-2">
            <IconBtn size="small" variant="tonal" color="primary" @click="downloadReceipt">
              <VIcon icon="tabler-download" size="18" />
              <VTooltip activator="parent">Descargar</VTooltip>
            </IconBtn>
            <IconBtn size="small" variant="tonal" color="secondary" @click="openOriginal">
              <VIcon icon="tabler-external-link" size="18" />
              <VTooltip activator="parent">Abrir Original</VTooltip>
            </IconBtn>
            <IconBtn size="small" variant="tonal" color="secondary" @click="isZoomed = false">
              <VIcon icon="tabler-x" size="18" />
            </IconBtn>
          </div>
        </VCardTitle>
        <VCardText class="pa-4 text-center bg-light overflow-auto" style="max-block-size: 85vh;">
          <img
            :src="props.receiptUrl"
            alt="Comprobante Ampliado"
            class="rounded-lg shadow-sm"
            style="max-inline-size: 100%; block-size: auto;"
          />
        </VCardText>
      </VCard>
    </VDialog>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.bg-light {
  background-color: #f8faff !important;
}

.receipt-body-wrapper {
  min-block-size: 380px;
  max-block-size: 70vh;
  overflow: hidden;
}

.receipt-preview-box {
  background-color: #ffffff;
  display: flex;
  justify-content: center;
  align-items: center;
  max-inline-size: 100%;
  max-block-size: 65vh;
}

.receipt-fit-image {
  max-inline-size: 100%;
  max-block-size: 65vh;
  object-fit: contain;
  display: block;
}

.zoom-overlay {
  position: absolute;
  inset-block-end: 12px;
  inset-inline-end: 12px;
  opacity: 0.85;
  transition: opacity 0.2s ease;
}

.zoom-overlay:hover {
  opacity: 1;
}

.cursor-pointer {
  cursor: pointer;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.border-b {
  border-block-end: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>

