<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { useBiThemeColors } from '@/composables/useBiThemeColors';

const props = defineProps({
  employees: { type: Array, default: () => [] },
  employeeA: { type: [Number, String], default: null },
  employeeB: { type: [Number, String], default: null },
  comparisonData: { type: Object, default: null },
  compareLoading: { type: Boolean, default: false }
});

const emit = defineEmits([
  'update:employeeA',
  'update:employeeB',
  'compare'
]);

const { colors } = useBiThemeColors();

const formatCurrency = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);
const formatNumber = (value) => new Intl.NumberFormat('en-US').format(value || 0);

const radarChartOptions = computed(() => ({
  chart: { 
    toolbar: { show: false },
    background: 'transparent',
    dropShadow: { enabled: true, blur: 2, left: 1, top: 1, opacity: 0.15 } 
  },
  theme: {
    mode: colors.value.isDark ? 'dark' : 'light'
  },
  colors: [colors.value.primary, colors.value.secondary],
  stroke: { width: 2 },
  fill: { opacity: 0.15 },
  markers: { size: 3 },
  xaxis: {
    categories: ['Ventas', 'Unidades', 'Tareas', 'Inventario', 'Estratégicos'],
    labels: { 
      style: { 
        colors: colors.value.isDark ? ['#ccc', '#ccc', '#ccc', '#ccc', '#ccc'] : ['#666', '#666', '#666', '#666', '#666'], 
        fontSize: '11px',
        fontWeight: 600
      } 
    }
  },
  tooltip: {
    theme: colors.value.isDark ? 'dark' : 'light'
  }
}));

const radarChartSeries = computed(() => {
  if (!props.comparisonData) return [];
  const empA = props.comparisonData.employee_a;
  const empB = props.comparisonData.employee_b;

  const maxVals = {
    sales: Math.max(empA.sales, empB.sales, 100),
    units: Math.max(empA.units, empB.units, 10),
    tasks: Math.max(empA.tasks_completed, empB.tasks_completed, 5),
    inv: Math.max(empA.inventory_counted, empB.inventory_counted, 10),
    strat: Math.max(empA.strategic_units, empB.strategic_units, 5)
  };

  return [
    {
      name: `${empA.name}`,
      data: [
        ((empA.sales / maxVals.sales) * 100).toFixed(0),
        ((empA.units / maxVals.units) * 100).toFixed(0),
        ((empA.tasks_completed / maxVals.tasks) * 100).toFixed(0),
        ((empA.inventory_counted / maxVals.inv) * 100).toFixed(0),
        ((empA.strategic_units / maxVals.strat) * 100).toFixed(0)
      ]
    },
    {
      name: `${empB.name}`,
      data: [
        ((empB.sales / maxVals.sales) * 100).toFixed(0),
        ((empB.units / maxVals.units) * 100).toFixed(0),
        ((empB.tasks_completed / maxVals.tasks) * 100).toFixed(0),
        ((empB.inventory_counted / maxVals.inv) * 100).toFixed(0),
        ((empB.strategic_units / maxVals.strat) * 100).toFixed(0)
      ]
    }
  ];
});

const comparisonKeys = [
  { k: 'points', l: 'Puntos de Honor' },
  { k: 'sales', l: 'Ventas USD' },
  { k: 'units', l: 'Unidades Vendidas' },
  { k: 'strategic_units', l: 'Ventas Estratégicas' },
  { k: 'tasks_completed', l: 'Tareas Completadas' },
  { k: 'inventory_counted', l: 'Inventario Contado' },
  { k: 'invoices_processed', l: 'Facturas Procesadas' }
];
</script>

<template>
  <div class="px-1">
    <VRow class="mb-6" dense>
      <VCol cols="12" md="6">
        <VCard class="rounded-lg border h-100">
          <VCardItem class="py-3 border-b">
            <VCardTitle class="text-subtitle-2 font-weight-bold text-uppercase">Configuración Cara a Cara</VCardTitle>
          </VCardItem>
          <VCardText class="pa-6">
            <VRow>
              <VCol cols="12" sm="6">
                <VSelect 
                  :model-value="employeeA" 
                  @update:model-value="val => emit('update:employeeA', val)"
                  :items="employees" 
                  item-title="name" 
                  item-value="id" 
                  label="Vendedor A" 
                  placeholder="Seleccionar..."
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <VSelect 
                  :model-value="employeeB" 
                  @update:model-value="val => emit('update:employeeB', val)"
                  :items="employees" 
                  item-title="name" 
                  item-value="id" 
                  label="Vendedor B" 
                  placeholder="Seleccionar..."
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                />
              </VCol>
            </VRow>
            <VBtn
              block
              color="primary"
              prepend-icon="tabler-swords"
              class="mt-4 font-weight-bold"
              :loading="compareLoading"
              :disabled="!employeeA || !employeeB"
              @click="emit('compare')"
            >
              Comparar Rendimiento
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
      
      <VCol cols="12" md="6">
        <VCard class="rounded-lg border h-100" v-if="comparisonData || compareLoading">
          <VCardItem class="py-3 border-b">
            <VCardTitle class="text-subtitle-2 font-weight-bold text-uppercase">Radar de Rendimiento</VCardTitle>
          </VCardItem>
          <VCardText class="pa-4 d-flex justify-center align-center" style="min-height: 280px;">
            <VProgressCircular v-if="compareLoading" indeterminate color="primary" />
            <VueApexCharts v-else height="280" width="100%" type="radar" :options="radarChartOptions" :series="radarChartSeries" />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard v-if="comparisonData && !compareLoading" class="rounded-lg border mb-6">
      <VTable class="comparison-table" density="comfortable">
        <thead>
          <tr>
            <th class="text-center font-weight-bold text-subtitle-2 text-primary" style="width: 38%;">
              {{ comparisonData.employee_a.name }} {{ comparisonData.employee_a.last_name || '' }}
            </th>
            <th class="text-center text-overline text-medium-emphasis font-weight-bold" style="width: 24%;">
              MÉTRICA
            </th>
            <th class="text-center font-weight-bold text-subtitle-2 text-secondary" style="width: 38%;">
              {{ comparisonData.employee_b.name }} {{ comparisonData.employee_b.last_name || '' }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="key in comparisonKeys" :key="key.k">
            <td class="text-center font-weight-bold" :class="comparisonData.employee_a[key.k] > comparisonData.employee_b[key.k] ? 'text-success' : ''">
              {{ key.k === 'sales' ? formatCurrency(comparisonData.employee_a[key.k]) : formatNumber(comparisonData.employee_a[key.k]) }}
            </td>
            <td class="text-center text-caption font-weight-bold text-medium-emphasis text-uppercase">
              {{ key.l }}
            </td>
            <td class="text-center font-weight-bold" :class="comparisonData.employee_b[key.k] > comparisonData.employee_a[key.k] ? 'text-success' : ''">
              {{ key.k === 'sales' ? formatCurrency(comparisonData.employee_b[key.k]) : formatNumber(comparisonData.employee_b[key.k]) }}
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </div>
</template>
