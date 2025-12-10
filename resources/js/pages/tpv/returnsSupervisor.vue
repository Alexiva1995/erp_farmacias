<script setup>
import ReturnsSupervisorTable from "@/components/ReturnsSupervisorTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";

const returns = ref([]);
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

const updateStatus = async (item, status) => {
  try {
    let returnId = item.id;
    await axios.patch(`/tpv/returns/${returnId}/${status}`);
    toast.success("Devolución aprobada exitosamente.");
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
</script>

<template>
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
