<script setup>
import CashoutFilters from "@/components/CashoutFilters.vue";
import TransactionsTable from "@/components/TransactionsTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const transactions = ref([]);
const transactionsGroupped = ref({});
const loading = ref(false);
const dataDetailed = ref(false);
const dateRange = ref("");
const selectedCurrency = ref("");
const previousCurrency = ref("");
const selectedOption = ref("");
const previousTotalUsd = ref(0);

const page = ref(1);
const transactionsTotal = ref(0);
const itemsPerPage = ref(10);

const fetchTransactions = async ({ date, currency, detailed, option } = {}) => {
  try {
    const params = {};
    if (date) {
      const [from, to] = date.split(" to ");
      params.start_date = from;
      params.end_date = to;
    }

    params.currency = detailed ? currency || "USD" : currency;
    if (detailed) {
      params.detailed = detailed;
      params.option = option;
    }

    const { data } = await axios.get("/finances/transactions", {
      params: {
        ...params,
        per_page: itemsPerPage.value,
        page: page.value,
      },
    });
    transactions.value = data.data.items;
    transactionsTotal.value = data.data.total;
    previousTotalUsd.value = data.data.previous_total_usd;
  } catch (error) {
    console.error("Hubo un error al obtener las transacciones:", error);
    toast.error("Error al obtener las transacciones.");
  } finally {
    loading.value = false;
  }
};

const fetchTransactionsGroupped = async ({ date, currency, detailed } = {}) => {
  try {
    const params = {};
    if (date) {
      const [from, to] = date.split(" to ");
      params.start_date = from;
      params.end_date = to;
    }
    if (currency) params.currency = currency;
    if (detailed) params.detailed = detailed;

    const { data } = await axios.get("/finances/transactions/stats", {
      params,
    });

    transactionsGroupped.value = data.data;
  } catch (error) {
    console.error("Hubo un error al obtener las transacciones:", error);
    toast.error("Error al obtener las transacciones.");
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchTransactions();
  fetchTransactionsGroupped();
});

watch(
  dateRange,
  ([date]) => {
    fetchTransactionsGroupped();
  },
  { deep: true }
);

watch(
  [dateRange, selectedCurrency, dataDetailed, selectedOption],
  ([date, currency, detailed, option]) => {
    const opts = { date, currency, detailed, option };
    page.value = 1;
    fetchTransactions(opts);
  },
  { deep: true }
);

watch(selectedCurrency, (newVal, oldVal) => {
  if (newVal && newVal !== oldVal && newVal !== previousCurrency.value) {
    toast.success(`Moneda seleccionada: ${newVal}`);
    previousCurrency.value = newVal;
  }
});

let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    dateRange,
    selectedCurrency,
    dataDetailed,
    selectedOption,
  ],
  ([page, items, date, currency, detailed, option]) => {
    clearTimeout(debounceTimer);
    const opts = { date, currency, detailed, option };
    debounceTimer = setTimeout(() => fetchTransactions(opts), 300);
  },
  { deep: true }
);

const handleClearFilters = () => {
  dateRange.value = "";
  dataDetailed.value = false;
  selectedCurrency.value = "";
  selectedOption.value = "";
};

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
};
</script>

<template>
  <div>
    <CashoutFilters
      v-model:dateRange="dateRange"
      v-model:dataDetailed="dataDetailed"
      v-model:selectedCurrency="selectedCurrency"
      v-model:selectedOption="selectedOption"
      :stats="transactionsGroupped"
      @clear="handleClearFilters"
    />
    <TransactionsTable
      v-model:selectedTab="selectedTab"
      v-model:dataDetailed="dataDetailed"
      :selectedCurrency="selectedCurrency"
      :previous-total-usd="previousTotalUsd"
      :transactions="transactions"
      :loading="loading"
      :itemsPerPage="itemsPerPage"
      :page="page"
      :totalTransactions="transactionsTotal"
      @update:options="updateTableOptions"
    />
  </div>
</template>
