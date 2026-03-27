<script setup>
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";
import { toast } from "@/plugins/sweetalert";

const skus = ref([]);
const loading = ref(false);
const totalItems = ref(0);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

// Filtros
const search = ref("");
const startDate = ref("");
const endDate = ref("");
const selectedLaboratory = ref(null);
const selectedGroup = ref(null);
const semaphoreFilter = ref(null);

const laboratories = ref([]);
const groups = ref([]);

const isAdvancedFiltersVisible = ref(false);

const hasActiveAdvancedFilters = computed(() => {
  return startDate.value || endDate.value || selectedLaboratory.value || selectedGroup.value;
});

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const fetchFilters = async () => {
  try {
    const [labRes, grpRes] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/groups"),
    ]);
    laboratories.value = labRes.data;
    groups.value = grpRes.data;
  } catch (error) {
    console.error("Error cargando filtros:", error);
  }
};

const fetchReport = async () => {
  loading.value = true;
  
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    search: search.value,
    start_date: startDate.value,
    end_date: endDate.value,
    laboratory_id: selectedLaboratory.value,
    group_id: selectedGroup.value,
    semaphore: semaphoreFilter.value
  };

  // Limpiar nulos
  Object.keys(params).forEach(key => (params[key] === null || params[key] === "") && delete params[key]);

  try {
    const { data } = await axios.get("/bi/sku-margin", { params });
    skus.value = data.data;
    totalItems.value = data.total;
  } catch (error) {
    console.error("Error obteniendo reporte SKU:", error);
    toast.error("Hubo un error cargando el reporte SKU.");
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchFilters();
  fetchReport();
});

const handleClearFilters = () => {
  search.value = "";
  startDate.value = "";
  endDate.value = "";
  selectedLaboratory.value = null;
  selectedGroup.value = null;
  semaphoreFilter.value = null;
  isAdvancedFiltersVisible.value = false;
};

// VDataTable Config
const headers = [
  { title: 'ID', key: 'id', sortable: true, width: '80px' },
  { title: 'SKU/Barras', key: 'barcode', sortable: false },
  { title: 'PRODUCTO', key: 'product_name', sortable: true },
  { title: 'Vendidos', key: 'total_sold', sortable: true },
  { title: 'Costo Unit.', key: 'current_cost', sortable: false },
  { title: 'Precio Lista', key: 'list_price', sortable: false },
  { title: 'M. Bruto', key: 'gross_margin_percent', sortable: true },
  { title: 'Descuento Prom.', key: 'discount_avg_percent', sortable: false },
  { title: 'M. Neto', key: 'net_margin_percent', sortable: false },
  { title: 'Mermas', key: 'loss_value', sortable: false },
  { title: 'M. Real %', key: 'real_margin_percent', sortable: true },
  { title: 'Estado', key: 'semaphore', sortable: false }
];

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if(options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key;
    orderBy.value = options.sortBy[0].order;
  } else {
    sortBy.value = null;
    orderBy.value = null;
  }
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, search, startDate, endDate, selectedLaboratory, selectedGroup, semaphoreFilter],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchReport(), 500);
  },
  { deep: true }
);

const getSemaphoreColor = (status) => {
  const mapping = {
    'verde': 'success',
    'amarillo': 'warning',
    'rojo': 'error',
    'negro': 'dark'
  };
  return mapping[status] || 'default';
};

const formatPercent = (val) => {
  return Number(val).toFixed(2) + '%';
};
const formatMoney = (val) => {
  return '$' + Number(val).toFixed(2);
};
</script>

