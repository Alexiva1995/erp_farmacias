<script setup>
import { ref, onMounted } from 'vue'
import axios from '@axios'
import CyclicFilterBar from '@/components/bi/CyclicFilterBar.vue'
import CyclicKpiCards from '@/components/bi/CyclicKpiCards.vue'
import CyclicTrendCharts from '@/components/bi/CyclicTrendCharts.vue'
import CyclicCodeCrossingTable from '@/components/bi/CyclicCodeCrossingTable.vue'
import { generateBiInventoryCyclicPdf } from '@/utils/pdfBiInventoryCyclicGenerator'

const dashboardData = ref(null)
const categories = ref([])
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
  categoryId: null,
})

const fetchCategories = async () => {
  try {
    const response = await axios.get('/categories')
    categories.value = response.data.data || response.data || []
  } catch (error) {
    console.error('Error fetching categories:', error)
  }
}

const fetchDashboardData = async () => {
  loading.value = true
  errorState.value = null
  try {
    const response = await axios.get('/bi/inventory-cyclic', {
      params: {
        start_date: filters.value.startDate,
        end_date: filters.value.endDate,
        category_id: filters.value.categoryId || undefined,
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
  filters.value.categoryId = null
  fetchDashboardData()
}

const handleSelectCategory = categoryName => {
  const matched = categories.value.find(c => c.name?.toLowerCase() === categoryName?.toLowerCase())
  if (matched) {
    filters.value.categoryId = matched.id
    fetchDashboardData()
  }
}

const exportPdf = () => {
  if (!dashboardData.value) return
  generateBiInventoryCyclicPdf(dashboardData.value, filters.value)
}

const exportExcel = () => {
  if (!dashboardData.value?.substitutions?.length) {
    if (window.toast) window.toast.info('No hay cruces de códigos para exportar.')
    return
  }

  const rows = [
    ['Categoría', 'Producto Faltante', 'Principio Activo A', 'Cant. Faltante', 'Producto Sobrante', 'Principio Activo B', 'Cant. Sobrante', 'Nivel Confianza', 'Criterios Detectados'],
    ...dashboardData.value.substitutions.map(sub => [
      `"${sub.category || ''}"`,
      `"${sub.product_a || ''}"`,
      `"${sub.active_ingredient_a || ''}"`,
      sub.discrepancy_a ?? 0,
      `"${sub.product_b || ''}"`,
      `"${sub.active_ingredient_b || ''}"`,
      sub.discrepancy_b ?? 0,
      `"${sub.confidence || ''}"`,
      `"${sub.match_reason || ''}"`,
    ]),
  ]

  const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + rows.map(e => e.join(',')).join('\n')
  const encodedUri = encodeURI(csvContent)
  const link = document.createElement('a')
  link.setAttribute('href', encodedUri)
  link.setAttribute('download', `cruces_inventario_${filters.value.startDate}_${filters.value.endDate}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

onMounted(() => {
  fetchCategories()
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
      :categories="categories"
      :loading="loading"
      @apply="fetchDashboardData"
      @clear="handleClearFilters"
      @export-pdf="exportPdf"
      @export-excel="exportExcel"
    />

    <!-- Estado de Error -->
    <div v-if="errorState" class="mb-6">
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

    <!-- Contenido del Dashboard con Soporte de Carga Esquelética -->
    <div v-if="dashboardData || loading">
      <!-- Fila 1: KPIs Principales -->
      <CyclicKpiCards
        :kpis="dashboardData?.kpis || {}"
        :loading="loading"
      />

      <!-- Fila 2 y 3: Gráficos de Tendencias y Desviaciones -->
      <CyclicTrendCharts
        :dashboard-data="dashboardData || {}"
        :chart-key="chartKey"
        :loading="loading"
        @select-category="handleSelectCategory"
      />

      <!-- Fila 4: Tabla de Cruce de Códigos (Sustituciones) -->
      <CyclicCodeCrossingTable
        :substitutions="dashboardData?.substitutions || []"
        :loading="loading"
      />
    </div>
  </VContainer>
</template>
