<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from '@/plugins/axios';
import { formatCurrency } from '@/utils/currencyFormatter';
const loading = ref(false);
const items = ref([]);
const totalItems = ref(0);

// Filters
const selectedDateRange = ref('30 days'); // Default 30 days
const selectedLaboratories = ref([]);
const selectedFinalClassification = ref(null);

// Pagination & Sorting
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref([{ key: 'total_sales', order: 'desc' }]);

// Global Search & KPIs
const search = ref('');
const summaryStats = ref({
  total_volume: 0,
  aax_products: 0,
  avg_margin: 0
});

// Catalogs
const laboratories = ref([]);

const isAdvancedFiltersVisible = ref(false);

const hasActiveAdvancedFilters = computed(() => {
  return selectedLaboratories.value.length > 0 || selectedFinalClassification.value !== null;
});

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

// Mapping quick date ranges
const getDateRange = (rangeType) => {
  const end = new Date();
  const start = new Date();
  
  if (rangeType === '30 days') {
    start.setDate(end.getDate() - 30);
  } else if (rangeType === '90 days') {
    start.setDate(end.getDate() - 90);
  } else if (rangeType === '12 months') {
    start.setMonth(end.getMonth() - 12);
  }

  return {
    start_date: start.toISOString().split('T')[0],
    end_date: end.toISOString().split('T')[0]
  };
};

const dateRangeOptions = [
  { title: 'Últimos 30 días', value: '30 days' },
  { title: 'Últimos 90 días', value: '90 days' },
  { title: 'Últimos 12 meses', value: '12 months' },
];

const classificationOptions = [
  'AAX', 'AAY', 'AAZ', 'ABX', 'ABY', 'ABZ', 'ACX', 'ACY', 'ACZ',
  'BAX', 'BAY', 'BAZ', 'BBX', 'BBY', 'BBZ', 'BCX', 'BCY', 'BCZ',
  'CAX', 'CAY', 'CAZ', 'CBX', 'CBY', 'CBZ', 'CCX', 'CCY', 'CCZ'
];

const headers = [
  { title: 'ID', key: 'id', sortable: true, width: '80px' },
  { title: 'PRODUCTO', key: 'name', sortable: true },
  { title: 'Volumen y Ventas', key: 'sold_units', align: 'end', sortable: true },
  { title: 'Rentabilidad (Margen / Costo)', key: 'margin_percentage', align: 'end', sortable: true },
  { title: 'Inventario (Status)', key: 'current_stock', align: 'end', sortable: true },
  { title: 'Costo Act.', key: 'last_cost', align: 'end', sortable: true },
  { title: 'Clasificación Biométrica', key: 'final_classification', align: 'center', sortable: true },
];

const fetchCatalogs = async () => {
  try {
    const labsRes = await axios.get('/laboratories');
    laboratories.value = labsRes.data;
  } catch (err) {
    console.error('Error loading catalogs:', err);
  }
};

const fetchReport = async () => {
  loading.value = true;
  try {
    const dates = getDateRange(selectedDateRange.value);
    
    const params = {
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value[0]?.key || 'total_sales',
      orderBy: sortBy.value[0]?.order || 'desc',
      start_date: dates.start_date,
      end_date: dates.end_date,
      laboratory_id: selectedLaboratories.value,
      final_classification: selectedFinalClassification.value,
    };

    const response = await axios.get('/bi/abc', { params });
    const responseData = response.data.data;
    items.value = responseData;
    totalItems.value = response.data.meta.total;

    // Calcular KPIs Visuales Client-Side (Solo para la pagina actual como sumario)
    summaryStats.value.total_volume = responseData.reduce((acc, curr) => acc + curr.total_sales, 0);
    summaryStats.value.aax_products = responseData.filter(i => ['AAX', 'AAY'].includes(i.final_classification)).length;
    
    // Margen promedio ponderado sencillo 
    const totalMarginAmt = responseData.reduce((acc, curr) => acc + curr.margin_amount, 0);
    summaryStats.value.avg_margin = summaryStats.value.total_volume > 0 ? (totalMarginAmt / summaryStats.value.total_volume) * 100 : 0;
    
  } catch (error) {
    console.error('Error fetching ABC report:', error);
  } finally {
    loading.value = false;
  }
};

