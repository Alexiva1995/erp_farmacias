<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import axios from '@axios'
import VueApexCharts from 'vue3-apexcharts'
import { formatCurrency } from '@/utils/currencyFormatter'
import AppTextField from '@core/components/app-form-elements/AppTextField.vue'

const dashboardData = ref(null)
const loading = ref(false)
const filterDate = ref(new Date().toISOString().substr(0, 7)) // Mes actual

const fetchDashboardData = async () => {
  loading.value = true
  try {
    const year = filterDate.value.split('-')[0]
    const month = filterDate.value.split('-')[1]
    const startDate = `${year}-${month}-01`
    const endDate = new Date(year, month, 0).toISOString().split('T')[0]

    const response = await axios.get('/bi/inventory-cyclic', {
      params: { start_date: startDate, end_date: endDate }
    })
    dashboardData.value = response.data
  } catch (error) {
    console.error('Error fetching inventory dashboard:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchDashboardData()
})

watch(filterDate, () => {
  fetchDashboardData()
})

// --- OPCIONES DE GRÁFICOS ---

// 1. Tendencia Histórica (Líneas Faltantes vs Sobrantes)
const trendOptions = computed(() => ({
  chart: { type: 'line', toolbar: { show: false }, zoom: { enabled: false } },
  colors: ['#EA5455', '#28C76F'],
  stroke: { curve: 'smooth', width: 3 },
  markers: { size: 4 },
  xaxis: { categories: dashboardData.value?.trends?.categories || [] },
  yaxis: { title: { text: 'Unidades' } },
  legend: { position: 'top', horizontalAlign: 'right' },
  tooltip: { theme: 'dark' }
}))

// 2. Impacto Financiero (Barras)
const impactOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  plotOptions: {
    bar: {
      colors: {
        ranges: [{ from: -9999999, to: 0, color: '#EA5455' }, { from: 0.1, to: 9999999, color: '#28C76F' }]
      },
      columnWidth: '50%',
    }
  },
  xaxis: { categories: dashboardData.value?.trends?.categories || [] },
  yaxis: { title: { text: 'USD ($)' }, labels: { formatter: (v) => formatCurrency(v) } },
  tooltip: { theme: 'dark' }
}))

// 3. Desviación por Categoría (Dona)
const categoryOptions = computed(() => ({
  labels: dashboardData.value?.deviations?.categories?.labels || [],
  colors: ['#7367F0', '#28C76F', '#FF9F43', '#EA5455', '#00CFE8'],
  legend: { position: 'bottom' },
  dataLabels: { enabled: true, formatter: (val) => `${val.toFixed(1)}%` },
  tooltip: { theme: 'dark' }
}))

// 4. Top 10 Faltantes (Barras Horizontales)
const topMissingOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '70%' } },
  colors: ['#EA5455'],
  xaxis: { categories: dashboardData.value?.deviations?.top_missing?.categories || [] },
  tooltip: { theme: 'dark' }
}))

// 5. Top 10 Sobrantes (Barras Horizontales)
const topSurplusOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '70%' } },
  colors: ['#28C76F'],
  xaxis: { categories: dashboardData.value?.deviations?.top_surplus?.categories || [] },
  tooltip: { theme: 'dark' }
}))
</script>

