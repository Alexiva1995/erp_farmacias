<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useAbility } from '@casl/vue'
import axios from '@/plugins/axios'
import { toast, confirmDialog } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'

const ability = useAbility()
const brandingStore = useBrandingStore()

// Control de estados de carga
const isLoading = ref(true)
const isSaving = ref(false)

// Estado del formulario
const defaultSettings = {
  cyclic_inventory_mode: 'double',
  cyclic_inventory_barcode_required: true,
  cyclic_inventory_scope: 'all',
  cyclic_inventory_daily_quota: 50,
  enable_lots: true,
  enable_stock_control: true,
}

const originalSettings = ref({ ...defaultSettings })
const formState = reactive({ ...defaultSettings })

// Verificación de cambios sin guardar (Dirty State)
const isDirty = computed(() => {
  return JSON.stringify(formState) !== JSON.stringify(originalSettings.value)
})

// Carga inicial de datos
const fetchSettings = async () => {
  isLoading.value = true
  try {
    const keys = Object.keys(defaultSettings).join(',')
    const response = await axios.get(`/general-settings?only=${keys}`)
    const settings = response.data.data || {}

    const loadedData = {
      cyclic_inventory_mode: settings.cyclic_inventory_mode || 'double',
      cyclic_inventory_barcode_required: settings.cyclic_inventory_barcode_required ?? true,
      cyclic_inventory_scope: settings.cyclic_inventory_scope || 'all',
      cyclic_inventory_daily_quota: Number(settings.cyclic_inventory_daily_quota) || 50,
      enable_lots: settings.enable_lots ?? true,
      enable_stock_control: settings.enable_stock_control ?? true,
    }

    Object.assign(formState, loadedData)
    originalSettings.value = { ...loadedData }
  } catch (error) {
    console.error('Error al cargar la configuración:', error)
    toast.error('No se pudo cargar la configuración de inventario')
  } finally {
    isLoading.value = false
  }
}

// Restaurar cambios
const resetSettings = () => {
  Object.assign(formState, originalSettings.value)
  toast.info('Cambios restablecidos')
}

