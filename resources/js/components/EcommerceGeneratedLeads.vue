<script setup>
import { useTheme, useDisplay } from 'vuetify'
import { hexToRgb } from '@layouts/utils'
import VueApexCharts from 'vue3-apexcharts'
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const vuetifyTheme = useTheme()
const display = useDisplay()

// Datos reactivos de clientes compradores
const totalBuyers = ref(0)
const clientsChangePct = ref('0.00%')
const isPositiveChange = ref(true)

// Las series del donut para los 6 tipos de clientes (Nuevo, Ocasional, Frecuente, VIP, En Riesgo, Inactivo)
const series = ref([0, 0, 0, 0, 0, 0])

const fetchClientsData = async () => {
  try {
    const res = await axios.get('/api/dashboard/client-stats')
    const data = res.data && res.data.data ? res.data.data : null

    if (data) {
      totalBuyers.value = data.total_buyers || 0
      clientsChangePct.value = `${data.is_positive ? '+' : ''}${data.change_pct}%`
      isPositiveChange.value = data.is_positive

      const typesData = data.types || {}
      series.value = [
        typesData['Nuevo'] || 0,
        typesData['Ocasional'] || 0,
        typesData['Frecuente'] || 0,
        typesData['VIP'] || 0,
        typesData['En Riesgo'] || 0,
        typesData['Inactivo'] || 0,
      ]
    }
  } catch (error) {
    console.error('Error fetching dashboard clients count:', error)
  }
}

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  const headingColor = `rgba(${ hexToRgb(currentTheme['on-background']) },${ variableTheme['high-emphasis-opacity'] })`

  const chartColors = {
    donut: {
      series1: currentTheme.success,
      series2: `rgba(${ hexToRgb(currentTheme.success) }, 0.82)`,
      series3: `rgba(${ hexToRgb(currentTheme.success) }, 0.65)`,
      series4: `rgba(${ hexToRgb(currentTheme.success) }, 0.48)`,
      series5: `rgba(${ hexToRgb(currentTheme.success) }, 0.32)`,
      series6: `rgba(${ hexToRgb(currentTheme.success) }, 0.16)`,
    },
  }
  
  return {
    chart: {
      parentHeightOffset: 0,
      type: 'donut',
    },
    labels: [
      'Nuevo',
      'Ocasional',
      'Frecuente',
      'VIP',
      'En Riesgo',
      'Inactivo',
    ],
    colors: [
      chartColors.donut.series1,
      chartColors.donut.series2,
      chartColors.donut.series3,
      chartColors.donut.series4,
      chartColors.donut.series5,
      chartColors.donut.series6,
    ],
    stroke: { width: 0 },
    dataLabels: {
      enabled: false,
    },
    legend: { show: false },
    tooltip: {
      theme: vuetifyTheme.current.value.dark ? 'dark' : 'light',
      y: {
        formatter: (val) => `${val} Clientes`
      }
    },
    grid: {
      padding: {
        top: 0,
        bottom: -10,
        right: -20,
        left: -20,
      },
    },
    states: { hover: { filter: { type: 'none' } } },
    plotOptions: {
      pie: {
        donut: {
          size: '70%',
          labels: {
            show: true,
            value: {
              fontSize: '1.25rem',
              color: headingColor,
              fontWeight: 600,
              offsetY: -15,
              formatter(val) {
                return `${val}`
              },
            },
            name: {
              offsetY: 20,
            },
            total: {
              show: true,
              showAlways: true,
              color: currentTheme.success,
              fontSize: '.75rem',
              label: 'Total',
              formatter() {
                return `${totalBuyers.value}`
              },
            },
          },
        },
      },
    },
    responsive: [
      {
        breakpoint: display.thresholds.value.lg,
        options: {
          chart: {
            width: 140,
            height: 120,
          },
        },
      },
      {
        breakpoint: 420,
        options: {
          chart: {
            width: 120,
            height: 100,
          },
        },
      },
    ],
  }
})

onMounted(() => {
  fetchClientsData()
})
</script>

<template>
  <VCard class="overflow-visible h-100">
    <VCardText class="d-flex justify-space-between align-center h-100 pb-3">
      <div class="d-flex flex-column justify-space-between h-100">
        <div>
          <h5 class="text-subtitle-2 text-medium-emphasis mb-1">
            Clientes Compradores
          </h5>
          <div class="text-caption text-medium-emphasis">
            Mes en Curso
          </div>
        </div>

        <div class="mt-4">
          <h3 class="text-h4 font-weight-bold mb-1">
            {{ totalBuyers }}
          </h3>
          <div class="d-flex align-center">
            <VIcon
              :icon="isPositiveChange ? 'tabler-chevron-up' : 'tabler-chevron-down'"
              :color="isPositiveChange ? 'success' : 'error'"
              class="me-1"
              size="18"
            />
            <span :class="[isPositiveChange ? 'text-success' : 'text-error', 'text-caption font-weight-medium']">
              {{ clientsChangePct }}
            </span>
          </div>
        </div>
      </div>
      <div>
        <VueApexCharts
          :options="chartOptions"
          :series="series"
          :height="130"
          :width="130"
        />
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
</style>
