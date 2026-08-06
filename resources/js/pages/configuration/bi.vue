<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert"
import { useBrandingStore } from "@/stores/useBrandingStore"
import BiModuleCard from '@/Components/configuration/BiModuleCard.vue'

const brandingStore = useBrandingStore()

// Estados reactivos de la interfaz
const isLoading = ref(true)
const isSaving = ref(false)
const hasError = ref(false)
const errorMessage = ref('')

const enabledBiViews = ref([])

// Lista estática de vistas configurables de BI
const availableBiViews = [
  { key: 'abc', title: 'Reporte ABC', description: 'Categorización de inventario ABC según valor y rotación.', icon: 'tabler-abc' },
  { key: 'dead-stock', title: 'Stock Muerto', description: 'Detección de productos sin movimiento o de baja rotación.', icon: 'tabler-package-off' },
  { key: 'sku', title: 'Margen SKU', description: 'Detalle de utilidad y rendimiento individual por SKU.', icon: 'tabler-calculator' },
  { key: 'products', title: 'Dashboard Maestro', description: 'Visión consolidada y analíticas globales de ventas de productos.', icon: 'tabler-chart-pie' },
  { key: 'expiry', title: 'BI Caducidad', description: 'Predicciones y alertas de productos próximos a vencer.', icon: 'tabler-calendar' },
  { key: 'laboratories', title: 'Marcas / Labs', description: 'Análisis comercial agrupado por laboratorios y marcas.', icon: 'tabler-building-factory-2' },
  { key: 'pos', title: 'Analíticas TPV', description: 'Métricas de ventas, transacciones y promedios en tiempo real.', icon: 'tabler-device-desktop' },
  { key: 'cyclic', title: 'Análisis Cíclico', description: 'Auditoría de discrepancias y efectividad de conteos cíclicos.', icon: 'tabler-refresh' },
  { key: 'customer', title: 'Analítica Clientes', description: 'Comportamiento, ticket promedio e historial de frecuencia.', icon: 'tabler-users' },
  { key: 'performance', title: 'Rendimiento RRHH', description: 'Scorecard de gamificación y KPIs de rendimiento del personal.', icon: 'tabler-trophy' }
]

// Propiedades computadas para métricas y estado global
const totalCount = computed(() => availableBiViews.length)

const activeCount = computed(() => enabledBiViews.value.length)

const activePercentage = computed(() => {
  if (totalCount.value === 0) return 0
  return Math.round((activeCount.value / totalCount.value) * 100)
})

const allEnabled = computed(() => activeCount.value === totalCount.value)

const noneEnabled = computed(() => activeCount.value === 0)

// Petición optimizada mediante el filtro 'only'
const fetchSettings = async () => {
  isLoading.value = true
  hasError.value = false
  errorMessage.value = ''

  try {
    const response = await axios.get('/general-settings', {
      params: { only: 'enabled_bi_views' }
    })
    const settings = response.data.data
    if (settings && Array.isArray(settings.enabled_bi_views)) {
      enabledBiViews.value = settings.enabled_bi_views
    }
  } catch (error) {
    console.error("Error cargando configuración de BI:", error)
    hasError.value = true
    errorMessage.value = "No se pudo cargar la configuración de BI. Verifique su conexión e intente de nuevo."
    toast.error("Error al cargar la configuración")
  } finally {
    isLoading.value = false
  }
}

// Alternar vista individual
const toggleBiView = async (key) => {
  if (isSaving.value || isLoading.value) return

  const previousState = [...enabledBiViews.value]
  const updatedViews = [...enabledBiViews.value]
  const index = updatedViews.indexOf(key)

  if (index > -1) {
    updatedViews.splice(index, 1)
  } else {
    updatedViews.push(key)
  }

  enabledBiViews.value = updatedViews
  await updateSettings(previousState)
}

// Acciones masivas: Activar o Desactivar todas las vistas
const setAllViews = async (enable) => {
  if (isSaving.value || isLoading.value) return

  const previousState = [...enabledBiViews.value]
  enabledBiViews.value = enable ? availableBiViews.map(v => v.key) : []
  await updateSettings(previousState)
}

// Persistir la configuración en el servidor
const updateSettings = async (previousState = null) => {
  isSaving.value = true
  try {
    await axios.post('/general-settings', {
      enabled_bi_views: enabledBiViews.value
    })

    await brandingStore.fetchSettings()
    toast.success("Configuración de BI actualizada exitosamente")
  } catch (error) {
    if (previousState) {
      enabledBiViews.value = previousState
    }
    console.error("Error al guardar la configuración de BI:", error)
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
  <div class="position-relative">
    <!-- Barra de progreso superior durante guardado -->
    <VProgressLinear
      v-if="isSaving"
      color="primary"
      indeterminate
      height="4"
      class="position-absolute top-0 left-0 right-0"
      style="z-index: 99;"
    />

    <!-- Tarjeta Principal de Configuración de BI -->
    <VCard class="mb-6 rounded-lg border shadow-sm">
      <VCardItem class="py-5">
        <!-- Encabezado Estandarizado con Métricas -->
        <div class="d-flex flex-column flex-sm-row justify-space-between align-start align-sm-center gap-4 mb-4">
          <div>
            <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2">
              <VIcon icon="tabler-chart-bar" color="primary" size="28" />
              Configuración de Vistas de BI (Business Intelligence)
            </VCardTitle>
            <p class="text-caption text-medium-emphasis mb-0 mt-1">
              Habilita o deshabilita los tableros y herramientas analíticas en la barra de navegación lateral.
            </p>
          </div>

          <!-- Métricas y Botones de Acción Masiva -->
          <div class="d-flex align-center gap-2 flex-wrap" v-if="!isLoading && !hasError">
            <VChip color="primary" variant="tonal" size="small" class="font-weight-bold">
              {{ activeCount }} / {{ totalCount }} Vistas Activas ({{ activePercentage }}%)
            </VChip>
            <VBtn
              size="small"
              variant="outlined"
              color="primary"
              :disabled="allEnabled || isSaving"
              @click="setAllViews(true)"
            >
              Activar Todas
            </VBtn>
            <VBtn
              size="small"
              variant="outlined"
              color="error"
              :disabled="noneEnabled || isSaving"
              @click="setAllViews(false)"
            >
              Desactivar Todas
            </VBtn>
          </div>
        </div>

        <VDivider class="mb-6" />

        <!-- Banner de Error con Reintento -->
        <VAlert
          v-if="hasError"
          type="error"
          variant="tonal"
          class="mb-6 rounded-lg"
          closable
        >
          <template #title> Error de Carga </template>
          {{ errorMessage }}
          <template #append>
            <VBtn color="error" variant="text" size="small" @click="fetchSettings">
              Reintentar
            </VBtn>
          </template>
        </VAlert>

        <!-- Skeletons durante Carga Inicial -->
        <VRow v-if="isLoading">
          <VCol v-for="n in 9" :key="n" cols="12" sm="6" md="4">
            <VSkeletonLoader type="article, actions" class="rounded-lg border" height="130" />
          </VCol>
        </VRow>

        <!-- Rejilla de Módulos BI -->
        <VRow v-else-if="!hasError">
          <VCol
            v-for="view in availableBiViews"
            :key="view.key"
            cols="12"
            sm="6"
            md="4"
          >
            <BiModuleCard
              :view="view"
              :is-active="enabledBiViews.includes(view.key)"
              :is-saving="isSaving"
              @toggle="toggleBiView"
            />
          </VCol>
        </VRow>
      </VCardItem>
    </VCard>
  </div>
</template>
