<script setup>
import { computed } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  receiptUrl: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const isVisible = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

const openOriginal = () => {
  if (props.receiptUrl) {
    window.open(props.receiptUrl, "_blank");
  }
};
</script>

<template>
  <VDialog
    v-model="isVisible"
    max-width="850"
    scrollable
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

      <VCardText class="pa-4 pa-sm-6 text-center bg-light receipt-container">
        <div class="d-flex justify-center align-center w-100">
          <img
            v-if="props.receiptUrl"
            :src="props.receiptUrl"
            alt="Comprobante de Pago"
            class="receipt-image rounded-xl border shadow-md"
          />
          <div v-else class="pa-10 text-medium-emphasis">
            No se ha encontrado el archivo del comprobante.
          </div>
        </div>
      </VCardText>

      <VCardActions class="pa-4 px-6 bg-white border-t d-flex justify-space-between align-center flex-shrink-0">
        <VBtn
          variant="outlined"
          color="secondary"
          class="rounded-lg font-weight-bold"
          prepend-icon="tabler-external-link"
          @click="openOriginal"
        >
          Ver Original
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          class="rounded-lg font-weight-bold px-6"
          @click="isVisible = false"
        >
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
  max-block-size: 90vh;
}

.bg-light {
  background-color: #f8faff !important;
}

.receipt-container {
  overflow-y: auto !important;
  max-block-size: calc(90vh - 140px);
}

.receipt-image {
  max-inline-size: 100%;
  block-size: auto;
  object-fit: contain;
  background-color: #ffffff;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
