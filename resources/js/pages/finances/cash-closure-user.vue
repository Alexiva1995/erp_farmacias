<script setup>
import CashSummary from "@/components/CashSummary.vue";
import axios from "@/plugins/axios";
import { ref, onMounted } from "vue";
import ClosedCashClosure from "@/components/dialogs/ClosedCashClosure.vue";
import ClosingHistoryTable from "@/components/ClosingHistoryTable.vue";

const loading = ref(false);
const cashClosure = ref([]);
const isCloseCashModalVisible = ref(false);

const closing = ref([]);
const totalClosing = ref(0);
const loadingClosing = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const fetchCashClosure = async () => {
  try {
    loading.value = true;
    const response = await axios.get("/finances/cash-closure/");
    cashClosure.value = response.data;
  } catch (error) {
    console.error("Hubo un error al obtener el resumen de caja:", error);
    toast.error("Error al obtener el resumen de caja.");
    cashClosure.value = null;
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchCashClosure();
  fetchClosingHistory();
});

const confirmCloseCash = async () => {
  console.log("Caja cerrada confirmada desde el padre!");
  try {
    //const response = await axios.post(`/finances/cash-closure/close/${cashClosure.value.id}`);
    toast.success("Caja cerrada con éxito.");
    isCloseCashModalVisible.value = false;
    fetchCashClosure();
  } catch (error) {
    console.error("Error al cerrar la caja:", error);
    toast.error(
      "Error al cerrar la caja: " +
        (error.response?.data?.message || error.message)
    );
  }
};

const handleRequestCloseCash = () => {
  isCloseCashModalVisible.value = true;
};

const fetchClosingHistory = async () => {
  loadingClosing.value = true;
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
    //const response = await axios.get("/tpv/orders/cancelled", { params });
    closing.value = response.data.data;
    totalClosing.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los cierres:", error);
    toast.error("Error al obtener los cierres.");
  } finally {
    loadingClosing.value = false;
  }
};
</script>

<template>
  <div>
    <p v-if="loading">Cargando resumen de caja...</p>
    <p v-else-if="!cashClosure">No hay datos de cierre de caja disponibles.</p>
    <CashSummary
      v-else
      :cashClosureData="cashClosure"
      @requestCloseCash="handleRequestCloseCash"
    />

    <ClosedCashClosure
      v-model:isDialogVisible="isCloseCashModalVisible"
      :cashClosureData="cashClosure"
    />
  </div>
  <div class="mb-5"></div>
  <VCard title="Histórico de cierre">
    <div class="mb-2"></div>
    <ClosingHistoryTable
      :closing="closing"
      :loading="loadingClosing"
      :total-closing="totalClosing"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
    />
  </VCard>
</template>
