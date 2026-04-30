<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import axios from '@axios'
import VueApexCharts from 'vue3-apexcharts'
import { formatCurrency } from '@/utils/currencyFormatter'

const dashboardData = ref(null)
const loading = ref(false)

const filters = ref({
  startDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substr(0, 10),
  endDate: new Date().toISOString().substr(0, 10),
  search: ''
})

const fetchDashboardData = async () => {
  loading.value = true
  try {
    const response = await axios.get('/bi/inventory-cyclic', {
      params: { 
        start_date: filters.value.startDate, 
        end_date: filters.value.endDate,
        search: filters.value.search
      }
    })
    dashboardData.value = response.data
  } catch (error) {
    console.error('Error fetching inventory dashboard:', error)
  } finally {
    loading.value = false
  }
}

const handleClearFilters = () => {
  filters.value.startDate = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substr(0, 10)
  filters.value.endDate = new Date().toISOString().substr(0, 10)
  filters.value.search = ''
  fetchDashboardData()
}

onMounted(() => {
  fetchDashboardData()
})

// --- OPCIONES DE GRÁFICOS ---

// 1. Tendencia Histórica (Líneas Faltantes vs Sobrantes)
const trendOptions = computed(() => ({
  chart: { type: 'line', toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'Inter, sans-serif' },
  colors: ['#EA5455', '#28C76F'],
  stroke: { curve: 'smooth', width: 3 },
  markers: { size: 4 },
  xaxis: { 
    categories: dashboardData.value?.trends?.categories || [],
    labels: { style: { colors: '#a3a3a3', fontSize: '10px' } }
  },
  yaxis: { 
    labels: { style: { colors: '#a3a3a3' } }
  },
  legend: { position: 'top', horizontalAlign: 'right', labels: { colors: '#a3a3a3' } },
  grid: { borderColor: 'rgba(144, 164, 174, 0.1)' },
  tooltip: { theme: 'dark' }
}))

// 2. Impacto Financiero (Barras)
const impactOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
  plotOptions: {
    bar: {
      colors: {
        ranges: [{ from: -9999999, to: 0, color: '#EA5455' }, { from: 0.1, to: 9999999, color: '#28C76F' }]
      },
      columnWidth: '50%',
      borderRadius: 4
    }
  },
  xaxis: { 
    categories: dashboardData.value?.trends?.categories || [],
    labels: { style: { colors: '#a3a3a3', fontSize: '10px' } }
  },
  yaxis: { 
    labels: { 
      formatter: (v) => formatCurrency(v),
      style: { colors: '#a3a3a3' }
    } 
  },
  grid: { borderColor: 'rgba(144, 164, 174, 0.1)' },
  tooltip: { theme: 'dark' }
}))

// 3. Desviación por Categoría (Dona)
const categoryOptions = computed(() => ({
  labels: dashboardData.value?.deviations?.categories?.labels || [],
  colors: ['#7367F0', '#28C76F', '#FF9F43', '#EA5455', '#00CFE8'],
  legend: { position: 'bottom', labels: { colors: '#a3a3a3' } },
  dataLabels: { enabled: true, formatter: (val) => `${val.toFixed(1)}%` },
  stroke: { show: false },
  tooltip: { theme: 'dark' }
}))

// 4. Top 10 Faltantes (Barras Horizontales)
const topMissingOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
  plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '70%' } },
  colors: ['#EA5455'],
  xaxis: { 
    categories: dashboardData.value?.deviations?.top_missing?.categories || [],
    labels: { style: { colors: '#a3a3a3' } }
  },
  yaxis: { labels: { style: { colors: '#a3a3a3', fontSize: '10px' } } },
  grid: { borderColor: 'rgba(144, 164, 174, 0.1)' },
  tooltip: { theme: 'dark' }
}))

// 5. Top 10 Sobrantes (Barras Horizontales)
const topSurplusOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
  plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '70%' } },
  colors: ['#28C76F'],
  xaxis: { 
    categories: dashboardData.value?.deviations?.top_surplus?.categories || [],
    labels: { style: { colors: '#a3a3a3' } }
  },
  yaxis: { labels: { style: { colors: '#a3a3a3', fontSize: '10px' } } },
  grid: { borderColor: 'rgba(144, 164, 174, 0.1)' },
  tooltip: { theme: 'dark' }
}))
</script>

