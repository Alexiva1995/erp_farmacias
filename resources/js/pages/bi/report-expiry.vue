<script setup>
import { useExpiryStore } from '@/stores/expiry-store'
import { storeToRefs } from 'pinia'
import { onMounted, onUnmounted, ref, computed, watch } from 'vue'
import axios from '@/plugins/axios'
import VueApexCharts from 'vue3-apexcharts'

// Componentes desacoplados
import ExpiryKpiCards from '@/components/bi/ExpiryKpiCards.vue'
import ExpiryChartOverlay from '@/components/bi/ExpiryChartOverlay.vue'
import ExpiryOverstockTable from '@/components/bi/ExpiryOverstockTable.vue'
// Composable que centraliza la configuración de los 4 gráficos ApexCharts
import { useExpiryCharts } from '@/composables/useExpiryCharts'

const expiryStore = useExpiryStore()
const { dashboardData, loading, error, filters } = storeToRefs(expiryStore)

// Referencia al componente tabla para acceder al computed de exportación
const overstockTableRef = ref(null)

// Catálogos de filtros
const laboratories = ref([])
const categories = ref([])
const groups = ref([])
const isAdvancedFiltersVisible = ref(false)
const metricType = ref('units') // 'units' | 'value'

// Snackbar de notificaciones
const snackbar = ref({ show: false, message: '', color: 'success' })

const showMessage = (msg, color = 'success') => {
  snackbar.value = { show: true, message: msg, color }
}

const hasActiveAdvancedFilters = computed(() =>
  filters.value.laboratory_id || filters.value.category_id || filters.value.group_id
)

const resetFilters = () => {
  expiryStore.resetFilters()
  isAdvancedFiltersVisible.value = false
  showMessage('Filtros restablecidos', 'info')
}

// Reaccionar a errores del store y mostrarlos al usuario
watch(error, val => {
  if (val) showMessage(val, 'error')
})

// Carga de catálogos con resiliencia
const fetchFilters = async () => {
  try {
    const [labRes, catRes, groupRes] = await Promise.all([
      axios.get('/laboratories').catch(() => ({ data: [] })),
      axios.get('/categories').catch(() => ({ data: [] })),
      axios.get('/groups/consult-all').catch(() => axios.get('/groups')).catch(() => ({ data: [] })),
    ])

    laboratories.value = Array.isArray(labRes.data) ? labRes.data : []
    categories.value = Array.isArray(catRes.data) ? catRes.data : []
    const grpData = groupRes.data
    groups.value = Array.isArray(grpData) ? grpData : (Array.isArray(grpData?.data) ? grpData.data : [])
  } catch (err) {
    console.error('Error cargando catálogos de filtros:', err)
    showMessage('Error al cargar catálogos de filtros', 'error')
  }
}

// Debounce reutilizable para cualquier watch con delay
let searchDebounceTimer = null

const createDebounced = (fn, delay) => {
  let timer = null
  const debounced = (...args) => {
    clearTimeout(timer)
    timer = setTimeout(() => fn(...args), delay)
  }
  debounced.cancel = () => clearTimeout(timer)
  return debounced
}

// Búsqueda principal — 500ms
watch(() => filters.value.search, () => {
  clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    expiryStore.fetchDashboardData()
  }, 500)
})

// Filtros avanzados — auto-apply al cambiar (400ms para mayor fluidez)
const advancedFetch = createDebounced(() => expiryStore.fetchDashboardData(), 400)

watch(
  () => [filters.value.laboratory_id, filters.value.category_id, filters.value.group_id],
  () => advancedFetch(),
  { deep: false }
)

onMounted(() => {
  fetchFilters()
  expiryStore.fetchDashboardData()
})

// Limpiar timers al desmontar para evitar memory leaks
onUnmounted(() => {
  clearTimeout(searchDebounceTimer)
  advancedFetch.cancel()
})

// ─── Configuraciones de Gráficos (composable) ────────────────────────────────
// dashboardData es un reactive (no Ref), se pasa directamente al composable
const {
  horizonChartConfig,
  sixMonthTrendConfig,
  riskBarChartConfig,
  lossHistoryChartConfig,
  fmtMoney: formatMoney,
  fmtNum: formatNumber,
} = useExpiryCharts(dashboardData.value, metricType)

