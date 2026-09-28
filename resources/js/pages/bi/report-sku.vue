<script setup>
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";
import { toast } from "@/plugins/sweetalert";
import SkuReportFilters from "./components/SkuReportFilters.vue";
import SkuReportKpis from "./components/SkuReportKpis.vue";
import SkuReportCharts from "./components/SkuReportCharts.vue";
import SkuReportMobileView from "./components/SkuReportMobileView.vue";

const skus = ref([]);
const loading = ref(false);
const exporting = ref(false);
const totalItems = ref(0);

const page = ref(1);
const itemsPerPage = ref(15);
const sortBy = ref();
const orderBy = ref();
const expanded = ref([]);

const getFirstDayOfCurrentMonth = () => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
};

const activeFilterKey = ref(null);

const filters = ref({
  search: "",
  start_date: getFirstDayOfCurrentMonth(),
  end_date: "",
  laboratory_id: null,
  group_id: null,
  semaphore: null,
  has_loss: null,
  has_discount: null,
  is_active: 1
});

const laboratories = ref([]);

const summaryStats = ref({
  global_margin_real: 0,
  global_margin_net: 0,
  total_discounts: 0,
  total_loss: 0,
  critical_skus: 0,
  gmroi: 0
});

const chartsData = ref({
  waterfall: [],
  semaphore_distribution: { green: 0, yellow: 0, red: 0, black: 0 },
  summary: {}
});

const fetchFilters = async () => {
  try {
    const [labRes] = await Promise.all([
      axios.get("/laboratories")
    ]);
    laboratories.value = labRes.data;
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
    ...filters.value
  };

  Object.keys(params).forEach(key => (params[key] === null || params[key] === "") && delete params[key]);

  try {
    const { data } = await axios.get("/bi/sku-margin", { params });
    skus.value = data.data;
    totalItems.value = data.total;
    if (data.summary) {
      summaryStats.value = data.summary;
    }
    if (data.charts) {
      chartsData.value = data.charts;
    }
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
  activeFilterKey.value = null;
  page.value = 1;
  filters.value.start_date = getFirstDayOfCurrentMonth();
  filters.value.end_date = "";
  filters.value.search = "";
  filters.value.laboratory_id = null;
  filters.value.group_id = null;
  filters.value.has_loss = null;
  filters.value.has_discount = null;
  filters.value.semaphore = null;
  filters.value.is_active = 1;
  fetchReport();
};

const handleKpiFilter = (key) => {
  if (activeFilterKey.value === key) {
    activeFilterKey.value = null;
    filters.value.semaphore = null;
    filters.value.has_loss = null;
    filters.value.has_discount = null;
  } else {
    activeFilterKey.value = key;
    if (key === 'critical') {
      filters.value.semaphore = 'critico';
      filters.value.has_loss = null;
      filters.value.has_discount = null;
    } else if (key === 'losses') {
      filters.value.has_loss = 1;
      filters.value.has_discount = null;
      filters.value.semaphore = null;
    } else if (key === 'discounts') {
      filters.value.has_discount = 1;
      filters.value.has_loss = null;
      filters.value.semaphore = null;
    } else if (key === 'global') {
      activeFilterKey.value = null;
      filters.value.semaphore = null;
      filters.value.has_loss = null;
      filters.value.has_discount = null;
    }
  }
};

const handleExport = async () => {
  exporting.value = true;
  const params = { ...filters.value };
  Object.keys(params).forEach(key => (params[key] === null || params[key] === "") && delete params[key]);

  try {
    const response = await axios.get('/bi/sku-margin/export', {
      params,
      responseType: 'blob'
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    
    const contentDisposition = response.headers['content-disposition'];
    let fileName = 'margen_sku.csv';
    if (contentDisposition) {
      const fileNameMatch = contentDisposition.match(/filename="?(.+)"?/);
      if (fileNameMatch && fileNameMatch.length >= 2) {
        fileName = fileNameMatch[1].replace(/"/g, '');
      }
    }

    link.setAttribute('download', fileName);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    toast.success('Reporte exportado con éxito');
  } catch (error) {
    console.error('Error exportando reporte:', error);
    toast.error('Hubo un error exportando el reporte.');
  } finally {
    exporting.value = false;
  }
};

const headers = [
  { title: '', key: 'data-table-expand', width: '40px' },
  { title: 'ID/SKU', key: 'id', sortable: true, width: '90px' },
  { title: 'PRODUCTO / MOLÉCULA', key: 'product_name', sortable: true, minWidth: '220px' },
  { title: 'STOCK', key: 'current_stock', sortable: true, width: '80px' },
  { title: 'VEND.', key: 'total_sold', sortable: true, width: '80px' },
  { title: 'COSTO', key: 'current_cost', sortable: false, width: '90px' },
  { title: 'P. LISTA', key: 'list_price', sortable: false, width: '90px' },
  { title: 'M. BRUTO', key: 'gross_margin_percent', sortable: true, width: '100px' },
  { title: 'DESC.', key: 'discount_avg_percent', sortable: false, width: '90px' },
  { title: 'M. NETO', key: 'net_margin_percent', sortable: false, width: '100px' },
  { title: 'MERMAS', key: 'loss_value', sortable: false, width: '100px' },
  { title: 'M. REAL', key: 'real_margin_percent', sortable: true, width: '105px' },
  { title: 'ESTADO', key: 'semaphore', sortable: false, width: '120px' }
];

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key;
    orderBy.value = options.sortBy[0].order;
  } else {
    sortBy.value = null;
    orderBy.value = null;
  }
};

let debounceTimer;
const debouncedFetchReport = (delay = 300) => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchReport();
  }, delay);
};

