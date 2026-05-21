<script setup lang="ts">
import { useTheme } from 'vuetify'
import axios from 'axios'
import { onMounted, ref, computed } from 'vue'

const vuetifyTheme = useTheme()

const averageDailySales = ref(0)
const historicalAverages = ref([])

const fetchAnalytics = async () => {
  try {
    const response = await axios.get('/api/dashboard/analytics-data')
    averageDailySales.value = response.data.average_daily_sales
    historicalAverages.value = response.data.historical_averages
  } catch (error) {
    console.error('Error al cargar datos de promedio de ventas:', error)
  }
}

const series = computed(() => [
  {
    name: 'Promedio USD',
    data: historicalAverages.value.map(item => item.average),
  },
])

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors

  return {
    chart: {
      type: 'area',
      toolbar: { show: false },
    },
    markers: {
      size: 4,
      colors: [currentTheme.success],
      strokeColors: '#fff',
      strokeWidth: 2,
      hover: { size: 6 }
    },
    grid: { 
      show: true,
      borderColor: `rgba(var(--v-border-color), var(--v-border-opacity))`,
      strokeDashArray: 5,
      xaxis: { lines: { show: true } },
      yaxis: { lines: { show: false } },
      padding: { top: 0, bottom: 0, left: 10, right: 10 }
    },
    colors: [currentTheme.success],
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 0.8,
        opacityFrom: 0.6,
        opacityTo: 0.1,
      },
    },
    dataLabels: { enabled: false },
    stroke: {
      width: 3,
      curve: 'smooth',
    },
    xaxis: {
      categories: historicalAverages.value.map(item => item.month),
      labels: { 
        show: true,
        style: {
          colors: `rgba(var(--v-theme-on-surface), var(--v-disabled-opacity))`,
          fontSize: '12px'
        }
      },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: { 
      show: true,
      labels: {
        formatter: (val) => `$${Math.round(val)}`,
        style: {
          colors: `rgba(var(--v-theme-on-surface), var(--v-disabled-opacity))`,
          fontSize: '12px'
        }
      }
    },
    tooltip: {
      enabled: true,
      x: { show: true }
    }
  }
})

onMounted(fetchAnalytics)

const formatCurrencyUSD = (value) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(value)
}
</script>

<template>
  <VCard>
    <VCardText class="pb-2">
      <div class="mb-2">
        <h6 class="text-h6 mb-0 text-no-wrap">
          Promedio de Venta Diaria
        </h6>
        <p class="mb-0 text-caption text-medium-emphasis">
          Mes Actual: <span class="font-weight-bold text-success">{{ formatCurrencyUSD(averageDailySales) }}</span>
        </p>
      </div>

      <VueApexCharts
        :options="chartOptions"
        :series="series"
        :height="135"
      />
    </VCardText>
  </VCard>
</template>
