<script setup lang="ts">
import axios from 'axios'
import { onMounted, ref, computed } from 'vue'
import { useTheme } from 'vuetify'

const vuetifyTheme = useTheme()

const conversionData = ref([])

const fetchConversion = async () => {
  try {
    const response = await axios.get('/api/dashboard/analytics-data')
    conversionData.value = response.data.conversion_summary || []
  } catch (error) {
    console.error('Error al cargar tasa de conversión:', error)
  }
}

const totalQuotations = computed(() => conversionData.value.reduce((acc, curr) => acc + curr.quotations, 0))
const totalConversions = computed(() => conversionData.value.reduce((acc, curr) => acc + curr.conversions, 0))
const conversionRate = computed(() => {
  if (totalQuotations.value === 0) return 0
  return Math.round((totalConversions.value / totalQuotations.value) * 100)
})

const series = computed(() => [
  {
    name: 'Cotizaciones',
    type: 'column',
    data: conversionData.value.map(item => item.quotations),
  },
  {
    name: 'Pedidos Reales',
    type: 'line',
    data: conversionData.value.map(item => item.conversions),
  },
])

const chartOptions = computed(() => {
  const headingColor = 'rgba(var(--v-theme-on-background), var(--v-high-emphasis-opacity))'
  const labelColor = 'rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity))'
  const borderColor = 'rgba(var(--v-border-color), var(--v-border-opacity))'
  const currentTheme = vuetifyTheme.current.value.colors

  return {
    chart: {
      type: 'line',
      stacked: false,
      parentHeightOffset: 0,
      toolbar: { show: false },
    },
    markers: {
      size: 4,
      colors: '#fff',
      strokeColors: currentTheme.primary,
      hover: { size: 6 },
    },
    stroke: {
      curve: 'smooth',
      width: [0, 3],
      lineCap: 'round',
    },
    legend: {
      show: true,
      position: 'bottom',
      fontSize: '13px',
      labels: { colors: headingColor },
    },
    grid: {
      strokeDashArray: 8,
      borderColor,
    },
    colors: [currentTheme.warning, currentTheme.primary],
    plotOptions: {
      bar: {
        columnWidth: '30%',
        borderRadius: 4,
      },
    },
    dataLabels: { enabled: false },
    xaxis: {
      categories: conversionData.value.map(item => item.label),
      labels: {
        rotate: -45,
        style: { colors: labelColor, fontSize: '11px' },
      },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: {
      tickAmount: 4,
      labels: {
        style: { colors: labelColor, fontSize: '11px' },
        formatter: (val) => Math.round(val),
      },
    },
    tooltip: { enabled: true },
  }
})

onMounted(fetchConversion)
</script>

<template>
  <VCard>
    <VCardItem
      title="Tasa de Conversión"
      :subtitle="`Efectividad del mes: ${conversionRate}%`"
    >
      <template #append>
        <div class="text-caption text-disabled text-uppercase">
          {{ new Date().toLocaleString('es-ES', { month: 'long' }) }}
        </div>
      </template>
    </VCardItem>

    <VCardText>
      <div class="d-flex align-center justify-space-between mb-4 px-2">
        <div class="d-flex flex-column">
          <div class="text-h4 font-weight-bold">{{ totalQuotations }}</div>
          <div class="text-caption">Cotizaciones</div>
        </div>
        <VDivider vertical class="mx-4" />
        <div class="d-flex flex-column">
          <div class="text-h4 font-weight-bold text-primary">{{ totalConversions }}</div>
          <div class="text-caption">Ventas Reales</div>
        </div>
        <VSpacer />
        <VChip color="success" variant="tonal" label size="small">
          Tasa: {{ conversionRate }}%
        </VChip>
      </div>

      <VueApexCharts
        id="shipment-statistics"
        type="line"
        height="320"
        :options="chartOptions"
        :series="series"
      />
    </VCardText>
  </VCard>
</template>

<style lang="scss">
@use "@core-scss/template/libs/apex-chart.scss";
</style>
