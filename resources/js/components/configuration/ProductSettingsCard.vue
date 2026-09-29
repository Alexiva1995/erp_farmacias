<script setup>
const props = defineProps({
  enableProductTypes: { type: Boolean, default: true },
  enableFavorites: { type: Boolean, default: true },
  enableBulkToggleActive: { type: Boolean, default: true },
  enableVariations: { type: Boolean, default: true },
  enableMerge: { type: Boolean, default: false },
  enableGroups: { type: Boolean, default: true },
  enableExpirations: { type: Boolean, default: true },
  enableDonations: { type: Boolean, default: true },
  enableBrandGroups: { type: Boolean, default: false },
  enableLocations: { type: Boolean, default: true },
  enableOptimization: { type: Boolean, default: true },
  enableDishes: { type: Boolean, default: true },
  traceabilityMode: { type: String, default: 'units' },
  isSaving: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: true },
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
  'change',
])

const updateField = (val, fieldName) => {
  if (props.isSaving || !props.canEdit) return
  emit(`update:${fieldName}`, val)
  emit('change')
}

const toggleTraceability = (val) => {
  if (props.isSaving || !props.canEdit) return
  emit('update:traceabilityMode', val ? 'consumption' : 'units')
  emit('change')
}

// Configuración de módulos con microtextos y tooltips
const features = [
  {
    key: 'enableProductTypes',
    title: 'Tipos de Productos',
    description: 'Clasificación por tipo (Redundantes, Exentos, Novaventa, etc.).',
    tooltip: 'Permite filtrar el catálogo por tipo de producto y clasificaciones especiales.',
    icon: 'tabler-category',
  },
  {
    key: 'enableFavorites',
    title: 'Productos Favoritos',
    description: 'Destaca productos frecuentes en el catálogo y tienda virtual.',
    tooltip: 'Habilita el marcado con estrella para acceso rápido en ventas.',
    icon: 'tabler-star',
  },
  {
    key: 'enableBulkToggleActive',
    title: 'Activar / Inactivar Lote',
    description: 'Botón para alternar el estado activo de productos seleccionados.',
    tooltip: 'Permite deshabilitar o habilitar múltiples productos simultáneamente.',
    icon: 'tabler-power',
  },
  {
    key: 'enableVariations',
    title: 'Variaciones',
    description: 'Habilita pestañas de tallas, colores y presentaciones.',
    tooltip: 'Gestiona matrices de atributos en fichas de producto.',
    icon: 'tabler-versions',
  },
  {
    key: 'enableMerge',
    title: 'Fusión de Productos',
    description: 'Permite unificar productos duplicados en el inventario.',
    tooltip: 'Atención: Combina historiales, precios y existencias en un único registro.',
    icon: 'tabler-git-merge',
  },
  {
    key: 'enableGroups',
    title: 'Grupos de Productos',
    description: 'Agrupación para combos, promociones y clasificaciones.',
    tooltip: 'Permite armar kits promocionales y listas de precios por combo.',
    icon: 'tabler-packages',
  },
  {
    key: 'enableExpirations',
    title: 'Módulo Caducidad',
    description: 'Control de fechas de vencimiento y semaforización de alertas.',
    tooltip: 'Muestra accesos y reportes de lotes por vencer en el menú.',
    icon: 'tabler-calendar-off',
  },
  {
    key: 'enableDonations',
    title: 'Donaciones',
    description: 'Registra actas y cartas institucionales de donación.',
    tooltip: 'Genera salidas de inventario bajo el concepto de donación benéfica.',
    icon: 'tabler-heart-handshake',
  },
  {
    key: 'enableBrandGroups',
    title: 'Grupos de Marcas',
    description: 'Manejo de marcas agrupadas por corporaciones.',
    tooltip: 'Organiza laboratorios y marcas bajo un mismo consorcio o distribuidor.',
    icon: 'tabler-brand-sublime',
  },
  {
    key: 'enableLocations',
    title: 'Ubicaciones',
    description: 'Muestra la opción de pasillos y estantes en el menú.',
    tooltip: 'Control de coordenadas físicas de almacenamiento dentro de la farmacia.',
    icon: 'tabler-map-pin',
  },
  {
    key: 'enableOptimization',
    title: 'Optimización',
    description: 'Submenú para productos incompletos y lotificación.',
    tooltip: 'Auditoría rápida de ítems sin código, sin costo o sin imagen.',
    icon: 'tabler-bolt',
  },
  {
    key: 'enableDishes',
    title: 'Platos / Menú',
    description: 'Habilita la gestión de menú gastronómico.',
    tooltip: 'Activa la vista de recetas y preparaciones para áreas de cafetería o restaurante.',
    icon: 'tabler-soup',
  },
]
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm">
    <VCardItem class="py-5">
      <!-- Encabezado Estandarizado -->
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
            class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between transition-all"
            :class="props[item.key] ? 'border-primary bg-surface' : 'opacity-90'"
          >
            <div>
              <div class="d-flex align-start justify-space-between w-100 mb-3">
                <div class="d-flex align-center gap-3">
                  <VAvatar
                    :color="props[item.key] ? 'primary' : 'secondary'"
                    variant="tonal"
                    size="38"
                    class="rounded-lg"
                  >
                    <VIcon :icon="item.icon" size="20" />
                  </VAvatar>
                  <div>
                    <div class="d-flex align-center gap-1">
                      <h3 class="text-subtitle-2 font-weight-bold mb-0">
                        {{ item.title }}
                      </h3>
                      <VTooltip v-if="item.tooltip" location="top">
                        <template #activator="{ props: tooltipProps }">
                          <VIcon
                            v-bind="tooltipProps"
                            icon="tabler-info-circle"
                            size="16"
                            class="text-medium-emphasis cursor-pointer"
                          />
                        </template>
                        <span>{{ item.tooltip }}</span>
                      </VTooltip>
                    </div>
                    <VChip
                      :color="props[item.key] ? 'success' : 'secondary'"
                      size="x-small"
                      variant="tonal"
                      class="mt-1 font-weight-bold"
                    >
                      {{ props[item.key] ? 'Habilitado' : 'Deshabilitado' }}
                    </VChip>
                  </div>
                </div>
                <VSwitch
                  :model-value="props[item.key]"
                  color="primary"
                  density="comfortable"
                  hide-details="auto"
                  :disabled="isSaving || !canEdit"
                  @update:model-value="(val) => updateField(val, item.key)"
                />
              </div>
              <p class="text-caption text-medium-emphasis mb-0">
                {{ item.description }}
              </p>
            </div>
          </VCard>
        </VCol>

        <!-- Trazabilidad Especial -->
        <VCol cols="12" sm="6" md="4" lg="3">
          <VCard
            variant="outlined"
            class="rounded-lg h-100 pa-4 d-flex flex-column justify-space-between transition-all"
            :class="traceabilityMode === 'consumption' ? 'border-primary bg-surface' : 'opacity-90'"
          >
            <div>
              <div class="d-flex align-start justify-space-between w-100 mb-3">
                <div class="d-flex align-center gap-3">
                  <VAvatar
                    :color="traceabilityMode === 'consumption' ? 'primary' : 'secondary'"
                    variant="tonal"
                    size="38"
                    class="rounded-lg"
                  >
                    <VIcon icon="tabler-scale" size="20" />
                  </VAvatar>
                  <div>
                    <div class="d-flex align-center gap-1">
                      <h3 class="text-subtitle-2 font-weight-bold mb-0">Trazabilidad</h3>
                      <VTooltip location="top">
                        <template #activator="{ props: tooltipProps }">
                          <VIcon
                            v-bind="tooltipProps"
                            icon="tabler-info-circle"
                            size="16"
                            class="text-medium-emphasis cursor-pointer"
                          />
                        </template>
                        <span>Alterna entre control de consumo fraccionado y control por unidades cerradas.</span>
                      </VTooltip>
                    </div>
                    <VChip
                      :color="traceabilityMode === 'consumption' ? 'primary' : 'secondary'"
                      size="x-small"
                      variant="tonal"
                      class="mt-1 font-weight-bold"
                    >
                      {{ traceabilityMode === 'consumption' ? 'Consumo' : 'Unidades' }}
                    </VChip>
                  </div>
                </div>
                <VSwitch
                  :model-value="traceabilityMode === 'consumption'"
                  color="primary"
                  density="comfortable"
                  hide-details="auto"
                  :disabled="isSaving || !canEdit"
                  @update:model-value="toggleTraceability"
                />
              </div>
              <p class="text-caption text-medium-emphasis mb-0">
                Seguimiento por consumo (peso/volumen) o unidades fijas.
              </p>
            </div>
          </VCard>
        </VCol>
      </VRow>
    </VCardItem>
  </VCard>
</template>
