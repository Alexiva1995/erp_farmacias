<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
  employeeDetail: { type: Object, default: null },
  detailLoading: { type: Boolean, default: false }
});

const formatCurrency = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);
const formatNumber = (value) => new Intl.NumberFormat('en-US').format(value || 0);

const getStatusColor = (val, target) => {
  const ratio = (val / target) * 100;
  if (ratio >= 100) return 'success';
  if (ratio >= 80) return 'warning';
  return 'error';
};

const historyChartOptions = computed(() => ({
  chart: { toolbar: { show: false } },
  stroke: { width: [4, 0], curve: 'smooth' },
  plotOptions: { bar: { columnWidth: '50%', borderRadius: 4 } },
  colors: ['#E20074', '#7A0099'],
  labels: props.employeeDetail?.history?.map(h => h.label) || [],
  yaxis: [
    { title: { text: 'Ventas (USD)' }, labels: { style: { colors: '#E20074' } } },
    { opposite: true, title: { text: 'Unidades' }, labels: { style: { colors: '#7A0099' } } }
  ],
  tooltip: { shared: true, intersect: false, theme: 'dark' }
}));

const historyChartSeries = computed(() => [
  { name: 'Ventas USD', type: 'line', data: props.employeeDetail?.history?.map(h => h.sales) || [] },
  { name: 'Unidades', type: 'column', data: props.employeeDetail?.history?.map(h => h.units) || [] }
]);
</script>

<template>
  <div>
    <div v-if="!employeeDetail && !detailLoading" class="d-flex flex-column justify-center align-center h-100 border rounded-lg border-dashed opacity-40 py-10">
      <VIcon icon="tabler-click" size="48" class="mb-2" />
      <p class="font-weight-bold text-center px-4">Selecciona un vendedor para ver su ficha detallada</p>
    </div>

    <div v-else-if="detailLoading" class="d-flex flex-column gap-4">
      <VSkeletonLoader type="card, article" />
    </div>

    <div v-else-if="employeeDetail">
      <!-- Scorecards principales -->
      <VRow class="mb-4" dense>
        <VCol cols="12" sm="4" v-for="(kpi, idx) in [
          { label: 'Cumplimiento Venta', val: employeeDetail.metrics.sales, target: 5000, icon: 'tabler-trending-up', unit: '$' },
          { label: 'Tareas Realizadas', val: employeeDetail.metrics.tasks_completed, target: 20, icon: 'tabler-sparkles', unit: '' },
          { label: 'Inventario Auditado', val: employeeDetail.metrics.inventory_counted, target: 100, icon: 'tabler-checkbox', unit: '' }
        ]" :key="idx">
          <VCard border class="rounded-lg shadow-sm bg-surface">
            <VCardText class="pa-4">
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-[10px] font-weight-black uppercase text-disabled">{{ kpi.label }}</span>
                <VIcon :icon="kpi.icon" :color="getStatusColor(kpi.val, kpi.target)" size="14" />
              </div>
              <div class="text-h6 font-weight-black">{{ kpi.unit }}{{ formatNumber(kpi.val) }}</div>
              <VProgressLinear :model-value="(kpi.val / kpi.target) * 100" :color="getStatusColor(kpi.val, kpi.target)" height="4" rounded class="mt-1" />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Gráfico de Histórico -->
      <VCard class="rounded-lg border shadow-sm mb-4">
        <VCardItem class="py-3 border-b">
          <VCardTitle class="text-subtitle-2 font-weight-black uppercase">Evolución: Ventas vs Unidades</VCardTitle>
        </VCardItem>
        <VCardText class="pa-4">
          <VueApexCharts height="280" type="line" :options="historyChartOptions" :series="historyChartSeries" />
        </VCardText>
      </VCard>

      <!-- Desglose de Eficiencia y Operaciones -->
      <VRow dense>
        <VCol cols="12" sm="6">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem class="py-2 border-b bg-light"><VCardTitle class="text-super-xs font-weight-black uppercase">Eficiencia Comercial</VCardTitle></VCardItem>
            <VList density="compact">
              <VListItem>
                <template #prepend><VIcon icon="tabler-currency-dollar" color="success" size="18" /></template>
                <VListItemTitle class="text-[11px] font-weight-bold">Ticket Promedio</VListItemTitle>
                <template #append><span class="font-weight-black text-success">{{ formatCurrency(employeeDetail.metrics.avg_ticket) }}</span></template>
              </VListItem>
              <VListItem>
                <template #prepend><VIcon icon="tabler-arrows-cross" color="info" size="18" /></template>
                <VListItemTitle class="text-[11px] font-weight-bold">Tasa Conversión</VListItemTitle>
                <template #append><span class="font-weight-black text-info">{{ (employeeDetail.metrics.conversion_rate || 0).toFixed(1) }}%</span></template>
              </VListItem>
              <VListItem>
                <template #prepend><VIcon icon="tabler-star" color="warning" size="18" /></template>
                <VListItemTitle class="text-[11px] font-weight-bold">Venta Estratégica</VListItemTitle>
                <template #append><span class="font-weight-black text-warning">{{ formatNumber(employeeDetail.metrics.strategic_units) }} unds</span></template>
              </VListItem>
            </VList>
          </VCard>
        </VCol>
        <VCol cols="12" sm="6">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem class="py-2 border-b bg-light"><VCardTitle class="text-super-xs font-weight-black uppercase">Operaciones & Riesgo</VCardTitle></VCardItem>
            <VList density="compact">
              <VListItem>
                <template #prepend><VIcon icon="tabler-alert-triangle" color="error" size="18" /></template>
                <VListItemTitle class="text-[11px] font-weight-bold">Salida Caducidad</VListItemTitle>
                <template #append><span class="font-weight-black text-error">{{ formatNumber(employeeDetail.metrics.expiring_units) }}</span></template>
              </VListItem>
              <VListItem>
                <template #prepend><VIcon icon="tabler-package-import" color="primary" size="18" /></template>
                <VListItemTitle class="text-[11px] font-weight-bold">Facturas Cargadas</VListItemTitle>
                <template #append><span class="font-weight-black text-primary">{{ formatNumber(employeeDetail.metrics.invoices_processed) }}</span></template>
              </VListItem>
              <VListItem>
                <template #prepend><VIcon icon="tabler-search" color="secondary" size="18" /></template>
                <VListItemTitle class="text-[11px] font-weight-bold">Errores Inventario</VListItemTitle>
                <template #append><span class="font-weight-black text-error">{{ formatNumber(employeeDetail.metrics.inventory_errors) }}</span></template>
              </VListItem>
            </VList>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
</template>

<style scoped>
.bg-surface { background-color: #fff !important; }
.font-weight-black { font-weight: 900 !important; }
.uppercase { text-transform: uppercase; letter-spacing: 0.5px; }
.text-super-xs { font-size: 9px; line-height: 1; }
</style>
