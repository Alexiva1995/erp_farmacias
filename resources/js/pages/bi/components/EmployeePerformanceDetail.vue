<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { useBiThemeColors } from '@/composables/useBiThemeColors';

const props = defineProps({
  employeeDetail: { type: Object, default: null },
  detailLoading: { type: Boolean, default: false }
});

const { colors } = useBiThemeColors();

const formatCurrency = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);
const formatNumber = (value) => new Intl.NumberFormat('en-US').format(value || 0);

const getStatusColor = (val, target) => {
  if (!target || target <= 0) return 'primary';
  const ratio = (val / target) * 100;
  if (ratio >= 100) return 'success';
  if (ratio >= 80) return 'warning';
  return 'error';
};

const historyChartOptions = computed(() => ({
  chart: { 
    toolbar: { show: false },
    background: 'transparent'
  },
  theme: { 
    mode: colors.value.isDark ? 'dark' : 'light' 
  },
  stroke: { width: [3, 0], curve: 'smooth' },
  plotOptions: { bar: { columnWidth: '45%', borderRadius: 4 } },
  colors: [colors.value.primary, colors.value.secondary],
  labels: props.employeeDetail?.history?.map(h => h.label) || [],
  xaxis: {
    labels: {
      style: {
        colors: colors.value.isDark ? 'rgba(255, 255, 255, 0.7)' : 'rgba(0, 0, 0, 0.7)',
        fontSize: '11px'
      }
    }
  },
  yaxis: [
    { 
      title: { 
        text: 'Ventas (USD)', 
        style: { color: colors.value.primary, fontSize: '11px', fontWeight: 600 } 
      }, 
      labels: { 
        style: { colors: colors.value.primary },
        formatter: (val) => `$${Number(val || 0).toLocaleString()}`
      } 
    },
    { 
      opposite: true, 
      title: { 
        text: 'Unidades', 
        style: { color: colors.value.secondary, fontSize: '11px', fontWeight: 600 } 
      }, 
      labels: { 
        style: { colors: colors.value.secondary },
        formatter: (val) => Number(val || 0).toLocaleString()
      } 
    }
  ],
  tooltip: { 
    theme: colors.value.isDark ? 'dark' : 'light',
    shared: true, 
    intersect: false 
  },
  grid: {
    borderColor: colors.value.isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.08)',
    strokeDashArray: 4
  }
}));

const historyChartSeries = computed(() => [
  { name: 'Ventas USD', type: 'line', data: props.employeeDetail?.history?.map(h => h.sales) || [] },
  { name: 'Unidades', type: 'column', data: props.employeeDetail?.history?.map(h => h.units) || [] }
]);

const scorecards = computed(() => {
  if (!props.employeeDetail?.metrics) return [];
  const m = props.employeeDetail.metrics;
  return [
    { 
      label: 'Cumplimiento Venta', 
      val: m.sales || 0, 
      target: m.sales_target || 5000, 
      icon: 'tabler-trending-up', 
      isCurrency: true 
    },
    { 
      label: 'Tareas Realizadas', 
      val: m.tasks_completed || 0, 
      target: m.tasks_assigned || 20, 
      icon: 'tabler-sparkles', 
      isCurrency: false 
    },
    { 
      label: 'Inventario Auditado', 
      val: m.inventory_counted || 0, 
      target: m.inventory_target || 100, 
      icon: 'tabler-checkbox', 
      isCurrency: false 
    }
  ];
});
</script>