<template>
  <div>

    
    <!-- Filtros Estandarizados -->
    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardText class="pa-4">
        <!-- Barra de Búsqueda Principal (Siempre Visible) -->
        <VRow align="center" no-gutters class="gap-2">
          <!-- Buscador SKU/Producto -->
          <VCol cols="12" md="4" lg="4">
            <AppTextField
              v-model="search"
              placeholder="Buscar por SKU o Nombre..."
              prepend-inner-icon="tabler-search"
              clearable
              density="compact"
              hide-details
              class="premium-input-compact"
            />
          </VCol>

          <!-- Filtro Semáforo -->
          <VCol cols="12" md="3" lg="3">
            <AppSelect
              v-model="semaphoreFilter"
              :items="[{title: '✅ Rentable (>25%)', value: 'verde'}, {title: '⚠️ Medio (10-25%)', value: 'amarillo'}, {title: '🚨 Peligro (<10%)', value: 'rojo'}, {title: '🏴 Pérdidas (<0%)', value: 'negro'}]"
              placeholder="Estado de Rentabilidad"
              density="compact"
              hide-details
              clearable
              class="premium-select-compact"
              prepend-inner-icon="tabler-traffic-lights"
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

            <!-- Limpiar Filtros (Siempre Visible) -->
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
              <VCol cols="12" sm="6" md="4">
                <AppDateTimePicker
                  v-model="startDate"
                  placeholder="Fecha Inicio"
                  density="compact"
                  hide-details
                  class="premium-input-compact"
                  prepend-inner-icon="tabler-calendar"
                />
              </VCol>

              <VCol cols="12" sm="6" md="4">
                <AppDateTimePicker
                  v-model="endDate"
                  placeholder="Fecha Fin"
                  density="compact"
                  hide-details
                  class="premium-input-compact"
                  prepend-inner-icon="tabler-calendar-check"
                />
              </VCol>

              <VCol cols="12" sm="6" md="4">
                <AppAutocomplete
                  v-model="selectedLaboratory"
                  :items="laboratories"
                  item-title="name"
                  item-value="id"
                  placeholder="Laboratorio / Proveedor"
                  clearable
                  variant="outlined"
                  density="compact"
                  hide-details
                  class="premium-select-compact"
                  prepend-inner-icon="tabler-flask"
                />
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :headers="headers"
        :items="skus"
        :items-length="totalItems"
        :loading="loading"
        class="premium-table"
        @update:options="updateTableOptions"
      >
        <template #item.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + (item.id || item.product_id)"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.id || item.product_id }}
          </a>
        </template>

        <template #item.product_name="{ item }">
          <div class="d-flex flex-column py-2">
            <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" :title="item.product_name">
              {{ item.product_name.toUpperCase() }}
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
        
        <template #item.current_cost="{ item }">
          {{ formatMoney(item.current_cost) }}
        </template>
        
        <template #item.list_price="{ item }">
          {{ formatMoney(item.list_price) }}
        </template>

        <template #item.gross_margin_percent="{ item }">
          <VChip size="small" variant="tonal" color="info">
            {{ formatPercent(item.gross_margin_percent) }}
          </VChip>
          <div class="text-caption text-disabled mt-1">{{ formatMoney(item.gross_margin_value) }}</div>
        </template>

        <template #item.discount_avg_percent="{ item }">
          <span class="text-error font-weight-bold">-{{ formatPercent(item.discount_avg_percent) }}</span>
        </template>

        <template #item.net_margin_percent="{ item }">
          <VChip size="small" variant="tonal" color="primary">
            {{ formatPercent(item.net_margin_percent) }}
          </VChip>
          <div class="text-caption text-disabled mt-1">{{ formatMoney(item.net_margin_value) }}</div>
        </template>
        
        <template #item.loss_value="{ item }">
          <span v-if="item.loss_value > 0" class="text-error">-${{ Number(item.loss_value).toFixed(2) }}</span>
          <span v-else class="text-disabled">$0.00</span>
        </template>

        <template #item.real_margin_percent="{ item }">
          <strong class="text-h6" :class="`text-${getSemaphoreColor(item.semaphore)}`">
             {{ formatPercent(item.real_margin_percent) }}
          </strong>
          <div class="text-caption mt-1">{{ formatMoney(item.real_margin_value) }} Total</div>
        </template>

        <template #item.semaphore="{ item }">
          <VChip
            :color="getSemaphoreColor(item.semaphore)"
            size="small"
            class="text-uppercase font-weight-bold"
          >
            {{ item.semaphore }}
          </VChip>
        </template>
      </VDataTableServer>
    </VCard>
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

.text-dark {
  color: #212121 !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.gap-1 { gap: 4px !important; }
</style>