const getColorClass = (classification) => {
  if (!classification) return 'default';
  
  if (['AAX', 'AAY', 'BAX', 'CAX'].includes(classification)) return 'success';
  if (['CCZ', 'CBZ', 'ACX'].includes(classification)) return 'error';
  if (['ABX', 'BBX'].includes(classification)) return 'warning';
  
  return 'secondary';
};

watch([page, itemsPerPage, sortBy, selectedDateRange, selectedLaboratories, selectedFinalClassification], () => {
  fetchReport();
}, { deep: true });

onMounted(() => {
  fetchCatalogs();
  fetchReport();
});

const handleClearFilters = () => {
  search.value = '';
  selectedDateRange.value = '30 days';
  selectedLaboratories.value = [];
  selectedFinalClassification.value = null;
  isAdvancedFiltersVisible.value = false;
};
</script>

<template>
  <div class="report-abc-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">    <!-- Filtros Estandarizados -->
    <VCard class="mb-4 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardText class="pa-4">
        <!-- Barra de Búsqueda Principal (Siempre Visible) -->
        <VRow align="center" no-gutters class="gap-2">
          <!-- Buscador Global -->
          <VCol cols="12" md="4" lg="3">
            <AppTextField
              v-model="search"
              placeholder="Buscar Producto, ID..."
              prepend-inner-icon="tabler-search"
              clearable
              density="compact"
              hide-details
              class="premium-input-compact"
            />
          </VCol>

          <!-- Período de Análisis -->
          <VCol cols="12" md="3" lg="3">
            <AppSelect
              v-model="selectedDateRange"
              :items="dateRangeOptions"
              placeholder="Período de Análisis"
              density="compact"
              hide-details
              class="premium-select-compact"
              prepend-inner-icon="tabler-calendar-stats"
            />
          </VCol>

          <VSpacer />

          <div class="d-flex align-center gap-1">
            <!-- Toggle Filtros -->
            <VBtn
              icon
              variant="tonal"
              :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
              size="38"
              class="rounded-circle shadow-sm"
              @click="toggleAdvancedFilters"
            >
              <VBadge
                v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
                color="error"
                dot
                offset-x="2"
                offset-y="-2"
              >
                <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
              </VBadge>
              <VIcon v-else :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
              <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
            </VBtn>

            <!-- Aplicar Filtros -->
            <VBtn
              icon
              variant="flat"
              color="primary"
              size="38"
              class="rounded-circle shadow-sm"
              :loading="loading"
              @click="fetchReport"
            >
              <VIcon icon="tabler-player-play" size="20" />
              <VTooltip activator="parent" location="top">Aplicar Filtros</VTooltip>
            </VBtn>

            <VDivider vertical class="mx-1 my-2 border-opacity-10" />

            <!-- Limpiar Filtros -->
            <VBtn
              icon
              variant="text"
              color="secondary"
              size="38"
              class="rounded-circle shadow-sm"
              @click="handleClearFilters"
            >
              <VIcon icon="tabler-eraser" size="20" />
              <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
            </VBtn>
          </div>
        </VRow>

        <!-- Panel de Filtros Avanzado -->
        <VExpandTransition>
          <div v-show="isAdvancedFiltersVisible">
            <VDivider class="my-3 border-opacity-10" />
            
            <VRow>
              <VCol cols="12" sm="6" md="6">
                <AppAutocomplete
                  v-model="selectedLaboratories"
                  :items="laboratories"
                  item-title="name"
                  item-value="id"
                  placeholder="Seleccionar Laboratorios"
                  multiple
                  chips
                  closable-chips
                  clearable
                  variant="outlined"
                  density="compact"
                  hide-details
                  class="premium-select-compact"
                  prepend-inner-icon="tabler-flask"
                />
              </VCol>

              <VCol cols="12" sm="6" md="6">
                <AppAutocomplete
                  v-model="selectedFinalClassification"
                  :items="classificationOptions"
                  placeholder="Seleccionar Clasificación (AAX...)"
                  clearable
                  variant="outlined"
                  density="compact"
                  hide-details
                  class="premium-select-compact"
                  prepend-inner-icon="tabler-tags"
                />
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <!-- Dashboard KPIs Estilo Premium -->
    <VRow class="ma-0 mx-n1 mb-5 mt-2" dense>
      <VCol v-for="(kpi, index) in [
        {
          title: 'Ventas de Página',
          value: formatCurrency(summaryStats.total_volume),
          color: 'primary',
          icon: 'tabler-coin',
          desc: 'Volumen facturado visible'
        },
        {
          title: 'Prod. Estrella',
          value: summaryStats.aax_products,
          color: 'success',
          icon: 'tabler-star',
          desc: 'Clasificación AAX/AAY'
        },
        {
          title: 'Margen Global',
          value: summaryStats.avg_margin.toFixed(2) + '%',
          color: summaryStats.avg_margin > 0 ? 'primary' : 'error',
          icon: 'tabler-percentage',
          desc: 'Rentabilidad promedio'
        }
      ]" :key="index" cols="12" md="4" class="pa-1">
        <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full position-relative">
          <div class="card-bg-decoration" :style="{ background: `linear-gradient(45deg, rgba(var(--v-theme-${kpi.color}), 0.1), transparent)` }"></div>
          <VCardText class="pa-5 relative-content">
            <div class="d-flex align-center justify-space-between mb-4">
              <VAvatar :color="kpi.color" variant="tonal" size="48" rounded="lg" class="elevation-1">
                <VIcon :icon="kpi.icon" size="26" />
              </VAvatar>
              <div class="text-right">
                <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important">{{ kpi.title }}</span>
                <h4 class="text-h4 font-weight-black mt-1">{{ kpi.value }}</h4>
              </div>
            </div>
            <VDivider class="mb-3 opacity-20" />
            <div class="d-flex align-center justify-space-between">
              <span class="text-caption font-weight-medium text-medium-emphasis">{{ kpi.desc }}</span>
              <VIcon icon="tabler-chart-pie" size="16" :color="kpi.color" class="opacity-50" />
            </div>
          </VCardText>
          <div class="accent-border" :style="{ backgroundColor: `rgb(var(--v-theme-${kpi.color}))` }"></div>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <!-- Head de Tabla -->
      <VCardText class="d-flex justify-space-between align-center py-3">
        <h2 class="text-h6 font-weight-bold d-flex align-center">
          <VIcon icon="tabler-list-details" class="me-2 text-primary" size="22" />
          Resultados del Análisis
        </h2>
      </VCardText>
      <VDivider class="border-opacity-10" />

      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        v-model:sort-by="sortBy"
        :items-length="totalItems"
        :headers="headers"
        :items="items"
        :search="search"
        :loading="loading"
        class="premium-table"
        hover
        density="compact"
      >
        <template #item.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.id"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.id }}
          </a>
        </template>

        <!-- Combinación: Producto y Laboratorio -->
        <template #item.name="{ item }">
          <div class="d-flex flex-column py-2">
            <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" :title="item.name">
              {{ item.name.toUpperCase() }}
            </span>
            <div class="d-flex align-center gap-1 text-super-xs">
              <span class="text-disabled truncate" style="max-inline-size: 200px;">
                {{ item.active_ingredient || item.active_ingredient_inventory || 'SIN INGREDIENTE' }}
              </span>
              <span class="text-disabled mx-1">|</span>
              <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 150px;">
                {{ item.laboratory_name || 'S/L' }}
              </span>
            </div>
          </div>
        </template>

        <!-- Combinación: Unidades y Ventas Monetarias -->
        <template #item.sold_units="{ item }">
          <div class="d-flex flex-column align-end">
             <span class="font-weight-bold text-success">{{ formatCurrency(item.total_sales) }}</span>
             <span class="text-caption text-medium-emphasis"><VIcon icon="tabler-box" size="12" class="me-1"/>{{ item.sold_units }} unds</span>
          </div>
        </template>
        
        <!-- Combinación: Rentabilidad % y Costos Absolutos -->
        <template #item.margin_percentage="{ item }">
          <div class="d-flex flex-column align-end">
            <span class="font-weight-bold text-base" :class="item.margin_percentage > 0 ? 'text-primary' : 'text-error'">
              Margen: {{ item.margin_percentage }}%
            </span>
            <span class="text-caption text-medium-emphasis">Costo Ventas: {{ formatCurrency(item.total_cost) }}</span>
          </div>
        </template>

        <!-- Combinación: Stock Estático y Días Restantes -->
        <template #item.current_stock="{ item }">
           <div class="d-flex flex-column align-end">
            <span class="font-weight-bold">{{ item.current_stock }} unds</span>
            <span class="text-caption text-medium-emphasis mt-1">
              <VIcon icon="tabler-calendar-time" size="12" class="me-1" :class="item.inventory_days < 10 ? 'text-error' : ''"/>
              <span v-if="item.inventory_days === 9999" class="text-warning">Incalculable</span>
              <span v-else :class="item.inventory_days < 10 ? 'text-error' : ''">{{ Math.round(item.inventory_days) }} días d/inv</span>
            </span>
          </div>
        </template>
        
        <template #item.last_cost="{ item }">
          <span class="font-weight-medium">{{ formatCurrency(item.last_cost) }}</span>
        </template>

        <!-- BADGES INTERACTIVOS -->
        <template #item.final_classification="{ item }">
          <VTooltip location="top" content-class="bg-grey-900 border-opacity-100">
            <template #activator="{ props }">
               <VChip
                size="large"
                v-bind="props"
                :color="getColorClass(item.final_classification)"
                class="text-uppercase font-weight-black elevation-1"
                variant="elevated"
              >
                {{ item.final_classification }}
              </VChip>
            </template>
            <div class="d-flex flex-column gap-1 text-caption text-left text-white pa-1">
              <span><strong>A</strong>porte Ventas: {{ item.class_sales === 'A' ? 'Alto (80%)' : (item.class_sales === 'B' ? 'Medio (15%)' : 'Bajo (5%)') }}</span>
              <span><strong>M</strong>argen Cbción: {{ item.class_margin === 'A' ? 'Alto (80%)' : (item.class_margin === 'B' ? 'Medio (15%)' : 'Bajo (5%)') }}</span>
              <span><strong>R</strong>otación Dem.: {{ item.class_rotation === 'X' ? 'Constante (Seguro)' : (item.class_rotation === 'Y' ? 'Fluctuante (Normal)' : 'Esporádica (Riesgo)') }}</span>
            </div>
          </VTooltip>
        </template>

      </VDataTableServer>
    </VCard>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: #fff !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.report-abc-view {
  min-block-size: 100vh;
}

.stats-card {
  border-radius: 8px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 80%) !important;
  transition: all 0.3s ease;
}

.stats-card:hover {
  box-shadow: 0 8px 25px 0 rgba(0, 0, 0, 8%) !important;
  transform: translateY(-5px);
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 100px;
  filter: blur(40px);
  inline-size: 100px;
  inset-block-start: -20px;
  inset-inline-end: -20px;
  pointer-events: none;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 70%;
  border-end-end-radius: 4px;
  border-start-end-radius: 4px;
  inline-size: 4px;
  inset-block-start: 15%;
  inset-inline-start: 0;
  opacity: 0.8;
}

.text-h4 {
  color: rgb(var(--v-theme-on-surface));
  letter-spacing: -0.5px !important;
}
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.letter-spacing-tight { letter-spacing: -0.02em; }
.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
</style>
