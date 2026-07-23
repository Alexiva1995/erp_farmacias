<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from '@/plugins/axios';
import VueApexCharts from 'vue3-apexcharts';

// --- ESTADO ---
const loading = ref(false);
const detailLoading = ref(false);
const compareLoading = ref(false);
const errorAlert = ref({ show: false, text: '' });

const startDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substr(0, 10));
const endDate = ref(new Date().toISOString().substr(0, 10));
const dashboardData = ref(null);
const selectedEmployee = ref(null);
const employeeDetail = ref(null);

// Face-Off
const compareMode = ref(false);
const employeeA = ref(null);
const employeeB = ref(null);
const comparisonData = ref(null);

// --- FORMATEO ---
const formatCurrency = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
const formatNumber = (value) => new Intl.NumberFormat('en-US').format(value);

// --- MOSTRAR ERROR ---
const showError = (msg) => {
  errorAlert.value = { show: true, text: msg };
  setTimeout(() => {
    errorAlert.value.show = false;
  }, 4000);
};

// --- CARGA DE DATOS ---
const fetchDashboard = async () => {
  loading.value = true;
  try {
    const params = { start_date: startDate.value, end_date: endDate.value };
    const { data } = await axios.get('/bi/employees/dashboard', { params });
    dashboardData.value = data;
  } catch (error) {
    showError("No se pudo cargar el Balanced Scorecard. Intente nuevamente.");
    console.error("Error al cargar Balanced Scorecard:", error);
  } finally {
    loading.value = false;
  }
};

const fetchDetail = async (id) => {
  detailLoading.value = true;
  selectedEmployee.value = id;
  try {
    const params = { start_date: startDate.value, end_date: endDate.value };
    const { data } = await axios.get(`/bi/employees/${id}/detail`, { params });
    employeeDetail.value = data;
  } catch (error) {
    showError("No se pudo cargar el detalle del vendedor.");
    console.error("Error al cargar detalle de empleado:", error);
  } finally {
    detailLoading.value = false;
  }
};

const fetchComparison = async () => {
  if (!employeeA.value || !employeeB.value) return;
  compareLoading.value = true;
  try {
    const params = { 
        start_date: startDate.value, 
        end_date: endDate.value,
        employee_a: employeeA.value,
        employee_b: employeeB.value
    };
    const { data } = await axios.get('/bi/employees/compare', { params });
    comparisonData.value = data;
  } catch (error) {
    showError("Error al generar la comparación.");
    console.error("Error al cargar comparativa:", error);
  } finally {
    compareLoading.value = false;
  }
};

onMounted(fetchDashboard);

// Watcher unificado para refrescar Dashboard y Detalle si está abierto al cambiar fechas
watch([startDate, endDate], () => {
  fetchDashboard();
  if (selectedEmployee.value) {
    fetchDetail(selectedEmployee.value);
  }
  if (employeeA.value && employeeB.value && compareMode.value) {
    fetchComparison();
  }
});

// --- GRÁFICOS ---

// 1. Histórico Doble Eje (Ventas vs Unidades)
const historyChartOptions = computed(() => ({
  chart: { toolbar: { show: false } },
  stroke: { width: [4, 0], curve: 'smooth' },
  plotOptions: { bar: { columnWidth: '50%', borderRadius: 4 } },
  colors: ['#E20074', '#7A0099'],
  labels: employeeDetail.value?.history?.map(h => h.label) || [],
  yaxis: [
    { title: { text: 'Ventas (USD)' }, labels: { style: { colors: '#E20074' } } },
    { opposite: true, title: { text: 'Unidades' }, labels: { style: { colors: '#7A0099' } } }
  ],
  tooltip: { shared: true, intersect: false, theme: 'dark' }
}));

const historyChartSeries = computed(() => [
  { name: 'Ventas USD', type: 'line', data: employeeDetail.value?.history?.map(h => h.sales) || [] },
  { name: 'Unidades', type: 'column', data: employeeDetail.value?.history?.map(h => h.units) || [] }
]);

// 2. Radar Chart (Face-Off)
const radarChartOptions = computed(() => ({
  chart: { toolbar: { show: false }, dropShadow: { enabled: true, blur: 1, left: 1, top: 1 } },
  colors: ['#E20074', '#7A0099'],
  stroke: { width: 2 },
  fill: { opacity: 0.1 },
  markers: { size: 0 },
  xaxis: {
    categories: ['Ventas', 'Unidades', 'Tareas', 'Inventario', 'Estratégicos'],
    labels: { style: { colors: '#a3a3a3', fontSize: '10px' } }
  }
}));

