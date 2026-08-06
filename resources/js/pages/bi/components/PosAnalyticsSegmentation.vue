<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
  segmentation: { type: Object, default: () => ({}) },
  kpis: { type: Object, default: () => ({}) }
});

const formatCurrency = (val) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val || 0);

const unitsDonutOptions = computed(() => ({
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
            fontWeight: 900,
            formatter: () => props.kpis.completed_sales || 0
          }
        }
      }
    }
  },
  colors: ['#E20074', '#7A0099', '#ff9f43', '#28c76f'],
  legend: { position: 'bottom', labels: { colors: '#a3a3a3' }, fontSize: '11px', fontWeight: 600 },
  dataLabels: { enabled: false }
}));

const monetaryChartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
  plotOptions: {
    bar: {
      borderRadius: 4,
      horizontal: true,
      barHeight: '70%',
      distributed: true
    }
  },
  colors: ['#E20074', '#7A0099', '#28c76f', '#ff9f43', '#ea5455', '#00cfe8', '#161616', '#a8aaad'],
  dataLabels: {
    enabled: true,
    style: { fontSize: '10px', fontWeight: 900, colors: ['#fff'] },
    formatter: (val) => val
  },
  xaxis: {
    categories: props.segmentation.monetary?.labels?.map(l => `$ ${l}`) || [],
    labels: { style: { fontSize: '10px' } }
  },
  yaxis: {
    labels: { style: { fontSize: '11px', fontWeight: 700 } }
  },
  grid: { borderColor: 'rgba(144, 164, 174, 0.05)' },
  legend: { show: false },
  tooltip: { theme: 'dark' }
}));
</script>

<template>
  <VRow dense class="mb-6">
    <VCol cols="12" md="7">
      <VCard class="rounded-lg border shadow-sm h-100">
        <VCardItem class="py-3 border-b">
          <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
            <VIcon icon="tabler-package" class="me-2 text-primary" size="20" />
            Segmentación por Volumen (Unidades)
          </VCardTitle>
        </VCardItem>
        <VRow no-gutters class="pa-4 align-center">
          <VCol cols="12" sm="7">
            <VueApexCharts height="260" :options="unitsDonutOptions" :series="segmentation.units?.series || []" type="donut" />
          </VCol>
          <VCol cols="12" sm="5" class="ps-sm-4 pt-4 pt-sm-0">
            <div class="mb-4">
              <div class="d-flex align-center mb-1">
                <VIcon icon="tabler-arrows-cross" size="14" class="me-1 text-info" />
                <span class="text-[11px] font-weight-black uppercase">Penetración V. Cruzada</span>
              </div>
              <h4 class="text-h6 font-weight-black text-info">{{ kpis.cross_selling_rate || 0 }}%</h4>
              <VProgressLinear :model-value="kpis.cross_selling_rate || 0" color="info" height="6" rounded class="mt-1" />
            </div>

            <div v-for="(label, idx) in (segmentation.units?.labels || [])" :key="label" class="d-flex justify-space-between align-center py-1 border-b">
              <span class="text-[10px] font-weight-bold uppercase opacity-60">{{ label }}</span>
              <VChip density="comfortable" size="x-small" variant="tonal" color="primary" class="font-weight-black">{{ segmentation.units?.series?.[idx] || 0 }} Tks</VChip>
            </div>
          </VCol>
        </VRow>
      </VCard>
    </VCol>

    <VCol cols="12" md="5">
      <VCard class="rounded-lg border shadow-sm h-100">
        <VCardItem class="py-3 border-b">
          <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
            <VIcon icon="tabler-currency-dollar" class="me-2 text-success" size="20" />
            Tipología por Valor del Ticket
          </VCardTitle>
        </VCardItem>
        <VCardText class="pa-4">
          <VueApexCharts height="220" :options="monetaryChartOptions" :series="[{ data: segmentation.monetary?.series || [] }]" />
          
          <div class="mt-4 p-3 bg-light-info rounded-lg border border-info border-opacity-10 d-flex align-top">
            <VAvatar color="info" variant="tonal" size="32" rounded="lg" class="me-3">
              <VIcon icon="tabler-trending-up" size="18" />
            </VAvatar>
            <div>
              <div class="text-[11px] font-weight-black text-info uppercase">Oportunidad de Venta Cruzada</div>
              <div class="text-[10px] text-info font-weight-bold opacity-80">
                Un incremento al 40% en esta métrica generaría un ingreso adicional estimado de {{ formatCurrency((kpis.total_revenue || 0) * 0.15) }}.
              </div>
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.font-weight-black { font-weight: 900 !important; }
.uppercase { text-transform: uppercase; letter-spacing: 0.5px; }
.bg-light-info { background-color: #f0f9ff; }
</style>