watch(
  () => filters.value,
  () => {
    if (page.value !== 1) {
      page.value = 1;
    } else {
      debouncedFetchReport(400);
    }
  },
  { deep: true }
);

watch(
  [page, itemsPerPage, sortBy, orderBy],
  () => {
    debouncedFetchReport(100);
  }
);

const getSemaphoreColor = (status) => {
  const mapping = { verde: 'success', amarillo: 'warning', rojo: 'error', negro: 'secondary' };
  return mapping[status] || 'default';
};

const getSemaphoreLabel = (status) => {
  const mapping = { verde: 'Rentable', amarillo: 'Medio', rojo: 'Peligro', negro: 'Pérdidas' };
  return mapping[status] || status;
};

const formatPercent = (val) => Number(val || 0).toFixed(2) + '%';
const formatMoney = (val) => '$' + Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
  <div>
    <!-- Filtros Desacoplados -->
    <SkuReportFilters
      v-model="filters"
      :loading="loading"
      :exporting="exporting"
      :laboratories="laboratories"
      @fetch="fetchReport"
      @clear="handleClearFilters"
      @export="handleExport"
    />

    <!-- KPIs Desacoplados e Interactivos -->
    <SkuReportKpis
      :summary-stats="summaryStats"
      :loading="loading"
      :active-filter-key="activeFilterKey"
      @filter-click="handleKpiFilter"
    />

    <!-- Visualización y Storytelling BI (ApexCharts) -->
    <SkuReportCharts
      :charts-data="chartsData"
      :loading="loading"
    />

    <!-- Card Principal con Tabla Cascada -->
    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardText class="d-flex justify-space-between align-center py-3 border-b">
        <div class="d-flex align-center gap-2">
          <VAvatar color="primary" variant="tonal" size="32" rounded="sm">
            <VIcon icon="tabler-list-details" size="18" />
          </VAvatar>
          <h2 class="text-subtitle-1 font-weight-bold">
            Desglose Financiero por SKU (Waterfall)
          </h2>
        </div>
        <div v-if="activeFilterKey" class="d-flex align-center gap-2">
          <VChip
            size="small"
            color="primary"
            variant="tonal"
            closable
            @click:close="handleClearFilters"
          >
            Filtro rápido: <strong>{{ activeFilterKey }}</strong>
          </VChip>
        </div>
      </VCardText>

      <!-- Vista Desktop -->
      <div class="d-none d-md-block">
        <VDataTableServer
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          v-model:expanded="expanded"
          :headers="headers"
          :items="skus"
          :items-length="totalItems"
          :loading="loading"
          item-value="product_id"
          show-expand
          class="border-0"
          density="comfortable"
          @update:options="updateTableOptions"
        >
          <!-- Loading State con Skeleton -->
          <template #loading>
            <VSkeletonLoader type="table-row@5" />
          </template>

          <!-- Columna ID -->
          <template #item.id="{ item }">
            <a
              :href="'/inventory/traceability?q=' + (item.id || item.product_id)"
              target="_blank"
              class="text-decoration-none font-weight-bold text-primary"
            >
              {{ item.barcode || item.product_id }}
            </a>
          </template>

          <!-- Columna Producto y Molécula -->
          <template #item.product_name="{ item }">
            <div class="d-flex flex-column py-2">
              <span class="text-body-2 font-weight-bold text-high-emphasis text-uppercase text-truncate" :title="item.product_name">
                {{ item.product_name }}
              </span>
              <div class="d-flex align-center gap-1 text-caption text-medium-emphasis">
                <span class="text-truncate" style="max-inline-size: 200px;">
                  {{ item.active_ingredient || 'Sin Molécula' }}
                </span>
                <span class="text-disabled">•</span>
                <span class="text-primary font-weight-medium text-truncate" style="max-inline-size: 150px;">
                  {{ item.laboratory_name || 'S/L' }}
                </span>
              </div>
            </div>
          </template>
          
          <!-- Columna Stock Actual -->
          <template #item.current_stock="{ item }">
            <VChip
              size="small"
              :color="item.current_stock > 10 ? 'default' : item.current_stock > 0 ? 'warning' : 'error'"
              variant="tonal"
              class="font-weight-bold"
            >
              {{ Number(item.current_stock).toFixed(0) }}
            </VChip>
          </template>

          <!-- Columna Vendidos -->
          <template #item.total_sold="{ item }">
            <span class="font-weight-medium text-body-2">{{ item.total_sold }}</span>
          </template>

          <!-- Columna Costo -->
          <template #item.current_cost="{ item }">
            <span class="font-weight-medium text-body-2">{{ formatMoney(item.current_cost) }}</span>
          </template>
          
          <!-- Columna Precio Lista -->
          <template #item.list_price="{ item }">
            <span class="font-weight-medium text-body-2">{{ formatMoney(item.list_price) }}</span>
          </template>

          <!-- M. BRUTO -->
          <template #item.gross_margin_percent="{ item }">
            <div class="d-flex flex-column py-1">
              <span class="text-body-2 font-weight-bold text-info">
                {{ formatPercent(item.gross_margin_percent) }}
              </span>
              <span class="text-caption text-medium-emphasis">
                {{ formatMoney(item.gross_margin_value) }}
              </span>
            </div>
          </template>

          <!-- DESCUENTOS -->
          <template #item.discount_avg_percent="{ item }">
            <div class="d-flex flex-column py-1">
              <span v-if="item.discount_avg_percent > 0" class="text-body-2 font-weight-bold text-error">
                -{{ formatPercent(item.discount_avg_percent) }}
              </span>
              <span v-else class="text-caption text-disabled">0.00%</span>
              <span v-if="item.total_discount_amount > 0" class="text-caption text-medium-emphasis">
                -{{ formatMoney(item.total_discount_amount) }}
              </span>
            </div>
          </template>

          <!-- M. NETO -->
          <template #item.net_margin_percent="{ item }">
            <div class="d-flex flex-column py-1">
              <span class="text-body-2 font-weight-bold text-primary">
                {{ formatPercent(item.net_margin_percent) }}
              </span>
              <span class="text-caption text-medium-emphasis">
                {{ formatMoney(item.net_margin_value) }}
              </span>
            </div>
          </template>
          
          <!-- MERMAS -->
          <template #item.loss_value="{ item }">
            <div class="d-flex align-center py-1">
              <VChip
                v-if="Number(item.loss_value) > 0"
                size="small"
                color="error"
                variant="tonal"
                class="font-weight-bold"
              >
                <VIcon icon="tabler-trending-down" size="14" class="me-1" />
                -{{ formatMoney(item.loss_value) }}
              </VChip>
              <span v-else class="text-caption text-disabled">$0.00</span>
            </div>
          </template>

          <!-- M. REAL -->
          <template #item.real_margin_percent="{ item }">
            <div class="d-flex flex-column py-1">
              <span class="text-body-2 font-weight-black" :class="`text-${getSemaphoreColor(item.semaphore)}`">
                {{ formatPercent(item.real_margin_percent) }}
              </span>
              <span class="text-caption text-medium-emphasis">
                {{ formatMoney(item.real_margin_value) }}
              </span>
            </div>
          </template>

          <!-- ESTADO -->
          <template #item.semaphore="{ item }">
            <VChip
              :color="getSemaphoreColor(item.semaphore)"
              size="small"
              variant="tonal"
              class="font-weight-bold text-caption text-uppercase"
            >
              {{ getSemaphoreLabel(item.semaphore) }}
            </VChip>
          </template>

          <!-- Expansión de Fila para Detalle Enriquecido -->
          <template #expanded-row="{ columns, item }">
            <tr>
              <td :colspan="columns.length" class="pa-4 bg-var-theme-background">
                <div class="d-flex align-center justify-space-between flex-wrap gap-4">
                  <div class="d-flex align-center gap-4">
                    <div>
                      <span class="text-caption text-disabled text-uppercase font-weight-bold d-block">Ingresos Totales</span>
                      <span class="text-body-2 font-weight-bold">{{ formatMoney(item.total_revenue) }}</span>
                    </div>
                    <VDivider vertical class="mx-2" />
                    <div>
                      <span class="text-caption text-disabled text-uppercase font-weight-bold d-block">Stock Actual</span>
                      <span class="text-body-2 font-weight-bold">{{ item.current_stock }} unidades</span>
                    </div>
                    <VDivider vertical class="mx-2" />
                    <div>
                      <span class="text-caption text-disabled text-uppercase font-weight-bold d-block">Laboratorio</span>
                      <span class="text-body-2 font-weight-bold">{{ item.laboratory_name || 'Sin Asignar' }}</span>
                    </div>
                  </div>

                  <div>
                    <VBtn
                      :href="'/inventory/traceability?q=' + (item.product_id || item.id)"
                      target="_blank"
                      size="small"
                      variant="tonal"
                      color="primary"
                      class="font-weight-bold"
                    >
                      <VIcon icon="tabler-history" size="16" class="me-1" />
                      Ver Trazabilidad y Lotes
                    </VBtn>
                  </div>
                </div>
              </td>
            </tr>
          </template>
          
          <!-- Empty State -->
          <template #no-data>
            <div class="text-center pa-8 text-medium-emphasis">
              <VIcon icon="tabler-database-off" size="48" class="mb-3 opacity-40" />
              <p class="text-body-1 font-weight-medium">Sin resultados para los filtros aplicados</p>
            </div>
          </template>
        </VDataTableServer>
      </div>

      <!-- Vista Móvil Desacoplada -->
      <SkuReportMobileView
        :skus="skus"
        :loading="loading"
        :page="page"
        :items-per-page="itemsPerPage"
        @update:page="val => page = val"
      />
    </VCard>
  </div>
</template>
