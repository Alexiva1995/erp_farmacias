<script setup>
import CreditTable from "@/components/CreditTable.vue";
import axios from "@/plugins/axios";

const credits = ref([]);
const totalCredits = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

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
    console.log(response.data.data);
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

</script>

<template>
    <CreditTable
      :credits="credits"
      :loading="loading"
      :total-credits="totalCredits"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
    />


      <div id="creditsPrint" :class="{ 'd-none': !isPrinting, 'print-container': true }">
      <CreditsTicket
        v-if="isPrinting && openOrderData"
        :order-data="openOrderData"
        :order-products="orderItems"
        :total-amount="myCalculatedTotal"
        :selected-currency="selectedDisplayCurrency"
        :payments="paymentsForPrint"
        :change-amount="changeAmountForPrint"
        :credit-amount="creditAmountForPrint"
        :credit="creditForPrint"
      />
    </div>

</template>
