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
  { title: 'Producto / Lab', key: 'name', sortable: true },
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
  <VContainer fluid>
    <VRow class="mb-2">
      <VCol cols="12">
        <h1 class="text-h4 font-weight-bold text-primary">
          <VIcon icon="tabler-chart-pie" class="me-2" />
          Reporte ABC Multicriterio
        </h1>
      </VCol>
    </VRow>

    <!-- Filters -->
    <VCard class="mb-6 border-0 shadow-sm overflow-hidden">
      <VCardText class="pa-3">
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
              color="primary"
              size="38"
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
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">
                  Laboratorios
                </span>
                <AppAutocomplete
                  v-model="selectedLaboratories"
                  :items="laboratories"
                  item-title="name"
                  item-value="id"
                  placeholder="Seleccionar Laboratorios"
                  multiple
                  chips
                  clearable
                  variant="outlined"
                  density="compact"
                  hide-details
                  class="premium-select-compact"
                />
              </VCol>

              <VCol cols="12" sm="6" md="6">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">
                  Clasificación Final (AAX..)
                </span>
                <AppAutocomplete
                  v-model="selectedFinalClassification"
                  :items="classificationOptions"
                  placeholder="Seleccionar Clasificación"
                  clearable
                  variant="outlined"
                  density="compact"
                  hide-details
                  class="premium-select-compact"
                />
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <!-- Dashboard KPIs -->
    <VRow class="mb-6">
      <VCol cols="12" md="4">
        <VCard elevation="1" class="h-100 bg-light-primary text-center pa-2">
          <VCardText>
            <div class="text-overline mb-1 text-primary">Ventas de Página</div>
            <div class="text-h5 font-weight-bold">{{ formatCurrency(summaryStats.total_volume) }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard elevation="1" class="h-100 bg-light-success text-center pa-2">
          <VCardText>
            <div class="text-overline mb-1 text-success">Productos Estrella (AAX/AAY)</div>
            <div class="text-h5 font-weight-bold">{{ summaryStats.aax_products }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard elevation="1" class="h-100 text-center pa-2">
          <VCardText>
            <div class="text-overline mb-1">Margen Promedio Global</div>
            <div class="text-h5 font-weight-bold" :class="summaryStats.avg_margin > 0 ? 'text-primary' : 'text-error'">
              {{ summaryStats.avg_margin.toFixed(2) }}%
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="border-0 shadow-sm overflow-hidden">
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
        class="premium-table rounded-lg"
        hover
        density="compact"
      >
        <!-- Combinación: Producto y Laboratorio -->
        <template #item.name="{ item }">
          <div class="d-flex flex-column py-2">
            <span class="font-weight-bold text-high-emphasis text-base">{{ item.name }}</span>
            <span class="text-sm text-medium-emphasis">ID: {{ item.id }} | Lab: <span class="font-weight-medium">{{ item.laboratory_name }}</span></span>
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
  </VContainer>
</template>