// ─── Exportación CSV ────────────────────────────────────────────────────────────
const handleExport = () => {
  try {
    const data = overstockTableRef.value?.aggregatedOverstock ?? []

    if (!data.length) {
      showMessage('No hay datos disponibles para exportar', 'warning')
      return
    }

    const csvHeaders = ['ID PRODUCTO', 'PRODUCTO', 'LABORATORIO', 'STOCK ACTUAL', 'VENTA MENSUAL PROM', 'EXCEDENTE PROYECTADO (U)', 'COSTO RIESGO EXCEDENTE']

    const rows = data.map(item => [
      item.product_id,
      item.name,
      item.laboratory_name ?? 'N/A',
      item.stock_actual,
      item.venta_mensual_promedio,
      item.excedente_proyectado,
      item.costo_excedente,
    ])

    const escape = val => {
      const text = String(val ?? '').replace(/"/g, '""')
      return text.includes(';') || text.includes('\n') || text.includes('"') ? `"${text}"` : text
    }

    const csvContent = '\uFEFF' + [csvHeaders.join(';'), ...rows.map(r => r.map(escape).join(';'))].join('\n')
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = Object.assign(document.createElement('a'), {
      href: url,
      download: `reporte_sobrestock_vencimiento_${new Date().toISOString().slice(0, 10)}.csv`,
      style: 'visibility:hidden',
    })

    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)

    showMessage('Reporte exportado exitosamente en formato CSV', 'success')
  } catch (err) {
    console.error('Error al exportar reporte:', err)
    showMessage('Ocurrió un error al exportar el reporte', 'error')
  }
}
</script>

