<script setup>
const props = defineProps({
  enableProductTypes: Boolean,
  enableFavorites: Boolean,
  enableBulkToggleActive: Boolean,
  enableVariations: Boolean,
  enableMerge: Boolean,
  enableGroups: Boolean,
  enableExpirations: Boolean,
  enableDonations: Boolean,
  enableBrandGroups: Boolean,
  enableLocations: Boolean,
  enableOptimization: Boolean,
  enableDishes: Boolean,
  traceabilityMode: String,
  isSaving: Boolean
})

const emit = defineEmits([
  'update:enableProductTypes',
  'update:enableFavorites',
  'update:enableBulkToggleActive',
  'update:enableVariations',
  'update:enableMerge',
  'update:enableGroups',
  'update:enableExpirations',
  'update:enableDonations',
  'update:enableBrandGroups',
  'update:enableLocations',
  'update:enableOptimization',
  'update:enableDishes',
  'update:traceabilityMode',
  'change'
])

const updateField = (val, fieldName) => {
  if (props.isSaving) return
  emit(`update:${fieldName}`, val)
  emit('change')
}

const toggleTraceability = (val) => {
  if (props.isSaving) return
  emit('update:traceabilityMode', val ? 'consumption' : 'units')
  emit('change')
}

const features = [
  { key: 'enableProductTypes', title: 'Tipos de Productos', description: 'Clasificación por tipo (Redundantes, Exentos, Novaventa, etc.).', icon: 'tabler-category' },
  { key: 'enableFavorites', title: 'Productos Favoritos', description: 'Destaca productos frecuentes en el catálogo y tienda virtual.', icon: 'tabler-star' },
  { key: 'enableBulkToggleActive', title: 'Botón Activar/Inactivar Lote', description: 'Muestra el botón para alternar el estado activo de productos seleccionados.', icon: 'tabler-power' },
  { key: 'enableVariations', title: 'Variaciones', description: 'Habilita pestañas de tallas, colores y presentaciones.', icon: 'tabler-versions' },
  { key: 'enableMerge', title: 'Fusión de Productos', description: 'Permite unificar productos duplicados en el inventario.', icon: 'tabler-git-merge' },
  { key: 'enableGroups', title: 'Grupos de Productos', description: 'Agrupación para combos, promociones y clasificaciones.', icon: 'tabler-packages' },
  { key: 'enableExpirations', title: 'Módulo Caducidad', description: 'Muestra el control de fechas de vencimiento en el menú.', icon: 'tabler-calendar-off' },
  { key: 'enableDonations', title: 'Donaciones', description: 'Registra actas y cartas institucionales de donación.', icon: 'tabler-heart-handshake' },
  { key: 'enableBrandGroups', title: 'Grupos de Marcas', description: 'Manejo de marcas agrupadas por corporaciones.', icon: 'tabler-brand-sublime' },
  { key: 'enableLocations', title: 'Ubicaciones', description: 'Muestra la opción de pasillos y estantes en el menú.', icon: 'tabler-map-pin' },
  { key: 'enableOptimization', title: 'Optimización', description: 'Submenú para productos incompletos y lotificación.', icon: 'tabler-bolt' },
  { key: 'enableDishes', title: 'Platos / Menú', description: 'Habilita la gestión de menú gastronómico.', icon: 'tabler-soup' },
]
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm">
    <VCardItem class="py-5">
      <!-- Encabezado Principal Estandarizado -->
      <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2 mb-2">
        <VIcon icon="tabler-settings" color="primary" size="28" />
        Configuración de Características de Productos
      </VCardTitle>
      <p class="text-caption text-medium-emphasis mb-6">
        Habilita o deshabilita los módulos, filtros y opciones especiales para el catálogo e inventario.
      </p>

      <VDivider class="mb-6" />

      <VRow>
        <VCol
          v-for="item in features"
          :key="item.key"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <VCard
            variant="outlined"
            class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between product-setting-card"
            :class="props[item.key] ? 'is-active border-primary' : 'border-color-light opacity-90'"
          >
            <div>
              <div class="d-flex align-center justify-space-between w-100 mb-3">
                <div class="d-flex align-center gap-2">
                  <VAvatar
                    :color="props[item.key] ? 'primary' : 'secondary'"
                    variant="tonal"
                    size="36"
                    class="rounded-lg"
                  >
                    <VIcon :icon="item.icon" size="18" />
                  </VAvatar>
                  <div>
                    <h3 class="text-subtitle-2 font-weight-bold mb-0 text-truncate" style="max-width: 120px;" :title="item.title">
                      {{ item.title }}
                    </h3>
                    <VChip
                      :color="props[item.key] ? 'success' : 'grey-darken-1'"
                      size="x-small"
                      variant="flat"
                      class="mt-1 font-weight-bold text-white"
                    >
                      {{ props[item.key] ? 'Habilitado' : 'Deshabilitado' }}
                    </VChip>
                  </div>
                </div>
                <VSwitch
                  :model-value="props[item.key]"
                  color="primary"
                  density="compact"
                  hide-details
                  :disabled="isSaving"
                  @update:model-value="(val) => updateField(val, item.key)"
                />
              </div>
              <p class="text-caption text-medium-emphasis mb-0 leading-tight">
                {{ item.description }}
              </p>
            </div>
          </VCard>
        </VCol>

        <!-- Trazabilidad Especial -->
        <VCol cols="12" sm="6" md="4" lg="3">
          <VCard
            variant="outlined"
            class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between product-setting-card"
            :class="traceabilityMode === 'consumption' ? 'is-active border-primary' : 'border-color-light opacity-90'"
          >
            <div>
              <div class="d-flex align-center justify-space-between w-100 mb-3">
                <div class="d-flex align-center gap-2">
                  <VAvatar
                    :color="traceabilityMode === 'consumption' ? 'primary' : 'secondary'"
                    variant="tonal"
                    size="36"
                    class="rounded-lg"
                  >
                    <VIcon icon="tabler-scale" size="18" />
                  </VAvatar>
                  <div>
                    <h3 class="text-subtitle-2 font-weight-bold mb-0">Trazabilidad</h3>
                    <VChip
                      :color="traceabilityMode === 'consumption' ? 'primary' : 'grey-darken-1'"
                      size="x-small"
                      variant="flat"
                      class="mt-1 font-weight-bold text-white"
                    >
                      {{ traceabilityMode === 'consumption' ? 'Consumo' : 'Unidades' }}
                    </VChip>
                  </div>
                </div>
                <VSwitch
                  :model-value="traceabilityMode === 'consumption'"
                  color="primary"
                  density="compact"
                  hide-details
                  :disabled="isSaving"
                  @update:model-value="toggleTraceability"
                />
              </div>
              <p class="text-caption text-medium-emphasis mb-0 leading-tight">
                Seguimiento por consumo (peso/volumen) o unidades fijas.
              </p>
            </div>
          </VCard>
        </VCol>
      </VRow>
    </VCardItem>
  </VCard>
</template>

<style scoped>
.product-setting-card {
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  border-width: 1.5px !important;
}

.product-setting-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px -4px rgba(var(--v-theme-primary), 0.15) !important;
}

.product-setting-card.is-active {
  background-color: rgba(var(--v-theme-primary), 0.03) !important;
}

.border-color-light {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
}
</style>
