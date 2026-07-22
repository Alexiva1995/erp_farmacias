<script setup>
import CashoutFilters from "@/components/CashoutFilters.vue";
import TransactionsTable from "@/components/TransactionsTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";

const router = useRouter();
const authStore = useAuthStore();

const transactions = ref([]);
const transactionsGroupped = ref({});
const wallets = ref({ sections: [], total_usd: 0 });
const loading = ref(false);
const walletsLoading = ref(false);
const dataDetailed = ref(false);
const dateRange = ref("");
const selectedCurrency = ref("");
const previousCurrency = ref("");
const selectedOption = ref("");
const previousTotalUsd = ref(0);
const isAdjustmentModalOpen = ref(false);
const isAdjusting = ref(false);
const adjustmentValue = ref(0);
const adjustmentWallet = ref(null);
const isInitialized = ref(false);

const page = ref(1);
const transactionsTotal = ref(0);
const itemsPerPage = ref(10);

// ─── Transacciones ───────────────────────────────────────────────────────────
const fetchTransactions = async ({ date, currency, detailed, option } = {}) => {
  loading.value = true;
  try {
    const params = {};
    if (date) {
      const parts = date.split(" to ");
      if (parts.length === 2) {
        params.start_date = parts[0];
        params.end_date = parts[1];
      } else {
        params.start_date = date;
      }
    }

    params.currency = detailed ? currency || "USD" : currency;
    if (detailed) {
      params.detailed = detailed;
      params.option = option;
    }

    const { data } = await axios.get("/finances/transactions", {
      params: { ...params, per_page: itemsPerPage.value, page: page.value },
    });
    transactions.value = data.data.items;
    transactionsTotal.value = data.data.total;
    previousTotalUsd.value = data.data.previous_total_usd;
  } catch (error) {
    console.error("Error al obtener las transacciones:", error);
    toast.error("Error al obtener las transacciones.");
  } finally {
    loading.value = false;
  }
};

const fetchTransactionsGroupped = async ({ date, currency, detailed } = {}) => {
  try {
    const params = {};
    if (date) {
      const parts = date.split(" to ");
      if (parts.length === 2) {
        params.start_date = parts[0];
        params.end_date = parts[1];
      }
    }
    if (currency) params.currency = currency;
    if (detailed) params.detailed = detailed;

    const { data } = await axios.get("/finances/transactions/stats", {
      params,
    });
    transactionsGroupped.value = data.data;
  } catch (error) {
    console.error("Error al obtener stats:", error);
  }
};

const fetchWallets = async (date) => {
  walletsLoading.value = true;
  try {
    const params = {};
    if (date) {
      const parts = date.split(" to ");
      if (parts.length === 2) {
        params.start_date = parts[0];
        params.end_date = parts[1];
      }
    }
    const { data } = await axios.get("/finances/transactions/wallets", {
      params,
    });
    wallets.value = data.data;
  } catch (error) {
    console.error("Error al obtener wallets:", error);
  } finally {
    walletsLoading.value = false;
  }
};

// ─── Handlers ─────────────────────────────────────────────────────────────────
const handleClearFilters = () => {
  dateRange.value = "";
  dataDetailed.value = false;
  selectedCurrency.value = "";
  selectedOption.value = "";
  page.value = 1;
};

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
};

