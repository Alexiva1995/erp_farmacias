<script setup>
// Panel lateral empotrado para visor de PDF / Foto de Factura
const props = defineProps({
  previewImageUrl: {
    type: String,
    required: true,
  },
})

const emit = defineEmits(['openModal', 'close'])

const isPdf = computed(() => {
  const url = (props.previewImageUrl || '').toLowerCase()
  return url.endsWith('.pdf') || url.includes('.pdf')
})
</script>

<template>
  <VCard class="pdf-side-card border shadow-sm sticky-pdf-panel rounded-lg overflow-hidden">
    <VCardTitle class="py-2 px-3 bg-surface border-b d-flex justify-space-between align-center">
      <div class="d-flex align-center ga-1">
        <VIcon icon="tabler-file-type-pdf" color="error" size="18" />
        <span class="text-subtitle-2 font-weight-bold">Factura Digital</span>
      </div>
      <div class="d-flex align-center ga-1">
        <VTooltip text="Abrir en ventana completa">
          <template #activator="{ props: tipProps }">
            <VBtn
              v-bind="tipProps"
              icon="tabler-maximize"
              size="x-small"
              variant="text"
              @click="emit('openModal')"
            />
          </template>
        </VTooltip>
        <VTooltip text="Cerrar visor lateral">
          <template #activator="{ props: tipProps }">
            <VBtn
              v-bind="tipProps"
              icon="tabler-x"
              size="x-small"
              variant="text"
              @click="emit('close')"
            />
          </template>
        </VTooltip>
      </div>
    </VCardTitle>
    <VCardText class="pa-0 bg-grey-lighten-4 pdf-iframe-container">
      <iframe
        v-if="isPdf"
        :src="previewImageUrl"
        width="100%"
        height="100%"
        class="pdf-embed-frame"
        style="border: none; min-height: calc(100vh - 170px); height: calc(100vh - 170px);"
      />
      <VImg
        v-else
        :src="previewImageUrl"
        width="100%"
        height="100%"
        cover
        style="min-height: calc(100vh - 170px); height: calc(100vh - 170px);"
      />
    </VCardText>
  </VCard>
</template>

<style scoped>
.sticky-pdf-panel {
  position: sticky;
  top: 80px;
  max-height: calc(100vh - 100px);
  z-index: 5;
}
</style>
