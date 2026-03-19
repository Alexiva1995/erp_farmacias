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
    ...(stockStatusFilter.value !== null && { hasStock: stockStatusFilter.value }),
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
    const response = await axios.get("/products", { params });
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
    const response = await axios.get("/finances/profitability");
    profitability.value = response.data.default_profitability_percentage || 0;
  } catch (error) {
    console.error("Hubo un error al obtener la rentabilidad:", error);
  }
};

// Handlers
const addProfitability = () => {
  dialog.value = true;
};

const editProductProfitability = (profitability_id = null, percentage = 0, id_product, is_locked = 1) => {
  editDialog.value = true;
  productProfitability.value = {
    id: profitability_id,
    percentage: percentage,
    product_id: id_product,
    is_locked: is_locked,
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
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

function reloadTable() {
  fetchProducts();
  percentProfitability();
}

// Watchers
let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter, startDate, endDate, lockedValue],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true }
);

watch([searchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter, startDate, endDate, lockedValue], () => {
  page.value = 1;
});

// Lifecycle
onMounted(() => {
  fetchFiltersOptions();
  reloadTable();
});

// Computed for summary
const avgPrice = computed(() => {
  if (!products.value.length) return 0;
  return products.value.reduce((acc, p) => acc + (parseFloat(p.unit_cost) || 0), 0) / products.value.length;
});

const formatCurrency = (amount) =>
  new Intl.NumberFormat("es-US", { style: "currency", currency: "USD" }).format(amount);
</script>

<template>
  <VContainer fluid class="profitability-page pa-4">
    <!-- Header Premium -->
    <VRow class="mb-6" dense>
      <VCol cols="12" md="4">
        <VCard class="header-main-card h-100 overflow-hidden border-0 shadow-lg position-relative">
          <div class="premium-header pa-5 d-flex align-center gap-4">
            <VAvatar size="64" color="white" variant="elevated" class="shadow-sm">
              <VIcon icon="tabler-chart-pie" color="primary" size="32" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-super-xs font-weight-black text-white opacity-80 uppercase mb-1">Configuración Global</span>
              <span class="text-h5 font-weight-black text-white leading-tight mb-1">Rentabilidad</span>
              <div class="d-flex align-center gap-2">
                <span class="text-h4 font-weight-black text-white">{{ profitability }}%</span>
                <VChip color="white" size="x-small" variant="tonal" class="font-weight-black">POR DEFECTO</VChip>
              </div>
            </div>
            <VIcon icon="tabler-trending-up" size="80" class="position-absolute opacity-10" style="inset-block-end: -10px; inset-inline-end: -10px;" />
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" md="8">
        <VCard class="h-100 border-0 shadow-sm rounded-xl overflow-hidden">
          <VCardText class="d-flex align-center h-100 pa-6 bg-surface-variant-light">
            <VRow class="w-100 text-center align-center">
              <VCol cols="6" sm="3">
                <div class="d-flex flex-column align-center">
                  <VAvatar color="primary" variant="tonal" size="40" class="mb-2">
                    <VIcon icon="tabler-package" size="20" />
                  </VAvatar>
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Productos</span>
                  <span class="text-h6 font-weight-black">{{ totalProduct }}</span>
                </div>
              </VCol>
              <VCol cols="6" sm="3">
                <div class="d-flex flex-column align-center">
                  <VAvatar color="success" variant="tonal" size="40" class="mb-2">
                    <VIcon icon="tabler-currency-dollar" size="20" />
                  </VAvatar>
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Costo Promedio</span>
                  <span class="text-h6 font-weight-black text-success">{{ formatCurrency(avgPrice) }}</span>
                </div>
              </VCol>
              <VCol cols="6" sm="3">
                <div class="d-flex flex-column align-center">
                  <VAvatar color="warning" variant="tonal" size="40" class="mb-2">
                    <VIcon icon="tabler-lock" size="20" />
                  </VAvatar>
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Bloqueados</span>
                  <span class="text-h6 font-weight-black text-warning">
                    {{ products.filter(p => p.profitability?.is_locked == '1').length }}
                  </span>
                </div>
              </VCol>
              <VCol cols="6" sm="3">
                <div class="d-flex flex-column align-center">
                  <VAvatar color="info" variant="tonal" size="40" class="mb-2">
                    <VIcon icon="tabler-history" size="20" />
                  </VAvatar>
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Última Act.</span>
                  <span class="text-h6 font-weight-black text-info">HOY</span>
                </div>
              </VCol>
            </VRow>
          </VCardText>
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
      :page="page"
      :itemsPerPage="itemsPerPage"
      :loading="loading"
      @refresh="reloadTable"
      @update:options="updateTableOptions"
      @editProduct="editProductProfitability"
    />

    <!-- Diálogos -->
    <addProfitabilityDialog
      :percentage="percentage"
      :dialog="dialog"
      @close-modal="dialog = false"
      @refresh="reloadTable"
    />

    <ProductProfitabilityEditDialog
      :product="productProfitability"
      :dialog="editDialog"
      @close-modal="editDialog = false"
      @refresh="reloadTable"
    />
  </VContainer>
</template>

<style scoped>
.profitability-page {
  background-color: rgb(var(--v-theme-background));
  min-block-size: 100vh;
}

.premium-header {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #2c3e50 100%);
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 0.03);
}

.header-main-card {
  border-radius: 24px !important;
}

:deep(.v-card) {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.header-main-card:hover {
  transform: translateY(-2px);
}
</style>
