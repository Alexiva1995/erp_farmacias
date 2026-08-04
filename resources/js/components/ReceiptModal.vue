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
    <VCard class="rounded-lg overflow-hidden">
      <VCardTitle class="pa-0">
        <div class="premium-dialog-header pa-5 d-flex align-center bg-success">
          <VAvatar
            size="40"
            color="rgba(255,255,255,0.2)"
            class="me-3 rounded-lg shadow-sm"
          >
            <VIcon icon="tabler-file-dollar" color="white" size="24" />
          </VAvatar>
          <span class="text-h6 font-weight-black text-white">Comprobante Digital</span>
          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="isVisible = false"
          >
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>
      <VCardText class="pa-6 text-center">
        <VImg
          :src="props.receiptUrl"
          alt="Comprobante de Pago"
          class="rounded-lg border shadow-lg mx-auto"
          contain
        >
          <template #placeholder>
            <div class="d-flex align-center justify-center h-100 bg-surface-variant-light">
              <VProgressCircular indeterminate color="primary" />
            </div>
          </template>
        </VImg>
      </VCardText>
      <VCardActions class="pa-4 pt-0">
        <VBtn
          block
          color="success"
          variant="tonal"
          class="rounded-lg font-weight-black"
          @click="isVisible = false"
        >
          ENTENDIDO
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 0.04);
}

.premium-dialog-header {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end)) 100%
  );
}
</style>