const radarChartSeries = computed(() => {
  if (!comparisonData.value) return [];
  const empA = comparisonData.value.employee_a;
  const empB = comparisonData.value.employee_b;

  // Normalización dinámica basada en los valores máximos del conjunto o un mínimo base
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

// Semáforos
const getStatusColor = (val, target) => {
    const ratio = (val / target) * 100;
    if (ratio >= 100) return 'success';
    if (ratio >= 80) return 'warning';
    return 'error';
};

</script>

<template>
  <VContainer fluid class="employee-performance pa-0">
    
    <!-- Header & Filtros -->
    <VCard class="mb-6 rounded-lg border shadow-sm bg-surface">
      <VCardText class="pa-4">
        <VRow align="center" no-gutters class="gap-4">
          <div class="d-flex align-center">
            <VAvatar color="primary" variant="tonal" size="44" rounded="lg" class="me-3">
              <VIcon icon="tabler-chart-bar-popular" size="26" />
            </VAvatar>
            <div>
              <h2 class="text-h6 font-weight-black mb-0">Cuadro de Mando Integral RRHH</h2>
              <p class="text-[11px] text-disabled mb-0 uppercase font-weight-bold">Análisis de Personal y Gamificación</p>
            </div>
          </div>

          <VSpacer />

          <div class="d-flex align-center gap-2">
            <VBtnToggle v-model="compareMode" mandatory density="compact" color="primary" variant="tonal" class="me-4 rounded-lg overflow-hidden border">
                <VBtn :value="false" class="px-3">
                  <VIcon icon="tabler-trophy" size="22" />
                  <VTooltip activator="parent">Ranking de Empleados</VTooltip>
                </VBtn>
                <VBtn :value="true" class="px-3">
                  <VIcon icon="tabler-arrows-cross" size="22" />
                  <VTooltip activator="parent">Cara a Cara (Comparativa)</VTooltip>
                </VBtn>
            </VBtnToggle>

            <AppTextField v-model="startDate" type="date" density="compact" hide-details class="premium-input" />
            <AppTextField v-model="endDate" type="date" density="compact" hide-details class="premium-input" />
            
            <VBtn icon variant="tonal" color="primary" size="38" :loading="loading" @click="fetchDashboard">
              <VIcon icon="tabler-refresh" size="20" />
            </VBtn>
          </div>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Alerta de Errores de la API -->
    <VAlert v-if="errorAlert.show" type="error" variant="tonal" closable class="mb-4 mx-1" @click:close="errorAlert.show = false">
      {{ errorAlert.text }}
    </VAlert>

    <div v-if="loading && !dashboardData" class="d-flex flex-column justify-center align-center h-[60vh] gap-3">
      <VProgressCircular indeterminate color="primary" size="40" />
      <span class="text-disabled text-xs">Cargando Balanced Scorecard...</span>
    </div>

    <!-- VISTA COMPARATIVA (FACE-OFF / CARA A CARA) -->
    <div v-else-if="compareMode" class="px-1">
        <VRow class="mb-6" dense>
            <VCol cols="12" md="6">
                <VCard class="rounded-lg border shadow-sm h-100">
                    <VCardItem class="py-3 border-b bg-light-primary">
                        <VCardTitle class="text-subtitle-2 font-weight-black uppercase">Configuración Cara a Cara</VCardTitle>
                    </VCardItem>
                    <VCardText class="pa-6">
                        <VRow>
                            <VCol cols="6">
                                <AppSelect 
                                    v-model="employeeA" 
                                    :items="dashboardData?.employees || []" 
                                    item-title="name" 
                                    item-value="id" 
                                    label="Vendedor A" 
                                    placeholder="Seleccionar..."
                                />
                            </VCol>
                            <VCol cols="6">
                                <AppSelect 
                                    v-model="employeeB" 
                                    :items="dashboardData?.employees || []" 
                                    item-title="name" 
                                    item-value="id" 
                                    label="Vendedor B" 
                                    placeholder="Seleccionar..."
                                />
                            </VCol>
                        </VRow>
                        <VBtn block color="primary" prepend-icon="tabler-swords" class="mt-4 font-weight-black" :loading="compareLoading" :disabled="!employeeA || !employeeB" @click="fetchComparison">
                            Comparar Rendimiento
                        </VBtn>
                    </VCardText>
                </VCard>
            </VCol>
            
            <VCol cols="12" md="6">
                <VCard class="rounded-lg border shadow-sm h-100" v-if="comparisonData || compareLoading">
                    <VCardItem class="py-3 border-b">
                        <VCardTitle class="text-subtitle-2 font-weight-black uppercase">Radar de Rendimiento</VCardTitle>
                    </VCardItem>
                    <VCardText class="pa-4 d-flex justify-center align-center min-h-[300px]">
                        <VProgressCircular v-if="compareLoading" indeterminate color="primary" />
                        <VueApexCharts v-else height="300" width="100%" type="radar" :options="radarChartOptions" :series="radarChartSeries" />
                    </VCardText>
                </VCard>
            </VCol>
        </VRow>

        <VCard v-if="comparisonData && !compareLoading" class="rounded-lg border shadow-sm overflow-hidden mb-6">
            <VTable class="comparison-table">
                <thead>
                    <tr class="bg-light">
                        <th class="text-center font-weight-black">{{ comparisonData.employee_a.name }}</th>
                        <th class="text-center bg-white text-disabled">MÉTRICA</th>
                        <th class="text-center font-weight-black">{{ comparisonData.employee_b.name }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="key in [
                      { k: 'points', l: 'Puntos de Honor' },
                      { k: 'sales', l: 'Ventas USD' },
                      { k: 'units', l: 'Unidades Vendidas' },
                      { k: 'strategic_units', l: 'Ventas Estratégicas' },
                      { k: 'tasks_completed', l: 'Tareas Completadas' },
                      { k: 'inventory_counted', l: 'Inventario Contado' },
                      { k: 'invoices_processed', l: 'Facturas Cargadas' }
                    ]" :key="key.k">
                        <td class="text-center font-weight-black" :class="comparisonData.employee_a[key.k] > comparisonData.employee_b[key.k] ? 'text-success' : ''">
                            {{ key.k === 'sales' ? formatCurrency(comparisonData.employee_a[key.k]) : formatNumber(comparisonData.employee_a[key.k]) }}
                        </td>
                        <td class="text-center text-[10px] font-weight-bold uppercase opacity-60 bg-light-surface">{{ key.l }}</td>
                        <td class="text-center font-weight-black" :class="comparisonData.employee_b[key.k] > comparisonData.employee_a[key.k] ? 'text-success' : ''">
                            {{ key.k === 'sales' ? formatCurrency(comparisonData.employee_b[key.k]) : formatNumber(comparisonData.employee_b[key.k]) }}
                        </td>
                    </tr>
                </tbody>
            </VTable>
        </VCard>
    </div>

    <!-- VISTA PRINCIPAL (RANKING & DRILL-DOWN) -->
    <div v-else-if="dashboardData" class="px-1">
      
      <!-- Hall of Fame / Salón de la Fama -->
      <VRow class="mb-6" dense>
        <VCol cols="12" md="3" v-for="(hero, key) in dashboardData.hall_of_fame" :key="key">
          <VCard border class="rounded-lg shadow-sm h-100 bg-surface position-relative overflow-hidden">
            <div class="position-absolute top-0 right-0 pa-2">
                <VIcon :icon="key === 'employee_of_the_month' ? 'tabler-crown' : 'tabler-medal'" :color="key === 'employee_of_the_month' ? 'warning' : 'primary'" size="40" class="opacity-10" />
            </div>
            <VCardText class="pa-4 d-flex align-center">
              <VAvatar size="60" class="me-4 border-2 border-primary border-opacity-50">
                <VImg :src="hero?.photo || 'https://ui-avatars.com/api/?name='+hero?.name" />
              </VAvatar>
              <div>
                <p class="text-[10px] text-disabled mb-0 font-weight-bold uppercase">
                  {{ key === 'employee_of_the_month' ? 'Empleado del Mes' : (key === 'top_seller' ? 'Mejor Vendedor' : key.replace(/_/g, ' ')) }}
                </p>
                <h4 class="text-subtitle-1 font-weight-black mb-0">{{ hero?.name }} {{ hero?.last_name }}</h4>
                <VChip size="x-small" color="primary" class="font-weight-black mt-1">{{ formatNumber(hero?.points) }} PTS</VChip>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <VRow dense>
        <!-- Ranking List -->
        <VCol cols="12" md="5">
          <VCard class="rounded-lg border shadow-sm overflow-hidden h-100">
            <VCardItem class="py-3 border-b bg-light-primary">
              <VCardTitle class="text-subtitle-2 font-weight-black uppercase">Ranking Integral (Gamificación)</VCardTitle>
            </VCardItem>
            <VTable density="compact" hover class="analytics-table clickable-rows">
              <thead>
                <tr>
                  <th class="text-center">#</th>
                  <th>Vendedor</th>
                  <th class="text-right">Venta USD</th>
                  <th class="text-center">Pts</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(emp, idx) in dashboardData.employees" :key="emp.id" @click="fetchDetail(emp.id)" :class="{ 'bg-light-primary': selectedEmployee === emp.id }">
                  <td class="text-center font-weight-black opacity-30">{{ idx + 1 }}</td>
                  <td>
                    <div class="d-flex align-center py-1">
                        <VAvatar size="24" class="me-2"><VImg :src="emp.photo || 'https://ui-avatars.com/api/?name='+emp.name" /></VAvatar>
                        <span class="text-[11px] font-weight-bold">{{ emp.name }}</span>
                    </div>
                  </td>
                  <td class="text-right font-weight-bold">{{ formatCurrency(emp.sales) }}</td>
                  <td class="text-center">
                    <VChip size="x-small" label color="primary" variant="tonal" class="font-weight-black">{{ formatNumber(emp.points) }}</VChip>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCard>
        </VCol>

        <!-- Drill-Down Detail -->
        <VCol cols="12" md="7">
            <div v-if="!employeeDetail && !detailLoading" class="d-flex flex-column justify-center align-center h-100 border rounded-lg border-dashed opacity-40 py-10">
                <VIcon icon="tabler-click" size="48" class="mb-2" />
                <p class="font-weight-bold text-center px-4">Selecciona un vendedor para ver su ficha detallada</p>
            </div>
            
            <div v-else-if="detailLoading" class="d-flex flex-column gap-4">
                <VSkeletonLoader type="card, article" />
            </div>

            <div v-else-if="employeeDetail">
                <!-- Detail Scorecard -->
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

                <!-- History Chart -->
                <VCard class="rounded-lg border shadow-sm mb-4">
                    <VCardItem class="py-3 border-b">
                        <VCardTitle class="text-subtitle-2 font-weight-black uppercase">Evolución: Ventas vs Unidades</VCardTitle>
                    </VCardItem>
                    <VCardText class="pa-4">
                        <VueApexCharts height="280" type="line" :options="historyChartOptions" :series="historyChartSeries" />
                    </VCardText>
                </VCard>

                <!-- Detailed Metrics Grid -->
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
                                    <template #append><span class="font-weight-black text-info">{{ employeeDetail.metrics.conversion_rate.toFixed(1) }}%</span></template>
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
        </VCol>
      </VRow>
    </div>
  </VContainer>
</template>

<style scoped>
.employee-performance { background-color: transparent; }
.bg-surface { background-color: #fff !important; }
.bg-light-primary { background-color: #fff0f6; }
.bg-light-surface { background-color: #fafafa; }
.bg-light-error { background-color: #fff5f5; }

.font-weight-black { font-weight: 900 !important; }
.uppercase { text-transform: uppercase; letter-spacing: 0.5px; }

.clickable-rows tbody tr { cursor: pointer; transition: all 0.2s; }
.clickable-rows tbody tr:hover { background-color: #f1f5f9; }

.text-super-xs { font-size: 9px; line-height: 1; }

.comparison-table td { padding: 12px !important; border-bottom: 1px solid #f1f5f9 !important; }

.premium-input { max-width: 150px; }

.analytics-table :deep(th) {
  background-color: #f8fafc !important;
  color: #64748b !important;
  font-size: 10px !important;
  border-bottom: 2px solid #e2e8f0 !important;
}

.analytics-table :deep(td) {
  font-size: 0.75rem !important;
  border-bottom: 1px solid #f1f5f9 !important;
}

.gap-2 { gap: 8px; }
.gap-4 { gap: 16px; }
</style>
