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

onMounted(() => {
    fetchSummaryData();
});
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
