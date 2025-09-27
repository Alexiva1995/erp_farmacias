<script setup>
import SellerBoxTable from "@/components/SellerBoxTable.vue";
import DailyCashClosingTable from "@/components/DailyCashClosingTable.vue";
import MonthlyCashClosingTable from "@/components/MonthlyCashClosingTable.vue";
import CashAverage from "@/components/cards/CashAverage.vue";
import axios from "@/plugins/axios";
import { ref, onMounted } from 'vue';

const sellerCash = ref([]);
const totalSellerCash = ref(0);
const loadingSellerCash = ref(false);
const pageSellerCash = ref(1);
const itemsPerPageSellerCash = ref(10);
const sortBySellerCash = ref();
const orderBySellerCash = ref();

const dailyCash = ref([]);
const totalDailyCash = ref(0);
const loadingDailyCash = ref(false);
const pageDailyCash = ref(1);
const itemsPerPageDailyCash = ref(10);
const sortByDailyCash = ref();
const orderByDailyCash = ref();

const monthlyCash = ref([]);
const totalMonthlyCash = ref(0);
const loadingMonthlyCash = ref(false);
const pageMonthlyCash = ref(1);
const itemsPerPageMonthlyCash = ref(10);
const sortByMonthlyCash = ref();
const orderByMonthlyCash = ref();

const summaryData = ref({
    current_month_average: '0.00',
    last_month_average: '0.00',
    percentage_change: '0.0',
    is_positive: true,
});

const fetchSummaryData = async () => {
    try {
        const response = await axios.get('/finances/cash-closure/sales/summary'); 
        summaryData.value = response.data;
    } catch (error) {
        console.error("Error al obtener el resumen de ventas:", error);
    }
};

const fetchDailyCashData = async () => {
loadingDailyCash.value = true;
 const params = {
    page: pageDailyCash.value,
    itemsPerPage: itemsPerPageDailyCash.value,
    sortBy: sortByDailyCash.value,
    orderBy: orderByDailyCash.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
    try {
        const response = await axios.get('/finances/cash-closure/dailyCash',{params}); 
        dailyCash.value = response.data.data;
        totalDailyCash.value = response.data.total;
     } catch (error) {
      console.error("Hubo un error al obtener los cierres diarios:", error);
      toast.error("Error al obtener los cierres diarios.");
    } finally {
      loadingDailyCash.value = false;
    }
};

onMounted(() => {
    fetchSummaryData();
    fetchDailyCashData();
});

let debounceTimer;
watch(
  [
    pageDailyCash,
    itemsPerPageDailyCash,
    sortByDailyCash,
    orderByDailyCash,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      fetchDailyCashData();
    }, 300);
  },
  { deep: true }
);
const updateTableOptionsDailyCash = (options) => {
  pageDailyCash.value = options.page;
  itemsPerPageDailyCash.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortByDailyCash.value = options.sortBy[0].key;
    orderByDailyCash.value = options.sortBy[0].order;
  } else {
    sortByDailyCash.value = null;
    orderByDailyCash.value = null;
  }
};
</script>
<template>
  <CashAverage
    :average-amount="summaryData.current_month_average"
    :last-month-average="summaryData.last_month_average"
    :percentage-change="summaryData.percentage_change"
    :is-positive="summaryData.is_positive"
  />
  <div class="mb-5"></div>
  <SellerBoxTable
    :sellerCash="sellerCash"
    :loading="loadingSellerCash"
    :total-sellerCash="totalSellerCash"
    :items-per-page="itemsPerPageSellerCash"
    :page="pageSellerCash"
  />
  <div class="mb-5"></div>
  <DailyCashClosingTable
    :dailyCash="dailyCash"
    :loading="loadingDailyCash"
    :total-dailyCash="totalDailyCash"
    :items-per-page="itemsPerPageDailyCash"
    :page="pageDailyCash"
    @update:options="updateTableOptionsDailyCash"
  />
  <div class="mb-5"></div>
  <MonthlyCashClosingTable
    :monthlyCash="monthlyCash"
    :loading="loadingMonthlyCash"
    :total-monthlyCash="totalMonthlyCash"
    :items-per-page="itemsPerPageMonthlyCash"
    :page="pageMonthlyCash"
  />
</template>
