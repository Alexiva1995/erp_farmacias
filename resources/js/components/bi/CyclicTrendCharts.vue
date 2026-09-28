<script setup>
import { computed } from 'vue'
import { useTheme } from 'vuetify'
import VueApexCharts from 'vue3-apexcharts'
import { formatCurrency } from '@/utils/currencyFormatter'

const props = defineProps({
  dashboardData: {
    type: Object,
    required: true,
  },
  chartKey: {
    type: Number,
    default: 0,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['select-category', 'select-product'])

const theme = useTheme()
const isDark = computed(() => theme.current.value.dark)
const currentColors = computed(() => theme.current.value.colors)

// Colores de texto y bordes según tema
const labelColor = computed(() => (isDark.value ? '#AAB3DE' : '#616161'))
const gridBorderColor = computed(() => (isDark.value ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)'))

// 1. Tendencia Histórica
const trendOptions = computed(() => ({
  chart: {
    type: 'line',
    toolbar: { show: false },
    zoom: { enabled: false },
    fontFamily: 'inherit',
    background: 'transparent',
  },
  colors: [currentColors.value.error || '#FF4C51', currentColors.value.success || '#28C76F'],
  stroke: { curve: 'smooth', width: 3 },
  markers: { size: 4 },
  xaxis: {
    categories: props.dashboardData?.trends?.categories || [],
    labels: { style: { colors: labelColor.value, fontSize: '11px' } },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: {
    labels: { style: { colors: labelColor.value } },
  },
  legend: {
    position: 'top',
    horizontalAlign: 'right',
    labels: { colors: labelColor.value },
  },
  grid: { borderColor: gridBorderColor.value },
  tooltip: { theme: isDark.value ? 'dark' : 'light' },
}))

// 2. Impacto Financiero
const impactOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    fontFamily: 'inherit',
    background: 'transparent',
  },
  plotOptions: {
    bar: {
      colors: {
        ranges: [
          { from: -99999999, to: 0, color: currentColors.value.error || '#FF4C51' },
          { from: 0.01, to: 99999999, color: currentColors.value.success || '#28C76F' },
        ],
      },
      columnWidth: '45%',
      borderRadius: 4,
    },
  },
  xaxis: {
    categories: props.dashboardData?.trends?.categories || [],
    labels: { style: { colors: labelColor.value, fontSize: '11px' } },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: {
    labels: {
      formatter: v => formatCurrency(v),
      style: { colors: labelColor.value },
    },
  },
  grid: { borderColor: gridBorderColor.value },
  tooltip: { theme: isDark.value ? 'dark' : 'light' },
}))

// 3. Top Faltantes
const topMissingOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    fontFamily: 'inherit',
    background: 'transparent',
    events: {
      dataPointSelection: (event, chartContext, config) => {
        const selectedIndex = config.dataPointIndex
        const label = props.dashboardData?.deviations?.top_missing?.categories?.[selectedIndex]
        if (label) {
          emit('select-product', label)
        }
      },
    },
  },
  plotOptions: {
    bar: {
      horizontal: true,
      borderRadius: 4,
      barHeight: '65%',
    },
  },
  colors: [currentColors.value.error || '#FF4C51'],
  xaxis: {
    categories: props.dashboardData?.deviations?.top_missing?.categories || [],
    labels: { style: { colors: labelColor.value } },
  },
  yaxis: {
    labels: { style: { colors: labelColor.value, fontSize: '11px' } },
  },
  grid: { borderColor: gridBorderColor.value },
  tooltip: { theme: isDark.value ? 'dark' : 'light' },
}))

// 4. Top Sobrantes
const topSurplusOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    fontFamily: 'inherit',
    background: 'transparent',
    events: {
      dataPointSelection: (event, chartContext, config) => {
        const selectedIndex = config.dataPointIndex
        const label = props.dashboardData?.deviations?.top_surplus?.categories?.[selectedIndex]
        if (label) {
          emit('select-product', label)
        }
      },
    },
  },
  plotOptions: {
    bar: {
      horizontal: true,
      borderRadius: 4,
      barHeight: '65%',
    },
  },
  colors: [currentColors.value.success || '#28C76F'],
  xaxis: {
    categories: props.dashboardData?.deviations?.top_surplus?.categories || [],
    labels: { style: { colors: labelColor.value } },
  },
  yaxis: {
    labels: { style: { colors: labelColor.value, fontSize: '11px' } },
  },
  grid: { borderColor: gridBorderColor.value },
  tooltip: { theme: isDark.value ? 'dark' : 'light' },
}))

// 5. Desviación por Categoría
const categoryOptions = computed(() => ({
  chart: {
    events: {
      dataPointSelection: (event, chartContext, config) => {
        const selectedIndex = config.dataPointIndex
        const label = props.dashboardData?.deviations?.categories?.labels?.[selectedIndex]
        if (label) {
          emit('select-category', label)
        }
      },
    },
  },
  labels: props.dashboardData?.deviations?.categories?.labels || [],
  colors: [
    currentColors.value.primary || '#E20074',
    currentColors.value.secondary || '#7A0099',
    currentColors.value.info || '#00BAD1',
    currentColors.value.warning || '#FF9F43',
    currentColors.value.success || '#28C76F',
  ],
  legend: {
    position: 'bottom',
    labels: { colors: labelColor.value },
  },
  dataLabels: {
    enabled: true,
    formatter: val => `${val.toFixed(1)}%`,
  },
  stroke: { show: false },
  tooltip: { theme: isDark.value ? 'dark' : 'light' },
}))

