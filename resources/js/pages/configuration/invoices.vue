<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore()
const enableInvoices = ref(true)
const enableInvoiceLocations = ref(true)

const fetchSettings = async () => {
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data
    enableInvoices.value = settings.enable_invoices ?? true
    enableInvoiceLocations.value = settings.enable_invoice_locations ?? true
  } catch (error) {
    console.error("Error cargando configuración de facturas:", error)
    toast.error("Error al cargar la configuración")
  }
}

const updateSettings = async () => {
  try {
    await axios.post('/general-settings', {
      enable_invoices: enableInvoices.value,
      enable_invoice_locations: enableInvoiceLocations.value,
    })
    // Forzar actualización del store de branding
    await brandingStore.fetchSettings(true)
    toast.success("Configuración de facturas actualizada exitosamente")
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
  <div class="d-flex flex-column gap-6 pb-12 w-full">
    <VCard class="rounded-lg border shadow-sm">
      <VCardItem class="py-4">
        <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2">
          <VIcon icon="tabler-file-invoice" class="text-primary" />
          Configuración de Facturación
        </VCardTitle>
        <div class="text-body-2 text-medium-emphasis mt-1">
          Controla los parámetros de carga de facturas y los flujos de distribución del inventario.
        </div>
      </VCardItem>

      <VDivider />

      <VCardItem class="py-6">
        <VRow>
          <!-- Habilitar Módulo de Facturas -->
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column gap-2 h-full">
              <div class="d-flex align-center justify-space-between mb-1">
                <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Habilitar Módulo de Facturas</span>
                <VSwitch
                  v-model="enableInvoices"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="updateSettings"
                />
              </div>
              <span class="text-caption text-medium-emphasis">
                Muestra u oculta por completo las opciones del módulo de Facturas del menú lateral del sistema.
              </span>
            </div>
          </VCol>

          <!-- Habilitar Ubicación de Productos -->
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column gap-2 h-full">
              <div class="d-flex align-center justify-space-between mb-1">
                <span class="text-subtitle-2 font-weight-bold text-high-emphasis">Habilitar Ubicaciones en Carga</span>
                <VSwitch
                  v-model="enableInvoiceLocations"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="updateSettings"
                />
              </div>
              <span class="text-caption text-medium-emphasis">
                Si se deshabilita, las facturas aprobadas pasarán directamente al estado de "Ordenadas". Los lotes se guardarán con ubicación "N/A" sin requerir asignación manual.
              </span>
            </div>
          </VCol>
        </VRow>
      </VCardItem>
    </VCard>
  </div>
</template>


