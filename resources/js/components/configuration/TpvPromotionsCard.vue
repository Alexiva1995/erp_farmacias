<script setup>
const props = defineProps({
  enabledOfferTypes: {
    type: Array,
    required: true
  },
  canEdit: {
    type: Boolean,
    default: true
  },
  isSaving: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['toggle'])

const availableOfferOptions = [
  { key: 'general', title: 'Oferta General (% Descuento)', icon: 'tabler-percentage', description: 'Descuento porcentual aplicable al total o base de la venta.' },
  { key: 'individual', title: 'Oferta Individual (por Producto)', icon: 'tabler-package', description: 'Precios especiales asignados por código o ítem específico.' },
  { key: 'category', title: 'Oferta por Categoría', icon: 'tabler-category', description: 'Promociones directas por línea o familia de artículos.' },
  { key: 'pack', title: 'Oferta Combos / Packs', icon: 'tabler-packages', description: 'Empaquetado de productos con tarifa promocional fija.' },
  { key: 'company', title: 'Oferta por Convenio', icon: 'tabler-building', description: 'Descuentos acordados con aseguradoras y empresas corporativas.' },
  { key: 'doctor', title: 'Oferta por Médico', icon: 'tabler-stethoscope', description: 'Bonificaciones e incentivos según especialista prescriptor.' },
  { key: 'prescription', title: 'Oferta por Receta / Récipe', icon: 'tabler-file-text', description: 'Condiciones especiales ligadas a prescripción médica física o digital.' },
  { key: 'expiration', title: 'Oferta por Caducidad', icon: 'tabler-calendar-time', description: 'Liquidación de lotes con fecha próxima de vencimiento.' },
]

const handleToggle = (key) => {
  if (!props.canEdit || props.isSaving) return
  emit('toggle', key)
}
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm">
    <VCardItem class="px-6 py-5">
      <!-- Encabezado Estandarizado -->
      <div class="d-flex align-center gap-3 mb-2">
        <VAvatar color="primary" variant="tonal" size="36" class="rounded-lg">
          <VIcon icon="tabler-tags" size="22" />
        </VAvatar>
        <div>
          <VCardTitle class="text-h6 font-weight-bold mb-0">
            Tipos de Ofertas y Promociones Habilitadas
          </VCardTitle>
          <VCardSubtitle class="text-body-2 text-medium-emphasis">
            Selecciona qué esquemas de descuentos y promociones estarán disponibles en el TPV y caja rápida.
          </VCardSubtitle>
        </div>
      </div>

      <VDivider class="my-4" />

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
            class="rounded-lg transition-all h-100 pa-4 d-flex flex-column justify-space-between cursor-pointer"
            :class="[
              enabledOfferTypes.includes(offer.key)
                ? 'border-primary bg-var-theme-background'
                : 'opacity-75',
              { 'pointer-events-none opacity-50': !canEdit || isSaving }
            ]"
            @click="handleToggle(offer.key)"
          >
            <div>
              <div class="d-flex align-center justify-space-between mb-3">
                <VAvatar
                  :color="enabledOfferTypes.includes(offer.key) ? 'primary' : 'secondary'"
                  variant="tonal"
                  size="36"
                  class="rounded-lg"
                >
                  <VIcon :icon="offer.icon" size="20" />
                </VAvatar>
                <VSwitch
                  :model-value="enabledOfferTypes.includes(offer.key)"
                  density="comfortable"
                  hide-details="auto"
                  color="primary"
                  :disabled="!canEdit || isSaving"
                  @click.stop
                  @update:model-value="() => handleToggle(offer.key)"
                />
              </div>

              <h4
                class="text-subtitle-2 font-weight-bold mb-1"
                :class="enabledOfferTypes.includes(offer.key) ? 'text-high-emphasis' : 'text-medium-emphasis'"
              >
                {{ offer.title }}
              </h4>
              <p class="text-caption text-medium-emphasis mb-0">
                {{ offer.description }}
              </p>
            </div>
          </VCard>
        </VCol>
      </VRow>
    </VCardItem>
  </VCard>
</template>

