<script setup>
import { ref, onMounted } from 'vue'
import axios from '@axios'
import CyclicFilterBar from '@/components/bi/CyclicFilterBar.vue'
import CyclicKpiCards from '@/components/bi/CyclicKpiCards.vue'
import CyclicTrendCharts from '@/components/bi/CyclicTrendCharts.vue'
import CyclicCodeCrossingTable from '@/components/bi/CyclicCodeCrossingTable.vue'

const dashboardData = ref(null)
const loading = ref(false)
const chartKey = ref(0)
const errorState = ref(null)

// Helper para obtener fecha local en formato YYYY-MM-DD
const getLocalDateString = date => {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const filters = ref({
  startDate: getLocalDateString(new Date(new Date().getFullYear(), new Date().getMonth(), 1)),
  endDate: getLocalDateString(new Date()),
})

const fetchDashboardData = async () => {
  loading.value = true
  errorState.value = null
  try {
    const response = await axios.get('/bi/inventory-cyclic', {
      params: {
        start_date: filters.value.startDate,
        end_date: filters.value.endDate,
      },
    })
    dashboardData.value = response.data.data || response.data
    chartKey.value++
  } catch (error) {
    console.error('Error fetching inventory dashboard:', error)
    errorState.value = 'Ocurrió un error al cargar la información del inventario cíclico. Por favor, reintente.'
    if (window.toast) {
      window.toast.error('Error al cargar datos de inventario cíclico.')
    }
  } finally {
    loading.value = false
  }
}

const handleClearFilters = () => {
  filters.value.startDate = getLocalDateString(new Date(new Date().getFullYear(), new Date().getMonth(), 1))
  filters.value.endDate = getLocalDateString(new Date())
  fetchDashboardData()
}

onMounted(() => {
  fetchDashboardData()
})
</script>

<template>
  <VContainer fluid class="pa-0">
    <!-- Encabezado de la Vista -->
    <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-6">
      <div>
        <h3 class="text-h4 font-weight-bold mb-1">
          BI - Inventario Cíclico
        </h3>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Control de precisión de inventario (ERI), desviaciones y detección de cruces de productos.
        </p>
      </div>
    </div>

    <!-- Barra de Filtros Estandarizada -->
    <CyclicFilterBar
      :filters="filters"
      :loading="loading"
      @apply="fetchDashboardData"
      @clear="handleClearFilters"
    />

    <!-- Estado de Carga -->
    <div v-if="loading" class="d-flex justify-center align-center my-12" style="min-height: 300px;">
      <VProgressCircular indeterminate color="primary" size="56" />
    </div>

    <!-- Estado de Error -->
    <div v-else-if="errorState" class="mb-6">
      <VAlert
        type="error"
        variant="tonal"
        closable
        icon="tabler-alert-circle"
        title="Error de Conexión"
        @click:close="errorState = null"
      >
        {{ errorState }}
        <template #append>
          <VBtn size="small" color="error" class="ms-4" @click="fetchDashboardData">
            Reintentar
          </VBtn>
        </template>
      </VAlert>
    </div>

    <!-- Contenido del Dashboard -->
    <div v-else-if="dashboardData">
      <!-- Fila 1: KPIs Principales -->
      <CyclicKpiCards :kpis="dashboardData.kpis || {}" />

      <!-- Fila 2 y 3: Gráficos de Tendencias y Desviaciones -->
      <CyclicTrendCharts
        :dashboard-data="dashboardData"
        :chart-key="chartKey"
      />

      <!-- Fila 4: Tabla de Cruce de Códigos (Sustituciones) -->
      <CyclicCodeCrossingTable
        :substitutions="dashboardData.substitutions || []"
        :loading="loading"
      />
    </div>
  </VContainer>
</template>
