<script setup>
import ReturnsFilter from "@/components/ReturnsFilter.vue";
import ReturnsSupervisorTable from "@/components/ReturnsSupervisorTable.vue";
import LotDistributionModal from "@/components/dialogs/LotDistributionModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const search = ref("");
const status = ref("pending");
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

const showLotModal = ref(false);
const returnForLotDistribution = ref(null);
const lotsForDistribution = ref([]);

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

/** Abre el modal de lotes para aprobar la devolución (obligatorio). No se aprueba hasta guardar. */
const openApproveLotsModal = async (item) => {
  if (!item?.product_id) {
    toast.error("No se puede abrir el ajuste de lotes para esta devolución.");
    return;
  }
  try {
    const { data: lotsData } = await axios.get(
      `/tpv/returns/product/${item.product_id}/lots`
    );
    const lots = lotsData.lots ?? [];
    returnForLotDistribution.value = item;
    lotsForDistribution.value = Array.isArray(lots) ? lots : [];
    showLotModal.value = true;
  } catch (err) {
    console.error("Error al cargar lotes:", err);
    toast.error("No se pudieron cargar los lotes del producto.");
  }
};

/** Solo para rechazar (la aprobación va por approve-with-distribution). */
const updateStatus = async (item, newStatus) => {
  try {
    const returnId = item.id;
    await axios.patch(`/tpv/returns/${returnId}/${newStatus}`);
    toast.success("Devolución rechazada exitosamente.");
    await fetchReturn();
  } catch (error) {
    console.error("Error al rechazar la devolución:", error.response?.data ?? error.message);
    const errorMessage =
      error.response?.data?.message ||
      "Error al rechazar la devolución. Inténtalo de nuevo.";
    toast.error(errorMessage);
  }
};

/** Al guardar en el modal: distribuir lotes y luego aprobar la devolución (en un solo paso). */
const handleLotsDistributed = async (payload) => {
  const returnItem = returnForLotDistribution.value;
  if (!returnItem?.id) return;
  try {
    await axios.post(`/tpv/returns/${returnItem.id}/approve-with-distribution`, {
      updated_lots: payload.updatedLots ?? [],
      new_lots: payload.newLots ?? [],
    });
    toast.success("Devolución aprobada y cantidad distribuida en lotes correctamente.");
    showLotModal.value = false;
    returnForLotDistribution.value = null;
    lotsForDistribution.value = [];
    await fetchReturn();
  } catch (error) {
    const msg =
      error.response?.data?.message ??
      error.message ??
      "Error al aprobar y distribuir lotes.";
    toast.error(msg);
  }
};

const clearFilters = () => {
  search.value = "";
  status.value = "pending";
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
    @open-approve-lots="openApproveLotsModal"
  />

  <LotDistributionModal
    v-model="showLotModal"
    :product-name="returnForLotDistribution?.product?.name ?? 'Producto'"
    :lots="lotsForDistribution"
    :target-quantity="returnForLotDistribution?.quantity ?? 0"
    @save="handleLotsDistributed"
  />
</template>
