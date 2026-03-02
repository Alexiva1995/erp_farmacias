<script setup>
import CashoutFilters from '@/components/CashoutFilters.vue';
import TransactionsTable from '@/components/TransactionsTable.vue';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import { onMounted, ref, watch } from 'vue';

const transactions      = ref([]);
const transactionsGroupped = ref({});
const wallets           = ref({ sections: [], total_usd: 0 });
const loading           = ref(false);
const walletsLoading    = ref(false);
const dataDetailed      = ref(false);
const dateRange         = ref('');
const selectedCurrency  = ref('');
const previousCurrency  = ref('');
const selectedOption    = ref('');
const previousTotalUsd  = ref(0);

const page           = ref(1);
const transactionsTotal = ref(0);
const itemsPerPage   = ref(10);

// ─── Transacciones ───────────────────────────────────────────────────────────
const fetchTransactions = async ({ date, currency, detailed, option } = {}) => {
  try {
    const params = {};
    if (date) {
      const [from, to] = date.split(' to ');
      params.start_date = from;
      params.end_date   = to;
    }

    params.currency = detailed ? currency || 'USD' : currency;
    if (detailed) {
      params.detailed = detailed;
      params.option   = option;
    }

    const { data } = await axios.get('/finances/transactions', {
      params: { ...params, per_page: itemsPerPage.value, page: page.value },
    });
    transactions.value      = data.data.items;
    transactionsTotal.value = data.data.total;
    previousTotalUsd.value  = data.data.previous_total_usd;
  } catch (error) {
    console.error('Error al obtener las transacciones:', error);
    toast.error('Error al obtener las transacciones.');
  } finally {
    loading.value = false;
  }
};

// ─── Stats (gráfico sparkline) ────────────────────────────────────────────────
const fetchTransactionsGroupped = async ({ date, currency, detailed } = {}) => {
  try {
    const params = {};
    if (date) {
      const [from, to] = date.split(' to ');
      params.start_date = from;
      params.end_date   = to;
    }
    if (currency) params.currency = currency;
    if (detailed)  params.detailed = detailed;

    const { data } = await axios.get('/finances/transactions/stats', { params });
    transactionsGroupped.value = data.data;
  } catch (error) {
    console.error('Error al obtener stats:', error);
  } finally {
    loading.value = false;
  }
};

// ─── Wallets ──────────────────────────────────────────────────────────────────
const fetchWallets = async (date) => {
  walletsLoading.value = true;
  try {
    const params = {};
    if (date) {
      const parts = date.split(' to ');
      if (parts.length === 2) {
        params.start_date = parts[0];
        params.end_date   = parts[1];
      }
    }
    const { data } = await axios.get('/finances/transactions/wallets', { params });
    wallets.value = data.data;
  } catch (error) {
    console.error('Error al obtener wallets:', error);
  } finally {
    walletsLoading.value = false;
  }
};

// ─── Ciclo de vida ────────────────────────────────────────────────────────────
onMounted(() => {
  fetchTransactions();
  fetchTransactionsGroupped();
  fetchWallets();
});

// Actualizar wallets y stats cuando cambia el rango de fechas
watch(dateRange, (date) => {
  fetchTransactionsGroupped({ date });
  fetchWallets(date);
}, { deep: true });

// Actualizar tabla cuando cambian filtros
watch(
  [dateRange, selectedCurrency, dataDetailed, selectedOption],
  ([date, currency, detailed, option]) => {
    page.value = 1;
    fetchTransactions({ date, currency, detailed, option });
  },
  { deep: true },
);

watch(selectedCurrency, (newVal, oldVal) => {
  if (newVal && newVal !== oldVal && newVal !== previousCurrency.value) {
    toast.success(`Moneda seleccionada: ${newVal}`);
    previousCurrency.value = newVal;
  }
});

let debounceTimer;
watch(
  [page, itemsPerPage, dateRange, selectedCurrency, dataDetailed, selectedOption],
  ([pg, items, date, currency, detailed, option]) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchTransactions({ date, currency, detailed, option }), 300);
  },
  { deep: true },
);

// ─── Acciones ─────────────────────────────────────────────────────────────────
const handleClearFilters = () => {
  dateRange.value       = '';
  dataDetailed.value    = false;
  selectedCurrency.value = '';
  selectedOption.value  = '';
};

const updateTableOptions = (options) => {
  page.value         = options.page;
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
      :wallets="wallets"
      :wallets-loading="walletsLoading"
      @clear="handleClearFilters"
    />
    <TransactionsTable
      v-model:selectedTab="selectedTab"
      v-model:dataDetailed="dataDetailed"
      :selectedCurrency="selectedCurrency"
      :transactions="transactions"
      :loading="loading"
      :itemsPerPage="itemsPerPage"
      :page="page"
      :totalTransactions="transactionsTotal"
      @update:options="updateTableOptions"
    />
  </div>
</template>
