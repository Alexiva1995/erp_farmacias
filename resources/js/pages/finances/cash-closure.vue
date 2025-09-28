<script setup>
import SellerBoxTable from "@/components/SellerBoxTable.vue";
import DailyCashClosingTable from "@/components/DailyCashClosingTable.vue";
import MonthlyCashClosingTable from "@/components/MonthlyCashClosingTable.vue";
import axios from "@/plugins/axios";


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

</script>
<template>
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
