<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from '@/plugins/axios';
import VueApexCharts from 'vue3-apexcharts';

// --- ESTADO ---
const loading = ref(false);
const startDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substr(0, 10));
const endDate = ref(new Date().toISOString().substr(0, 10));
const analyticsData = ref(null);

// --- FORMATEO ---
const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
};

const formatNumber = (value) => {
  return new Intl.NumberFormat('en-US').format(value);
};

const translateSegment = (key) => {
  const translations = {
    platinum: 'Platino',
    gold: 'Oro',
    silver: 'Plata',
    bronze: 'Bronce'
  };
  return translations[key] || key;
};

// --- CARGA DE DATOS ---
const fetchAnalytics = async () => {
  loading.value = true;
  try {
    const params = {
      start_date: startDate.value,
      end_date: endDate.value
    };
    const { data } = await axios.get('/bi/customers/dashboard', { params });
    analyticsData.value = data;
  } catch (error) {
    console.error("Error al cargar analítica de clientes:", error);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchAnalytics();
});

watch([startDate, endDate], () => {
  fetchAnalytics();
});

// --- CONFIGURACIÓN DE GRÁFICOS ---

// 1. Velocidad de Adquisición (Líneas)
const acquisitionChartOptions = computed(() => ({
  chart: { 
    type: 'line', 
    toolbar: { show: false }, 
    fontFamily: 'Inter, sans-serif',
    dropShadow: { enabled: true, top: 10, left: 0, blur: 3, color: '#E20074', opacity: 0.1 }
  },
  stroke: { curve: 'smooth', width: 4 },
  colors: ['#E20074'],
  fill: {
    type: 'gradient',
    gradient: { shade: 'dark', gradientToColors: ['#7A0099'], shadeIntensity: 1, type: 'horizontal', opacityFrom: 1, opacityTo: 1, stops: [0, 100] }
  },
  xaxis: {
    categories: analyticsData.value?.growth?.new_customers_daily?.map(d => d.date) || [],
    labels: { style: { fontSize: '10px', colors: '#a3a3a3' } },
    axisBorder: { show: false }
  },
  yaxis: { labels: { style: { colors: '#a3a3a3' } } },
  grid: { borderColor: 'rgba(144, 164, 174, 0.05)' },
  tooltip: { theme: 'dark' },
  markers: { size: 4, colors: ['#E20074'], strokeColors: '#fff', strokeWidth: 2, hover: { size: 7 } }
}));

// 2. Frecuencia de Compra (Donut)
const frequencyDonutOptions = computed(() => ({
  labels: Object.keys(analyticsData.value?.frequency || {}).map(f => `${f} ${f === '1' ? 'Orden' : 'Órdenes'}`),
  colors: ['#E20074', '#7A0099', '#28C76F', '#FF9F43', '#EA5455'],
  plotOptions: {
    pie: {
      donut: {
        size: '75%',
        labels: {
          show: true,
          total: {
            show: true,
            label: 'Clientes',
            fontSize: '12px',
            fontWeight: 900,
            formatter: () => analyticsData.value?.kpis?.total_customers || 0
          }
        }
      }
    }
  },
  legend: { position: 'bottom', fontSize: '11px' }
}));

// 3. Pirámide de Valor (Treemap)
const valueTreemapOptions = computed(() => ({
  legend: { show: false },
  chart: { height: 350, type: 'treemap', toolbar: { show: false } },
  colors: ['#E20074', '#7A0099', '#FF9F43', '#EA5455'],
  plotOptions: {
    treemap: {
      distributed: true,
      enableShades: false
    }
  },
  tooltip: {
    theme: 'dark',
    y: {
      formatter: (value) => formatCurrency(value)
    }
  }
}));

const valueTreemapSeries = computed(() => [
  {
    data: [
      { x: 'Platino (5%)', y: Number((analyticsData.value?.segmentation?.platinum?.revenue || 0).toFixed(2)) },
      { x: 'Oro (15%)', y: Number((analyticsData.value?.segmentation?.gold?.revenue || 0).toFixed(2)) },
      { x: 'Plata (30%)', y: Number((analyticsData.value?.segmentation?.silver?.revenue || 0).toFixed(2)) },
      { x: 'Bronce (50%)', y: Number((analyticsData.value?.segmentation?.bronze?.revenue || 0).toFixed(2)) },
    ]
  }
]);

// Lógica para color de celdas en cohortes
const getCohortColor = (percentage) => {
  if (!percentage) return 'transparent';
  const opacity = percentage / 100;
  return `rgba(226, 0, 116, ${opacity})`;
};

const getTextColor = (percentage) => {
  return percentage > 50 ? '#fff' : '#444';
};

</script>