<template>
  <div>
    <div v-if="!employeeDetail && !detailLoading" class="d-flex flex-column justify-center align-center h-100 border rounded-lg border-dashed py-12 px-4 text-medium-emphasis">
      <VIcon icon="tabler-user-search" size="48" class="mb-2 opacity-50" />
      <h4 class="text-subtitle-1 font-weight-bold text-center">Ficha de Rendimiento Individual</h4>
      <p class="text-caption text-center mb-0">Selecciona un vendedor del ranking para ver su desglose operativo y evolución histórica</p>
    </div>

    <div v-else-if="detailLoading" class="d-flex flex-column ga-4">
      <VSkeletonLoader type="card, article, table" />
    </div>

    <div v-else-if="employeeDetail">
      <!-- Scorecards principales con metas dinámicas -->
      <VRow class="mb-4" dense>
        <VCol cols="12" sm="4" v-for="(kpi, idx) in scorecards" :key="idx">
          <VCard border class="rounded-lg">
            <VCardText class="pa-4">
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-overline text-medium-emphasis font-weight-bold">{{ kpi.label }}</span>
                <VIcon :icon="kpi.icon" :color="getStatusColor(kpi.val, kpi.target)" size="16" />
              </div>
              <div class="text-h6 font-weight-bold">
                {{ kpi.isCurrency ? formatCurrency(kpi.val) : formatNumber(kpi.val) }}
              </div>
              <div class="d-flex align-center justify-space-between text-caption text-medium-emphasis mt-1">
                <span>Meta: {{ kpi.isCurrency ? formatCurrency(kpi.target) : formatNumber(kpi.target) }}</span>
                <span>{{ kpi.target > 0 ? ((kpi.val / kpi.target) * 100).toFixed(0) : 0 }}%</span>
              </div>
              <VProgressLinear 
                :model-value="kpi.target > 0 ? (kpi.val / kpi.target) * 100 : 0" 
                :color="getStatusColor(kpi.val, kpi.target)" 
                height="6" 
                rounded 
                class="mt-1" 
              />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Gráfico de Histórico -->
      <VCard class="rounded-lg border mb-4">
        <VCardItem class="py-3 border-b">
          <VCardTitle class="text-subtitle-2 font-weight-bold text-uppercase">Evolución Histórica: Ventas vs Unidades</VCardTitle>
        </VCardItem>
        <VCardText class="pa-4">
          <VueApexCharts height="280" type="line" :options="historyChartOptions" :series="historyChartSeries" />
        </VCardText>
      </VCard>

      <!-- Desglose de Eficiencia y Operaciones -->
      <VRow dense>
        <VCol cols="12" sm="6">
          <VCard class="rounded-lg border h-100">
            <VCardItem class="py-2 border-b">
              <VCardTitle class="text-caption font-weight-bold text-uppercase">Eficiencia Comercial</VCardTitle>
            </VCardItem>
            <VList density="comfortable">
              <VListItem>
                <template #prepend><VIcon icon="tabler-receipt" color="success" size="20" class="me-2" /></template>
                <VListItemTitle class="text-body-2 font-weight-medium">Ticket Promedio</VListItemTitle>
                <template #append><span class="font-weight-bold text-body-2 text-success">{{ formatCurrency(employeeDetail.metrics.avg_ticket) }}</span></template>
              </VListItem>
              <VListItem>
                <template #prepend><VIcon icon="tabler-arrows-cross" color="info" size="20" class="me-2" /></template>
                <VListItemTitle class="text-body-2 font-weight-medium">Tasa de Conversión</VListItemTitle>
                <template #append><span class="font-weight-bold text-body-2 text-info">{{ (employeeDetail.metrics.conversion_rate || 0).toFixed(1) }}%</span></template>
              </VListItem>
              <VListItem>
                <template #prepend><VIcon icon="tabler-star" color="warning" size="20" class="me-2" /></template>
                <VListItemTitle class="text-body-2 font-weight-medium">Venta Estratégica</VListItemTitle>
                <template #append><span class="font-weight-bold text-body-2 text-warning">{{ formatNumber(employeeDetail.metrics.strategic_units) }} unds</span></template>
              </VListItem>
            </VList>
          </VCard>
        </VCol>
        <VCol cols="12" sm="6">
          <VCard class="rounded-lg border h-100">
            <VCardItem class="py-2 border-b">
              <VCardTitle class="text-caption font-weight-bold text-uppercase">Operaciones & Riesgo</VCardTitle>
            </VCardItem>
            <VList density="comfortable">
              <VListItem>
                <template #prepend><VIcon icon="tabler-clock-alert" color="error" size="20" class="me-2" /></template>
                <VListItemTitle class="text-body-2 font-weight-medium">Salida de Caducidad</VListItemTitle>
                <template #append><span class="font-weight-bold text-body-2 text-error">{{ formatNumber(employeeDetail.metrics.expiring_units) }} unds</span></template>
              </VListItem>
              <VListItem>
                <template #prepend><VIcon icon="tabler-file-invoice" color="primary" size="20" class="me-2" /></template>
                <VListItemTitle class="text-body-2 font-weight-medium">Facturas Procesadas</VListItemTitle>
                <template #append><span class="font-weight-bold text-body-2 text-primary">{{ formatNumber(employeeDetail.metrics.invoices_processed) }}</span></template>
              </VListItem>
              <VListItem>
                <template #prepend><VIcon icon="tabler-alert-triangle" color="error" size="20" class="me-2" /></template>
                <VListItemTitle class="text-body-2 font-weight-medium">Errores de Inventario</VListItemTitle>
                <template #append><span class="font-weight-bold text-body-2 text-error">{{ formatNumber(employeeDetail.metrics.inventory_errors) }}</span></template>
              </VListItem>
            </VList>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
</template>