<template>
  <VContainer fluid class="analytics-inventory">
    <!-- Header / Filtros -->
    <VRow align="center" class="mb-6">
      <VCol>
        <h2 class="text-h4 font-weight-black mb-1">Análisis de Inventario Cíclico</h2>
        <p class="text-body-2 text-muted">Auditoría de Precisión Física vs Stock Teórico</p>
      </VCol>
      <VCol cols="auto">
        <div style="width: 200px;">
          <AppTextField
            v-model="filterDate"
            type="month"
            label="Mes de Análisis"
            density="compact"
            hide-details
          />
        </div>
      </VCol>
    </VRow>

    <div v-if="loading" class="d-flex justify-center align-center" style="height: 400px;">
      <VProgressCircular indeterminate color="primary" size="64" />
    </div>

    <div v-else-if="dashboardData">
      <!-- Row 1: KPIs -->
      <VRow class="mb-6">
        <VCol cols="12" sm="6" md="2.4">
          <VCard class="rounded-xl border shadow-sm h-100">
            <VCardText class="text-center py-6">
              <div class="text-caption font-weight-bold text-uppercase text-muted mb-2">ERI (Precisión)</div>
              <div class="text-h3 font-weight-black" :class="dashboardData.kpis.eri > 95 ? 'text-success' : 'text-warning'">
                {{ dashboardData.kpis.eri }}%
              </div>
              <VProgressLinear
                :model-value="dashboardData.kpis.eri"
                :color="dashboardData.kpis.eri > 95 ? 'success' : 'warning'"
                rounded
                height="6"
                class="mt-4"
              />
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="2.4">
          <VCard class="rounded-xl border shadow-sm h-100">
            <VCardText class="text-center py-6">
              <div class="text-caption font-weight-bold text-uppercase text-muted mb-2">Pérdida Neta ($)</div>
              <div class="text-h3 font-weight-black" :class="dashboardData.kpis.net_loss > 0 ? 'text-error' : 'text-success'">
                {{ formatCurrency(dashboardData.kpis.net_loss) }}
              </div>
              <div class="text-caption mt-2 italic text-muted">Ajuste de valorización</div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="2.4">
          <VCard class="rounded-xl border shadow-sm h-100">
            <VCardText class="text-center py-6">
              <div class="text-caption font-weight-bold text-uppercase text-muted mb-2">Tasa de Error</div>
              <div class="text-h3 font-weight-black text-warning">
                {{ dashboardData.kpis.error_rate }}%
              </div>
              <div class="text-caption mt-2 text-muted">SKUs con discrepancia</div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="2.4">
          <VCard class="rounded-xl border shadow-sm h-100 bg-light-error">
            <VCardText class="text-center py-6">
              <div class="text-caption font-weight-bold text-uppercase text-error mb-2">Unid. Faltantes</div>
              <div class="text-h3 font-weight-black text-error">
                {{ dashboardData.kpis.total_missing_units }}
              </div>
              <VIcon icon="tabler-trending-down" color="error" class="mt-2" />
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="2.4">
          <VCard class="rounded-xl border shadow-sm h-100 bg-light-success">
            <VCardText class="text-center py-6">
              <div class="text-caption font-weight-bold text-uppercase text-success mb-2">Unid. Sobrantes</div>
              <div class="text-h3 font-weight-black text-success">
                {{ dashboardData.kpis.total_surplus_units }}
              </div>
              <VIcon icon="tabler-trending-up" color="success" class="mt-2" />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Row 2: Tendencias Históricas -->
      <VRow class="mb-6">
        <VCol cols="12" md="7">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem class="pb-0">
              <VCardTitle class="text-subtitle-1 font-weight-black">Variación Histórica de Inventario</VCardTitle>
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
            <VCardItem class="pb-0">
              <VCardTitle class="text-subtitle-1 font-weight-black">Impacto Financiero ($)</VCardTitle>
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
            <VCardItem class="pb-0">
              <VCardTitle class="text-subtitle-1 font-weight-black text-error">Mayores Faltantes (Top 10)</VCardTitle>
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
            <VCardItem class="pb-0">
              <VCardTitle class="text-subtitle-1 font-weight-black text-success">Mayores Sobrantes (Top 10)</VCardTitle>
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
            <VCardItem class="pb-0">
              <VCardTitle class="text-subtitle-1 font-weight-black text-primary">Desviación por Categoría</VCardTitle>
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
          <VCard class="rounded-lg border shadow-sm">
            <VCardItem class="py-4 border-b bg-light-primary">
              <VCardTitle class="d-flex align-center text-subtitle-1 font-weight-black text-uppercase">
                <VIcon icon="tabler-arrows-left-right" class="me-2 text-primary" size="24" />
                Cuadrante de Cruce de Códigos (Posibles Sustituciones)
              </VCardTitle>
              <VCardSubtitle>Detección automática de errores de despacho vs pérdidas reales</VCardSubtitle>
            </VCardItem>
            <VTable class="text-no-wrap">
              <thead>
                <tr>
                  <th class="text-uppercase text-[11px] font-weight-black">Categoría</th>
                  <th class="text-uppercase text-[11px] font-weight-black">Producto A (Faltante)</th>
                  <th class="text-uppercase text-[11px] font-weight-black text-center">Cant. A</th>
                  <th class="text-icon text-center">-</th>
                  <th class="text-uppercase text-[11px] font-weight-black">Producto B (Sobrante)</th>
                  <th class="text-uppercase text-[11px] font-weight-black text-center">Cant. B</th>
                  <th class="text-uppercase text-[11px] font-weight-black text-right">Confianza</th>
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

.analytics-inventory h2 {
  letter-spacing: -1px;
}

.analytics-table th {
  background-color: #f8f9fa !important;
}
</style>