<template>
  <VContainer fluid class="customer-analytics pa-0">
    
    <!-- Filtros Superiores -->
    <VCard class="mb-6 rounded-lg border shadow-sm bg-surface">
      <VCardText class="pa-4">
        <VRow align="center" no-gutters class="gap-4">
          <div class="d-flex align-center">
            <VAvatar color="primary" variant="tonal" size="40" rounded="lg" class="me-3">
              <VIcon icon="tabler-users-group" size="24" />
            </VAvatar>
            <div>
              <h2 class="text-h6 font-weight-black mb-0">Análisis de Cartera de Clientes</h2>
              <p class="text-[11px] text-disabled mb-0 uppercase font-weight-bold">Inteligencia de Clientes y Recurrencia</p>
            </div>
          </div>

          <VSpacer />

          <div class="d-flex align-center gap-2">
            <AppTextField v-model="startDate" type="date" density="compact" hide-details class="premium-input" />
            <span class="text-disabled">a</span>
            <AppTextField v-model="endDate" type="date" density="compact" hide-details class="premium-input" />
            
            <VBtn icon variant="flat" color="primary" size="38" :loading="loading" @click="fetchAnalytics">
              <VIcon icon="tabler-refresh" size="20" />
            </VBtn>
          </div>
        </VRow>
      </VCardText>
    </VCard>

    <div v-if="loading && !analyticsData" class="d-flex justify-center align-center h-[60vh]">
      <VProgressCircular indeterminate color="primary" size="40" />
    </div>

    <div v-else-if="analyticsData" class="px-1">
      
      <!-- Fila 1: Métricas de Oro -->
      <VRow class="mb-6" dense>
        <VCol cols="12" md="3" sm="6" v-for="(kpi, idx) in [
          { title: 'Tasa de Retención (CRR)', value: analyticsData.kpis.crr + '%', icon: 'tabler-user-check', color: 'primary', desc: 'Fidelidad del periodo' },
          { title: 'Tasa de Recompra', value: analyticsData.kpis.repurchase_rate.toFixed(1) + '%', icon: 'tabler-repeat', color: 'success', desc: 'Clientes recurrentes' },
          { title: 'Tasa de Abandono (Churn)', value: analyticsData.kpis.churn_rate.toFixed(1) + '%', icon: 'tabler-user-minus', color: 'error', desc: 'Inactivos > 90 días' },
          { title: 'LTV Promedio (Valor de Vida)', value: formatCurrency(analyticsData.kpis.avg_ltv), icon: 'tabler-coin', color: 'warning', desc: 'Valor de vida promedio del cliente' }
        ]" :key="idx">
          <VCard border class="rounded-lg shadow-sm h-100 kpi-card">
            <VCardText class="pa-4 d-flex align-center">
              <VAvatar :color="kpi.color" variant="tonal" size="48" rounded="lg" class="me-4">
                <VIcon :icon="kpi.icon" size="24" />
              </VAvatar>
              <div>
                <p class="text-[12px] text-disabled mb-0 font-weight-bold">{{ kpi.title }}</p>
                <h3 class="text-h5 font-weight-black mb-0">{{ kpi.value }}</h3>
                <p class="text-[10px] text-medium-emphasis mb-0 opacity-60">{{ kpi.desc }}</p>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Fila 2: Crecimiento y Frecuencia -->
      <VRow class="mb-6" dense>
        <VCol cols="12" md="8">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem class="py-3 border-b">
              <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
                <VIcon icon="tabler-chart-line" class="me-2 text-primary" size="20" />
                Velocidad de Adquisición (Clientes Nuevos)
              </VCardTitle>
            </VCardItem>
            <VCardText class="pa-4">
              <VueApexCharts height="300" :options="acquisitionChartOptions" :series="[{ name: 'Nuevos Clientes', data: analyticsData.growth.new_customers_daily.map(d => d.count) }]" />
            </VCardText>
          </VCard>
        </VCol>
        
        <VCol cols="12" md="4">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem class="py-3 border-b">
              <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
                <VIcon icon="tabler-chart-donut" class="me-2 text-success" size="20" />
                Frecuencia de Compra
              </VCardTitle>
            </VCardItem>
            <VCardText class="pa-4">
              <VueApexCharts height="300" :options="frequencyDonutOptions" :series="Object.values(analyticsData.frequency)" type="donut" />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Fila 3: Análisis de Cohortes (Heatmap) -->
      <VRow class="mb-6" dense>
        <VCol cols="12">
          <VCard class="rounded-lg border shadow-sm overflow-hidden">
            <VCardItem class="py-3 border-b bg-light-primary">
              <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
                <VIcon icon="tabler-table" class="me-2 text-primary" size="20" />
                Análisis de Cohortes (Retención Mensual %)
              </VCardTitle>
            </VCardItem>
            <div class="overflow-x-auto">
              <VTable class="cohort-table">
                <thead>
                  <tr>
                    <th class="cohort-header">Cohorte (Mes)</th>
                    <th class="cohort-header text-center">N</th>
                    <th v-for="i in 12" :key="i" class="cohort-header text-center">Mes {{ i-1 }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="cohort in analyticsData.cohorts" :key="cohort.month">
                    <td class="font-weight-black text-primary bg-light">{{ cohort.month }}</td>
                    <td class="text-center font-weight-bold bg-light">{{ formatNumber(cohort.initial) }}</td>
                    <td 
                      v-for="i in 12" 
                      :key="i" 
                      class="text-center cohort-cell"
                      :style="{ backgroundColor: getCohortColor(cohort.data[i-1]?.percentage), color: getTextColor(cohort.data[i-1]?.percentage) }"
                    >
                      {{ cohort.data[i-1] ? cohort.data[i-1].percentage + '%' : '-' }}
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>
          </VCard>
        </VCol>
      </VRow>

      <!-- Fila 4: Segmentación y Clientes en Riesgo -->
      <VRow dense>
        <VCol cols="12" md="6">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem class="py-3 border-b">
              <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
                <VIcon icon="tabler-pyramid" class="me-2 text-warning" size="20" />
                Pirámide de Valor (Aporte por Segmento)
              </VCardTitle>
            </VCardItem>
            <VCardText class="pa-4">
              <VueApexCharts height="300" :options="valueTreemapOptions" :series="valueTreemapSeries" type="treemap" />
              <div class="mt-4">
                <VRow dense>
                  <VCol cols="6" v-for="(val, key) in analyticsData.segmentation" :key="key" v-if="typeof val === 'object'">
                    <div class="d-flex align-center mb-2 pa-2 rounded border border-opacity-10">
                      <div class="segment-indicator me-2" :class="key"></div>
                      <div>
                        <div class="text-[10px] font-weight-black uppercase opacity-60">{{ translateSegment(key) }}</div>
                        <div class="text-subtitle-2 font-weight-black">{{ formatCurrency(val.revenue) }}</div>
                        <div class="text-[9px] text-disabled">{{ val.count }} clientes | Prom: {{ formatCurrency(val.avg_per_client) }}</div>
                      </div>
                    </div>
                  </VCol>
                </VRow>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" md="6">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem class="py-3 border-b bg-light-error">
              <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
                <VIcon icon="tabler-alert-triangle" class="me-2 text-error" size="20" />
                Clientes Críticos en Riesgo (RFM)
              </VCardTitle>
            </VCardItem>
            <VTable density="compact" class="analytics-table">
              <thead>
                <tr>
                  <th class="text-uppercase text-[10px] font-weight-black">Cliente</th>
                  <th class="text-uppercase text-[10px] font-weight-black text-right">Gasto USD</th>
                  <th class="text-uppercase text-[10px] font-weight-black text-center">Última Compra</th>
                  <th class="text-uppercase text-[10px] font-weight-black text-center">Días</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="client in analyticsData.at_risk" :key="client.id">
                  <td>
                    <div class="font-weight-black text-primary text-[11px]">{{ client.name }} {{ client.last_name }}</div>
                    <div class="text-[10px] text-disabled">{{ client.phone }}</div>
                  </td>
                  <td class="text-right font-weight-black text-error">{{ formatCurrency(client.monetary) }}</td>
                  <td class="text-center text-[10px]">{{ client.last_order_date }}</td>
                  <td class="text-center">
                    <VChip size="x-small" label color="error" variant="tonal" class="font-weight-black">{{ client.recency_days }}d</VChip>
                  </td>
                </tr>
              </tbody>
            </VTable>
            <VCardText v-if="analyticsData.at_risk.length === 0" class="text-center pa-10 text-disabled">
              No hay clientes críticos identificados actualmente.
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

    </div>
  </VContainer>
</template>

<style scoped>
.customer-analytics {
  background-color: transparent;
}

.bg-surface {
  background-color: #fff !important;
}

.kpi-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.kpi-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important;
}

.bg-light-primary { background-color: #fff0f6; }
.bg-light-error { background-color: #fff5f5; }

.font-weight-black { font-weight: 900 !important; }
.uppercase { text-transform: uppercase; letter-spacing: 0.5px; }

.cohort-table { border-collapse: collapse; width: 100%; }
.cohort-header { 
  background-color: #f8fafc !important; 
  font-size: 10px !important; 
  font-weight: 900 !important;
  text-transform: uppercase;
  border-bottom: 2px solid #e2e8f0 !important;
}
.cohort-cell {
  font-size: 10px !important;
  font-weight: 700 !important;
  border: 1px solid rgba(0,0,0,0.05) !important;
  min-width: 60px;
}

.segment-indicator { width: 12px; height: 12px; border-radius: 3px; }
.platinum { background-color: #E20074; }
.gold { background-color: #28C76F; }
.silver { background-color: #FF9F43; }
.bronze { background-color: #EA5455; }

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

.premium-input {
  max-width: 150px;
}
</style>
