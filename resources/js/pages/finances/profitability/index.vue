<script setup>
import ProfitabilityTable from "@/components/ProfitabilityTable.vue";
import ProductProfitabilityEditDialog from "@/components/dialogs/ProductProfitabilityEditDialog.vue";
import addProfitabilityDialog from "@/components/dialogs/addProfitabilityDialog.vue";
import profitabilityFilters from "@/components/profitabilityFilters.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref, watch } from "vue";

// State
const products = ref([]);
const totalProduct = ref(0);
const profitability = ref(0);
const globalSettings = ref({});
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();
const loading = ref(false);

const dialog = ref(false);
const percentage = ref(25); // Valor por defecto común

const searchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const stockStatusFilter = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const laboratories = ref([]);
const origins = ref([]);
const isLoadingFilters = ref(false);
const lockedValue = ref(null);

const editDialog = ref(false);
const productProfitability = ref({});

// API Methods
const fetchFiltersOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
  } catch (error) {
    console.error("Error al cargar los filtros:", error);
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchProducts = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    startDate: startDate.value,
    endDate: endDate.value,
    lockedValue: lockedValue.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );

  try {
    const response = await axios.get("/finances/profitability/products", { params });
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos.");
  } finally {
    loading.value = false;
  }
};

const percentProfitability = async () => {
  try {
    const [profitabilityResponse, generalSettingsResponse] = await Promise.all([
      axios.get("/finances/profitability"),
      axios.get("/general-settings"),
    ]);

    const profitData = profitabilityResponse.data || {};
    const generalData = generalSettingsResponse.data?.data || {};

    globalSettings.value = {
      ...profitData,
      profitability_calculation_type: generalData.profitability_calculation_type || 'simple'
    };
    profitability.value = profitData.default_profitability_percentage || 0;
  } catch (error) {
    console.error("Hubo un error al obtener la rentabilidad:", error);
  }
};

// Handlers
const addProfitability = () => {
  dialog.value = true;
};

const editProductProfitability = (item) => {
  editDialog.value = true;
  productProfitability.value = {
    id: item.profitability?.id,
    percentage: item.profitability?.profitability_percentage || 0,
    product_id: item.id,
    is_locked: item.profitability?.is_locked || 0,
    name: item.name,
    unit_cost: item.unit_cost,
    shipping_cost: item.profitability?.shipping_cost,
    packaging_cost: item.profitability?.packaging_cost,
    expense_margin: item.profitability?.expense_margin,
    profit_margin: item.profitability?.profit_margin,
    tax_usa: item.profitability?.tax_usa,
  };
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
  selectedOrigin.value = null;
  stockStatusFilter.value = null;
  startDate.value = null;
  endDate.value = null;
  lockedValue.value = null;
};

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;

  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0]?.key;
    orderBy.value = options.sortBy[0]?.order;
  }
};

function reloadTable() {
  fetchProducts();
  percentProfitability();
}

function handleProductUpdated(updatedProduct) {
  if (!updatedProduct || !updatedProduct.id) return;
  const index = products.value.findIndex((p) => p.id === updatedProduct.id);
  if (index !== -1) {
    products.value[index] = { ...products.value[index], ...updatedProduct };
  }
}

// Watchers unificados para evitar race conditions
let debounceTimer;
watch(
  [
    searchQuery,
    selectedLaboratory,
    selectedOrigin,
    stockStatusFilter,
    startDate,
    endDate,
    lockedValue,
  ],
  () => {
    // Si cambia cualquier filtro, reiniciamos a la primera página.
    // Esto disparará a su vez el watcher del page.
    if (page.value !== 1) {
      page.value = 1;
    } else {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => fetchProducts(), 300);
    }
  }
);

watch(
  [page, itemsPerPage, sortBy, orderBy],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  }
);

// Lifecycle
onMounted(() => {
  fetchFiltersOptions();
  reloadTable();
});

// Computed for summary

</script>

