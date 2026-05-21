<script setup>
import { useTheme } from 'vuetify'
import { hexToRgb } from '@layouts/utils'
import VueApexCharts from 'vue3-apexcharts'
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const vuetifyTheme = useTheme()

// Datos reactivos de gastos totales
const series = ref([0])
const totalExpensesFormatted = ref('$0.00')
const expensesComparisonText = ref('Sin datos del mes anterior')

// Función para formatear dinero en USD
const formatCurrencyUSD = (amount) =>
  new Intl.NumberFormat("es-US", {
    style: "currency",
    currency: "USD",
  }).format(amount)

// Obtener datos reales de los últimos 2 meses para comparar
const fetchExpensesData = async () => {
  try {
    const currentYear = new Date().getFullYear()
    const currentMonthIdx = new Date().getMonth() // 0 = Enero, 4 = Mayo

    // Mes Actual
    const currentStart = new Date(currentYear, currentMonthIdx, 1).toISOString().split('T')[0]
    const currentEnd = new Date(currentYear, currentMonthIdx + 1, 0).toISOString().split('T')[0]

    // Mes Anterior
    let prevMonthIdx = currentMonthIdx - 1
    let prevYear = currentYear
    if (prevMonthIdx < 0) {
      prevMonthIdx = 11
      prevYear -= 1
    }
    const prevStart = new Date(prevYear, prevMonthIdx, 1).toISOString().split('T')[0]
    const prevEnd = new Date(prevYear, prevMonthIdx + 1, 0).toISOString().split('T')[0]

    // Consultas asincrónicas en paralelo de las estadísticas reales (ventas y todos los gastos)
    const [currentRes, prevRes] = await Promise.all([
      axios.get('/api/dashboard/stats', {
        params: { start_date: currentStart, end_date: currentEnd }
      }),
      axios.get('/api/dashboard/stats', {
        params: { start_date: prevStart, end_date: prevEnd }
      })
    ])

    const currentSales = currentRes.data ? parseFloat(currentRes.data.sales || 0) : 0.00
    const currentExpenses = currentRes.data ? parseFloat(currentRes.data.expenses || 0) : 0.00
    const prevExpenses = prevRes.data ? parseFloat(prevRes.data.expenses || 0) : 0.00

    totalExpensesFormatted.value = formatCurrencyUSD(currentExpenses)

    // Porcentaje de los gastos reales sobre las ventas reales del mes
    let expensePct = 0
    if (currentSales > 0) {
      expensePct = Math.min(Math.round((currentExpenses / currentSales) * 100), 100)
    } else if (currentExpenses > 0) {
      expensePct = 100
    }
    series.value = [expensePct]

    // Texto comparativo de variación
    if (prevExpenses > 0) {
      const diff = Math.abs(currentExpenses - prevExpenses)
      if (currentExpenses >= prevExpenses) {
        expensesComparisonText.value = `${formatCurrencyUSD(diff)} más que el mes pasado`
      } else {
        expensesComparisonText.value = `${formatCurrencyUSD(diff)} menos que el mes pasado`
      }
    } else if (currentExpenses > 0) {
      expensesComparisonText.value = `${formatCurrencyUSD(currentExpenses)} más que el mes pasado`
    } else {
      expensesComparisonText.value = 'Mismo nivel que el mes pasado'
    }
  } catch (error) {
    console.error('Error fetching expenses stats for radial chart:', error)
  }
}

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  
  return {
    chart: {
      sparkline: { enabled: true },
      parentHeightOffset: 0,
      type: 'radialBar',
    },
    colors: ['rgba(var(--v-theme-warning), 1)'],
    plotOptions: {
      radialBar: {
        offsetY: 0,
        startAngle: -90,
        endAngle: 90,
        hollow: { size: '65%' },
        track: {
          strokeWidth: '45%',
          background: vuetifyTheme.current.value.dark ? '#3a3e5b' : '#f0f2f8',
        },
        dataLabels: {
          name: { show: false },
          value: {
            fontSize: '20px',
            color: `rgba(${ hexToRgb(currentTheme['on-background']) },${ variableTheme['high-emphasis-opacity'] })`,
            fontWeight: 600,
            offsetY: -5,
          },
        },
      },
    },
    grid: {
      show: false,
      padding: { bottom: 5 },
    },
    stroke: { lineCap: 'round' },
    labels: ['Gastos'],
    responsive: [
      {
        breakpoint: 1442,
        options: {
          chart: { height: 130 },
          plotOptions: {
            radialBar: {
              dataLabels: { value: { fontSize: '18px' } },
              hollow: { size: '60%' },
            },
          },
        },
      },
      {
        breakpoint: 1370,
        options: { chart: { height: 110 } },
      },
    ],
  }
})

onMounted(() => {
  fetchExpensesData()
})
</script>

<template>
  <VCard class="h-100">
    <VCardText class="d-flex flex-column justify-space-between h-100 pb-3">
      <div>
        <div class="text-subtitle-2 text-medium-emphasis mb-1">
          Gastos Totales
        </div>
        <div class="text-h5 font-weight-bold mb-1">
          {{ totalExpensesFormatted }}
        </div>
        <div class="text-caption text-medium-emphasis">
          Mes en Curso
        </div>
      </div>

      <div class="chart-container d-flex justify-center">
        <VueApexCharts
          :options="chartOptions"
          :series="series"
          type="radialBar"
          :height="135"
        />
      </div>

      <div class="text-xs text-center text-medium-emphasis mt-2">
        {{ expensesComparisonText }}
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.chart-container {
  width: 100%;
  margin-top: 10px;
}
</style>
