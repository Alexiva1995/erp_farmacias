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

const cyclicInventoryOptions = [
  { label: "Doble Verificación (Con supervisión)", value: "double" },
  { label: "Verificación Simple (Directo/Sin supervisor)", value: "simple" },
]

const fetchSettings = async () => {
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data
    cyclicInventoryMode.value = settings.cyclic_inventory_mode || 'double'
    barcodeRequired.value = settings.cyclic_inventory_barcode_required ?? true
    enableLots.value = settings.enable_lots ?? true
    enableStockControl.value = settings.enable_stock_control ?? true
  } catch (error) {
    console.error("Error cargando configuración:", error)
    toast.error("Error al cargar la configuración")
  }
}

const updateSettings = async () => {
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
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm">
    <VCardItem class="py-4">
      <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2">
        <VIcon icon="tabler-settings" class="text-primary" />
        Configuración de Inventario
      </VCardTitle>
    </VCardItem>

    <VDivider />

    <VCardItem class="py-5">
      <VRow>
        <!-- Modalidad de Inventario Cíclico -->
        <VCol cols="12" md="4">
          <div class="d-flex flex-column gap-1">
            <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Modalidad de Inventario Cíclico</span>
            <span class="text-caption text-medium-emphasis mb-2" style="min-height: 48px;">
              Doble Verificación requiere supervisión de administrador. Simple realiza el conteo y aprueba en un solo paso.
            </span>
            <VSwitch
              v-model="cyclicInventoryMode"
              true-value="simple"
              false-value="double"
              :label="cyclicInventoryMode === 'simple' ? 'Verificación Simple' : 'Doble Verificación'"
              color="primary"
              density="compact"
              hide-details
              @update:model-value="updateSettings"
            />
          </div>
        </VCol>

        <!-- Requerir Escaneo de Código de Barras -->
        <VCol cols="12" md="4">
          <div class="d-flex flex-column gap-1">
            <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Escaneo de Código de Barras</span>
            <span class="text-caption text-medium-emphasis mb-2" style="min-height: 48px;">
              Obliga al operador a escanear o digitar el código de barras del producto antes de registrar el conteo.
            </span>
            <VSwitch
              v-model="barcodeRequired"
              :label="barcodeRequired ? 'Escaneo Obligatorio' : 'Escaneo Opcional'"
              color="primary"
              density="compact"
              hide-details
              @update:model-value="updateSettings"
            />
          </div>
        </VCol>

        <!-- Habilitar Uso de Lotes de Inventario -->
        <VCol cols="12" md="4">
          <div class="d-flex flex-column gap-1">
            <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Uso de Lotes de Inventario</span>
            <span class="text-caption text-medium-emphasis mb-2" style="min-height: 48px;">
              Gestiona el inventario en múltiples lotes con fecha de vencimiento y costos individuales.
            </span>
            <VSwitch
              v-model="enableLots"
              :label="enableLots ? 'Lotes Habilitados' : 'Lote Único (Sin Vencimientos)'"
              color="primary"
              density="compact"
              hide-details
              @update:model-value="updateSettings"
            />
          </div>
        </VCol>

        <!-- Habilitar Control de Stock en Menú -->
        <VCol cols="12" md="4">
          <div class="d-flex flex-column gap-1">
            <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Control de Stock en Menú</span>
            <span class="text-caption text-medium-emphasis mb-2" style="min-height: 48px;">
              Muestra u oculta la opción "Control de Stock" del menú lateral del sistema de forma dinámica.
            </span>
            <VSwitch
              v-model="enableStockControl"
              :label="enableStockControl ? 'Habilitado' : 'Deshabilitado'"
              color="primary"
              density="compact"
              hide-details
              @update:model-value="updateSettings"
            />
          </div>
        </VCol>
      </VRow>
    </VCardItem>
  </VCard>
</template>

