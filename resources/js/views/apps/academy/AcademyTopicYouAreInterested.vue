<script setup lang="ts">
import axios from 'axios'
import { onMounted, ref, computed } from 'vue'

const packsSummary = ref([])

const fetchPacks = async () => {
  try {
    const response = await axios.get('/api/dashboard/analytics-data')
    packsSummary.value = response.data.packs_summary || []
  } catch (error) {
    console.error('Error al cargar detalle de packs:', error)
  }
}

const totalPacksSales = computed(() => {
  return packsSummary.value.reduce((acc, curr) => acc + curr.amount, 0)
})

const series = computed(() => {
  if (packsSummary.value.length === 0) return [{ data: [0] }]
  return [
    {
      name: 'Packs Vendidos',
      data: packsSummary.value.map(item => item.units),
    },
  ]
})

const chartOptions = computed(() => {
  const labelColor = 'rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity))'
  const borderColor = 'rgba(var(--v-border-color), var(--v-border-opacity))'

  return {
    chart: {
      type: 'bar',
      toolbar: { show: false },
    },
    plotOptions: {
      bar: {
        horizontal: true,
        barHeight: '70%',
        distributed: true,
        borderRadius: 6,
      },
    },
    colors: [
      'rgba(var(--v-theme-primary),1)', 
      'rgba(var(--v-theme-info),1)', 
      'rgba(var(--v-theme-success),1)', 
      'rgba(var(--v-theme-warning),1)', 
      'rgba(var(--v-theme-error),1)', 
      'rgba(var(--v-theme-secondary),1)'
    ],
    grid: {
      borderColor,
      strokeDashArray: 10,
      xaxis: { lines: { show: true } },
      yaxis: { lines: { show: false } },
      padding: { top: -25, bottom: -12 },
    },
    dataLabels: {
      enabled: true,
      formatter: (val, opt) => packsSummary.value[opt.dataPointIndex]?.name,
      style: { fontSize: '12px', fontWeight: 500 },
      offsetX: 0,
    },
    xaxis: {
      categories: packsSummary.value.map((_, i) => i + 1),
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: {
        style: { colors: labelColor, fontSize: '11px' },
        formatter: (val) => `${val} uds`,
      },
    },
    yaxis: {
      labels: { show: false },
    },
    tooltip: { enabled: true },
    legend: { show: false },
  }
})

const formatCurrencyUSD = (value) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(value)
}

onMounted(fetchPacks)
</script>

<template>
  <VCard>
    <VCardItem title="Detalle de Pack">
      <VCardSubtitle>Rendimiento por combos este mes</VCardSubtitle>
    </VCardItem>

    <VCardText>
      <VRow>
        <VCol cols="12" md="7">
          <VueApexCharts
            type="bar"
            height="260"
            :options="chartOptions"
            :series="series"
          />
        </VCol>

        <VCol cols="12" md="5">
          <div class="d-flex flex-column gap-y-4 mt-2">
            <div
              v-for="(pack, index) in packsSummary.slice(0, 5)"
              :key="index"
              class="d-flex align-center gap-x-2"
            >
              <VBadge
                dot
                inline
                :color="['primary', 'info', 'success', 'warning', 'error'][index % 5]"
              />
              <div class="flex-grow-1">
                <div class="text-body-2 text-truncate" style="max-width: 120px;">
                  {{ pack.name }}
                </div>
                <h6 class="text-h6">
                  {{ pack.units }} packs vendidos
                </h6>
              </div>
            </div>
            
            <div v-if="packsSummary.length === 0" class="text-center py-10 text-disabled">
              No hay ventas de packs registradas
            </div>
          </div>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
