<script setup>
import ReturnsFilter from "@/components/ReturnsFilter.vue";
import ReturnsOrderGeneralTable from "@/components/ReturnsOrderGeneralTable.vue";
import { toast } from "@/plugins/sweetalert";
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";

const returns = ref([]);
const totalReturns = ref(0);
const loading = ref(false);

const search = ref("");
const status = ref("");
const supplier = ref(null);
const seller = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const sellers = ref([]);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const fetchSellers = async () => {
  try {
    const { data } = await axios.get("/users");
    sellers.value = data.data;
  } catch {
    toast.error("No se pudo obtener el listado de vendedores");
  }
};

const clearFilters = () => {
  search.value = "";
  status.value = "";
  seller.value = null;
  supplier.value = null;
  startDate.value = null;
  endDate.value = null;
  page.value = 1;
};

onMounted(() => {
  fetchSellers();
  fetchReturn();
});

let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
    search,
    status,
    seller,
    startDate,
    endDate,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchReturn(), 300);
  },
  { deep: true }
);

const fetchReturn = async () => {
  loading.value = true;
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    search: search.value,
    status: status.value,
    seller: seller.value,
    startDate: startDate.value,
    endDate: endDate.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get("/tpv/returns", { params });
    returns.value = response.data.data;
    totalReturns.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener las devoluciones:", error);
    toast.error("Error al obtener las devoluciones.");
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <ReturnsFilter
    :search="search"
    :supplier="supplier"
    :status="status"
    :start-date="startDate"
    :end-date="endDate"
    :sellers="sellers"
    :seller="seller"
    @update:search="search = $event"
    @update:status="status = $event"
    @update:supplier="supplier = $event"
    @update:start-date="startDate = $event"
    @update:end-date="endDate = $event"
    @update:seller="seller = $event"
    @clear="clearFilters"
  />
  <ReturnsOrderGeneralTable
      :returns="returns"
      :loading="loading"
      :total-returns="totalReturns"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
    />

</template>
