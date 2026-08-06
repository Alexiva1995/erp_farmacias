<script setup>
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";
import { toast } from "@/plugins/sweetalert";
import SkuReportFilters from "./components/SkuReportFilters.vue";
import SkuReportKpis from "./components/SkuReportKpis.vue";
import SkuReportMobileView from "./components/SkuReportMobileView.vue";

const skus = ref([]);
const loading = ref(false);
const exporting = ref(false);
const totalItems = ref(0);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const filters = ref({
  search: "",
  start_date: "2026-04-01",
  end_date: "",
  laboratory_id: null,
  group_id: null,
  semaphore: null,
  is_active: 1
});

const laboratories = ref([]);

const summaryStats = ref({
  global_margin_real: 0,
  total_discounts: 0,
  total_loss: 0,
  critical_skus: 0
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
  page.value = 1;
  fetchReport();
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
  { title: 'ID', key: 'id', sortable: true, width: '60px' },
  { title: 'PRODUCTO', key: 'product_name', sortable: true, minWidth: '200px' },
  { title: 'VEND.', key: 'total_sold', sortable: true, width: '70px' },
  { title: 'COSTO', key: 'current_cost', sortable: false, width: '90px' },
  { title: 'P. LISTA', key: 'list_price', sortable: false, width: '90px' },
  { title: 'M. BRUTO', key: 'gross_margin_percent', sortable: true, width: '95px' },
  { title: 'DESC.', key: 'discount_avg_percent', sortable: false, width: '85px' },
  { title: 'M. NETO', key: 'net_margin_percent', sortable: false, width: '95px' },
  { title: 'MERMAS', key: 'loss_value', sortable: false, width: '85px' },
  { title: 'M. REAL', key: 'real_margin_percent', sortable: true, width: '95px' },
  { title: 'ESTADO', key: 'semaphore', sortable: false, width: '110px' }
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
  const mapping = { verde: 'success', amarillo: 'warning', rojo: 'error', negro: 'dark' };
  return mapping[status] || 'default';
};

const getSemaphoreLabel = (status) => {
  const mapping = { verde: 'Rentable', amarillo: 'Medio', rojo: 'Peligro', negro: 'Pérdidas' };
  return mapping[status] || status;
};

const formatPercent = (val) => Number(val || 0).toFixed(2) + '%';
const formatMoney = (val) => '$' + Number(val || 0).toFixed(2);
</script>

<template>
  <div>
    <!-- Filtros Desacoplados -->
    <SkuReportFilters
      :loading="loading"
      :exporting="exporting"
      :laboratories="laboratories"
      @update:filters="val => filters = val"
      @fetch="fetchReport"
      @clear="handleClearFilters"
      @export="handleExport"
    />

    <!-- KPIs Desacoplados -->
    <SkuReportKpis
      :summary-stats="summaryStats"
      :loading="loading"
    />

    <!-- Card Principal -->
    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardText class="d-flex justify-space-between align-center py-3">
        <h2 class="text-h6 font-weight-bold d-flex align-center">
          <VIcon icon="tabler-list-details" class="me-2 text-primary" size="22" />
          Desglose Financiero (Waterfall)
        </h2>
      </VCardText>
      <VDivider class="border-opacity-10" />

      <!-- Vista Desktop -->
      <div class="d-none d-md-block">
        <VDataTableServer
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :headers="headers"
          :items="skus"
          :items-length="totalItems"
          :loading="loading"
          fixed-header
          height="600px"
          class="premium-table density-compact"
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
              {{ getSemaphoreLabel(item.semaphore) }}
            </VChip>
          </template>
          
          <template #no-data>
            <div class="text-center pa-8 text-medium-emphasis">
              <VIcon icon="tabler-database-off" size="48" class="mb-3 opacity-40" />
              <p>Sin resultados para los filtros aplicados</p>
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

<style scoped>
.premium-table :deep(th) {
  background-color: rgb(var(--v-theme-surface)) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08) !important;
  padding: 0 8px !important;
}

.premium-table :deep(td) {
  padding: 0 8px !important;
  font-size: 0.75rem !important;
  height: 48px !important;
}

.text-super-xs {
  font-size: 0.6rem !important;
  line-height: 1.1;
}

.gap-1 { gap: 4px !important; }
</style>