<template>
  <VContainer fluid class="analytics-inventory pa-0">
    <!-- Filtros Estandarizados Estilo Premium -->
    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardText class="pa-4">
        <VRow align="center" no-gutters class="gap-2">
          <VCol cols="12" md="4">
            <AppTextField
              v-model="filters.search"
              placeholder="Buscar producto..."
              prepend-inner-icon="tabler-search"
              clearable
              density="compact"
              hide-details
              class="premium-input-compact"
            />
          </VCol>

          <VCol cols="12" md="2.5">
            <AppDateTimePicker
              v-model="filters.startDate"
              placeholder="Fecha Inicio"
              density="compact"
              hide-details
              class="premium-input-compact"
              prepend-inner-icon="tabler-calendar"
            />
          </VCol>

          <VCol cols="12" md="2.5">
            <AppDateTimePicker
              v-model="filters.endDate"
              placeholder="Fecha Fin"
              density="compact"
              hide-details
              class="premium-input-compact"
              prepend-inner-icon="tabler-calendar-check"
            />
          </VCol>

          <VSpacer />

          <div class="d-flex align-center gap-2">
            <VBtn
              icon
              variant="flat"
              color="primary"
              size="38"
              class="rounded-circle shadow-sm"
              :loading="loading"
              @click="fetchDashboardData"
            >
              <VIcon icon="tabler-player-play" size="20" />
              <VTooltip activator="parent" location="top">Aplicar Filtros</VTooltip>
            </VBtn>

            <VBtn
              icon
              variant="text"
              color="secondary"
              size="38"
              class="rounded-circle shadow-sm"
              @click="handleClearFilters"
            >
              <VIcon icon="tabler-eraser" size="20" />
              <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
            </VBtn>
          </div>
        </VRow>
      </VCardText>
    </VCard>

    <div v-if="loading" class="d-flex justify-center align-center" style="height: 400px;">
      <VProgressCircular indeterminate color="primary" size="64" />
    </div>

    <div v-else-if="dashboardData">
      <!-- Row 1: KPI Cards Diseño Horizontal -->
      <VRow class="mb-6">
        <VCol cols="12" sm="6" md="2.4" v-for="(kpi, idx) in [
          { 
            title: 'ERI (Precisión)', 
            value: (dashboardData.kpis.eri || 0) + '%', 
            icon: 'tabler-target', 
            color: (dashboardData.kpis.eri || 0) > 95 ? 'success' : 'warning',
            desc: 'Precisión física'
          },
          { 
            title: 'Pérdida Neta', 
            value: formatCurrency(dashboardData.kpis.net_loss || 0), 
            icon: 'tabler-currency-dollar', 
            color: (dashboardData.kpis.net_loss || 0) > 0 ? 'error' : 'success',
            desc: 'Ajuste de valor'
          },
          { 
            title: 'Tasa de Error', 
            value: (dashboardData.kpis.error_rate || 0) + '%', 
            icon: 'tabler-alert-circle', 
            color: 'warning',
            desc: 'SKUs discrepantes'
          },
          { 
            title: 'Unid. Faltantes', 
            value: dashboardData.kpis.total_missing_units || 0, 
            icon: 'tabler-trending-down', 
            color: 'error',
            desc: 'Stock no hallado'
          },
          { 
            title: 'Unid. Sobrantes', 
            value: dashboardData.kpis.total_surplus_units || 0, 
            icon: 'tabler-trending-up', 
            color: 'success',
            desc: 'Exceso físico'
          }
        ]" :key="idx">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardText class="pa-4 d-flex align-center">
              <VAvatar :color="kpi.color" variant="tonal" size="48" rounded="lg" class="me-4">
                <VIcon :icon="kpi.icon" size="24" />
              </VAvatar>
              <div>
                <p class="text-caption text-disabled mb-0 font-weight-bold">{{ kpi.title }}</p>
                <h3 class="text-h5 font-weight-black">{{ kpi.value }}</h3>
                <p class="text-super-xs text-disabled mb-0">{{ kpi.desc }}</p>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Row 2: Tendencias Históricas -->
      <VRow class="mb-6">
        <VCol cols="12" md="7">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem>
              <VCardTitle class="d-flex align-center">
                <VIcon icon="tabler-chart-line" class="me-2 text-primary" />
                Variación Histórica de Inventario
              </VCardTitle>
            </VCardItem>
            <VCardText>
              <VueApexCharts
                height="300"
                :options="trendOptions"
                :series="dashboardData.trends.series"
              />
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="12" md="5">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem>
              <VCardTitle class="d-flex align-center">
                <VIcon icon="tabler-chart-bar" class="me-2 text-success" />
                Impacto Financiero ($)
              </VCardTitle>
            </VCardItem>
            <VCardText>
              <VueApexCharts
                height="300"
                :options="impactOptions"
                :series="dashboardData.trends.financial_series"
              />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Row 3: Top Desviaciones y Categorías -->
      <VRow class="mb-6">
        <VCol cols="12" md="4">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem>
              <VCardTitle class="d-flex align-center text-error">
                <VIcon icon="tabler-arrow-down-circle" class="me-2" />
                Mayores Faltantes (Top 10)
              </VCardTitle>
            </VCardItem>
            <VCardText>
              <VueApexCharts
                height="350"
                :options="topMissingOptions"
                :series="dashboardData.deviations.top_missing.series"
              />
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="12" md="4">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem>
              <VCardTitle class="d-flex align-center text-success">
                <VIcon icon="tabler-arrow-up-circle" class="me-2" />
                Mayores Sobrantes (Top 10)
              </VCardTitle>
            </VCardItem>
            <VCardText>
              <VueApexCharts
                height="350"
                :options="topSurplusOptions"
                :series="dashboardData.deviations.top_surplus.series"
              />
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="12" md="4">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem>
              <VCardTitle class="d-flex align-center text-primary">
                <VIcon icon="tabler-category" class="me-2" />
                Desviación por Categoría
              </VCardTitle>
            </VCardItem>
            <VCardText class="d-flex justify-center align-center" style="height: 350px;">
              <VueApexCharts
                width="100%"
                type="donut"
                :options="categoryOptions"
                :series="dashboardData.deviations.categories.series"
              />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Row 4: Cuadrante de Cruce de Códigos -->
      <VRow>
        <VCol cols="12">
          <VCard class="rounded-lg border shadow-sm overflow-hidden">
            <VCardItem class="py-4 border-b bg-light-primary">
              <VCardTitle class="d-flex align-center text-subtitle-1 font-weight-black text-uppercase">
                <VIcon icon="tabler-arrows-left-right" class="me-2 text-primary" size="24" />
                Cuadrante de Cruce de Códigos (Posibles Sustituciones)
              </VCardTitle>
              <VCardSubtitle>Detección automática de errores de despacho vs pérdidas reales</VCardSubtitle>
            </VCardItem>
            <VTable class="premium-table density-compact">
              <thead>
                <tr>
                  <th class="text-uppercase">Categoría</th>
                  <th class="text-uppercase">Producto A (Faltante)</th>
                  <th class="text-uppercase text-center">Cant. A</th>
                  <th class="text-center">-</th>
                  <th class="text-uppercase">Producto B (Sobrante)</th>
                  <th class="text-uppercase text-center">Cant. B</th>
                  <th class="text-uppercase text-right">Confianza</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(sub, idx) in dashboardData.substitutions" :key="idx">
                  <td class="text-primary font-weight-bold">{{ sub.category }}</td>
                  <td class="text-error font-weight-medium">{{ sub.product_a }}</td>
                  <td class="text-center font-weight-black text-error">{{ sub.discrepancy_a }}</td>
                  <td class="text-center">
                    <VIcon icon="tabler-arrows-exchange-2" color="warning" size="20" />
                  </td>
                  <td class="text-success font-weight-medium">{{ sub.product_b }}</td>
                  <td class="text-center font-weight-black text-success">{{ sub.discrepancy_b }}</td>
                  <td class="text-right">
                    <VChip size="small" color="primary" label class="font-weight-black">{{ sub.confidence }}</VChip>
                  </td>
                </tr>
                <tr v-if="dashboardData.substitutions.length === 0">
                  <td colspan="7" class="text-center py-10 text-muted">
                    No se detectaron cruces de códigos directos en este periodo.
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </VContainer>
</template>

<style scoped>
.bg-light-error { background-color: rgba(234, 84, 85, 0.05) !important; }
.bg-light-success { background-color: rgba(40, 199, 111, 0.05) !important; }
.bg-light-primary { background-color: rgba(115, 103, 240, 0.05) !important; }

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

/* Efecto de cristal para fondo dark */
.v-theme--dark .bg-surface {
  background-color: rgba(47, 51, 73, 0.7) !important;
  backdrop-filter: blur(10px);
}
</style>
