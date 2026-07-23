<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore()

const enabledBiViews = ref([
  'abc',
  'dead-stock',
  'sku',
  'products',
  'expiry',
  'laboratories',
  'pos',
  'cyclic',
  'customer',
  'performance'
])

const availableBiViews = [
  { key: 'abc', title: 'Reporte ABC', description: 'Categorización de inventario ABC según valor y rotación.', icon: 'tabler-abc' },
  { key: 'dead-stock', title: 'Reporte Stock Muerto', description: 'Detección de productos sin movimiento o de baja rotación.', icon: 'tabler-package-off' },
  { key: 'sku', title: 'Reporte de Margen SKU', description: 'Detalle de utilidad y rendimiento individual por SKU.', icon: 'tabler-calculator' },
  { key: 'products', title: 'Dashboard Maestro', description: 'Visión consolidada y analíticas globales de ventas de productos.', icon: 'tabler-chart-pie' },
  { key: 'expiry', title: 'BI Caducidad', description: 'Predicciones y alertas de productos próximos a vencer.', icon: 'tabler-calendar' },
  { key: 'laboratories', title: 'Marcas', description: 'Análisis de rendimiento comercial agrupado por laboratorios/marcas.', icon: 'tabler-brand-sublime' },
  { key: 'pos', title: 'Analíticas TPV', description: 'Métricas de ventas, transacciones y promedios en tiempo real del TPV.', icon: 'tabler-device-desktop' },
  { key: 'cyclic', title: 'Análisis Cíclico', description: 'Auditoría de discrepancias y efectividad de conteos cíclicos.', icon: 'tabler-refresh' },
  { key: 'customer', title: 'Analítica de Clientes', description: 'Comportamiento, ticket promedio e historial de frecuencia de clientes.', icon: 'tabler-users' },
  { key: 'performance', title: 'Rendimiento RRHH', description: 'Scorecard de gamificación y KPIs de rendimiento del personal.', icon: 'tabler-trophy' }
]

const fetchSettings = async () => {
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data
    if (settings.enabled_bi_views) {
      enabledBiViews.value = settings.enabled_bi_views
    }
  } catch (error) {
    console.error("Error cargando configuración de BI:", error)
    toast.error("Error al cargar la configuración")
  }
}

const toggleBiView = (key) => {
  const index = enabledBiViews.value.indexOf(key)
  if (index > -1) {
    enabledBiViews.value.splice(index, 1)
  } else {
    enabledBiViews.value.push(key)
  }
  updateSettings()
}

const updateSettings = async () => {
  try {
    await axios.post('/general-settings', {
      enabled_bi_views: enabledBiViews.value
    })
    await brandingStore.fetchSettings()

    toast.success("Configuración de BI actualizada exitosamente")
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
  <div>
    <!-- Tarjeta Principal de Configuración de Vistas de BI -->
    <VCard class="mb-6 rounded-lg border shadow-sm">
      <VCardItem class="py-5">
        <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2 mb-2">
          <VIcon icon="tabler-chart-bar" class="text-primary" size="26" />
          Configuración de Vistas de BI (Business Intelligence)
        </VCardTitle>
        <p class="text-caption text-medium-emphasis">
          Selecciona qué reportes, tableros y herramientas analíticas del módulo de Inteligencia de Negocios (BI) estarán disponibles y visibles en el menú lateral para los usuarios autorizados.
        </p>
      </VCardItem>

      <VDivider />

      <VCardText class="pa-6">
        <VRow>
          <VCol
            v-for="view in availableBiViews"
            :key="view.key"
            cols="12"
            sm="6"
            md="4"
            class="d-flex"
          >
            <VCard
              variant="outlined"
              class="w-100 rounded-lg d-flex flex-column justify-space-between transition-all"
              :class="enabledBiViews.includes(view.key) ? 'border-primary bg-var-theme-background' : 'opacity-70'"
            >
              <VCardItem class="pa-4 flex-grow-1">
                <div class="d-flex justify-space-between align-start gap-4">
                  <VAvatar color="primary" variant="tonal" size="40" class="rounded-lg">
                    <VIcon :icon="view.icon" size="20" />
                  </VAvatar>
                  <VSwitch
                    :model-value="enabledBiViews.includes(view.key)"
                    color="primary"
                    density="compact"
                    hide-details
                    @update:model-value="toggleBiView(view.key)"
                  />
                </div>
                <div class="d-flex flex-column gap-1 mt-4">
                  <span class="font-weight-black text-body-1 text-high-emphasis">{{ view.title }}</span>
                  <p class="text-caption text-medium-emphasis mb-0 leading-snug">
                    {{ view.description }}
                  </p>
                </div>
              </VCardItem>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.bg-var-theme-background {
  background-color: rgba(var(--v-theme-primary), 0.04) !important;
}
.leading-snug {
  line-height: 1.35;
}
</style>
