<script setup>
const props = defineProps({
  enabledOfferTypes: {
    type: Array,
    required: true
  },
  isSaving: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['toggle'])

const availableOfferOptions = [
  { key: 'general', title: 'Oferta General (% Descuento)', icon: 'tabler-percentage' },
  { key: 'individual', title: 'Oferta Individual (por Producto)', icon: 'tabler-package' },
  { key: 'category', title: 'Oferta por Categoría', icon: 'tabler-category' },
  { key: 'pack', title: 'Oferta Combos / Packs', icon: 'tabler-packages' },
  { key: 'company', title: 'Oferta por Convenio', icon: 'tabler-building' },
  { key: 'doctor', title: 'Oferta por Médico', icon: 'tabler-stethoscope' },
  { key: 'prescription', title: 'Oferta por Receta / Récipe', icon: 'tabler-file-text' },
  { key: 'expiration', title: 'Oferta por Caducidad', icon: 'tabler-calendar-time' },
]

const handleToggle = (key) => {
  if (props.isSaving) return
  emit('toggle', key)
}
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm">
    <VCardItem class="py-5">
      <!-- Encabezado Principal Estandarizado -->
      <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2 mb-2">
        <VIcon icon="tabler-tags" color="primary" size="28" />
        Tipos de Ofertas y Promociones Habilitadas
      </VCardTitle>
      <p class="text-caption text-medium-emphasis mb-6">
        Selecciona las promociones que estarán activas en el Punto de Venta (TPV) y el catálogo.
      </p>

      <VDivider class="mb-6" />

      <VRow>
        <VCol
          v-for="offer in availableOfferOptions"
          :key="offer.key"
          cols="12"
          sm="6"
          md="3"
        >
          <VCard
            variant="outlined"
            class="rounded-lg cursor-pointer transition-all h-100 offer-option-card"
            :class="[
              enabledOfferTypes.includes(offer.key)
                ? 'is-active border-primary'
                : 'border-color-light opacity-70',
              { 'is-disabled pointer-events-none opacity-50': isSaving }
            ]"
            @click="handleToggle(offer.key)"
          >
            <VCardItem class="py-3 px-4">
              <div class="d-flex align-center justify-space-between w-100">
                <div class="d-flex align-center me-2">
                  <VAvatar
                    :color="enabledOfferTypes.includes(offer.key) ? 'primary' : 'secondary'"
                    variant="tonal"
                    size="32"
                    class="me-2 rounded-lg"
                  >
                    <VIcon :icon="offer.icon" size="18" />
                  </VAvatar>
                  <span
                    class="font-weight-black text-body-2 leading-tight"
                    :class="enabledOfferTypes.includes(offer.key) ? 'text-high-emphasis' : 'text-disabled'"
                  >
                    {{ offer.title }}
                  </span>
                </div>
                <VSwitch
                  :model-value="enabledOfferTypes.includes(offer.key)"
                  density="compact"
                  hide-details
                  color="primary"
                  :disabled="isSaving"
                  @click.stop
                  @update:model-value="handleToggle(offer.key)"
                />
              </div>
            </VCardItem>
          </VCard>
        </VCol>
      </VRow>
    </VCardItem>
  </VCard>
</template>

<style scoped>
.offer-option-card {
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  border-width: 1.5px !important;
}

.offer-option-card:hover:not(.is-disabled) {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px -4px rgba(var(--v-theme-primary), 0.15) !important;
}

.offer-option-card.is-active {
  background-color: rgba(var(--v-theme-primary), 0.03) !important;
}

.border-color-light {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
}
</style>
