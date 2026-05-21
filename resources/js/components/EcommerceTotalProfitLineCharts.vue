<script setup>
import { useTheme } from 'vuetify'
import { hexToRgb } from '@layouts/utils'
import VueApexCharts from 'vue3-apexcharts'
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const vuetifyTheme = useTheme()

// Datos reactivos de ventas
const series = ref([{
  name: 'Ventas (USD)',
  data: [0, 0, 0, 0, 0, 0]
}])

const chartCategories = ref(['', '', '', '', '', ''])
const currentSales = ref('$0.00')
const salesChangePct = ref('0.00%')
const isPositiveChange = ref(true)

// Función para formatear dinero en USD
const formatCurrencyUSD = (amount) =>
  new Intl.NumberFormat("es-US", {
    style: "currency",
    currency: "USD",
  }).format(amount)

// Obtener datos históricos de los últimos 6 meses desde el backend
const fetchSalesData = async () => {
  try {
    const months = []
    const currentYear = new Date().getFullYear()
    const currentMonthIdx = new Date().getMonth() // 0 = Enero, 4 = Mayo
    const monthsEs = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"]
    const loadedCategories = []

    // Construir los rangos de fechas mensuales para los últimos 6 meses
    for (let i = 5; i >= 0; i--) {
      let targetMonthIdx = currentMonthIdx - i
      let targetYear = currentYear
      if (targetMonthIdx < 0) {
        targetMonthIdx += 12
        targetYear -= 1
      }
      
      const start = new Date(targetYear, targetMonthIdx, 1)
      const end = new Date(targetYear, targetMonthIdx + 1, 0)
      
      months.push({
        startDate: start.toISOString().split('T')[0],
        endDate: end.toISOString().split('T')[0],
      })
      loadedCategories.push(monthsEs[targetMonthIdx])
    }

    // Peticiones asincrónicas en paralelo para obtener las ventas mensuales reales
    const promises = months.map(m => 
      axios.get('/api/dashboard/stats', {
        params: {
          start_date: m.startDate,
          end_date: m.endDate
        }
      })
    )

    const results = await Promise.all(promises)
    const salesHistory = results.map(res => res.data ? parseFloat(res.data.sales || 0) : 0.00)

    series.value = [{
      name: 'Ventas (USD)',
      data: salesHistory
    }]
    chartCategories.value = loadedCategories

    // Venta del mes actual (último mes de la serie)
    const currentMonthSales = salesHistory[5]
    currentSales.value = formatCurrencyUSD(currentMonthSales)

    // Venta del mes anterior para calcular variación
    const prevMonthSales = salesHistory[4]

    if (prevMonthSales > 0) {
      const change = ((currentMonthSales - prevMonthSales) / prevMonthSales) * 100
      isPositiveChange.value = change >= 0
      salesChangePct.value = `${change >= 0 ? '+' : ''}${change.toFixed(2)}%`
    } else if (currentMonthSales > 0) {
      isPositiveChange.value = true
      salesChangePct.value = '+100.00%'
    } else {
      isPositiveChange.value = true
      salesChangePct.value = '0.00%'
    }
  } catch (error) {
    console.error('Error fetching sales stats for chart:', error)
  }
}

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  
  return {
    chart: {
      height: 90,
      type: 'line',
      parentHeightOffset: 0,
      toolbar: { show: false },
      sparkline: { enabled: true }
    },
    grid: {
      show: false,
      padding: {
        top: 10,
        left: 5,
        right: 5,
        bottom: 5,
      },
    },
    colors: [currentTheme.info],
    stroke: { 
      width: 3,
      curve: 'smooth'
    },
    tooltip: {
      enabled: true,
      theme: vuetifyTheme.current.value.dark ? 'dark' : 'light',
      x: { 
        show: true,
        formatter: (val, opts) => {
          return chartCategories.value[opts.dataPointIndex] || ''
        }
      },
      y: {
        formatter: (val) => formatCurrencyUSD(val)
      }
    },
    xaxis: {
      categories: chartCategories.value,
      labels: { show: false },
      axisTicks: { show: false },
      axisBorder: { show: false },
    },
    yaxis: { labels: { show: false } },
    markers: {
      size: 0,
      hover: { size: 5 }
    },
    responsive: [{
      breakpoint: 960,
      options: { chart: { height: 110 } },
    }],
  }
})

onMounted(() => {
  fetchSalesData()
})
</script>

<template>
  <VCard class="h-100">
    <VCardText class="d-flex flex-column justify-space-between h-100 pb-3">
      <div>
        <div class="d-flex justify-space-between align-center mb-1">
          <span class="text-subtitle-2 text-medium-emphasis">Ventas del Mes</span>
          <span :class="['text-xs font-weight-medium', isPositiveChange ? 'text-success' : 'text-error']">
            {{ salesChangePct }}
          </span>
        </div>
        <div class="text-h5 font-weight-bold mb-1">
          {{ currentSales }}
        </div>
        <div class="text-caption text-medium-emphasis">
          Mes en Curso
        </div>
      </div>

      <div class="chart-container">
        <VueApexCharts
          type="line"
          :options="chartOptions"
          :series="series"
          :height="80"
        />
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.chart-container {
  margin-top: 8px;
  width: 100%;
}
</style>