// ─── Watchers ─────────────────────────────────────────────────────────────────
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
  ([pg, items, date, currency, detailed, option]) => {
    if (!isInitialized.value) return;

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

// ─── Ajustes de Saldo ────────────────────────────────────────────────────────
const handleAdjustRequest = (wallet) => {
  adjustmentWallet.value = wallet;
  adjustmentValue.value = wallet.balance;
  isAdjustmentModalOpen.value = true;
};

const submitAdjustment = async () => {
  if (!adjustmentWallet.value) return;

  isAdjusting.value = true;
  try {
    await axios.post("/finances/transactions/adjustment", {
      currency: adjustmentWallet.value.currency,
      type: adjustmentWallet.value.method,
      new_balance: adjustmentValue.value,
    });

    toast.success("Saldo ajustado correctamente");
    isAdjustmentModalOpen.value = false;

    // Refrescar datos
    fetchTransactions({
      date: dateRange.value,
      currency: selectedCurrency.value,
      detailed: dataDetailed.value,
      option: selectedOption.value,
    });
    fetchTransactionsGroupped({
      date: dateRange.value,
      currency: selectedCurrency.value,
      detailed: dataDetailed.value,
    });
    fetchWallets(dateRange.value);
  } catch (error) {
    console.error("Error al ajustar saldo:", error);
    toast.error(error.response?.data?.message || "Error al ajustar el saldo.");
  } finally {
    isAdjusting.value = false;
  }
};

// ─── Ciclo de vida ────────────────────────────────────────────────────────────
onMounted(async () => {
  if (authStore.isVendedor) {
    toast.error("Acceso denegado: No tienes permisos para ver esta sección.");
    router.push("/invoice/invoices");
    return;
  }
  await Promise.all([
    fetchTransactions(),
    fetchTransactionsGroupped(),
    fetchWallets()
  ]);
  isInitialized.value = true;
});
</script>

<template>
  <div class="cashout-page pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
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
        @adjust="handleAdjustRequest"
        class="mb-0"
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
        class="ma-0"
      />
    </div>

    <!-- Modal de Ajuste de Saldo -->
    <VDialog v-model="isAdjustmentModalOpen" max-width="400" persistent>
      <VCard class="pa-4 rounded-xl shadow-lg border-0">
        <VCardTitle class="px-0 pt-0 d-flex align-center gap-2 mb-4">
          <VAvatar size="32" color="primary" variant="tonal" class="rounded-lg">
            <VIcon icon="tabler-adjustments-alt" size="18" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-subtitle-1 font-weight-black uppercase letter-spacing-1">Ajustar Saldo</span>
            <span class="text-super-xs text-disabled font-weight-medium">Modificar balance de cuenta</span>
          </div>
        </VCardTitle>

        <VCardText class="px-0 pb-6">
          <div class="mb-6 pa-4 bg-surface-variant-light rounded-xl border">
            <span class="text-super-xs text-disabled uppercase font-weight-black d-block mb-1">Cuenta actual</span>
            <div class="d-flex align-center gap-3">
              <VAvatar size="40" :color="adjustmentWallet?.currency === 'USD' ? 'warning' : (adjustmentWallet?.currency === 'BS' ? 'error' : 'primary')" variant="tonal" class="rounded-lg">
                <VIcon icon="tabler-building-bank" size="20" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-sm font-weight-black">{{ adjustmentWallet?.method }} ({{ adjustmentWallet?.currency }})</span>
                <span class="text-xs text-medium-emphasis">Saldo actual: {{ adjustmentWallet?.currency }} {{ adjustmentWallet?.balance }}</span>
              </div>
            </div>
          </div>

          <VTextField
            v-model="adjustmentValue"
            label="Nuevo Saldo Deseado"
            type="number"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="tabler-edit"
            :prefix="adjustmentWallet?.currency"
            class="rounded-lg"
            placeholder="0.00"
            hide-details="auto"
            autofocus
            @keyup.enter="submitAdjustment"
          />
        </VCardText>

        <VCardActions class="px-0 pb-0 gap-2">
          <VBtn color="secondary" variant="tonal" class="flex-grow-1 font-weight-black" @click="isAdjustmentModalOpen = false" :disabled="isAdjusting">
            CANCELAR
          </VBtn>
          <VBtn color="primary" variant="elevated" class="flex-grow-1 font-weight-black shadow-primary" :loading="isAdjusting" @click="submitAdjustment">
            AJUSTAR
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.cashout-page {
  background-color: rgb(var(--v-theme-background));
  min-block-size: 100vh;
}

.premium-header {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #1e272e 100%
  );
}

.header-main-card {
  border-radius: 8px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

:deep(.v-card) {
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}
</style>
