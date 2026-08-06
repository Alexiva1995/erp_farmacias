<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore()
const cyclicInventoryMode = ref('double')
const barcodeRequired = ref(true)
const enableLots = ref(true)
const enableStockControl = ref(true)

const isLoading = ref(true)
const isSaving = ref(false)

const fetchSettings = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/general-settings?only=cyclic_inventory_mode,cyclic_inventory_barcode_required,enable_lots,enable_stock_control')
    const settings = response.data.data
    cyclicInventoryMode.value = settings.cyclic_inventory_mode || 'double'
    barcodeRequired.value = settings.cyclic_inventory_barcode_required ?? true
    enableLots.value = settings.enable_lots ?? true
    enableStockControl.value = settings.enable_stock_control ?? true
  } catch (error) {
    console.error("Error cargando configuración:", error)
    toast.error("Error al cargar la configuración")
  } finally {
    isLoading.value = false
  }
}

const updateSettings = async () => {
  if (isSaving.value) return
  isSaving.value = true
  try {
    await axios.post('/general-settings', {
      cyclic_inventory_mode: cyclicInventoryMode.value,
      cyclic_inventory_barcode_required: barcodeRequired.value,
      enable_lots: enableLots.value,
      enable_stock_control: enableStockControl.value,
    })
    // Actualizar el store de branding para que refleje el cambio de inmediato en toda la app
    await brandingStore.fetchSettings()
    toast.success("Configuración de inventario actualizada exitosamente")
  } catch (error) {
    console.error("Error al guardar:", error)
    toast.error("Error al actualizar la configuración")
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <VCard class="mb-6 rounded-xl border border-light shadow-sm overflow-hidden inventory-config-card">
    <!-- Encabezado con degradado suave y estado de guardado -->
    <div class="px-6 py-5 d-flex align-center justify-space-between flex-wrap gap-4" style="background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.03) 0%, rgba(var(--v-theme-secondary), 0.03) 100%);">
      <div class="d-flex align-center gap-3">
        <div class="p-2 rounded-lg bg-primary-lighten-5 d-flex align-center justify-center" style="width: 42px; height: 42px; background-color: rgba(var(--v-theme-primary), 0.1);">
          <VIcon icon="tabler-settings" class="text-primary" size="24" />
        </div>
        <div>
          <VCardTitle class="text-h6 font-weight-black text-uppercase tracking-wider pa-0 ma-0 text-high-emphasis">
            Configuración de Inventario
          </VCardTitle>
          <span class="text-caption text-medium-emphasis">Gestiona el comportamiento global y las reglas del inventario físico y lógico</span>
        </div>
      </div>
      
      <!-- Indicador de guardado -->
      <VFadeTransition>
        <div v-if="isSaving" class="d-flex align-center gap-2 px-3 py-1.5 rounded-pill bg-action-saving text-primary text-caption font-weight-medium">
          <VProgressCircular indeterminate size="14" width="2" color="primary" />
          <span>Guardando cambios...</span>
        </div>
      </VFadeTransition>
    </div>

    <VDivider />

    <!-- Estado de carga: Skeletons estilizados -->
    <VCardItem v-if="isLoading" class="py-8 px-6">
      <VRow>
        <VCol v-for="i in 4" :key="i" cols="12" md="6" lg="3">
          <VCard variant="outlined" class="pa-5 rounded-lg border-dashed">
            <div class="d-flex align-center gap-3 mb-4">
              <div class="rounded bg-grey-lighten-3 animate-pulse" style="width: 40px; height: 40px;"></div>
              <div class="flex-grow-1">
                <div class="bg-grey-lighten-3 animate-pulse rounded mb-2" style="height: 16px; width: 70%;"></div>
                <div class="bg-grey-lighten-3 animate-pulse rounded" style="height: 12px; width: 40%;"></div>
              </div>
            </div>
            <div class="bg-grey-lighten-3 animate-pulse rounded mb-4" style="height: 32px; width: 100%;"></div>
            <div class="bg-grey-lighten-3 animate-pulse rounded" style="height: 24px; width: 50%;"></div>
          </VCard>
        </VCol>
      </VRow>
    </VCardItem>

    <!-- Contenido principal -->
    <VCardItem v-else class="py-6 px-6">
      <VRow>
        <!-- Modalidad de Inventario Cíclico -->
        <VCol cols="12" md="6" lg="3">
          <VCard variant="outlined" class="h-100 pa-5 rounded-xl border-light hover-card position-relative overflow-hidden d-flex flex-column justify-space-between transition-all">
            <div>
              <div class="d-flex align-center justify-space-between mb-4">
                <div class="p-2 rounded-lg bg-primary-light d-flex align-center justify-center" style="width: 44px; height: 44px; background-color: rgba(var(--v-theme-primary), 0.08);">
                  <VIcon icon="tabler-refresh" class="text-primary" size="24" />
                </div>
                <VChip 
                  :color="cyclicInventoryMode === 'simple' ? 'success' : 'warning'" 
                  size="x-small" 
                  class="font-weight-bold uppercase"
                  variant="tonal"
                >
                  {{ cyclicInventoryMode === 'simple' ? 'Simple' : 'Doble' }}
                </VChip>
              </div>
              <h4 class="text-subtitle-1 font-weight-bold text-high-emphasis mb-1">Inventario Cíclico</h4>
              <p class="text-caption text-medium-emphasis mb-4">
                Doble Verificación requiere supervisión de administrador. Simple realiza el conteo y aprueba directamente.
              </p>
            </div>
            
            <div class="mt-auto pt-2">
              <VSwitch
                v-model="cyclicInventoryMode"
                true-value="simple"
                false-value="double"
                :label="cyclicInventoryMode === 'simple' ? 'Verificación Simple' : 'Doble Verificación'"
                color="primary"
                density="comfortable"
                hide-details
                :disabled="isSaving"
                @update:model-value="updateSettings"
              />
            </div>
          </VCard>
        </VCol>

        <!-- Requerir Escaneo de Código de Barras -->
        <VCol cols="12" md="6" lg="3">
          <VCard variant="outlined" class="h-100 pa-5 rounded-xl border-light hover-card position-relative overflow-hidden d-flex flex-column justify-space-between transition-all">
            <div>
              <div class="d-flex align-center justify-space-between mb-4">
                <div class="p-2 rounded-lg bg-success-light d-flex align-center justify-center" style="width: 44px; height: 44px; background-color: rgba(76, 175, 80, 0.08);">
                  <VIcon icon="tabler-barcode" class="text-success" size="24" />
                </div>
                <VChip 
                  :color="barcodeRequired ? 'error' : 'secondary'" 
                  size="x-small" 
                  class="font-weight-bold uppercase"
                  variant="tonal"
                >
                  {{ barcodeRequired ? 'Obligatorio' : 'Opcional' }}
                </VChip>
              </div>
              <h4 class="text-subtitle-1 font-weight-bold text-high-emphasis mb-1">Código de Barras</h4>
              <p class="text-caption text-medium-emphasis mb-4">
                Obliga al operador a escanear o digitar el código de barras del producto antes de registrar el conteo de stock.
              </p>
            </div>
            
            <div class="mt-auto pt-2">
              <VSwitch
                v-model="barcodeRequired"
                :label="barcodeRequired ? 'Escaneo Obligatorio' : 'Escaneo Opcional'"
                color="primary"
                density="comfortable"
                hide-details
                :disabled="isSaving"
                @update:model-value="updateSettings"
              />
            </div>
          </VCard>
        </VCol>

        <!-- Habilitar Uso de Lotes de Inventario -->
        <VCol cols="12" md="6" lg="3">
          <VCard variant="outlined" class="h-100 pa-5 rounded-xl border-light hover-card position-relative overflow-hidden d-flex flex-column justify-space-between transition-all">
            <div>
              <div class="d-flex align-center justify-space-between mb-4">
                <div class="p-2 rounded-lg bg-info-light d-flex align-center justify-center" style="width: 44px; height: 44px; background-color: rgba(3, 169, 244, 0.08);">
                  <VIcon icon="tabler-packages" class="text-info" size="24" />
                </div>
                <VChip 
                  :color="enableLots ? 'info' : 'secondary'" 
                  size="x-small" 
                  class="font-weight-bold uppercase"
                  variant="tonal"
                >
                  {{ enableLots ? 'Lotes Activos' : 'Lote Único' }}
                </VChip>
              </div>
              <h4 class="text-subtitle-1 font-weight-bold text-high-emphasis mb-1">Lotes de Inventario</h4>
              <p class="text-caption text-medium-emphasis mb-4">
                Gestiona el inventario en múltiples lotes con fecha de vencimiento y costos de adquisición individuales.
              </p>
            </div>
            
            <div class="mt-auto pt-2">
              <VSwitch
                v-model="enableLots"
                :label="enableLots ? 'Lotes Habilitados' : 'Lote Único (Sin Vencimientos)'"
                color="primary"
                density="comfortable"
                hide-details
                :disabled="isSaving"
                @update:model-value="updateSettings"
              />
            </div>
          </VCard>
        </VCol>

        <!-- Habilitar Control de Stock en Menú -->
        <VCol cols="12" md="6" lg="3">
          <VCard variant="outlined" class="h-100 pa-5 rounded-xl border-light hover-card position-relative overflow-hidden d-flex flex-column justify-space-between transition-all">
            <div>
              <div class="d-flex align-center justify-space-between mb-4">
                <div class="p-2 rounded-lg bg-warning-light d-flex align-center justify-center" style="width: 44px; height: 44px; background-color: rgba(255, 152, 0, 0.08);">
                  <VIcon icon="tabler-adjustments-horizontal" class="text-warning" size="24" />
                </div>
                <VChip 
                  :color="enableStockControl ? 'success' : 'secondary'" 
                  size="x-small" 
                  class="font-weight-bold uppercase"
                  variant="tonal"
                >
                  {{ enableStockControl ? 'Visible' : 'Oculto' }}
                </VChip>
              </div>
              <h4 class="text-subtitle-1 font-weight-bold text-high-emphasis mb-1">Control de Stock</h4>
              <p class="text-caption text-medium-emphasis mb-4">
                Muestra u oculta de forma dinámica la opción de "Control de Stock" del menú de navegación lateral.
              </p>
            </div>
            
            <div class="mt-auto pt-2">
              <VSwitch
                v-model="enableStockControl"
                :label="enableStockControl ? 'Habilitado en Menú' : 'Deshabilitado'"
                color="primary"
                density="comfortable"
                hide-details
                :disabled="isSaving"
                @update:model-value="updateSettings"
              />
            </div>
          </VCard>
        </VCol>
      </VRow>
    </VCardItem>
  </VCard>
</template>

<style scoped>
.inventory-config-card {
  transition: all 0.3s ease;
}

.border-light {
  border-color: rgba(var(--v-border-color), 0.08) !important;
}

.hover-card {
  background-color: var(--v-theme-surface);
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.hover-card:hover {
  transform: translateY(-3px);
  border-color: rgba(var(--v-theme-primary), 0.25) !important;
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04) !important;
}

.bg-action-saving {
  background-color: rgba(var(--v-theme-primary), 0.08);
  border: 1px solid rgba(var(--v-theme-primary), 0.15);
}

/* Animación de pulso para carga de skeletons */
.animate-pulse {
  animation: pulse 1.8s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: .4;
  }
}
</style>
