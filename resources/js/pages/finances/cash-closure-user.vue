<script setup>
import CashSummary from "@/components/CashSummary.vue";
import axios from "@/plugins/axios";
import { ref, onMounted } from "vue";
import ClosedCashClosure from "@/components/dialogs/ClosedCashClosure.vue";
import ClosingHistoryTable from "@/components/ClosingHistoryTable.vue";

const loading = ref(false);
const cashClosure = ref([]);
const isCloseCashModalVisible = ref(false);

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
        :loading="loading"
        :total-closing="totalClosing"
        :items-per-page="itemsPerPage"
        :page="page"
        @update:options="updateTableOptions"
      />
  </VCard>

</template>
