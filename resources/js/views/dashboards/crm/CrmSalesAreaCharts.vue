<script setup lang="ts">
import { useTheme } from 'vuetify'

const props = defineProps({
  data: {
    type: Object,
    default: () => ({ value: 0, change: 0 })
  }
})

const vuetifyTheme = useTheme()

const currentTheme = vuetifyTheme.current.value.colors

const series = [
  {
    name: 'Ventas',
    data: [100, 150, 120, 200, 180, 250, 220],
  },
]

const formatCurrencyUSD = (value) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0
  }).format(value)
}

const chartOptions = {
  chart: {
    type: 'area',
    parentHeightOffset: 0,
    toolbar: {
      show: false,
    },
    sparkline: {
      enabled: true,
    },
  },
  markers: {
    colors: 'transparent',
    strokeColors: 'transparent',
  },
  grid: {
    show: false,
  },
  colors: [currentTheme.success],
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 0.9,
      opacityFrom: 0.5,
      opacityTo: 0.07,
      stops: [0, 80, 100],
    },
  },
  dataLabels: {
    enabled: false,
  },
  stroke: {
    width: 2,
    curve: 'smooth',
  },
  xaxis: {
    show: true,
    lines: {
      show: false,
    },
    labels: {
      show: false,
    },
    stroke: {
      width: 0,
    },
    axisBorder: {
      show: false,
    },
  },
  yaxis: {
    stroke: {
      width: 0,
    },
    show: false,
  },
  tooltip: {
    enabled: false,
  },
}
</script>

<template>
  <VCard>
    <VCardItem class="pb-3">
      <VCardTitle>
        Ventas
      </VCardTitle>
      <VCardSubtitle>
        Última Semana
      </VCardSubtitle>
    </VCardItem>

    <VueApexCharts
      :options="chartOptions"
      :series="series"
      :height="68"
    />

    <VCardText class="pt-1">
      <div class="d-flex align-center justify-space-between gap-x-2">
        <h4 class="text-h4 text-center">
          {{ formatCurrencyUSD(props.data.value) }}
        </h4>
        <span :class="`text-sm ${props.data.change >= 0 ? 'text-success' : 'text-error'}`">
          {{ props.data.change >= 0 ? '+' : '' }}{{ props.data.change }}%
        </span>
      </div>
    </VCardText>
  </VCard>
</template>
