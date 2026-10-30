<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast, confirmDialog } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'

const emit = defineEmits(['preset-applied'])

const brandingStore = useBrandingStore()
const isLoading = ref(false)
const isApplying = ref(false)
const isCreatingCustom = ref(false)
const showCustomDialog = ref(false)
const presets = ref([])
const selectedVertical = ref(brandingStore.settings.business_type || 'pharmacy')

const customForm = reactive({
  key: '',
  label: '',
  description: '',
  base_vertical: 'drogueria',
})

const currentVerticalLabel = computed(() => {
  const match = presets.value.find(p => p.key === brandingStore.settings.business_type)
  return match?.label || 'Farmacia & Salud'
})

const getVerticalIcon = (key) => {
  switch (key) {
    case 'pharmacy':
      return 'tabler-pill'
    case 'drogueria':
      return 'tabler-truck-delivery'
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
    case 'drogueria':
      return 'info'
    case 'restaurant':
      return 'success'
    case 'fast_food':
      return 'warning'
    case 'minimarket':
      return 'secondary'
    default:
      return 'primary'
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
        key: 'drogueria',
        label: 'Droguería & Distribución Mayorista B2B',
        description: 'Distribución mayorista a farmacias con telemarketing, crédito por producto, topes de compra, picking FEFO y portal B2B.',
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

const createCustomVertical = async () => {
  if (!customForm.key || !customForm.label) {
    toast.error('Ingresa la clave y nombre de la nueva vertical')
    return
  }
  isCreatingCustom.value = true
  try {
    await axios.post('/general-settings/custom-vertical', customForm)
    toast.success('Vertical personalizada registrada correctamente')
    showCustomDialog.value = false
    customForm.key = ''
    customForm.label = ''
    customForm.description = ''
    await fetchPresets()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al registrar la vertical personalizada')
  } finally {
    isCreatingCustom.value = false
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

      <div class="d-flex align-center gap-2">
        <VChip
          :color="getVerticalColor(brandingStore.settings.business_type)"
          variant="tonal"
          size="small"
          class="font-weight-medium"
        >
          <VIcon start size="16" :icon="getVerticalIcon(brandingStore.settings.business_type)" />
          Perfil Activo: {{ currentVerticalLabel }}
        </VChip>

        <VBtn
          color="primary"
          variant="outlined"
          size="small"
          prepend-icon="tabler-plus"
          @click="showCustomDialog = true"
        >
          Crear Vertical
        </VBtn>
      </div>
    </div>

    <VDialog v-model="showCustomDialog" max-width="580" persistent transition="dialog-bottom-transition">
      <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12 rounded-xl">
        <VCardTitle class="pa-0">
          <div class="header-gradient pa-4 d-flex align-center shadow-sm">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <VIcon icon="tabler-topology-star-3" color="primary" size="24" />
            </VAvatar>
            <div class="d-flex flex-column">
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
                Registrar Nueva Vertical
              </h2>
              <span class="text-super-xs text-white opacity-90 uppercase font-weight-bold letter-spacing-1">
                Configuración de plantilla operativa a medida
              </span>
            </div>
            <VSpacer />
            <VBtn
              icon="tabler-x"
              variant="tonal"
              color="white"
              size="small"
              class="rounded-lg"
              :disabled="isCreatingCustom"
              @click="showCustomDialog = false"
            />
          </div>
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-4 pa-sm-6 bg-light">
          <VCard variant="flat" class="border pa-4 bg-white rounded-lg elevation-1">
            <div class="d-flex align-center ga-2 mb-4">
              <div class="header-indicator primary" />
              <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">
                Definición de la Vertical
              </span>
            </div>

            <VRow dense>
              <VCol cols="12" md="6" class="mb-3">
                <VTextField
                  v-model="customForm.key"
                  label="Clave técnica *"
                  placeholder="Ej: distribuidora_medica"
                  prepend-inner-icon="tabler-key"
                  density="comfortable"
                  variant="outlined"
                  hide-details="auto"
                />
              </VCol>
              <VCol cols="12" md="6" class="mb-3">
                <VSelect
                  v-model="customForm.base_vertical"
                  :items="presets"
                  item-title="label"
                  item-value="key"
                  label="Clonar módulos base de *"
                  prepend-inner-icon="tabler-copy"
                  density="comfortable"
                  variant="outlined"
                  hide-details="auto"
                />
              </VCol>
              <VCol cols="12" class="mb-3">
                <VTextField
                  v-model="customForm.label"
                  label="Nombre visible de la vertical *"
                  placeholder="Ej: Distribuidora de Insumos Médicos"
                  prepend-inner-icon="tabler-tag"
                  density="comfortable"
                  variant="outlined"
                  hide-details="auto"
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="customForm.description"
                  label="Descripción operativa"
                  placeholder="Describe los módulos y enfoque operativo de esta vertical..."
                  prepend-inner-icon="tabler-file-description"
                  rows="2"
                  density="comfortable"
                  variant="outlined"
                  hide-details="auto"
                />
              </VCol>
            </VRow>
          </VCard>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 bg-light border-t">
          <VRow dense class="w-100 ma-0">
            <VCol cols="12" sm="6" class="pa-1">
              <VBtn
                color="secondary"
                variant="outlined"
                size="large"
                block
                height="48"
                class="font-weight-black rounded-lg text-button uppercase"
                :disabled="isCreatingCustom"
                @click="showCustomDialog = false"
              >
                Cancelar
              </VBtn>
            </VCol>
            <VCol cols="12" sm="6" class="pa-1">
              <VBtn
                color="primary"
                variant="flat"
                size="large"
                block
                height="48"
                class="font-weight-black rounded-lg shadow-primary text-button uppercase"
                :loading="isCreatingCustom"
                :disabled="isCreatingCustom"
                @click="createCustomVertical"
              >
                <VIcon icon="tabler-device-floppy" size="18" class="me-2" />
                Guardar Vertical
              </VBtn>
            </VCol>
          </VRow>
        </VCardActions>
      </VCard>
    </VDialog>

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
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 14px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.35) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 0.06em !important;
}

.leading-tight {
  line-height: 1.25 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.1) !important;
}

.text-button {
  font-size: 0.85rem !important;
  letter-spacing: 0.05em !important;
}

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
