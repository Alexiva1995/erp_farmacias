<script setup>
import ReturnsFilter from "@/components/ReturnsFilter.vue";
import ReturnsSupervisorTable from "@/components/ReturnsSupervisorTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const search = ref("");
const status = ref("");
const supplier = ref(null);
const seller = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const returns = ref([]);
const sellers = ref([]);
const totalReturns = ref(0);
const loading = ref(false);

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

onMounted(() => {
  fetchReturn();
  fetchSellers();
});

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy],
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

const fetchSellers = async () => {
  try {
    const { data } = await axios.get("/users");
    sellers.value = data.data;
  } catch (error) {
    toast.error("No se pudo obtener el listado de vendedores");
  }
};

const updateStatus = async (item, status) => {
  try {
    let returnId = item.id;
    const { data } = await axios.patch(`/tpv/returns/${returnId}/${status}`);

    const message = data.message.return.status;
    toast.success(
      `Devolución ${
        message === "Rejected" ? "rechazada" : "aprobada"
      } exitosamente.`
    );
    await fetchReturn();
  } catch (error) {
    console.error(
      "Error al aprobar la devolución:",
      error.response ? error.response.data : error.message
    );
    const errorMessage =
      error.response?.data?.message ||
      "Error al aprobar la devolución. Inténtalo de nuevo.";
    toast.error(errorMessage);
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

watch(
  [
    search,
    status,
    seller,
    startDate,
    endDate,
    page,
    itemsPerPage,
    sortBy,
    orderBy,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchReturn(), 300);
  },
  { deep: true }
);
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
  <ReturnsSupervisorTable
    :returns="returns"
    :loading="loading"
    :total-returns="totalReturns"
    :items-per-page="itemsPerPage"
    :page="page"
    @update:options="updateTableOptions"
    @status="updateStatus"
  />
</template>
