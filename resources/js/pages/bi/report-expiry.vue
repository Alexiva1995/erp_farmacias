<script setup>
import { useExpiryStore } from '@/stores/expiry-store'
import { storeToRefs } from 'pinia'
import { onMounted, ref, computed } from 'vue'
import axios from '@/plugins/axios'
import VueApexCharts from 'vue3-apexcharts'

const expiryStore = useExpiryStore()
const { dashboardData, loading, filters } = storeToRefs(expiryStore)

const laboratories = ref([])
const categories = ref([])
const isAdvancedFiltersVisible = ref(false)

const hasActiveAdvancedFilters = computed(() => {
  return filters.value.laboratory_id || filters.value.category_id || filters.value.group_id
})

const resetFilters = () => {
  expiryStore.resetFilters()
  isAdvancedFiltersVisible.value = false
}

const fetchFilters = async () => {
  try {
    const [labRes, catRes] = await Promise.all([
      axios.get('/laboratories'),
      axios.get('/categories')
    ])
    laboratories.value = labRes.data
    categories.value = catRes.data
  } catch (error) {
    console.error('Error loading filters:', error)
  }
}

onMounted(() => {
  fetchFilters()
  expiryStore.fetchDashboardData()
} )

const metricType = ref('units') // 'units' o 'value' por defecto ahora unidades

const formatValue = (val) => {
  if (metricType.value === 'value') return `$${Number(val).toLocaleString('en-US', { minimumFractionDigits: 2 })}`
  return `${Number(val).toLocaleString('en-US')} U.`
}

const formatMoney = (val) => `$${Number(val).toLocaleString('en-US', { minimumFractionDigits: 2 })}`
const formatNumber = (val) => Number(val).toLocaleString('en-US')

// --- Chart Configurations ---

// 1. Expiry Horizon (Stacked Bar)
const horizonChartConfig = computed(() => {
  const months = [...new Set(dashboardData.value.horizon.map(i => i.month))].sort()
  const categories = [...new Set(dashboardData.value.horizon.map(i => i.category_name))]
  
  const series = categories.map(cat => ({
    name: cat,
    data: months.map(m => {
      const item = dashboardData.value.horizon.find(i => i.month === m && i.category_name === cat)
      if (!item) return 0
      return metricType.value === 'value' ? parseFloat(item.total_value) : parseFloat(item.total_units)
    })
  }))

  return {
    series,
    options: {
      chart: { type: 'bar', stacked: true, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
      plotOptions: { bar: { horizontal: false, borderRadius: 4 } },
      xaxis: { categories: months, labels: { style: { colors: '#a3a3a3' } } },
      yaxis: { labels: { formatter: (val) => metricType.value === 'value' ? `$${val.toFixed(0)}` : `${val.toFixed(0)}`, style: { colors: '#a3a3a3' } } },
      legend: { position: 'top', labels: { colors: '#a3a3a3' } },
      dataLabels: { enabled: false },
      colors: ['#7367f0', '#28c76f', '#ea5455', '#ff9f43', '#00cfe8'],
      grid: { borderColor: 'rgba(144, 164, 174, 0.1)' }
    }
  }
})


// 3. Next 6 Months Trend (Area Chart)
const sixMonthTrendConfig = computed(() => {
  const months = []
  const now = new Date()
  
  // Generar próximos 6 meses
  for (let i = 0; i < 6; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() + i, 1)
    const monthName = d.toLocaleString('es-ES', { month: 'short', year: '2-digit' }).toUpperCase()
    const sortKey = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    months.push({ monthName, sortKey })
  }
  
  const categories = months.map(m => m.monthName)
  const data = months.map(m => {
    const items = dashboardData.value.horizon.filter(i => i.sort_key === m.sort_key)
    return items.reduce((acc, curr) => acc + (metricType.value === 'value' ? parseFloat(curr.total_value) : parseFloat(curr.total_units)), 0)
  })

  return {
    series: [{
      name: metricType.value === 'value' ? 'Vencimiento (USD)' : 'Vencimiento (Unidades)',
      data: data
    }],
    options: {
      chart: {
        type: 'area',
        toolbar: { show: false },
        zoom: { enabled: false }
      },
      dataLabels: { enabled: false },
      stroke: { curve: 'smooth', width: 3 },
      fill: {
        type: 'gradient',
        gradient: {
          shadeIntensity: 1,
          opacityFrom: 0.5,
          opacityTo: 0.1,
          stops: [0, 90, 100]
        }
      },
      xaxis: {
        categories: categories,
        labels: { style: { colors: '#a3a3a3', fontSize: '10px' } }
      },
      yaxis: {
        labels: {
          formatter: (val) => metricType.value === 'value' ? `$${val.toLocaleString()}` : `${val.toLocaleString()}`,
          style: { colors: '#a3a3a3' }
        }
      },
      colors: ['#28c76f'],
      grid: { borderColor: 'rgba(144, 164, 174, 0.1)' },
      tooltip: { theme: 'dark' }
    }
  }
})

