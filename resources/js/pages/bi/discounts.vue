<script setup>
import AppFilterBase from '@/components/AppFilterBase.vue';
import { useCurrencyConverter } from '@/components/useCurrencyConverter';
import { computed, onMounted, ref, watch } from 'vue';
import axios from '@/plugins/axios';
import { formatPrice, formatDateSimple } from "@/utils/formatters";
import VueApexCharts from 'vue3-apexcharts';

const { formatCurrency } = useCurrencyConverter();

// --- ESTADOS Y FILTROS ---
const loading = ref(false);
const auditLoading = ref(false);

const defaultDashboardData = {
  kpis: {
    total_money_given: 0,
    total_sales_with_discount: 0,
    offer_penetration: 0,
    avg_global_discount: 0
  },
  distribution: [],
  highlights: {
    pack_vs_individual: [],
    medical_recipe_conversion: 0,
    expiry_recovery_amount: 0
  },
  rankings: {
    top_offers: [],
    bottom_offers: []
  }
};

const dashboardData = ref(JSON.parse(JSON.stringify(defaultDashboardData)));
const auditData = ref({ data: [], total: 0 });
const auditPage = ref(1);
const auditItemsPerPage = ref(10);
const selectedDiscountType = ref('Todos');

const startDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]);
const endDate = ref(new Date().toISOString().split('T')[0]);

// --- CARGA DE DATOS ---
const fetchDashboard = async () => {
  loading.value = true;
  try {
    const params = {
      start_date: startDate.value,
      end_date: endDate.value
    };
    const { data } = await axios.get('/bi/discounts/dashboard', { params });
    if (data) {
      dashboardData.value = data;
    }
  } catch (error) {
    console.error("Error al cargar dashboard de descuentos:", error);
  } finally {
    loading.value = false;
  }
};

const fetchAudit = async () => {
  auditLoading.value = true;
  try {
    const params = {
      start_date: startDate.value,
      end_date: endDate.value,
      page: auditPage.value,
      itemsPerPage: auditItemsPerPage.value,
      discount_type: selectedDiscountType.value
    };
    const { data } = await axios.get('/bi/discounts/audit', { params });
    if (data) {
      auditData.value = data;
    }
  } catch (error) {
    console.error("Error al cargar auditoría de descuentos:", error);
  } finally {
    auditLoading.value = false;
  }
};

onMounted(() => {
  fetchDashboard();
  fetchAudit();
});

watch([startDate, endDate], () => {
  fetchDashboard();
  auditPage.value = 1;
  fetchAudit();
});

watch(selectedDiscountType, () => {
  auditPage.value = 1;
  fetchAudit();
});

watch([auditPage, auditItemsPerPage], () => {
  fetchAudit();
});

// --- CONFIGURACIÓN DE GRÁFICOS ---

// 1. Distribución por Tipo (Dona)
const distributionChartOptions = computed(() => ({
  chart: { type: 'donut', toolbar: { show: false } },
  labels: dashboardData.value.distribution.map(d => d.promo_type),
  colors: ['#E20074', '#7A0099', '#28C76F', '#FF9F43', '#EA5455', '#1E1E1E', '#BDBDBD'],
  legend: { position: 'bottom' },
  dataLabels: { enabled: true, formatter: (val) => `${val.toFixed(1)}%` },
  plotOptions: { 
    pie: { 
      donut: { 
        size: '70%', 
        labels: { 
          show: true, 
          total: { 
            show: true, 
            label: 'Total Cedido',
            formatter: () => formatCurrency(dashboardData.value.kpis.total_money_given)
          } 
        } 
      } 
    } 
  },
  tooltip: {
    y: {
      formatter: (val) => formatCurrency(val)
    }
  }
}));

const distributionChartSeries = computed(() => dashboardData.value.distribution.map(d => Number(d.money_given)));

