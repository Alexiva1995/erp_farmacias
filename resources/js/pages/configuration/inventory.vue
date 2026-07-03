<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore()
const cyclicInventoryMode = ref('double')
const barcodeRequired = ref(true)
const enableLots = ref(true)

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
        <VIcon icon="tabler-package" class="text-primary" />
        Configuración de Inventario
      </VCardTitle>
    </VCardItem>

    <VDivider />

    <!-- Modalidad de Inventario Cíclico -->
    <VCardItem class="py-6">
      <VCardTitle class="text-h6 font-weight-black text-high-emphasis mb-2">
        Modalidad de Inventario Cíclico
      </VCardTitle>
      <div class="text-body-2 text-medium-emphasis mb-4">
        Define el flujo de verificación y registro para los conteos cíclicos de inventario.
      </div>

      <VRadioGroup
        v-model="cyclicInventoryMode"
        @update:model-value="updateSettings"
        class="mt-2"
      >
        <VRadio
          v-for="item in cyclicInventoryOptions"
          :key="item.value"
          :value="item.value"
          class="mb-3"
        >
          <template #label>
            <div class="d-flex flex-column ms-2">
              <span class="text-subtitle-2 font-weight-bold text-high-emphasis">{{ item.label }}</span>
              <span class="text-caption text-medium-emphasis">
                {{ item.value === 'double'
                  ? 'El conteo del operador requiere una fase de supervisión donde el administrador valida la discrepancia y distribuye los lotes antes de impactar el stock.'
                  : 'El operador realiza el conteo y la distribución de lotes en un solo paso. Al guardar, el inventario se aprueba y actualiza el stock automáticamente.'
                }}
              </span>
            </div>
          </template>
        </VRadio>
      </VRadioGroup>
    </VCardItem>

    <VDivider />

    <!-- Modo Escaneo de Código de Barras -->
    <VCardItem class="py-6">
      <div class="d-flex align-center justify-space-between">
        <div>
          <VCardTitle class="text-h6 font-weight-black text-high-emphasis mb-1 pa-0">
            Requerir Escaneo de Código de Barras
          </VCardTitle>
          <div class="text-body-2 text-medium-emphasis">
            <template v-if="barcodeRequired">
              El operador <strong>debe</strong> escanear o digitar el código de barras del producto antes de poder registrar el conteo físico.
            </template>
            <template v-else>
              El campo de código de barras es <strong>opcional</strong>. El operador puede registrar la cantidad contada directamente sin escanear.
            </template>
          </div>
        </div>
        <VSwitch
          v-model="barcodeRequired"
          color="primary"
          hide-details
          :label="barcodeRequired ? 'Activado' : 'Desactivado'"
          @update:model-value="updateSettings"
          class="ms-4 flex-shrink-0"
        />
      </div>
    </VCardItem>

    <VDivider />

    <!-- Habilitar Lotes -->
    <VCardItem class="py-6">
      <div class="d-flex align-center justify-space-between">
        <div>
          <VCardTitle class="text-h6 font-weight-black text-high-emphasis mb-1 pa-0">
            Habilitar Uso de Lotes de Inventario
          </VCardTitle>
          <div class="text-body-2 text-medium-emphasis">
            <template v-if="enableLots">
              El inventario se gestiona dividiéndose en múltiples lotes con fecha de vencimiento y costo individual. **(Recomendado para Farmacias)**
            </template>
            <template v-else>
              El stock se gestiona en un lote único genérico. Se ocultan las secciones de lotes y vencimientos en toda la app. **(Recomendado para Alquileres o Restaurantes)**
            </template>
          </div>
        </div>
        <VSwitch
          v-model="enableLots"
          color="primary"
          hide-details
          :label="enableLots ? 'Activado' : 'Desactivado'"
          @update:model-value="updateSettings"
          class="ms-4 flex-shrink-0"
        />
      </div>
    </VCardItem>
  </VCard>
</template>

