<script setup lang="ts">
import { useTheme } from 'vuetify'
import { hexToRgb } from '@layouts/utils'

const props = defineProps({
  data: {
    type: Object,
    default: () => ({ value: 0, change: 0 })
  }
})

const vuetifyTheme = useTheme()

const series = [
  {
    data: [25, 40, 55, 70, 85, 70, 55], // Datos dummy para la gráfica por ahora
  },
]

const formatCurrencyUSD = (value) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0
  }).format(value)
}

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables

  const labelSuccessColor = `rgba(${hexToRgb(currentTheme.success)},0.2)`
  const labelColor = `rgba(${hexToRgb(currentTheme['on-surface'])},${variableTheme['disabled-opacity']})`

  return {
    chart: {
      height: 162,
      type: 'bar',
      parentHeightOffset: 0,
      toolbar: {
        show: false,
      },
    },
    plotOptions: {
      bar: {
        barHeight: '80%',
        columnWidth: '30%',
        startingShape: 'rounded',
        endingShape: 'rounded',
        borderRadius: 6,
        distributed: true,
      },
    },
    tooltip: {
      enabled: false,
    },
    grid: {
      show: false,
      padding: {
        top: -20,
        bottom: -12,
        left: -10,
        right: 0,
      },
    },
    colors: [
      labelSuccessColor,
      labelSuccessColor,
      labelSuccessColor,
      labelSuccessColor,
      currentTheme.success,
      labelSuccessColor,
      labelSuccessColor,
    ],
    dataLabels: {
      enabled: false,
    },
    legend: {
      show: false,
    },
    xaxis: {
      categories: ['L', 'M', 'X', 'J', 'V', 'S', 'D'],
      axisBorder: {
        show: false,
      },
      axisTicks: {
        show: false,
      },
      labels: {
        style: {
          colors: labelColor,
          fontSize: '13px',
          fontFamily: 'Public sans',
        },
      },
    },
    yaxis: {
      labels: {
        show: false,
      },
    },
    states: {
      hover: {
        filter: {
          type: 'none',
        },
      },
    },
    responsive: [
      {
        breakpoint: 1640,
        options: {
          plotOptions: {
            bar: {
              columnWidth: '40%',
              borderRadius: 6,
            },
          },
        },
      },
      {
        breakpoint: 1471,
        options: {
          plotOptions: {
            bar: {
              columnWidth: '50%',
            },
          },
        },
      },
      {
        breakpoint: 1350,
        options: {
          plotOptions: {
            bar: {
              columnWidth: '57%',
            },
          },
        },
      },
      {
        breakpoint: 1032,
        options: {
          plotOptions: {
            bar: {
              columnWidth: '50%',
              borderRadius: 4,
            },
          },
        },
      },
      {
        breakpoint: 992,
        options: {
          plotOptions: {
            bar: {
              columnWidth: '40%',
              borderRadius: 4,
            },
          },
        },
      },
      {
        breakpoint: 855,
        options: {
          plotOptions: {
            bar: {
              columnWidth: '50%',
              borderRadius: 6,
            },
          },
        },
      },
      {
        breakpoint: 440,
        options: {
          plotOptions: {
            bar: {
              columnWidth: '30%',
              borderRadius: 4,
            },
          },
        },
      },
      {
        breakpoint: 381,
        options: {
          plotOptions: {
            bar: {
              columnWidth: '45%',
              borderRadius: 4,
            },
          },
        },
      },
    ],
  }
})
</script>

<template>
  <VCard>
    <VCardText class="d-flex justify-space-between">
      <div class="d-flex flex-column">
        <div class="mb-auto">
          <h5 class="text-h5 text-no-wrap mb-2">
            Crecimiento de Ingresos
          </h5>
          <div class="text-body-1">
            Reporte Semanal
          </div>
        </div>

        <div>
          <h5 class="text-h3 mb-2">
            {{ formatCurrencyUSD(props.data.value) }}
          </h5>
          <VChip
            label
            :color="props.data.change >= 0 ? 'success' : 'error'"
            size="small"
          >
            {{ props.data.change >= 0 ? '+' : '' }}{{ props.data.change }}%
          </VChip>
        </div>
      </div>
      <div>
        <VueApexCharts
          :options="chartOptions"
          :series="series"
          :height="162"
        />
      </div>
    </VCardText>
  </VCard>
</template>
