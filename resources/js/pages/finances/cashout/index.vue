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
  loading.value = true;
  try {
    const params = {};
    if (date) {
      const parts = date.split(' to ');
      if (parts.length === 2) {
        params.start_date = parts[0];
        params.end_date   = parts[1];
      } else {
         params.start_date = date;
      }
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

const fetchTransactionsGroupped = async ({ date, currency, detailed } = {}) => {
  try {
    const params = {};
    if (date) {
      const parts = date.split(' to ');
      if (parts.length === 2) {
        params.start_date = parts[0];
        params.end_date   = parts[1];
      }
    }
    if (currency) params.currency = currency;
    if (detailed)  params.detailed = detailed;

    const { data } = await axios.get('/finances/transactions/stats', { params });
    transactionsGroupped.value = data.data;
  } catch (error) {
    console.error('Error al obtener stats:', error);
  }
};

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

// ─── Handlers ─────────────────────────────────────────────────────────────────
const handleClearFilters = () => {
  dateRange.value       = '';
  dataDetailed.value    = false;
  selectedCurrency.value = '';
  selectedOption.value  = '';
  page.value = 1;
};

const updateTableOptions = (options) => {
  page.value         = options.page;
  itemsPerPage.value = options.itemsPerPage;
};

// ─── Watchers ─────────────────────────────────────────────────────────────────
let debounceTimer;
watch(
  [page, itemsPerPage, dateRange, selectedCurrency, dataDetailed, selectedOption],
  ([pg, items, date, currency, detailed, option]) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      fetchTransactions({ date, currency, detailed, option });
      if (pg === 1) {
        fetchTransactionsGroupped({ date, currency, detailed });
        fetchWallets(date);
      }
    }, 300);
  },
  { deep: true },
);

// ─── Ciclo de vida ────────────────────────────────────────────────────────────
onMounted(() => {
  fetchTransactions();
  fetchTransactionsGroupped();
  fetchWallets();
});
</script>

<template>
  <VContainer fluid class="cashout-page pa-4">
    <!-- Header Premium -->
    <VCard class="header-main-card mb-6 overflow-hidden border-0 shadow-lg">
      <div class="premium-header pa-6 d-flex flex-wrap align-center gap-4">
        <VAvatar size="64" color="white" variant="elevated" class="shadow-sm">
          <VIcon icon="tabler-report-money" color="primary" size="32" />
        </VAvatar>
        <div class="d-flex flex-column">
          <span class="text-super-xs font-weight-black text-white opacity-80 uppercase mb-1">Tesorería / Flujo</span>
          <span class="text-h4 font-weight-black text-white leading-tight">Flujo de Caja</span>
        </div>
        
        <VSpacer class="d-none d-md-block" />
        
        <!-- KPI Destacado -->
        <VCard variant="tonal" color="white" class="rounded-xl px-6 py-2 border-opacity-10 d-none d-sm-block">
          <div class="d-flex flex-column align-end">
            <span class="text-super-xs font-weight-black text-white opacity-70 uppercase mb-1">Balance Consolidado</span>
            <span class="text-h5 font-weight-black text-white">
              USD {{ new Intl.NumberFormat('es-ES', { minimumFractionDigits: 2 }).format(wallets.total_usd) }}
            </span>
          </div>
        </VCard>
        
        <VIcon icon="tabler-chart-arrows-vertical" size="100" class="position-absolute opacity-10" style="inset-block-end: -20px; inset-inline-end: -20px;" />
      </div>
    </VCard>

    <!-- Filtros y Wallets -->
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

    <!-- Tabla de Movimientos -->
    <TransactionsTable
      v-model:dataDetailed="dataDetailed"
      :selectedCurrency="selectedCurrency"
      :transactions="transactions"
      :loading="loading"
      :itemsPerPage="itemsPerPage"
      :page="page"
      :totalTransactions="transactionsTotal"
      @update:options="updateTableOptions"
    />
  </VContainer>
</template>

<style scoped>
.cashout-page {
  background-color: rgb(var(--v-theme-background));
  min-block-size: 100vh;
}

.premium-header {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e272e 100%);
}

.header-main-card {
  border-radius: 24px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

:deep(.v-card) {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
</style>