// 4. Risk Ranking (Horizontal Bar) - Más entendible
const riskBarChartConfig = computed(() => {
  // Tomar los top 10 productos con mayor costo excedente de la data de overstock
  const topRisks = [...dashboardData.value.overstock]
    .sort((a, b) => b.costo_excedente - a.costo_excedente)
    .slice(0, 10)

  const categories = topRisks.map(i => i.name.substring(0, 20) + '...')
  const values = topRisks.map(i => i.costo_excedente)

  return {
    series: [{ name: 'Costo en Riesgo', data: values }],
    options: {
      chart: { type: 'bar', toolbar: { show: false } },
      plotOptions: { 
        bar: { 
          horizontal: true, 
          borderRadius: 4,
          distributed: true // Colores diferentes por barra
        } 
      },
      colors: ['#ea5455', '#ff9f43', '#ffc107', '#28c76f', '#00cfe8', '#7367f0', '#4b4b4b', '#82868b', '#212121', '#a8aaae'],
      xaxis: { 
        categories: categories,
        labels: { formatter: (val) => `$${val.toFixed(0)}`, style: { colors: '#a3a3a3' } }
      },
      yaxis: { labels: { style: { colors: '#a3a3a3' } } },
      dataLabels: { enabled: true, formatter: (val) => `$${val.toFixed(0)}`, style: { fontSize: '10px' } },
      legend: { show: false },
      grid: { borderColor: 'rgba(144, 164, 174, 0.1)' }
    }
  }
})

const overstockHeaders = [
  { title: 'Producto', key: 'name' },
  { title: 'Laboratorio', key: 'laboratory_name' },
  { title: 'Stock', key: 'stock_actual' },
  { title: 'Venta Prom.', key: 'venta_mensual_promedio' },
  { title: 'Vence', key: 'expiration_date' },
  { title: 'Excedente (U)', key: 'excedente_proyectado' },
  { title: 'Impacto ($)', key: 'costo_excedente' },
]

const handleExport = () => {
  // Aquí iría la lógica de exportación (CSV o Excel)
  // Por ahora log para debug
  console.log('Exporting data...')
}
</script>

