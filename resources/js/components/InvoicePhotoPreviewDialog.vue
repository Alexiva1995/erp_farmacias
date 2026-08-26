<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  previewImageUrl: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const close = () => {
  emit("update:modelValue", false);
};
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="800px"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="rounded-lg overflow-hidden">
      <VCardTitle class="d-flex justify-space-between align-center py-3 bg-surface">
        <span class="text-h6 font-weight-bold">Vista Previa de Factura</span>
        <VBtn icon="tabler-x" variant="text" size="small" @click="close" />
      </VCardTitle>
      <VDivider />
      <VCardText class="pa-0 bg-surface d-flex justify-center align-center" style="min-height: 500px; height: 80vh;">
        <iframe
          v-if="previewImageUrl.toLowerCase().endsWith('.pdf') || previewImageUrl.toLowerCase().includes('.pdf')"
          :src="previewImageUrl"
          width="100%"
          height="100%"
          style="border: none;"
        />
        <VImg
          v-else
          :src="previewImageUrl"
          width="100%"
          height="100%"
          cover
          class="rounded-0"
        >
          <template #placeholder>
            <div class="d-flex align-center justify-center fill-height">
              <VProgressCircular indeterminate color="primary" />
            </div>
          </template>
        </VImg>
      </VCardText>
      <VDivider />
      <VCardActions class="pa-3 bg-surface">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="ps-0 pe-2">
            <VBtn
              variant="tonal"
              color="secondary"
              block
              class="rounded-lg font-weight-bold"
              @click="close"
            >
              <VIcon start icon="tabler-x" />
              Cerrar
            </VBtn>
          </VCol>
          <VCol cols="6" class="ps-2 pe-0">
            <VBtn
              color="primary"
              variant="flat"
              block
              :href="previewImageUrl"
              target="_blank"
              class="rounded-lg font-weight-bold"
            >
              <VIcon start icon="tabler-external-link" />
              Abrir en Pestaña Nueva
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
