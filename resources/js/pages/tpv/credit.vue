<script setup>
import CreditTable from "@/components/CreditTable.vue";
import axios from "@/plugins/axios";
import CreditsModal from "@/components/dialogs/CreditsModal.vue";

const credits = ref([]);
const totalCredits = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const showCreditsModal = ref(false);
const creditsData = ref(null);

const fetchCredits= async () => {
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
    const response = await axios.get("/tpv/credits", { params });
    credits.value = response.data.data;
    totalCredits.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los créditos:", error);
    toast.error("Error al obtener los créditos.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchCredits(), 300);
  },
  { deep: true }
);

onMounted(() => {
  fetchCredits();
});


const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};


const openCreditsModal = (credit) => {
    creditsData.value = credit; 
        console.log(creditsData);
    showCreditsModal.value = true;
};


const closeCreditsModal = () => {
    showCreditsModal.value = false;
    creditsData.value = null;
};

const handleCreditsCompletion = async () => {
 try {
    showCreditsModal.value = false; 
 } catch (error) {
    console.error("Error al finalizar el pago del crédito:", error.response ? error.response.data : error.message);
    const errorMessage = error.response?.data?.message || "Hubo un problema al procesar su pago. Por favor, intente de nuevo.";
    toast.error(errorMessage);
  }
}

</script>

<template>
    <CreditTable
      :credits="credits"
      :loading="loading"
      :total-credits="totalCredits"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @open-payment-modal="openCreditsModal"
      @reload="fetchCredits"
    />

   <CreditsModal
            v-if="showCreditsModal && creditsData"
            v-model:is-dialog-visible="showCreditsModal"
            :credits-data="creditsData"
            @modal-closed="closeCreditsModal"
            @purchase-completed="handleCreditsCompletion"
        />

</template>