<template>
  <VContainer fluid class="expiry-dashboard pa-0">


    <!-- Filtros Estandarizados -->
    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardText class="pa-4">
        <!-- Barra de Búsqueda Principal -->
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
            />
          </VCol>

          <VCol cols="12" md="3">
            <AppSelect
              v-model="filters.semaphore"
              :items="[
                {title: '🚨 Crítico (<90 días)', value: 'critico'},
                {title: '⚠️ Moderado (<180 días)', value: 'moderado'},
                {title: '✅ Estable (>180 días)', value: 'estable'}
              ]"
              placeholder="Estado de Riesgo"
              density="compact"
              hide-details
              clearable
              class="premium-select-compact"
              prepend-inner-icon="tabler-traffic-lights"
            />
          </VCol>

          <VSpacer />

          <div class="d-flex align-center gap-2">
            <!-- Selector de Métrica -->
            <VBtnToggle
              v-model="metricType"
              mandatory
              variant="tonal"
              density="compact"
              color="primary"
              class="premium-toggle"
            >
              <VBtn value="value" size="small" class="px-4">
                <VIcon icon="tabler-currency-dollar" size="18" class="me-1" />
                USD
              </VBtn>
              <VBtn value="units" size="small" class="px-4">
                <VIcon icon="tabler-package" size="18" class="me-1" />
                UNI
              </VBtn>
            </VBtnToggle>

            <VDivider vertical class="mx-1 my-2 border-opacity-10" />

            <!-- Toggle Filtros -->
            <VBtn
              icon
              variant="tonal"
              :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
              size="38"
              class="rounded-circle shadow-sm"
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
              @click="expiryStore.fetchDashboardData()"
            >
              <VIcon icon="tabler-player-play" size="20" />
              <VTooltip activator="parent" location="top">Aplicar Filtros</VTooltip>
            </VBtn>

            <VDivider vertical class="mx-1 my-2 border-opacity-10" />

            <!-- Limpiar Filtros -->
            <VBtn
              icon
              variant="text"
              color="secondary"
              size="38"
              class="rounded-circle shadow-sm"
              @click="resetFilters"
            >
              <VIcon icon="tabler-eraser" size="20" />
              <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
            </VBtn>

            <!-- Exportar -->
            <VBtn
              icon
              variant="tonal"
              color="success"
              size="38"
              class="rounded-circle shadow-sm"
              @click="handleExport"
            >
              <VIcon icon="tabler-download" size="20" />
              <VTooltip activator="parent" location="top">Exportar</VTooltip>
            </VBtn>
          </div>
        </VRow>

        <!-- Panel de Filtros Avanzado -->
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
                />
              </VCol>

              <VCol cols="12" sm="6" md="4">
                <AppSelect
                  v-model="filters.group_id"
                  :items="[]"
                  placeholder="Grupo de Productos"
                  clearable
                  density="compact"
                  hide-details
                  class="premium-select-compact"
                  prepend-inner-icon="tabler-layers-intersect"
                />
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <!-- Row 1: KPI Cards -->
    <VRow class="mb-4">
      <VCol cols="12" md="3" v-for="(kpi, idx) in [
        { 
          title: 'Vencido (Mes)', 
          mainValue: formatNumber(dashboardData.kpis.total_units_expired_month) + ' U.', 
          subValue: formatMoney(dashboardData.kpis.total_cost_merma_month),
          icon: 'tabler-package-off', color: 'error', desc: 'Perdida total' 
        },
        { 
          title: 'Stock Riesgo (<6m)', 
          mainValue: formatNumber(dashboardData.horizon.reduce((a, b) => a + parseFloat(b.total_units), 0)) + ' U.',
          subValue: '= ' + formatMoney(dashboardData.horizon.reduce((a, b) => a + parseFloat(b.total_value), 0)),
          icon: 'tabler-alert-triangle', color: 'warning', desc: 'Fondo próximo' 
        },
        { 
          title: 'Excedente Unidades', 
          mainValue: formatNumber(dashboardData.overstock.reduce((a, b) => a + b.excedente_proyectado, 0)) + ' U.',
          subValue: '= ' + formatMoney(dashboardData.overstock.reduce((a, b) => a + b.costo_excedente, 0)),
          icon: 'tabler-chart-bar-off', color: 'info', desc: 'Sobre existencia' 
        },
        { 
          title: 'Costo Excedente', 
          mainValue: formatMoney(dashboardData.overstock.reduce((a, b) => a + b.costo_excedente, 0)),
          subValue: 'Impacto total',
          icon: 'tabler-cash-off', color: 'secondary', desc: 'Capital estancado' 
        }
      ]" :key="idx">
        <VCard class="rounded-lg border shadow-sm">
          <VCardText class="pa-4 d-flex align-center">
            <VAvatar :color="kpi.color" variant="tonal" size="48" rounded="lg" class="me-4 font-weight-bold">
              <VIcon :icon="kpi.icon" size="24" />
            </VAvatar>
            <div>
              <p class="text-caption text-disabled mb-0 font-weight-bold truncate" style="max-width: 150px">{{ kpi.title }}</p>
              <h3 class="text-h5 font-weight-black">{{ kpi.mainValue }}</h3>
              <p class="text-xs font-weight-bold text-medium-emphasis mb-0 mt-0">
                {{ kpi.subValue }}
              </p>
              <p class="text-super-xs text-disabled mb-0">{{ kpi.desc }}</p>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Row 2: Charts (Horizon & Trend) -->
    <VRow class="mb-6">
      <VCol cols="12" md="6">
        <VCard class="rounded-lg border shadow-sm h-full">
          <VCardItem>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-chart-bar-stacked" class="me-2 text-primary" />
              Horizonte por Categoría (Total)
            </VCardTitle>
          </VCardItem>
          <VCardText>
            <div v-if="loading" class="d-flex justify-center align-center h-full min-h-[300px]">
              <VProgressCircular indeterminate color="primary" />
            </div>
            <VueApexCharts
              v-else
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
          <VCardText>
            <div v-if="loading" class="d-flex justify-center align-center h-full min-h-[300px]">
              <VProgressCircular indeterminate color="primary" />
            </div>
            <VueApexCharts
              v-else
              :key="`trend-${metricType}`"
              height="350"
              :options="sixMonthTrendConfig.options"
              :series="sixMonthTrendConfig.series"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Row 3: Action & Risk (Scatter & Table) -->
    <VRow>
      <VCol cols="12" md="12">
        <VCard class="rounded-lg border shadow-sm h-full">
          <VCardItem>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-alert-triangle" class="me-2 text-error" />
              Top 10 Productos con Mayor Riesgo Financiero
            </VCardTitle>
          </VCardItem>
          <VCardText>
            <div v-if="loading" class="d-flex justify-center align-center h-full min-h-[300px]">
              <VProgressCircular indeterminate color="primary" />
            </div>
            <VueApexCharts
              v-else
              :key="`risk-${metricType}`"
              height="350"
              :options="riskBarChartConfig.options"
              :series="riskBarChartConfig.series"
            />
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="12">
        <VCard class="rounded-lg border shadow-sm">
          <VCardItem>
            <VCardTitle class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <VIcon icon="tabler-alert-square" class="me-2 text-warning" />
                Alerta de Sobrestock Proyectado
              </div>
              <VChip color="error" variant="tonal" size="x-small" class="font-weight-black">ORDENADO POR IMPACTO CRÍTICO</VChip>
            </VCardTitle>
          </VCardItem>
          <VDivider class="opacity-10" />
          <VCardText class="pa-0">
             <VDataTable
              :headers="overstockHeaders"
              :items="dashboardData.overstock"
              :loading="loading"
              class="premium-table density-compact"
              hide-default-footer
              no-data-text="No se detectaron riesgos de sobrestock"
            >
              <template #item.name="{ item }">
                <div class="d-flex flex-column py-2">
                  <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" style="max-inline-size: 250px;">
                    {{ item.name }}
                  </span>
                  <span class="text-super-xs text-disabled">{{ item.barcode }} | Lote: {{ item.lot_number }}</span>
                </div>
              </template>
              <template #item.expiration_date="{ item }">
                <VChip 
                  :color="item.color" 
                  size="x-small" 
                  class="font-weight-bold"
                  label
                >
                  {{ new Date(item.expiration_date).toLocaleDateString() }}
                </VChip>
              </template>
              <template #item.stock_actual="{ item }">
                <span class="font-weight-black">{{ formatNumber(item.stock_actual) }}</span>
              </template>
              <template #item.excedente_proyectado="{ item }">
                <span :class="item.excedente_proyectado > 0 ? 'text-error font-weight-black' : ''">
                  {{ formatNumber(item.excedente_proyectado) }}
                </span>
              </template>
              <template #item.costo_excedente="{ item }">
                <span class="font-weight-black">{{ formatMoney(item.costo_excedente) }}</span>
              </template>
            </VDataTable>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </VContainer>
</template>

<style scoped>
.expiry-dashboard {
  background-color: transparent;
}

.premium-table :deep(th) {
  background-color: #fff !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.65rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
}

.premium-table :deep(td) {
  font-size: 0.7rem !important;
  height: 48px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1.2;
}

.gap-2 { gap: 8px !important; }

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

/* Efecto de cristal para fondo dark si aplica */
.v-theme--dark .bg-surface {
  background-color: rgba(47, 51, 73, 0.7) !important;
  backdrop-filter: blur(10px);
}
</style>
