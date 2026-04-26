<script setup>
import { useExpiryStore } from '@/stores/expiry-store'
import { storeToRefs } from 'pinia'
import { onMounted, ref, computed } from 'vue'
import axios from '@axios'

const expiryStore = useExpiryStore()
const { dashboardData, loading, filters } = storeToRefs(expiryStore)

const laboratories = ref([])
const categories = ref([])

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
})

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
      return item ? parseFloat(item.total_value) : 0
    })
  }))

  return {
    series,
    options: {
      chart: { type: 'bar', stacked: true, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
      plotOptions: { bar: { horizontal: false, borderRadius: 4 } },
      xaxis: { categories: months, labels: { style: { colors: '#a3a3a3' } } },
      yaxis: { labels: { formatter: (val) => `$${val.toFixed(0)}`, style: { colors: '#a3a3a3' } } },
      legend: { position: 'top', labels: { colors: '#a3a3a3' } },
      dataLabels: { enabled: false },
      colors: ['#7367f0', '#28c76f', '#ea5455', '#ff9f43', '#00cfe8'],
      grid: { borderColor: 'rgba(144, 164, 174, 0.1)' }
    }
  }
})

// 2. Annual Trend (Lines)
const annualTrendConfig = computed(() => {
  const years = dashboardData.value.annual_trend.map(i => i.year)
  const values = dashboardData.value.annual_trend.map(i => parseFloat(i.total_value))

  return {
    series: [{ name: 'Valor Inventario', data: values }],
    options: {
      chart: { type: 'line', toolbar: { show: false }, zoom: { enabled: false } },
      stroke: { curve: 'smooth', width: 3 },
      xaxis: { categories: years, labels: { style: { colors: '#a3a3a3' } } },
      yaxis: { labels: { formatter: (val) => `$${val.toFixed(0)}`, style: { colors: '#a3a3a3' } } },
      colors: ['#7367f0'],
      markers: { size: 4 },
      grid: { borderColor: 'rgba(144, 164, 174, 0.1)' }
    }
  }
})