// Persistencia explícita con validación y confirmación en cambios críticos
const saveSettings = async () => {
  if (!ability.can('edit', 'Configuration') && !ability.can('manage', 'all')) {
    toast.error('No tienes permisos para modificar la configuración')
    return
  }

  // Confirmación al desactivar el sistema de lotes
  if (originalSettings.value.enable_lots && !formState.enable_lots) {
    const isConfirmed = await confirmDialog({
      title: '¿Desactivar gestión de lotes?',
      text: 'Deshabilitar los lotes ocultará los controles de vencimiento en las compras y ventas activas.',
      icon: 'warning',
      confirmButtonText: 'Sí, desactivar',
      cancelButtonText: 'Cancelar',
    })

    if (!isConfirmed) return
  }

  isSaving.value = true
  try {
    await axios.post('/general-settings', {
      cyclic_inventory_mode: formState.cyclic_inventory_mode,
      cyclic_inventory_barcode_required: formState.cyclic_inventory_barcode_required,
      cyclic_inventory_scope: formState.cyclic_inventory_scope,
      cyclic_inventory_daily_quota: Number(formState.cyclic_inventory_daily_quota) || 50,
      enable_lots: formState.enable_lots,
      enable_stock_control: formState.enable_stock_control,
    })

    originalSettings.value = { ...formState }
    await brandingStore.fetchSettings()
    toast.success('Configuración de inventario guardada exitosamente')
  } catch (error) {
    console.error('Error al guardar:', error)
    toast.error('Error al guardar la configuración')
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <VCard class="mb-6 rounded-xl border border-light">
    <!-- Encabezado -->
    <VCardItem class="py-5 px-6">
      <div class="d-flex align-center justify-space-between flex-wrap gap-4">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" rounded="lg" size="42">
            <VIcon icon="tabler-settings" size="24" />
          </VAvatar>
          <div>
            <VCardTitle class="text-h6 font-weight-bold text-uppercase tracking-wider pa-0 ma-0 text-high-emphasis">
              Configuración de Inventario
            </VCardTitle>
            <VCardSubtitle class="text-caption text-medium-emphasis pa-0 ma-0">
              Gestiona el comportamiento global y las reglas operativas del inventario físico y lógico
            </VCardSubtitle>
          </div>
        </div>

        <div class="d-flex align-center gap-2">
          <VBtn
            v-if="isDirty"
            variant="outlined"
            color="secondary"
            density="comfortable"
            :disabled="isSaving || isLoading"
            @click="resetSettings"
          >
            <VIcon icon="tabler-rotate" start />
            Restablecer
          </VBtn>

          <VBtn
            color="primary"
            density="comfortable"
            :loading="isSaving"
            :disabled="!isDirty || isLoading || (!ability.can('edit', 'Configuration') && !ability.can('manage', 'all'))"
            @click="saveSettings"
          >
            <VIcon icon="tabler-device-floppy" start />
            Guardar Cambios
          </VBtn>
        </div>
      </div>
    </VCardItem>

    <VDivider />

    <!-- Skeletons de Carga -->
    <VCardItem v-if="isLoading" class="py-8 px-6">
      <VRow>
        <VCol v-for="i in 4" :key="i" cols="12" md="6">
          <VSkeletonLoader type="card" class="rounded-xl border" />
        </VCol>
      </VRow>
    </VCardItem>

    <!-- Contenido del Formulario -->
    <VCardItem v-else class="py-6 px-6">
      <!-- Sección 1: Inventario Físico y Cíclico -->
      <div class="mb-6">
        <div class="d-flex align-center gap-2 mb-4">
          <VIcon icon="tabler-refresh" color="primary" size="20" />
          <h3 class="text-subtitle-1 font-weight-bold text-high-emphasis">
            Parámetros de Inventario Cíclico y Conteo
          </h3>
        </div>

        <VRow>
          <!-- Modo de Verificación -->
          <VCol cols="12" md="6" lg="4">
            <VCard variant="outlined" class="h-100 pa-5 rounded-xl d-flex flex-column justify-space-between">
              <div>
                <div class="d-flex align-center justify-space-between mb-3">
                  <VAvatar color="primary" variant="tonal" rounded size="38">
                    <VIcon icon="tabler-checks" size="20" />
                  </VAvatar>
                  <VChip
                    :color="formState.cyclic_inventory_mode === 'simple' ? 'success' : 'warning'"
                    size="small"
                    variant="tonal"
                    class="font-weight-medium"
                  >
                    {{ formState.cyclic_inventory_mode === 'simple' ? 'Simple' : 'Doble' }}
                  </VChip>
                </div>
                <h4 class="text-subtitle-2 font-weight-bold text-high-emphasis mb-1">
                  Modalidad de Verificación
                </h4>
                <p class="text-caption text-medium-emphasis mb-4">
                  Doble requiere aprobación del supervisor; Simple registra el ajuste inmediatamente.
                </p>
              </div>

              <VSwitch
                v-model="formState.cyclic_inventory_mode"
                true-value="simple"
                false-value="double"
                :label="formState.cyclic_inventory_mode === 'simple' ? 'Verificación Simple' : 'Doble Verificación'"
                color="primary"
                density="comfortable"
                hide-details="auto"
                :disabled="isSaving"
              />
            </VCard>
          </VCol>

          <!-- Alcance y Cuota Diaria -->
          <VCol cols="12" md="6" lg="4">
            <VCard variant="outlined" class="h-100 pa-5 rounded-xl d-flex flex-column justify-space-between">
              <div>
                <div class="d-flex align-center justify-space-between mb-3">
                  <VAvatar color="secondary" variant="tonal" rounded size="38">
                    <VIcon icon="tabler-target-arrow" size="20" />
                  </VAvatar>
                  <VChip
                    :color="formState.cyclic_inventory_scope === 'quota' ? 'secondary' : 'primary'"
                    size="small"
                    variant="tonal"
                    class="font-weight-medium"
                  >
                    {{ formState.cyclic_inventory_scope === 'quota' ? `Cuota (${formState.cyclic_inventory_daily_quota}/día)` : 'Todos' }}
                  </VChip>
                </div>
                <h4 class="text-subtitle-2 font-weight-bold text-high-emphasis mb-1">
                  Alcance de Productos
                </h4>
                <p class="text-caption text-medium-emphasis mb-3">
                  Muestra todo el catálogo simultáneamente o una cuota diaria progresiva.
                </p>
              </div>

              <div>
                <VRadioGroup
                  v-model="formState.cyclic_inventory_scope"
                  density="comfortable"
                  hide-details="auto"
                  class="mb-3"
                  :disabled="isSaving"
                >
                  <VRadio label="Todos los productos" value="all" color="primary" class="mb-1" />
                  <VRadio label="Cuota diaria asignada" value="quota" color="secondary" />
                </VRadioGroup>

                <VExpandTransition>
                  <div v-if="formState.cyclic_inventory_scope === 'quota'">
                    <VTextField
                      v-model.number="formState.cyclic_inventory_daily_quota"
                      type="number"
                      min="1"
                      max="10000"
                      label="Productos por día"
                      density="comfortable"
                      variant="outlined"
                      suffix="items"
                      hide-details="auto"
                      :disabled="isSaving"
                    />
                  </div>
                </VExpandTransition>
              </div>
            </VCard>
          </VCol>

          <!-- Escaneo de Código de Barras -->
          <VCol cols="12" md="6" lg="4">
            <VCard variant="outlined" class="h-100 pa-5 rounded-xl d-flex flex-column justify-space-between">
              <div>
                <div class="d-flex align-center justify-space-between mb-3">
                  <VAvatar color="info" variant="tonal" rounded size="38">
                    <VIcon icon="tabler-barcode" size="20" />
                  </VAvatar>
                  <VChip
                    :color="formState.cyclic_inventory_barcode_required ? 'info' : 'secondary'"
                    size="small"
                    variant="tonal"
                    class="font-weight-medium"
                  >
                    {{ formState.cyclic_inventory_barcode_required ? 'Obligatorio' : 'Opcional' }}
                  </VChip>
                </div>
                <h4 class="text-subtitle-2 font-weight-bold text-high-emphasis mb-1">
                  Validación de Código de Barras
                </h4>
                <p class="text-caption text-medium-emphasis mb-4">
                  Exige escanear el código de barras del producto antes de habilitar el conteo.
                </p>
              </div>

              <VSwitch
                v-model="formState.cyclic_inventory_barcode_required"
                :label="formState.cyclic_inventory_barcode_required ? 'Escaneo Requerido' : 'Escaneo Opcional'"
                color="primary"
                density="comfortable"
                hide-details="auto"
                :disabled="isSaving"
              />
            </VCard>
          </VCol>
        </VRow>
      </div>

      <VDivider class="my-6" />

      <!-- Sección 2: Operaciones y Trazabilidad -->
      <div>
        <div class="d-flex align-center gap-2 mb-4">
          <VIcon icon="tabler-packages" color="primary" size="20" />
          <h3 class="text-subtitle-1 font-weight-bold text-high-emphasis">
            Trazabilidad de Stock y Módulos
          </h3>
        </div>

        <VRow>
          <!-- Lotes de Inventario -->
          <VCol cols="12" md="6">
            <VCard variant="outlined" class="h-100 pa-5 rounded-xl d-flex flex-column justify-space-between">
              <div>
                <div class="d-flex align-center justify-space-between mb-3">
                  <VAvatar color="primary" variant="tonal" rounded size="38">
                    <VIcon icon="tabler-calendar-time" size="20" />
                  </VAvatar>
                  <VChip
                    :color="formState.enable_lots ? 'success' : 'secondary'"
                    size="small"
                    variant="tonal"
                    class="font-weight-medium"
                  >
                    {{ formState.enable_lots ? 'Activo' : 'Inactivo' }}
                  </VChip>
                </div>
                <h4 class="text-subtitle-2 font-weight-bold text-high-emphasis mb-1">
                  Control de Lotes y Vencimientos
                </h4>
                <p class="text-caption text-medium-emphasis mb-4">
                  Permite controlar múltiples lotes, fechas de caducidad y costos individuales por lote de producto.
                </p>
              </div>

              <VSwitch
                v-model="formState.enable_lots"
                :label="formState.enable_lots ? 'Lotes y Vencimientos Habilitados' : 'Lote Único Global'"
                color="primary"
                density="comfortable"
                hide-details="auto"
                :disabled="isSaving"
              />
            </VCard>
          </VCol>

          <!-- Control de Stock en Menú -->
          <VCol cols="12" md="6">
            <VCard variant="outlined" class="h-100 pa-5 rounded-xl d-flex flex-column justify-space-between">
              <div>
                <div class="d-flex align-center justify-space-between mb-3">
                  <VAvatar color="warning" variant="tonal" rounded size="38">
                    <VIcon icon="tabler-layout-sidebar" size="20" />
                  </VAvatar>
                  <VChip
                    :color="formState.enable_stock_control ? 'success' : 'secondary'"
                    size="small"
                    variant="tonal"
                    class="font-weight-medium"
                  >
                    {{ formState.enable_stock_control ? 'Visible' : 'Oculto' }}
                  </VChip>
                </div>
                <h4 class="text-subtitle-2 font-weight-bold text-high-emphasis mb-1">
                  Acceso a Control de Stock en Menú
                </h4>
                <p class="text-caption text-medium-emphasis mb-4">
                  Determina la visibilidad de la opción "Control de Stock" en la navegación lateral para los usuarios.
                </p>
              </div>

              <VSwitch
                v-model="formState.enable_stock_control"
                :label="formState.enable_stock_control ? 'Visible en Barra Lateral' : 'Oculto en Navegación'"
                color="primary"
                density="comfortable"
                hide-details="auto"
                :disabled="isSaving"
              />
            </VCard>
          </VCol>
        </VRow>
      </div>
    </VCardItem>
  </VCard>
</template>

<style scoped>
.border-light {
  border-color: rgba(var(--v-border-color), 0.08) !important;
}
</style>
