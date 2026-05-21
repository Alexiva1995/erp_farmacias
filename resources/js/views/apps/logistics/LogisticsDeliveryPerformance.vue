<script setup lang="ts">
import axios from 'axios'
import { onMounted, ref, computed } from 'vue'

const promotionsData = ref([])

const fetchPromotions = async () => {
  try {
    const response = await axios.get('/api/dashboard/analytics-data')
    promotionsData.value = response.data.promotions_summary || []
  } catch (error) {
    console.error('Error al cargar rendimiento de promociones:', error)
  }
}

const totalOrdersWithPromo = computed(() => {
  return promotionsData.value.reduce((acc, curr) => acc + curr.orders, 0)
})

const series = computed(() => {
  return promotionsData.value.map(item => item.orders)
})

const chartOptions = computed(() => {
  return {
    labels: promotionsData.value.map(item => item.name),
    chart: {
      type: 'donut',
      parentHeightOffset: 0,
    },
    dataLabels: { enabled: false },
    legend: {
      show: true,
      position: 'bottom',
      horizontalAlign: 'center',
      fontSize: '13px',
      markers: { width: 10, height: 10, radius: 12 },
      itemMargin: { horizontal: 10, vertical: 5 }
    },
    stroke: { width: 0 },
    plotOptions: {
      pie: {
        donut: {
          size: '70%',
          labels: {
            show: true,
            value: {
              fontSize: '1.5rem',
              color: 'rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity))',
              fontWeight: 500,
              offsetY: -15,
              formatter: (val) => val,
            },
            name: { offsetY: 20 },
            total: {
              show: true,
              fontSize: '13px',
              label: 'Ventas Promo',
              color: 'rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity))',
              formatter: () => totalOrdersWithPromo.value,
            },
          },
        },
      },
    },
  }
})

onMounted(fetchPromotions)
</script>

<template>
  <VCard>
    <VCardItem title="Rendimiento de Promociones">
      <VCardSubtitle>Órdenes completadas por tipo de oferta</VCardSubtitle>
    </VCardItem>

    <VCardText>
      <div class="mb-4 d-flex justify-center">
        <VueApexCharts
          type="donut"
          height="320"
          width="100%"
          :options="chartOptions"
          :series="series"
        />
      </div>

      <VDivider class="my-4" />

      <VRow>
        <VCol
          v-for="(item, index) in promotionsData"
          :key="index"
          cols="6"
          sm="3"
          class="text-center"
        >
          <div class="text-caption text-disabled mb-1 text-truncate">{{ item.name }}</div>
          <div class="text-h6">{{ item.orders }}</div>
          <div :class="`text-xs font-weight-bold ${['text-primary', 'text-success', 'text-warning', 'text-info'][index % 4]}`">
            {{ totalOrdersWithPromo > 0 ? Math.round((item.orders / totalOrdersWithPromo) * 100) : 0 }}%
          </div>
        </VCol>
      </VRow>
      
      <div v-if="promotionsData.length === 0" class="text-center py-4 text-disabled">
        Sin promociones registradas
      </div>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
</style>