// 2. Distribución por Tipo (Barras - Transacciones)
const transactionChartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  plotOptions: { bar: { horizontal: false, borderRadius: 4, columnWidth: '50%' } },
  dataLabels: { enabled: false },
  xaxis: {
    categories: dashboardData.value.distribution.map(d => d.promo_type),
  },
  colors: ['#E20074'],
  tooltip: { y: { formatter: (val) => `${val} Tickets` } }
}));

const transactionChartSeries = computed(() => ([{
  name: 'Tickets con Promoción',
  data: dashboardData.value.distribution.map(d => Number(d.transaction_count))
}]));

// 3. Comparativa Packs vs Individual (Radial)
const packVsIndividualOptions = computed(() => ({
  chart: { type: 'pie', toolbar: { show: false } },
  labels: ['Packs', 'Individuales'],
  colors: ['#E20074', '#7A0099'],
  legend: { position: 'bottom' },
  stroke: { width: 0 }
}));

const packVsIndividualSeries = computed(() => [
  Number(dashboardData.value.highlights.pack_vs_individual.find(i => i.name === 'Packs')?.value || 0),
  Number(dashboardData.value.highlights.pack_vs_individual.find(i => i.name === 'Individuales')?.value || 0)
]);

// Helpers
const resetFilters = () => {
  startDate.value = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
  endDate.value = new Date().toISOString().split('T')[0];
};

const getDiscountTypeColor = (type) => {
  const colors = {
    'Pack': 'primary',
    'Individual': 'success',
    'Categoría': 'warning',
    'Empresa': 'info',
    'Médico': 'error',
    'Récipe': 'secondary',
    'Caducidad': 'dark',
    'Otro': 'grey'
  };
  return colors[type] || 'grey';
};
</script>

