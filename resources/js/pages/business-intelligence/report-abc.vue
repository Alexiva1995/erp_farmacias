<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from '@axios';
import { formatCurrency } from '@/utils/currencyFormatter';

const loading = ref(false);
const items = ref([]);
const totalItems = ref(0);

// Filters
const selectedDateRange = ref('30 days'); // Default 30 days
const selectedCategories = ref([]);
const selectedLaboratories = ref([]);
const selectedFinalClassification = ref(null);

// Pagination & Sorting
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref([{ key: 'total_sales', order: 'desc' }]);

// Catalogs
const categories = ref([]);
const laboratories = ref([]);

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
  { title: 'ID', key: 'id', sortable: false },
  { title: 'Producto', key: 'name', sortable: true },
  { title: 'Categoría', key: 'category_name', sortable: true },
  { title: 'Lab.', key: 'laboratory_name', sortable: true },
  { title: 'Und. Vend.', key: 'sold_units', align: 'end', sortable: true },
  { title: 'Ventas $', key: 'total_sales', align: 'end', sortable: true },
  { title: 'Costo $', key: 'total_cost', align: 'end', sortable: true },
  { title: 'Margen $', key: 'margin_amount', align: 'end', sortable: true },
  { title: 'Margen %', key: 'margin_percentage', align: 'end', sortable: true },
  { title: 'Inventario (Días)', key: 'inventory_days', align: 'end', sortable: true },
  { title: 'Clas. Venta (A)', key: 'class_sales', align: 'center', sortable: true },
  { title: 'Clas. Margen (B)', key: 'class_margin', align: 'center', sortable: true },
  { title: 'Clas. Rotación (Z)', key: 'class_rotation', align: 'center', sortable: true },
  { title: 'Clas. Final', key: 'final_classification', align: 'center', sortable: true },
  { title: 'Stock', key: 'current_stock', align: 'end', sortable: true },
  { title: 'Costo Act.', key: 'last_cost', align: 'end', sortable: true },
];

const fetchCatalogs = async () => {
  try {
    const [catsRes, labsRes] = await Promise.all([
      axios.get('/categories'),
      axios.get('/laboratories')
    ]);
    categories.value = catsRes.data;
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
      category_id: selectedCategories.value,
      laboratory_id: selectedLaboratories.value,
      final_classification: selectedFinalClassification.value,
    };

    const response = await axios.get('/bi/abc', { params });
    items.value = response.data.data;
    totalItems.value = response.data.meta.total;
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

watch([page, itemsPerPage, sortBy, selectedDateRange, selectedCategories, selectedLaboratories, selectedFinalClassification], () => {
  fetchReport();
}, { deep: true });

onMounted(() => {
  fetchCatalogs();
  fetchReport();
});

const handleClearFilters = () => {
  selectedDateRange.value = '30 days';
  selectedCategories.value = [];
  selectedLaboratories.value = [];
  selectedFinalClassification.value = null;
};
</script>

<template>
  <VContainer fluid>
    <VRow class="mb-4">
      <VCol cols="12">
        <h1 class="text-h4 font-weight-bold text-primary">
          <VIcon icon="tabler-chart-pie" class="me-2" />
          Reporte ABC Multicriterio
        </h1>
        <p class="text-body-1 text-medium-emphasis">
          Clasificación de productos por Valor de Ventas (A-C), Margen de Ganancia (A-C) y Predictibilidad / Rotación (X-Z).
        </p>
      </VCol>
    </VRow>

    <!-- Filters -->
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="3">
            <AppSelect
              v-model="selectedDateRange"
              :items="dateRangeOptions"
              label="Período de Análisis"
              hide-details="auto"
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppAutocomplete
              v-model="selectedCategories"
              :items="categories"
              item-title="name"
              item-value="id"
              label="Categoría / Línea"
              multiple
              chips
              clearable
              hide-details="auto"
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppAutocomplete
              v-model="selectedLaboratories"
              :items="laboratories"
              item-title="name"
              item-value="id"
              label="Laboratorio"
              multiple
              chips
              clearable
              hide-details="auto"
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppAutocomplete
              v-model="selectedFinalClassification"
              :items="classificationOptions"
              label="Clasificación Final (AAX..)"
              clearable
              hide-details="auto"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VDivider />
      <VCardActions class="pa-4 px-6">
        <VBtn color="secondary" variant="outlined" @click="handleClearFilters">
          <VIcon start icon="tabler-filter-off" />
          Limpiar
        </VBtn>
        <VSpacer />
        <VBtn color="primary" @click="fetchReport" :loading="loading">
          <VIcon start icon="tabler-search" />
          Aplicar Filtros
        </VBtn>
      </VCardActions>
    </VCard>

    <!-- Data Table -->
    <VCard>
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        v-model:sort-by="sortBy"
        :items-length="totalItems"
        :headers="headers"
        :items="items"
        :loading="loading"
        class="text-no-wrap"
        hover
        density="compact"
      >
        <!-- Formatos de Moneda -->
        <template #item.total_sales="{ item }">
          <span class="font-weight-medium text-success">{{ formatCurrency(item.total_sales) }}</span>
        </template>
        
        <template #item.total_cost="{ item }">
          {{ formatCurrency(item.total_cost) }}
        </template>
        
        <template #item.margin_amount="{ item }">
          <span class="font-weight-medium" :class="item.margin_amount > 0 ? 'text-primary' : 'text-error'">
            {{ formatCurrency(item.margin_amount) }}
          </span>
        </template>
        
        <template #item.margin_percentage="{ item }">
          <span :class="item.margin_percentage > 0 ? 'text-primary' : 'text-error'">
            {{ item.margin_percentage }}%
          </span>
        </template>
        
        <template #item.last_cost="{ item }">
          {{ formatCurrency(item.last_cost) }}
        </template>

        <!-- Formatos numéricos generales -->
        <template #item.sold_units="{ item }">
          <span class="font-weight-bold">{{ item.sold_units }}</span>
        </template>

        <template #item.inventory_days="{ item }">
          <span v-if="item.inventory_days === 9999" class="text-error" title="Exceso/Impredecible">∞</span>
          <span v-else>{{ Math.round(item.inventory_days) }}</span>
        </template>

        <!-- Badges para Clasificación -->
        <template #item.final_classification="{ item }">
          <VChip
            size="small"
            :color="getColorClass(item.final_classification)"
            class="text-uppercase font-weight-bold"
          >
            {{ item.final_classification }}
          </VChip>
        </template>

        <!-- Tooltips explicativos para letras individuales por UX -->
        <template #item.class_sales="{ item }">
          <VChip size="x-small" :color="item.class_sales === 'A' ? 'success' : (item.class_sales === 'B' ? 'warning' : 'default')">
            {{ item.class_sales }}
          </VChip>
        </template>
        <template #item.class_margin="{ item }">
          <VChip size="x-small" :color="item.class_margin === 'A' ? 'success' : (item.class_margin === 'B' ? 'warning' : 'default')">
            {{ item.class_margin }}
          </VChip>
        </template>
        <template #item.class_rotation="{ item }">
          <VChip size="x-small" :color="item.class_rotation === 'X' ? 'success' : (item.class_rotation === 'Y' ? 'warning' : 'default')">
            {{ item.class_rotation }}
          </VChip>
        </template>

      </VDataTableServer>
    </VCard>
  </VContainer>
</template>
