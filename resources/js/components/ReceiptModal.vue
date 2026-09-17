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
</script>

<template>
  <VDialog
    v-model="isVisible"
    max-width="700"
    :fullscreen="$vuetify.display.smAndDown"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <VCardTitle class="pa-0">
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
            class="rounded-lg"
            @click="isVisible = false"
          >
            <VIcon icon="tabler-x" size="20" />
            <VTooltip activator="parent" location="top">Cerrar</VTooltip>
          </IconBtn>
        </div>
      </VCardTitle>
      <VCardText class="pa-4 pa-sm-6 text-center bg-light">
        <VImg
          :src="props.receiptUrl"
          alt="Comprobante de Pago"
          class="rounded-xl border shadow-md mx-auto"
          contain
          max-height="600"
        >
          <template #placeholder>
            <div class="d-flex align-center justify-center h-100 pa-10 bg-surface-variant-light">
              <VProgressCircular indeterminate color="primary" size="40" />
            </div>
          </template>
        </VImg>
      </VCardText>
      <VCardActions class="pa-4 bg-white border-t d-flex justify-end">
        <VBtn
          color="primary"
          variant="flat"
          height="42"
          class="rounded-lg font-weight-black px-6"
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
}

.bg-light {
  background-color: #f8faff !important;
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 0.04);
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
