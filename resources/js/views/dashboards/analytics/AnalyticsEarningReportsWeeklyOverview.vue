<script setup lang="ts">
import { useTheme } from 'vuetify'
import { hexToRgb } from '@layouts/utils'
import axios from 'axios'
import { onMounted, ref, computed } from 'vue'

const vuetifyTheme = useTheme()

const dailyEarnings = ref([])
const selectedIndex = ref(6) // Por defecto el día actual (último del array)

const fetchAnalytics = async () => {
  try {
    const response = await axios.get('/api/dashboard/analytics-data')
    dailyEarnings.value = response.data.daily_earnings
  } catch (error) {
    console.error('Error al cargar informes de ganancias:', error)
  }
}

const currentDayData = computed(() => {
  if (dailyEarnings.value.length === 0) return { sales: 0, cost: 0, profit: 0, label: '' }
  return dailyEarnings.value[selectedIndex.value]
})

const series = computed(() => [
  {
    name: 'Ventas',
    data: dailyEarnings.value.map(item => item.sales),
  },
])

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables

  return {
    chart: {
      parentHeightOffset: 0,
      type: 'bar',
      toolbar: { show: false },
      events: {
        dataPointSelection: (event, chartContext, config) => {
          selectedIndex.value = config.dataPointIndex
        }
      }
    },
    plotOptions: {
      bar: {
        columnWidth: '40%',
        startingShape: 'rounded',
        endingShape: 'rounded',
        borderRadius: 4,
        distributed: true,
      },
    },
    grid: {
      show: false,
      padding: { top: -30, bottom: 0, left: -10, right: -10 },
    },
    colors: dailyEarnings.value.map((_, index) => 
      index === selectedIndex.value 
        ? `rgba(${hexToRgb(currentTheme.primary)}, 1)` 
        : `rgba(${hexToRgb(currentTheme.primary)}, ${variableTheme['dragged-opacity']})`
    ),
    dataLabels: { enabled: false },
    legend: { show: false },
    xaxis: {
      categories: dailyEarnings.value.map(item => item.label),
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: {
        style: {
          colors: `rgba(${hexToRgb(currentTheme['on-surface'])}, ${variableTheme['disabled-opacity']})`,
          fontSize: '13px',
        },
      },
    },
    yaxis: { labels: { show: false } },
    tooltip: { enabled: false },
  }
})

const earningsReports = computed(() => [
  {
    color: 'primary',
    icon: 'tabler-chart-bar',
    title: 'Venta',
    amount: formatCurrencyUSD(currentDayData.value.sales),
    progress: currentDayData.value.sales > 0 ? 100 : 0,
  },
  {
    color: 'success',
    icon: 'tabler-currency-dollar',
    title: 'Ganancia',
    amount: formatCurrencyUSD(currentDayData.value.profit),
    progress: currentDayData.value.sales > 0 ? (currentDayData.value.profit / currentDayData.value.sales) * 100 : 0,
  },
  {
    color: 'error',
    icon: 'tabler-shopping-cart-discount',
    title: 'Costo',
    amount: formatCurrencyUSD(currentDayData.value.cost),
    progress: currentDayData.value.sales > 0 ? (currentDayData.value.cost / currentDayData.value.sales) * 100 : 0,
  },
])

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
    <VCardItem class="pb-sm-0">
      <VCardTitle>Informes de Ganancias</VCardTitle>
      <VCardSubtitle>Detalle de los últimos 7 días</VCardSubtitle>
    </VCardItem>

    <VCardText>
      <VRow>
        <VCol
          cols="12"
          sm="5"
          lg="6"
          class="d-flex flex-column align-self-center"
        >
          <div class="d-flex align-center gap-2 mb-3 flex-wrap">
            <h2 class="text-h2">
              {{ formatCurrencyUSD(currentDayData.profit) }}
            </h2>
          </div>

          <span class="text-sm text-medium-emphasis">
            Ganancia neta del día {{ currentDayData.date }}
          </span>
        </VCol>

        <VCol
          cols="12"
          sm="7"
          lg="6"
        >
          <VueApexCharts
            :options="chartOptions"
            :series="series"
            height="161"
          />
        </VCol>
      </VRow>

      <div class="border rounded mt-5 pa-5">
        <VRow>
          <VCol
            v-for="report in earningsReports"
            :key="report.title"
            cols="12"
            sm="4"
          >
            <div class="d-flex align-center">
              <VAvatar
                rounded
                size="26"
                :color="report.color"
                variant="tonal"
                class="me-2"
              >
                <VIcon
                  size="18"
                  :icon="report.icon"
                />
              </VAvatar>

              <h6 class="text-base font-weight-regular">
                {{ report.title }}
              </h6>
            </div>
            <h6 class="text-h4 my-2">
              {{ report.amount }}
            </h6>
            <VProgressLinear
              :model-value="report.progress"
              :color="report.color"
              height="4"
              rounded
              rounded-bar
            />
          </VCol>
        </VRow>
      </div>
    </VCardText>
  </VCard>
</template>
