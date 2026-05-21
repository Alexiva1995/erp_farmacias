<script setup lang="ts">
import { useTheme } from 'vuetify'
import { hexToRgb } from '@layouts/utils'
import axios from 'axios'
import { onMounted, ref, computed } from 'vue'

const vuetifyTheme = useTheme()

const ordersSummary = ref({
  completed: 0,
  total: 0,
  stats: {},
  completed_pct: 0,
})

const fetchAnalytics = async () => {
  try {
    const response = await axios.get('/api/dashboard/analytics-data')
    ordersSummary.value = response.data.orders_summary
  } catch (error) {
    console.error('Error al cargar rastreador de ventas:', error)
  }
}

const series = computed(() => [ordersSummary.value.completed_pct])

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables

  return {
    labels: ['Completadas'],
    chart: {
      type: 'radialBar',
    },
    plotOptions: {
      radialBar: {
        offsetY: 10,
        startAngle: -140,
        endAngle: 130,
        hollow: {
          size: '65%',
        },
        track: {
          background: currentTheme.surface,
          strokeWidth: '100%',
        },
        dataLabels: {
          name: {
            offsetY: -20,
            color: `rgba(${hexToRgb(currentTheme['on-surface'])},${variableTheme['disabled-opacity']})`,
            fontSize: '13px',
            fontWeight: '400',
            fontFamily: 'Public Sans',
          },
          value: {
            offsetY: 10,
            color: `rgba(${hexToRgb(currentTheme['on-background'])},${variableTheme['high-emphasis-opacity']})`,
            fontSize: '38px',
            fontWeight: '500',
            fontFamily: 'Public Sans',
          },
        },
      },
    },
    colors: [currentTheme.primary],
    fill: {
      type: 'gradient',
      gradient: {
        shade: 'dark',
        shadeIntensity: 0.5,
        gradientToColors: [currentTheme.primary],
        inverseColors: true,
        opacityFrom: 1,
        opacityTo: 0.6,
        stops: [30, 70, 100],
      },
    },
    stroke: {
      dashArray: 10,
    },
    grid: {
      padding: {
        top: -20,
        bottom: 5,
      },
    },
    states: {
      hover: { filter: { type: 'none' } },
      active: { filter: { type: 'none' } },
    },
    responsive: [
      {
        breakpoint: 960,
        options: {
          chart: { height: 280 },
        },
      },
    ],
  }
})

const orderStatsList = computed(() => {
  const stats = ordersSummary.value.stats || {}
  return [
    {
      avatarColor: 'success',
      avatarIcon: 'tabler-circle-check',
      title: 'Completadas',
      subtitle: stats['Completed'] || '0',
    },
    {
      avatarColor: 'error',
      avatarIcon: 'tabler-circle-x',
      title: 'Canceladas',
      subtitle: (stats['Cancelled'] || 0) + (stats['Abandoned'] || 0),
    },
    {
      avatarColor: 'warning',
      avatarIcon: 'tabler-clock',
      title: 'Pendientes',
      subtitle: (stats['Pending'] || 0) + (stats['Reserved'] || 0),
    },
  ]
})

onMounted(fetchAnalytics)
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Tracker de Ventas</VCardTitle>
      <VCardSubtitle>Estado de Órdenes del Mes</VCardSubtitle>
    </VCardItem>

    <VCardText>
      <VRow>
        <VCol
          cols="12"
          lg="4"
          md="4"
        >
          <div class="mb-lg-6 mb-4 mt-2">
            <h2 class="text-h2">
              {{ ordersSummary.total }}
            </h2>
            <p class="text-base mb-0">
              Total Ventas (Órdenes)
            </p>
          </div>

          <VList class="card-list">
            <VListItem
              v-for="stat in orderStatsList"
              :key="stat.title"
            >
              <VListItemTitle class="font-weight-medium">
                {{ stat.title }}
              </VListItemTitle>
              <VListItemSubtitle>
                {{ stat.subtitle }} pedidos
              </VListItemSubtitle>
              <template #prepend>
                <VAvatar
                  rounded
                  size="34"
                  :color="stat.avatarColor"
                  variant="tonal"
                  class="me-1"
                >
                  <VIcon
                    size="22"
                    :icon="stat.avatarIcon"
                  />
                </VAvatar>
              </template>
            </VListItem>
          </VList>
        </VCol>
        <VCol
          cols="12"
          lg="8"
          md="8"
        >
          <VueApexCharts
            :options="chartOptions"
            :series="series"
            height="360"
          />
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 16px;
}
</style>