<template>
  <div class="profitability-page pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <!-- Dashboard de KPIs -->
      <VRow class="ma-0 mb-6 mx-n1 match-height">
        <!-- Configuración Global -->
        <VCol cols="12" md="6" class="pa-1">
          <VCard class="stats-card border-0 overflow-hidden">
            <div
              class="card-bg-decoration"
              style="
                background: linear-gradient(
                  45deg,
                  rgba(var(--v-theme-primary), 0.1),
                  transparent
                );
              "
            ></div>
            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-4">
                <VAvatar
                  color="primary"
                  size="48"
                  variant="tonal"
                  rounded="lg"
                  class="elevation-1"
                >
                  <VIcon icon="tabler-chart-pie" size="26" />
                </VAvatar>
                <div
                  class="text-right d-flex flex-column text-truncate"
                  style="max-width: 180px"
                >
                  <span
                    class="text-overline font-weight-bold text-disabled"
                    style="letter-spacing: 1px !important"
                  >
                    Margen Global
                  </span>
                  <h4 class="text-h3 font-weight-black mt-1 text-primary">
                    {{ profitability }}%
                  </h4>
                </div>
              </div>
              <VDivider class="mb-3 opacity-20" />
              <div class="d-flex align-center justify-space-between">
                <span
                  class="text-caption font-weight-medium text-medium-emphasis uppercase"
                >
                  POR DEFECTO
                </span>
                <span
                  class="text-super-xs font-weight-black opacity-60 uppercase text-primary"
                  >Configuración</span
                >
              </div>
            </VCardText>
            <div class="accent-border bg-primary"></div>
          </VCard>
        </VCol>

        <!-- Productos -->
        <VCol cols="4" md="2" class="pa-1">
          <VCard class="stats-card border-0 overflow-hidden">
            <VCardText class="pa-5 relative-content">
              <div class="d-flex flex-column align-center text-center">
                <VAvatar
                  color="primary"
                  variant="tonal"
                  size="44"
                  rounded="lg"
                  class="mb-3"
                >
                  <VIcon icon="tabler-package" size="24" />
                </VAvatar>
                <span class="text-overline text-disabled leading-none mb-1 text-truncate" style="max-width: 100%"
                  >Productos</span
                >
                <h4 class="text-h4 font-weight-black">{{ totalProduct }}</h4>
              </div>
            </VCardText>
            <div class="accent-border bg-primary opacity-20"></div>
          </VCard>
        </VCol>


        <!-- Bloqueados -->
        <VCol cols="4" md="2" class="pa-1">
          <VCard class="stats-card border-0 overflow-hidden">
            <VCardText class="pa-5 relative-content">
              <div class="d-flex flex-column align-center text-center">
                <VAvatar
                  color="warning"
                  variant="tonal"
                  size="44"
                  rounded="lg"
                  class="mb-3"
                >
                  <VIcon icon="tabler-lock" size="24" />
                </VAvatar>
                <span class="text-overline text-disabled leading-none mb-1 text-truncate" style="max-width: 100%"
                  >Bloqueados</span
                >
                <h4 class="text-h4 font-weight-black text-warning">
                  {{
                    products.filter((p) => p.profitability?.is_locked == "1")
                      .length
                  }}
                </h4>
              </div>
            </VCardText>
            <div class="accent-border bg-warning opacity-20"></div>
          </VCard>
        </VCol>

        <!-- Última Actualización -->
        <VCol cols="4" md="2" class="pa-1">
          <VCard class="stats-card border-0 overflow-hidden">
            <VCardText class="pa-5 relative-content">
              <div class="d-flex flex-column align-center text-center">
                <VAvatar
                  color="info"
                  variant="tonal"
                  size="44"
                  rounded="lg"
                  class="mb-3"
                >
                  <VIcon icon="tabler-history" size="24" />
                </VAvatar>
                <span class="text-overline text-disabled leading-none mb-1 text-truncate" style="max-width: 100%"
                  >Última Act.</span
                >
                <h4 class="text-h4 font-weight-black text-info">HOY</h4>
              </div>
            </VCardText>
            <div class="accent-border bg-info opacity-20"></div>
          </VCard>
        </VCol>
      </VRow>

      <!-- Filtros Colapsables -->
      <profitabilityFilters
        v-model:searchQuery="searchQuery"
        v-model:selectedLaboratory="selectedLaboratory"
        v-model:selectedOrigin="selectedOrigin"
        v-model:stockStatusFilter="stockStatusFilter"
        v-model:startDate="startDate"
        v-model:endDate="endDate"
        v-model:lockedValue="lockedValue"
        :laboratories="laboratories"
        :origins="origins"
        :loading="isLoadingFilters"
        @add-profitability="addProfitability"
        @sort="handleSort"
        @clear="handleClearFilters"
      />

      <!-- Tabla Principal -->
      <ProfitabilityTable
        :products="products"
        :totalProduct="totalProduct"
        :profitability="profitability"
        :settings="globalSettings"
        :page="page"
        :itemsPerPage="itemsPerPage"
        :sort-by="sortBy"
        :order-by="orderBy"
        :loading="loading"
        @refresh="reloadTable"
        @update:options="updateTableOptions"
        @editProduct="editProductProfitability"
        @updateProduct="handleProductUpdated"
        class="ma-0 mt-1"
      />
    </div>

    <!-- Diálogos -->
    <addProfitabilityDialog
      :percentage="percentage"
      :settings="globalSettings"
      :dialog="dialog"
      @close-modal="dialog = false"
      @refresh="reloadTable"
    />

    <ProductProfitabilityEditDialog
      :product="productProfitability"
      :settings="globalSettings"
      :dialog="editDialog"
      @close-modal="editDialog = false"
      @refresh="reloadTable"
      @productUpdated="handleProductUpdated"
    />
  </div>
</template>

<style scoped>
.profitability-page {
  min-block-size: 100vh;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.stats-card {
  border-radius: 8px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 80%) !important;
  box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 5%) !important;
  transition: all 0.3s ease;
  position: relative;
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
  block-size: 100%;
  inline-size: 4px;
  inset-block-start: 0;
  inset-inline-start: 0;
}

.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

:deep(.v-btn.bg-success) {
  --v-theme-overlay: 255, 255, 255;
}
</style>
