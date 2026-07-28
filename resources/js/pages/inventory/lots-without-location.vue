<script setup>
import LotsWithoutLocationTable from "@/components/LotsWithoutLocationTable.vue";
import ProductFilters from "@/components/ProductFilters.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, onUnmounted, ref, watch } from "vue";

const lots = ref([]);
const totalLots = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref('product.name');
const orderBy = ref('asc');

const lotWithError = ref(null);
const errorMessage = ref("");
const searchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const stockStatusFilter = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const isStrictSearch = ref(false);

const laboratories = ref([]);
const origins = ref([]);

const isLoadingFilters = ref(false);

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const handleUpdateLot = async (payload) => {
  if (lotWithError.value === payload.lot_id) {
    lotWithError.value = null;
    errorMessage.value = "";
  }

  try {
    await axios.put(`/product-lots/${payload.lot_id}`, { location: payload.location });
    toast.success("Ubicación asignada correctamente");
    await fetchLots();
  } catch (err) {
    toast.error("Error al actualizar la ubicación");

    if (err.response?.status === 422) {
      lotWithError.value = payload.lot_id;
      const errors = err.response.data?.errors;
      if (errors?.location && errors.location[0]) {
        errorMessage.value = errors.location[0];
      } else {
        errorMessage.value = "Error al actualizar la ubicación";
      }
    }
  }
};

const fetchLots = async () => {
  loading.value = true;
  const params = {
    search: searchQuery.value || undefined,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value || undefined,
    orderBy: orderBy.value || undefined,
    laboratoryId: selectedLaboratory.value || undefined,
    originId: selectedOrigin.value || undefined,
    startDate: startDate.value || undefined,
    endDate: endDate.value || undefined,
    isStrictSearch: isStrictSearch.value || undefined,
    ...(stockStatusFilter.value !== null && { hasStock: stockStatusFilter.value }),
  };

  try {
    const response = await axios.get("/product-lots/without-location", { params });
    const payload = response.data?.data;
    lots.value = payload?.data || payload || [];
    totalLots.value = payload?.total || 0;
  } catch (error) {
    console.error("Hubo un error al obtener los lotes:", error);
    toast.error("Error al obtener los lotes sin ubicación.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;

// Watcher unificado para filtros con debounce y reseteo a página 1
watch(
  [
    searchQuery,
    selectedLaboratory,
    selectedOrigin,
    stockStatusFilter,
    startDate,
    endDate,
    isStrictSearch,
  ],
  () => {
    page.value = 1;
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchLots(), 300);
  }
);

// Watcher para paginación y ordenamiento directo
watch([page, itemsPerPage, sortBy, orderBy], () => {
  fetchLots();
});

onMounted(() => {
  fetchSelectOptions();
  fetchLots();
});

onUnmounted(() => clearTimeout(debounceTimer));

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0]?.key;
    orderBy.value = options.sortBy[0]?.order;
  }
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
  selectedOrigin.value = null;
  stockStatusFilter.value = null;
  startDate.value = null;
  endDate.value = null;
  isStrictSearch.value = false;
};

const handleSort = (sortOptions) => {
  if (sortOptions.key === undefined && sortOptions.order === undefined) {
    sortBy.value = undefined;
    orderBy.value = undefined;
  } else {
    sortBy.value = sortOptions.key;
    orderBy.value = sortOptions.order;
  }
};
</script>

<template>
  <div>
    <ProductFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:selectedOrigin="selectedOrigin"
      v-model:stockStatusFilter="stockStatusFilter"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      v-model:isStrictSearch="isStrictSearch"
      :laboratories="laboratories"
      :origins="origins"
      :loading="isLoadingFilters"
      mode="products"
      :show-add-button="false"
      @clear="handleClearFilters"
      @sort="handleSort"
    />

    <LotsWithoutLocationTable
      :lots="lots"
      :loading="loading"
      :total-lots="totalLots"
      :items-per-page="itemsPerPage"
      :page="page"
      :lot-with-error="lotWithError"
      :error-message="errorMessage"
      @update:options="updateTableOptions"
      @update-lot="handleUpdateLot"
    />
  </div>
</template>