// 3. Risk Matrix (Scatter Plot)
const scatterChartConfig = computed(() => {
  const data = dashboardData.value.risk_inventory.map(item => ({
    x: item.days_to_expiry,
    y: parseFloat(item.daily_sales_velocity).toFixed(2),
    name: item.name
  }))

  return {
    series: [{ name: 'Productos en Riesgo', data }],
    options: {
      chart: { type: 'scatter', zoom: { type: 'xy' }, toolbar: { show: false } },
      xaxis: { title: { text: 'Días para Vencimiento', style: { color: '#a3a3a3' } }, labels: { style: { colors: '#a3a3a3' } } },
      yaxis: { title: { text: 'Velocidad Venta Diaria', style: { color: '#a3a3a3' } }, labels: { style: { colors: '#a3a3a3' } } },
      tooltip: {
        custom: function({ series, seriesIndex, dataPointIndex, w }) {
          const item = w.config.series[seriesIndex].data[dataPointIndex]
          return '<div class="pa-2 bg-surface border rounded shadow-sm">' +
            '<strong>' + item.name + '</strong><br>' +
            'Días: ' + item.x + '<br>' +
            'Venta: ' + item.y + '/día' +
            '</div>'
        }
      },
      colors: ['#ea5455'],
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
    <!-- Header/Filters -->
    <VCard class="mb-6 rounded-lg border shadow-sm bg-surface overflow-visible">
      <VCardText class="pa-4">
        <VRow align="center" dense>
          <VCol cols="12" md="3">
            <h1 class="text-h4 font-weight-black d-flex align-center">
              <VIcon icon="tabler-calendar-time" class="me-2 text-primary" size="32" />
              BI Caducidad
            </h1>
            <p class="text-caption text-disabled mb-0">Control táctico de vencimientos y overstock</p>
          </VCol>
          <VCol cols="12" md="3">
            <AppAutocomplete
              v-model="filters.laboratory_id"
              :items="laboratories"
              item-title="name"
              item-value="id"
              placeholder="Laboratorio/Proveedor"
              clearable
              density="compact"
              hide-details
              @update:model-value="expiryStore.fetchDashboardData()"
              prepend-inner-icon="tabler-flask"
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppSelect
              v-model="filters.category_id"
              :items="categories"
              item-title="name"
              item-value="id"
              placeholder="Categoría"
              clearable
              density="compact"
              hide-details
              @update:model-value="expiryStore.fetchDashboardData()"
              prepend-inner-icon="tabler-category"
            />
          </VCol>
          <VCol cols="12" md="3" class="d-flex justify-end gap-2">
            <VBtn 
              color="primary" 
              variant="flat" 
              prepend-icon="tabler-refresh" 
              :loading="loading"
              @click="expiryStore.fetchDashboardData()"
            >
              Cargar
            </VBtn>
            <VBtn 
              color="success" 
              variant="tonal" 
              prepend-icon="tabler-download"
              @click="handleExport"
            >
              Exportar
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Row 1: KPI Cards -->
    <VRow class="mb-4">
      <VCol cols="12" md="3" v-for="(kpi, idx) in [
        { title: 'Unidades Vencidas (Mes)', value: dashboardData.kpis.total_units_expired_month, icon: 'tabler-package-off', color: 'error', desc: 'Perdidas este mes' },
        { title: 'Costo de Merma (Mes)', value: formatMoney(dashboardData.kpis.total_cost_merma_month), icon: 'tabler-currency-dollar-off', color: 'error', desc: 'Impacto financiero real' },
        { title: 'Stock en Riesgo (<6m)', value: formatMoney(dashboardData.horizon.reduce((a, b) => a + parseFloat(b.total_value), 0)), icon: 'tabler-alert-triangle', color: 'warning', desc: 'Fondo de vencimiento próximo' },
        { title: 'Excedente Proyectado', value: formatMoney(dashboardData.overstock.reduce((a, b) => a + b.costo_excedente, 0)), icon: 'tabler-chart-bar-off', color: 'info', desc: 'Lo que no se vende a vencimiento' }
      ]" :key="idx">
        <VCard class="rounded-lg border shadow-sm">
          <VCardText class="pa-4 d-flex align-center">
            <VAvatar :color="kpi.color" variant="tonal" size="48" rounded="lg" class="me-4 font-weight-bold">
              <VIcon :icon="kpi.icon" size="24" />
            </VAvatar>
            <div>
              <p class="text-caption text-disabled mb-0 font-weight-bold">{{ kpi.title }}</p>
              <h3 class="text-h5 font-weight-black">{{ kpi.value }}</h3>
              <p class="text-super-xs text-medium-emphasis mb-0 mt-1">{{ kpi.desc }}</p>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Row 2: Charts (Horizon & Trend) -->
    <VRow class="mb-6">
      <VCol cols="12" md="8">
        <VCard class="rounded-lg border shadow-sm h-full">
          <VCardItem>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-chart-bar-stacked" class="me-2 text-primary" />
              Horizonte de Vencimientos (Próximos 6 Meses)
            </VCardTitle>
          </VCardItem>
          <VCardText>
            <div v-if="loading" class="d-flex justify-center align-center h-full min-h-[300px]">
              <VProgressCircular indeterminate color="primary" />
            </div>
            <VueApexCharts
              v-else
              height="350"
              :options="horizonChartConfig.options"
              :series="horizonChartConfig.series"
            />
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard class="rounded-lg border shadow-sm h-full">
          <VCardItem>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-trending-up" class="me-2 text-primary" />
              Tendencia de Vencimiento Anual
            </VCardTitle>
          </VCardItem>
          <VCardText>
            <div v-if="loading" class="d-flex justify-center align-center h-full min-h-[300px]">
              <VProgressCircular indeterminate color="primary" />
            </div>
            <VueApexCharts
              v-else
              height="350"
              :options="annualTrendConfig.options"
              :series="annualTrendConfig.series"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Row 3: Action & Risk (Scatter & Table) -->
    <VRow>
      <VCol cols="12" md="5">
        <VCard class="rounded-lg border shadow-sm h-full">
          <VCardItem>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-point" class="me-2 text-error" />
              Matriz de Riesgo: Rotación vs Vencimiento
            </VCardTitle>
            <template #append>
              <VTooltip text="Inferior izquierdo: Riesgo Crítico (Pocos días y baja venta)">
                <template #activator="{ props }">
                  <VIcon v-bind="props" icon="tabler-help" size="20" class="text-disabled" />
                </template>
              </VTooltip>
            </template>
          </VCardItem>
          <VCardText>
            <div v-if="loading" class="d-flex justify-center align-center h-full min-h-[350px]">
              <VProgressCircular indeterminate color="primary" />
            </div>
            <VueApexCharts
              v-else
              height="350"
              :options="scatterChartConfig.options"
              :series="scatterChartConfig.series"
            />
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="7">
        <VCard class="rounded-lg border shadow-sm h-full">
          <VCardItem>
            <VCardTitle class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <VIcon icon="tabler-alert-square" class="me-2 text-warning" />
                Alerta de Sobrestock Proyectado
              </div>
              <VChip color="error" variant="tonal" size="x-small" class="font-weight-black">ALTA PRIORIDAD</VChip>
            </VCardTitle>
          </VCardItem>
          <VDivider class="opacity-10" />
          <VCardText class="pa-0">
             <VDataTable
              :headers="overstockHeaders"
              :items="dashboardData.overstock"
              :loading="loading"
              class="premium-table density-compact"
              height="400px"
              fixed-header
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

/* Efecto de cristal para fondo dark si aplica */
.v-theme--dark .bg-surface {
  background-color: rgba(47, 51, 73, 0.7) !important;
  backdrop-filter: blur(10px);
}
</style>