<template>
  <VContainer fluid class="report-discounts-container pa-0">
    <div class="bi-report-grid">
      <!-- FILTROS -->
      <VCard border class="mb-4 rounded-lg shadow-sm">
        <div class="pa-4 d-flex align-center gap-4 flex-wrap">
          <div class="d-flex align-center">
            <VIcon icon="tabler-brightness-up" class="me-2 text-primary" size="24" />
            <span class="text-h6 font-weight-bold">Rendimiento de Promociones</span>
          </div>
          <VSpacer />
          <div class="d-flex align-center gap-2">
            <div style="width: 180px;">
              <AppTextField v-model="startDate" type="date" label="Desde" density="compact" hide-details />
            </div>
            <div style="width: 180px;">
              <AppTextField v-model="endDate" type="date" label="Hasta" density="compact" hide-details />
            </div>
            <VBtn icon variant="tonal" color="secondary" size="38" class="rounded-pill" @click="fetchDashboard(); fetchAudit()">
              <VIcon icon="tabler-refresh" />
              <VTooltip activator="parent" location="top">Sincronizar</VTooltip>
            </VBtn>
          </div>
        </div>
      </VCard>

      <!-- SCORECARDS (KPIs) -->
      <VRow class="mb-4">
        <VCol cols="12" sm="6" md="3">
          <VCard border class="rounded-lg overflow-hidden shadow-sm h-100">
            <div class="pa-4 d-flex align-center gap-4">
              <VAvatar color="error" variant="tonal" rounded="lg" size="48">
                <VIcon icon="tabler-gift-off" size="26" />
              </VAvatar>
              <div>
                <div class="text-h5 font-weight-bold text-error">{{ formatCurrency(dashboardData.kpis.total_money_given) }}</div>
                <div class="text-caption text-medium-emphasis uppercase font-weight-black">Total Dinero Cedido ($)</div>
              </div>
            </div>
            <VProgressLinear v-if="loading" indeterminate color="error" height="2" />
          </VCard>
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VCard border class="rounded-lg overflow-hidden shadow-sm h-100">
            <div class="pa-4 d-flex align-center gap-4">
              <VAvatar color="success" variant="tonal" rounded="lg" size="48">
                <VIcon icon="tabler-receipt-2" size="26" />
              </VAvatar>
              <div>
                <div class="text-h5 font-weight-bold text-success">{{ formatCurrency(dashboardData.kpis.total_sales_with_discount) }}</div>
                <div class="text-caption text-medium-emphasis uppercase font-weight-black">Venta con Descuento ($)</div>
              </div>
            </div>
            <VProgressLinear v-if="loading" indeterminate color="success" height="2" />
          </VCard>
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VCard border class="rounded-lg overflow-hidden shadow-sm h-100">
            <div class="pa-4 d-flex align-center gap-4">
              <VAvatar color="primary" variant="tonal" rounded="lg" size="48">
                <VIcon icon="tabler-chart-pie" size="26" />
              </VAvatar>
              <div>
                <div class="text-h5 font-weight-bold text-primary">{{ Number(dashboardData.kpis.offer_penetration || 0).toFixed(1) }}%</div>
                <div class="text-caption text-medium-emphasis uppercase font-weight-black">Penetración de Ofertas (%)</div>
              </div>
            </div>
            <VProgressLinear v-if="loading" indeterminate color="primary" height="2" />
          </VCard>
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VCard border class="rounded-lg overflow-hidden shadow-sm h-100">
            <div class="pa-4 d-flex align-center gap-4">
              <VAvatar color="info" variant="tonal" rounded="lg" size="48">
                <VIcon icon="tabler-percentage" size="26" />
              </VAvatar>
              <div>
                <div class="text-h5 font-weight-bold text-info">{{ Number(dashboardData.kpis.avg_global_discount || 0).toFixed(1) }}%</div>
                <div class="text-caption text-medium-emphasis uppercase font-weight-black">Descuento Promedio (%)</div>
              </div>
            </div>
            <VProgressLinear v-if="loading" indeterminate color="info" height="2" />
          </VCard>
        </VCol>
      </VRow>

      <!-- GRÁFICOS DE DISTRIBUCIÓN -->
      <VRow class="mb-4">
        <VCol cols="12" md="6">
          <VCard border class="rounded-lg shadow-sm h-100">
            <VCardTitle class="pa-4 border-b">
              <span class="text-h6 font-weight-bold">Distribución de Impacto ($)</span>
            </VCardTitle>
            <VCardText class="pa-4">
              <VueApexCharts v-if="dashboardData.distribution.length" type="donut" height="350" :options="distributionChartOptions" :series="distributionChartSeries" />
              <div v-else class="text-center pa-10 text-medium-emphasis">Sin datos de distribución</div>
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="12" md="6">
          <VCard border class="rounded-lg shadow-sm h-100">
            <VCardTitle class="pa-4 border-b">
              <span class="text-h6 font-weight-bold">Tickets por Tipo de Oferta</span>
            </VCardTitle>
            <VCardText class="pa-4">
              <VueApexCharts v-if="dashboardData.distribution.length" type="bar" height="350" :options="transactionChartOptions" :series="transactionChartSeries" />
              <div v-else class="text-center pa-10 text-medium-emphasis">Sin datos de transacciones</div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- MÓDULOS DE RENDIMIENTO -->
      <VRow class="mb-4">
        <VCol cols="12" md="4">
          <VCard border class="rounded-lg shadow-sm h-100">
            <VCardTitle class="pa-4 border-b">
              <span class="text-h6 font-weight-bold">Pack vs Individual</span>
            </VCardTitle>
            <VCardText class="pa-4 d-flex flex-column align-center">
              <VueApexCharts type="pie" height="250" :options="packVsIndividualOptions" :series="packVsIndividualSeries" />
              <div class="mt-4 w-100">
                <div class="d-flex justify-space-between mb-2">
                  <span class="text-body-2">Unidades en Pack</span>
                  <span class="font-weight-bold">{{ packVsIndividualSeries[0] }}</span>
                </div>
                <div class="d-flex justify-space-between">
                  <span class="text-body-2">Unidades Individuales</span>
                  <span class="font-weight-bold">{{ packVsIndividualSeries[1] }}</span>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="12" md="4">
          <VCard border class="rounded-lg shadow-sm h-100 bg-light-primary">
            <VCardText class="pa-8 d-flex flex-column align-center justify-center text-center">
              <VAvatar color="primary" variant="tonal" size="80" class="mb-4">
                <VIcon icon="tabler-medical-cross" size="40" />
              </VAvatar>
              <div class="text-h3 font-weight-black text-primary mb-2">
                {{ Number(dashboardData.highlights.medical_recipe_conversion || 0).toFixed(1) }}%
              </div>
              <div class="text-h6 font-weight-bold mb-1">Conversión Médico/Récipe</div>
              <p class="text-body-2 text-medium-emphasis">Participación de prescripciones en la venta total con descuento.</p>
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="12" md="4">
          <VCard border class="rounded-lg shadow-sm h-100 bg-light-success">
            <VCardText class="pa-8 d-flex flex-column align-center justify-center text-center">
              <VAvatar color="success" variant="tonal" size="80" class="mb-4">
                <VIcon icon="tabler-refresh-dot" size="40" />
              </VAvatar>
              <div class="text-h3 font-weight-black text-success mb-2">
                {{ formatCurrency(dashboardData.highlights.expiry_recovery_amount) }}
              </div>
              <div class="text-h6 font-weight-bold mb-1">Recuperación Caducidad</div>
              <p class="text-body-2 text-medium-emphasis">Venta generada a través de ofertas de liquidación por proximidad de vencimiento.</p>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- RANKINGS -->
      <VRow class="mb-4">
        <VCol cols="12" md="6">
          <VCard border class="rounded-lg shadow-sm overflow-hidden">
            <VCardTitle class="pa-4 border-b bg-light-primary">
              <VIcon icon="tabler-trending-up" class="me-2 text-primary" />
              <span class="text-h6 font-weight-bold">TOP 10 Promociones Ganadoras</span>
            </VCardTitle>
            <VCardText class="pa-0">
              <VList lines="one">
                <VListItem v-for="(item, idx) in dashboardData.rankings.top_offers" :key="idx" class="border-b">
                  <template #prepend>
                    <VAvatar color="primary" variant="tonal" size="32" class="me-3 font-weight-bold">{{ idx + 1 }}</VAvatar>
                  </template>
                  <VListItemTitle class="font-weight-bold text-uppercase">{{ item.product_name }}</VListItemTitle>
                  <VListItemSubtitle>
                    <VChip :color="getDiscountTypeColor(item.type)" size="x-small" label class="me-2">{{ item.type }}</VChip>
                    <span class="text-xs">{{ item.units_sold }} Unidades vendidas</span>
                  </VListItemSubtitle>
                  <template #append>
                    <div class="text-right">
                      <div class="text-body-2 font-weight-bold text-primary">{{ formatCurrency(item.revenue) }}</div>
                      <div class="text-super-xs text-medium-emphasis">Margen: {{ formatCurrency(item.total_margin) }}</div>
                    </div>
                  </template>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="12" md="6">
          <VCard border class="rounded-lg shadow-sm overflow-hidden">
            <VCardTitle class="pa-4 border-b bg-light-error">
              <VIcon icon="tabler-ghost" class="me-2 text-error" />
              <span class="text-h6 font-weight-bold">TOP 10 Promociones Fantasma (Bajo Impacto)</span>
            </VCardTitle>
            <VCardText class="pa-0">
              <VList lines="one">
                <VListItem v-for="(item, idx) in dashboardData.rankings.bottom_offers" :key="idx" class="border-b">
                  <template #prepend>
                    <VAvatar color="error" variant="tonal" size="32" class="me-3 font-weight-bold">{{ idx + 1 }}</VAvatar>
                  </template>
                  <VListItemTitle class="font-weight-bold text-uppercase">{{ item.product_name }}</VListItemTitle>
                  <VListItemSubtitle>
                    <VChip :color="getDiscountTypeColor(item.type)" size="x-small" label class="me-2">{{ item.type }}</VChip>
                    <span class="text-xs">{{ item.units_sold }} Unidades vendidas</span>
                  </VListItemSubtitle>
                  <template #append>
                    <div class="text-right">
                      <div class="text-body-2 font-weight-bold text-error">{{ formatCurrency(item.revenue) }}</div>
                      <div class="text-super-xs text-medium-emphasis">Margen: {{ formatCurrency(item.total_margin) }}</div>
                    </div>
                  </template>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- AUDITORÍA DE TRANSACCIONES -->
      <VCard border class="rounded-lg shadow-sm">
        <VCardTitle class="pa-4 border-b d-flex align-center gap-4 flex-wrap">
          <span class="text-h6 font-weight-bold">Auditoría Transaccional de Descuentos</span>
          <div style="width: 220px;">
            <AppSelect
              v-model="selectedDiscountType"
              :items="['Todos', 'Pack', 'Individual', 'Categoría', 'Empresa', 'Médico', 'Récipe', 'Caducidad']"
              label="Tipo de Descuento"
              density="compact"
              hide-details
            />
          </div>
          <VSpacer />
          <VBtn color="primary" variant="flat" size="small" class="rounded-pill">
            <VIcon icon="tabler-file-export" class="me-2" /> Exportar CSV
          </VBtn>
        </VCardTitle>
        <VCardText class="pa-0">
          <VDataTableServer
            v-model:items-per-page="auditItemsPerPage"
            v-model:page="auditPage"
            :items="auditData.data"
            :items-length="auditData.total"
            :loading="auditLoading"
            :headers="[
              { title: 'Ticket', key: 'ticket_id', align: 'start', sortable: false },
              { title: 'Fecha', key: 'date', sortable: false },
              { title: 'Producto', key: 'product_name', sortable: false },
              { title: 'Tipo Descuento', key: 'discount_type', sortable: false },
              { title: '%', key: 'discount_percentage', align: 'end', sortable: false },
              { title: 'Dinero Cedido', key: 'discount_amount', align: 'end', sortable: false },
              { title: 'Vendedor', key: 'seller_name', sortable: false },
            ]"
            density="comfortable"
            hover
          >
            <template #item.date="{ item }">
              {{ formatDateSimple(item.date) }}
            </template>
            <template #item.discount_type="{ item }">
              <VChip :color="getDiscountTypeColor(item.discount_type)" size="x-small" label class="font-weight-bold uppercase">
                {{ item.discount_type }}
              </VChip>
            </template>
            <template #item.discount_percentage="{ item }">
              <span v-if="item.discount_percentage" class="font-weight-bold">{{ item.discount_percentage }}%</span>
              <span v-else class="text-disabled">-</span>
            </template>
            <template #item.discount_amount="{ item }">
              <span class="font-weight-bold text-error">{{ formatCurrency(item.discount_amount) }}</span>
            </template>
          </VDataTableServer>
        </VCardText>
      </VCard>
    </div>
  </VContainer>
</template>

<style scoped>
.bi-report-grid :deep(.v-row) { margin: -8px !important; }
.bi-report-grid :deep(.v-col) { padding: 8px !important; }

.bg-light-primary { background-color: rgba(226, 0, 116, 0.05); }
.bg-light-success { background-color: rgba(40, 199, 111, 0.05); }
.bg-light-error { background-color: rgba(234, 84, 85, 0.05); }

.text-super-xs { font-size: 0.65rem; line-height: 1; }

:deep(.v-data-table-server) {
  background: transparent !important;
}

:deep(.v-data-table-header__content) {
  font-weight: 800 !important;
  text-transform: uppercase;
  font-size: 0.75rem;
  letter-spacing: 0.5px;
}
</style>
