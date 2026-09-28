<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
  segmentation: { type: Object, default: () => ({}) },
  kpis: { type: Object, default: () => ({}) },
});

const formatCurrency = (val) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val || 0);

const unitsDonutOptions = computed(() => ({
  chart: {
    fontFamily: 'inherit',
    background: 'transparent',
  },
  labels: props.segmentation.units?.labels || [],
  plotOptions: {
    pie: {
      donut: {
        size: '75%',
        labels: {
          show: true,
          total: {
            show: true,
            label: 'Tickets',
            fontSize: '12px',
            fontWeight: 700,
            formatter: () => props.kpis.completed_sales || 0,
          },
        },
      },
    },
  },
  colors: ['#E20074', '#7A0099', '#FF9F43', '#28C76F'],
  legend: {
    position: 'bottom',
    fontSize: '11px',
    fontWeight: 600,
  },
  dataLabels: { enabled: false },
}));

const monetaryChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    fontFamily: 'inherit',
    background: 'transparent',
  },
  plotOptions: {
    bar: {
      borderRadius: 4,
      horizontal: true,
      barHeight: '65%',
      distributed: true,
    },
  },
  colors: ['#E20074', '#7A0099', '#28C76F', '#FF9F43', '#FF4C51', '#00BAD1', '#8C57FF'],
  dataLabels: {
    enabled: true,
    style: { fontSize: '11px', fontWeight: 700, colors: ['#fff'] },
    formatter: (val) => `${val} tks`,
  },
  xaxis: {
    categories: props.segmentation.monetary?.labels?.map((l) => `$ ${l}`) || [],
    labels: { style: { fontSize: '11px' } },
  },
  yaxis: {
    labels: { style: { fontSize: '11px', fontWeight: 600 } },
  },
  grid: {
    borderColor: 'rgba(var(--v-border-color), var(--v-border-opacity))',
    strokeDashArray: 4,
  },
  legend: { show: false },
  tooltip: {
    theme: 'dark',
    y: {
      formatter: (val) => `${val} tickets`,
    },
  },
}));
</script>

<template>
  <VRow dense class="mb-6">
    <!-- Segmentación por Unidades en Canasta -->
    <VCol cols="12" md="7">
      <VCard variant="outlined" class="rounded-lg h-100">
        <VCardItem class="py-3 border-b">
          <template #prepend>
            <VAvatar color="primary" variant="tonal" size="32" class="me-2 rounded">
              <VIcon icon="tabler-package" size="18" />
            </VAvatar>
          </template>
          <VCardTitle class="text-subtitle-2 font-weight-bold text-uppercase">
            Profundidad de Canasta (Unidades por Venta)
          </VCardTitle>
        </VCardItem>
        <VRow no-gutters class="pa-4 align-center">
          <VCol cols="12" sm="7">
            <VueApexCharts height="260" :options="unitsDonutOptions" :series="segmentation.units?.series || []" type="donut" />
          </VCol>
          <VCol cols="12" sm="5" class="ps-sm-4 pt-4 pt-sm-0">
            <div class="mb-4">
              <div class="d-flex align-center mb-1">
                <VIcon icon="tabler-arrows-cross" size="16" class="me-1 text-info" />
                <span class="text-caption font-weight-bold text-uppercase">Penetración V. Cruzada</span>
              </div>
              <h4 class="text-h6 font-weight-bold text-info">{{ kpis.cross_selling_rate || 0 }}%</h4>
              <VProgressLinear :model-value="kpis.cross_selling_rate || 0" color="info" height="6" rounded class="mt-1" />
            </div>

            <div v-for="(label, idx) in (segmentation.units?.labels || [])" :key="label" class="d-flex justify-space-between align-center py-1 border-b">
              <span class="text-caption text-medium-emphasis font-weight-medium">{{ label }}</span>
              <VChip density="comfortable" size="x-small" variant="tonal" color="primary" class="font-weight-bold">
                {{ segmentation.units?.series?.[idx] || 0 }} Tks
              </VChip>
            </div>
          </VCol>
        </VRow>
      </VCard>
    </VCol>

    <!-- Tipología por Valor Monetario -->
    <VCol cols="12" md="5">
      <VCard variant="outlined" class="rounded-lg h-100">
        <VCardItem class="py-3 border-b">
          <template #prepend>
            <VAvatar color="success" variant="tonal" size="32" class="me-2 rounded">
              <VIcon icon="tabler-currency-dollar" size="18" />
            </VAvatar>
          </template>
          <VCardTitle class="text-subtitle-2 font-weight-bold text-uppercase">
            Estratificación por Monto de Ticket ($)
          </VCardTitle>
        </VCardItem>
        <VCardText class="pa-4">
          <VueApexCharts height="220" :options="monetaryChartOptions" :series="[{ data: segmentation.monetary?.series || [] }]" />
          
          <VCard variant="tonal" color="info" class="mt-4 pa-3 rounded-lg">
            <div class="d-flex align-start">
              <VAvatar color="info" variant="flat" size="28" rounded class="me-3 mt-1">
                <VIcon icon="tabler-trending-up" size="16" color="white" />
              </VAvatar>
              <div>
                <div class="text-caption font-weight-bold text-uppercase">Potencial de Venta Cruzada en Caja</div>
                <div class="text-caption text-medium-emphasis">
                  Impulsar artículos complementarios para elevar el ticket promedio podría representar hasta {{ formatCurrency((kpis.total_revenue || 0) * 0.12) }} de incremento en el periodo.
                </div>
              </div>
            </div>
          </VCard>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