const hasMissingData = computed(() => (props.dashboardData?.deviations?.top_missing?.categories?.length || 0) > 0)
const hasSurplusData = computed(() => (props.dashboardData?.deviations?.top_surplus?.categories?.length || 0) > 0)
const hasCategoryData = computed(() => {
  const series = props.dashboardData?.deviations?.categories?.series || []
  return series.length > 0 && series.reduce((a, b) => a + b, 0) > 0
})
</script>

<template>
  <div>
    <!-- Tendencias Históricas e Impacto Financiero -->
    <VRow class="mb-6">
      <VCol cols="12" md="7">
        <VCard class="rounded-lg border shadow-sm h-100">
          <VCardItem>
            <VCardTitle class="d-flex align-center text-subtitle-1 font-weight-bold">
              <VIcon icon="tabler-chart-line" color="primary" class="me-2" />
              Variación Histórica de Inventario
            </VCardTitle>
          </VCardItem>
          <VCardText style="min-height: 320px;">
            <div v-if="loading" class="d-flex align-center justify-center h-100 py-10">
              <VSkeletonLoader type="image" width="100%" height="280" />
            </div>
            <VueApexCharts
              v-else
              :key="`trend-${chartKey}`"
              height="300"
              :options="trendOptions"
              :series="dashboardData.trends?.series || []"
            />
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="5">
        <VCard class="rounded-lg border shadow-sm h-100">
          <VCardItem>
            <VCardTitle class="d-flex align-center text-subtitle-1 font-weight-bold">
              <VIcon icon="tabler-chart-bar" color="success" class="me-2" />
              Impacto Financiero ($)
            </VCardTitle>
          </VCardItem>
          <VCardText style="min-height: 320px;">
            <div v-if="loading" class="d-flex align-center justify-center h-100 py-10">
              <VSkeletonLoader type="image" width="100%" height="280" />
            </div>
            <VueApexCharts
              v-else
              :key="`impact-${chartKey}`"
              height="300"
              :options="impactOptions"
              :series="dashboardData.trends?.financial_series || []"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Top Desviaciones y Categorías -->
    <VRow class="mb-6">
      <VCol cols="12" md="4">
        <VCard class="rounded-lg border shadow-sm h-100">
          <VCardItem>
            <VCardTitle class="d-flex align-center text-subtitle-1 font-weight-bold text-error">
              <VIcon icon="tabler-arrow-down-circle" class="me-2" />
              Mayores Faltantes (Top 10)
            </VCardTitle>
          </VCardItem>
          <VCardText style="min-height: 370px;">
            <div v-if="loading" class="d-flex align-center justify-center h-100 py-10">
              <VSkeletonLoader type="image" width="100%" height="320" />
            </div>
            <VueApexCharts
              v-else-if="hasMissingData"
              :key="`missing-${chartKey}`"
              height="350"
              :options="topMissingOptions"
              :series="dashboardData.deviations?.top_missing?.series || []"
            />
            <div v-else class="d-flex flex-column align-center justify-center py-12 text-center h-100 min-height-300">
              <VIcon icon="tabler-circle-check" size="48" color="success" class="mb-2 opacity-50" />
              <span class="text-caption font-weight-medium text-disabled px-4">
                No hay unidades faltantes en este periodo
              </span>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="4">
        <VCard class="rounded-lg border shadow-sm h-100">
          <VCardItem>
            <VCardTitle class="d-flex align-center text-subtitle-1 font-weight-bold text-success">
              <VIcon icon="tabler-arrow-up-circle" class="me-2" />
              Mayores Sobrantes (Top 10)
            </VCardTitle>
          </VCardItem>
          <VCardText style="min-height: 370px;">
            <div v-if="loading" class="d-flex align-center justify-center h-100 py-10">
              <VSkeletonLoader type="image" width="100%" height="320" />
            </div>
            <VueApexCharts
              v-else-if="hasSurplusData"
              :key="`surplus-${chartKey}`"
              height="350"
              :options="topSurplusOptions"
              :series="dashboardData.deviations?.top_surplus?.series || []"
            />
            <div v-else class="d-flex flex-column align-center justify-center py-12 text-center h-100 min-height-300">
              <VIcon icon="tabler-circle-check" size="48" color="success" class="mb-2 opacity-50" />
              <span class="text-caption font-weight-medium text-disabled px-4">
                No hay unidades sobrantes en este periodo
              </span>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="4">
        <VCard class="rounded-lg border shadow-sm h-100">
          <VCardItem>
            <VCardTitle class="d-flex align-center text-subtitle-1 font-weight-bold text-primary">
              <VIcon icon="tabler-category" class="me-2" />
              Desviación por Categoría
            </VCardTitle>
          </VCardItem>
          <VCardText class="d-flex justify-center align-center" style="min-height: 370px;">
            <div v-if="loading" class="d-flex align-center justify-center w-100 py-10">
              <VSkeletonLoader type="image" width="100%" height="320" />
            </div>
            <VueApexCharts
              v-else-if="hasCategoryData"
              :key="`category-${chartKey}`"
              width="100%"
              type="donut"
              :options="categoryOptions"
              :series="dashboardData.deviations?.categories?.series || []"
            />
            <div v-else class="d-flex flex-column align-center justify-center text-center w-100">
              <VIcon icon="tabler-discount-check" size="48" color="primary" class="mb-2 opacity-50" />
              <span class="text-caption font-weight-medium text-disabled px-4">
                Sin desviaciones registradas por categoría
              </span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