<template>
  <VContainer fluid class="expiry-dashboard pa-0">

    <!-- ─── Panel de Filtros ─────────────────────────────────────────── -->
    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardText class="pa-4">
        <!-- Barra principal -->
        <VRow align="center" no-gutters class="gap-2">
          <VCol cols="12" md="4">
            <AppTextField
              v-model="filters.search"
              placeholder="Buscar por SKU o Nombre..."
              prepend-inner-icon="tabler-search"
              clearable
              density="compact"
              hide-details
              class="premium-input-compact"
              :disabled="loading"
            />
          </VCol>

          <VSpacer />

          <!-- flex-wrap para evitar desbordamiento horizontal en móvil -->
          <div class="d-flex align-center gap-2 flex-wrap justify-end">
            <!-- Selector de Métrica -->
            <VBtnToggle
              v-model="metricType"
              mandatory
              variant="tonal"
              density="compact"
              color="primary"
              class="premium-toggle"
              :disabled="loading"
              aria-label="Tipo de métrica"
            >
              <VBtn value="value" size="small" class="px-2" aria-label="Ver por valor monetario">
                <VIcon icon="tabler-currency-dollar" size="20" />
              </VBtn>
              <VBtn value="units" size="small" class="px-2" aria-label="Ver por unidades">
                <VIcon icon="tabler-package" size="20" />
              </VBtn>
            </VBtnToggle>

            <VDivider vertical class="mx-1 my-2 border-opacity-10 d-none d-sm-block" />

            <!-- Filtros Avanzados -->
            <VBtn
              icon
              variant="tonal"
              :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
              size="38"
              class="rounded-circle shadow-sm"
              :disabled="loading"
              aria-label="Mostrar filtros avanzados"
              @click="isAdvancedFiltersVisible = !isAdvancedFiltersVisible"
            >
              <VBadge
                v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
                color="error"
                dot
                offset-x="2"
                offset-y="-2"
              >
                <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
              </VBadge>
              <VIcon v-else :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
              <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
            </VBtn>

            <!-- Aplicar Filtros -->
            <VBtn
              icon
              variant="flat"
              color="primary"
              size="38"
              class="rounded-circle shadow-sm"
              :loading="loading"
              :disabled="loading"
              aria-label="Aplicar filtros"
              @click="expiryStore.fetchDashboardData()"
            >
              <VIcon icon="tabler-player-play" size="20" />
              <VTooltip activator="parent" location="top">Aplicar Filtros</VTooltip>
            </VBtn>

            <VDivider vertical class="mx-1 my-2 border-opacity-10 d-none d-sm-block" />

            <!-- Limpiar Filtros -->
            <VBtn
              icon
              variant="text"
              color="secondary"
              size="38"
              class="rounded-circle shadow-sm"
              :disabled="loading"
              aria-label="Limpiar filtros"
              @click="resetFilters"
            >
              <VIcon icon="tabler-eraser" size="20" />
              <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
            </VBtn>

            <!-- Exportar CSV -->
            <VBtn
              icon
              variant="tonal"
              color="success"
              size="38"
              class="rounded-circle shadow-sm"
              :disabled="loading"
              aria-label="Exportar reporte CSV"
              @click="handleExport"
            >
              <VIcon icon="tabler-download" size="20" />
              <VTooltip activator="parent" location="top">Exportar CSV</VTooltip>
            </VBtn>
          </div>
        </VRow>

        <!-- Filtros Avanzados colapsables -->
        <VExpandTransition>
          <div v-show="isAdvancedFiltersVisible">
            <VDivider class="my-3 border-opacity-10" />
            <VRow>
              <VCol cols="12" sm="6" md="4">
                <AppAutocomplete
                  v-model="filters.laboratory_id"
                  :items="laboratories"
                  item-title="name"
                  item-value="id"
                  placeholder="Laboratorio / Proveedor"
                  clearable
                  density="compact"
                  hide-details
                  class="premium-select-compact"
                  prepend-inner-icon="tabler-flask"
                  :disabled="loading"
                />
              </VCol>
              <VCol cols="12" sm="6" md="4">
                <AppSelect
                  v-model="filters.category_id"
                  :items="categories"
                  item-title="name"
                  item-value="id"
                  placeholder="Categoría de Producto"
                  clearable
                  density="compact"
                  hide-details
                  class="premium-select-compact"
                  prepend-inner-icon="tabler-category"
                  :disabled="loading"
                />
              </VCol>
              <VCol cols="12" sm="6" md="4">
                <AppSelect
                  v-model="filters.group_id"
                  :items="groups"
                  item-title="name"
                  item-value="id"
                  placeholder="Grupo de Productos"
                  clearable
                  density="compact"
                  hide-details
                  class="premium-select-compact"
                  prepend-inner-icon="tabler-layers-intersect"
                  :disabled="loading"
                />
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <!-- ─── KPI Cards (componente desacoplado) ───────────────────────── -->
    <ExpiryKpiCards
      :dashboard-data="dashboardData"
      :loading="loading"
    />

    <!-- ─── Gráficos: Horizonte & Tendencia ──────────────────────────── -->
    <VRow class="mb-6">
      <VCol cols="12" md="6">
        <VCard class="rounded-lg border shadow-sm h-full">
          <VCardItem>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-chart-bar-stacked" class="me-2 text-primary" />
              Horizonte por Categoría (Total)
            </VCardTitle>
          </VCardItem>
          <VCardText class="position-relative" style="min-height: 380px;">
            <ExpiryChartOverlay :loading="loading" />
            <VueApexCharts
              :key="`horizon-${metricType}`"
              height="350"
              :options="horizonChartConfig.options"
              :series="horizonChartConfig.series"
            />
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="6">
        <VCard class="rounded-lg border shadow-sm h-full">
          <VCardItem>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-chart-area" class="me-2 text-success" />
              Tendencia Mensual (Próximos 6 Meses)
            </VCardTitle>
          </VCardItem>
          <VCardText class="position-relative" style="min-height: 380px;">
            <ExpiryChartOverlay :loading="loading" />
            <VueApexCharts
              :key="`trend-${metricType}`"
              height="350"
              :options="sixMonthTrendConfig.options"
              :series="sixMonthTrendConfig.series"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- ─── Gráfico: Top 10 Riesgo Financiero ───────────────────────── -->
    <VRow>
      <VCol cols="12">
        <VCard class="rounded-lg border shadow-sm">
          <VCardItem>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-alert-triangle" class="me-2 text-error" />
              Top 10 Productos con Mayor Riesgo Financiero
            </VCardTitle>
          </VCardItem>
          <VCardText class="position-relative" style="min-height: 380px;">
            <ExpiryChartOverlay :loading="loading" />
            <VueApexCharts
              :key="`risk-${metricType}`"
              height="350"
              :options="riskBarChartConfig.options"
              :series="riskBarChartConfig.series"
            />
          </VCardText>
        </VCard>
      </VCol>

      <!-- ─── Tabla Sobrestock (componente desacoplado) ─────────────── -->
      <VCol cols="12" md="8">
        <ExpiryOverstockTable
          ref="overstockTableRef"
          :items="dashboardData.overstock"
          :loading="loading"
          :items-per-page="10"
        />
      </VCol>

      <!-- ─── Historial de Mermas ───────────────────────────────────── -->
      <VCol cols="12" md="4">
        <VCard class="rounded-lg border shadow-sm h-full">
          <VCardItem>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-history" class="me-2 text-error" />
              Historial de Mermas (6m)
            </VCardTitle>
          </VCardItem>
          <VCardText class="position-relative" style="min-height: 530px;">
            <ExpiryChartOverlay :loading="loading" />
            <VueApexCharts
              :key="`history-${metricType}`"
              height="500"
              :options="lossHistoryChartConfig.options"
              :series="lossHistoryChartConfig.series"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- ─── Snackbar de notificaciones ───────────────────────────────── -->
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      location="top right"
      :timeout="3000"
    >
      {{ snackbar.message }}
      <template #actions>
        <VBtn icon variant="text" @click="snackbar.show = false">
          <VIcon icon="tabler-x" />
        </VBtn>
      </template>
    </VSnackbar>

  </VContainer>
</template>

<style scoped>
.expiry-dashboard {
  background-color: transparent;
}

.gap-2 {
  gap: 8px !important;
}

.premium-toggle {
  background: rgba(var(--v-theme-on-surface), 0.05) !important;
  border-radius: 8px !important;
  padding: 2px !important;
}

.premium-toggle .v-btn {
  border: none !important;
  border-radius: 6px !important;
  text-transform: none !important;
  font-weight: 700 !important;
  letter-spacing: 0.5px !important;
}

.premium-toggle .v-btn--active {
  background: rgb(var(--v-theme-primary)) !important;
  color: #fff !important;
  box-shadow: 0 2px 8px rgba(var(--v-theme-primary), 0.4) !important;
}

/* Dark mode — usa token del tema en lugar de #fff hardcodeado */
.v-theme--dark .bg-surface {
  background-color: rgba(47, 51, 73, 0.7) !important;
  backdrop-filter: blur(10px);
}
</style>
