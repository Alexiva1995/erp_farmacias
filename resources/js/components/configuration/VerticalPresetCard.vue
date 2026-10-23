<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast, confirmDialog } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'

const emit = defineEmits(['preset-applied'])

const brandingStore = useBrandingStore()
const isLoading = ref(false)
const isApplying = ref(false)
const presets = ref([])
const selectedVertical = ref(brandingStore.settings.business_type || 'pharmacy')

const currentVerticalLabel = computed(() => {
  const match = presets.value.find(p => p.key === brandingStore.settings.business_type)
  return match?.label || 'Farmacia & Salud'
})

const getVerticalIcon = (key) => {
  switch (key) {
    case 'pharmacy':
      return 'tabler-pill'
    case 'restaurant':
      return 'tabler-tools-kitchen-2'
    case 'fast_food':
      return 'tabler-burger'
    case 'minimarket':
      return 'tabler-building-store'
    default:
      return 'tabler-building'
  }
}

const getVerticalColor = (key) => {
  switch (key) {
    case 'pharmacy':
      return 'primary'
    case 'restaurant':
      return 'success'
    case 'fast_food':
      return 'warning'
    case 'minimarket':
      return 'info'
    default:
      return 'secondary'
  }
}

const fetchPresets = async () => {
  isLoading.value = true
  try {
    const { data } = await axios.get('/public/general-settings/presets')
    presets.value = data.data || []
  } catch (error) {
    console.error('Error cargando presets de verticales:', error)
    // Fallback estático seguro si falla la red
    presets.value = [
      {
        key: 'pharmacy',
        label: 'Farmacia & Salud Integral',
        description: 'Control de lotes FEFO, psicotrópicos, laboratorios, récipes y fechas de vencimiento.',
      },
      {
        key: 'restaurant',
        label: 'Restaurante con Salón (Mesas & Comandas)',
        description: 'Mapa de mesas, estaciones de cocina/KDS, escandallos, recetas, modificadores y división de cuentas.',
      },
      {
        key: 'fast_food',
        label: 'Comida Rápida, Cafetería o Dark Kitchen',
        description: 'Comanda directa a cocina, combos, modificadores, empaques CPV y cobro express.',
      },
      {
        key: 'minimarket',
        label: 'Bodegón, Minimarket & Retail',
        description: 'Venta minorista de alta rotación, lectores de código de barras, bultos y promociones.',
      },
    ]
  } finally {
    isLoading.value = false
  }
}

const applyPreset = async (verticalKey) => {
  const preset = presets.value.find(p => p.key === verticalKey)
  const isConfirmed = await confirmDialog({
    title: `¿Activar perfil "${preset?.label || verticalKey}"?`,
    text: 'Se precargarán y activarán automáticamente las vistas, reglas de inventario, estilo de TPV y parámetros ideales para este tipo de negocio. Podrás seguir personalizando cada opción manualmente después.',
    icon: 'question',
    confirmButtonText: 'Sí, aplicar perfil',
    cancelButtonText: 'Cancelar',
  })

  if (!isConfirmed) return

  isApplying.value = true
  try {
    const response = await brandingStore.applyVerticalPreset(verticalKey, true)
    selectedVertical.value = verticalKey
    toast.success(response?.message || 'Perfil de vertical aplicado exitosamente')
    emit('preset-applied', verticalKey)
  } catch (error) {
    console.error('Error aplicando preset de vertical:', error)
    toast.error(error.response?.data?.message || 'No se pudo aplicar el perfil de vertical')
  } finally {
    isApplying.value = false
  }
}

onMounted(() => {
  fetchPresets()
})
</script>

<template>
  <VCard variant="outlined" class="pa-5 rounded-lg mb-6 border">
    <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-4">
      <div class="d-flex align-center gap-3">
        <VAvatar color="primary" variant="tonal" rounded size="42">
          <VIcon icon="tabler-topology-star-3" size="26" />
        </VAvatar>
        <div>
          <div class="text-subtitle-1 font-weight-bold text-high-emphasis">
            Vertical y Perfil Operativo del Negocio
          </div>
          <div class="text-caption text-medium-emphasis">
            Selecciona la vertical para precargar en un clic los módulos y vistas ideales de tu empresa.
          </div>
        </div>
      </div>

      <VChip
        :color="getVerticalColor(brandingStore.settings.business_type)"
        variant="tonal"
        size="small"
        class="font-weight-medium"
      >
        <VIcon start size="16" :icon="getVerticalIcon(brandingStore.settings.business_type)" />
        Perfil Activo: {{ currentVerticalLabel }}
      </VChip>
    </div>

    <VProgressLinear
      v-if="isApplying"
      indeterminate
      color="primary"
      height="3"
      class="mb-4 rounded"
    />

    <VRow>
      <VCol
        v-for="preset in presets"
        :key="preset.key"
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard
          variant="outlined"
          :class="[
            'h-100 pa-4 rounded-lg cursor-pointer transition-all',
            brandingStore.settings.business_type === preset.key ? 'border-primary bg-primary-subtle' : 'border'
          ]"
          :disabled="isApplying"
          @click="applyPreset(preset.key)"
        >
          <div class="d-flex align-center justify-space-between mb-3">
            <VAvatar
              :color="getVerticalColor(preset.key)"
              variant="tonal"
              size="36"
              rounded
            >
              <VIcon :icon="getVerticalIcon(preset.key)" size="22" />
            </VAvatar>

            <VIcon
              v-if="brandingStore.settings.business_type === preset.key"
              icon="tabler-circle-check-filled"
              color="primary"
              size="20"
            />
          </div>

          <div class="font-weight-bold text-body-2 mb-1">
            {{ preset.label }}
          </div>

          <div class="text-caption text-medium-emphasis mb-4 line-clamp-3">
            {{ preset.description }}
          </div>

          <VBtn
            block
            size="small"
            :variant="brandingStore.settings.business_type === preset.key ? 'elevated' : 'outlined'"
            :color="getVerticalColor(preset.key)"
            :loading="isApplying && selectedVertical === preset.key"
            @click.stop="applyPreset(preset.key)"
          >
            {{ brandingStore.settings.business_type === preset.key ? 'Activo Actualmente' : 'Aplicar Perfil' }}
          </VBtn>
        </VCard>
      </VCol>
    </VRow>
  </VCard>
</template>

<style scoped>
.transition-all {
  transition: all 0.2s ease-in-out;
}
.transition-all:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.bg-primary-subtle {
  background-color: rgba(var(--v-theme-primary), 0.04);
}
</style>
