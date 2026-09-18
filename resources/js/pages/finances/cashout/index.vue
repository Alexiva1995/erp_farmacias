<script setup>
import CashoutFilters from "@/components/CashoutFilters.vue";
import TransactionsTable from "@/components/TransactionsTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";

const router = useRouter();
const authStore = useAuthStore();

const transactions = ref([]);
const groupedTransactions = ref({});
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
const isExporting = ref(false);

// ─── Estado Transferencia entre Cajas ──────────────────────────────────────────
const isTransferModalOpen = ref(false);
const isTransferring = ref(false);
const transferForm = reactive({
  source_currency: "COP",
  source_type: "CASH",
  source_amount: null,
  destination_currency: "COP",
  destination_type: "CAMBISTA",
  destination_amount: null,
  exchange_rate: 1.0,
  notes: "",
});

// Nuevos: tasas y estado de cierres de caja
const rates = ref({ bcv: { rate: 0 }, cop: { rate: 0 } });
const cashStatus = ref(null);

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
      } else {
        params.start_date = date;
        params.end_date = date;
      }
    }
    if (currency) params.currency = currency;
    if (detailed) params.detailed = detailed;

    const { data } = await axios.get("/finances/transactions/stats", {
      params,
    });
    groupedTransactions.value = data.data || {};
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
    toast.error("Error al cargar las cuentas/wallets.");
  } finally {
    walletsLoading.value = false;
  }
};

// ─── Tasas y Estado de Caja ───────────────────────────────────────────────────
const fetchCashStatus = async () => {
  try {
    const { data } = await axios.get("/finances/transactions/cash-status");
    cashStatus.value = data.data.closing_status;
    rates.value = data.data.rates;
  } catch (error) {
    console.error("Error al obtener estado de caja:", error);
  }
};

// ─── Exportar Excel ───────────────────────────────────────────────────────────
const exportExcel = async () => {
  isExporting.value = true;
  try {
    const params = {};
    if (dateRange.value) {
      const parts = dateRange.value.split(" to ");
      if (parts.length === 2) {
        params.start_date = parts[0];
        params.end_date = parts[1];
      } else {
        params.start_date = dateRange.value;
      }
    }
    if (selectedCurrency.value) params.currency = selectedCurrency.value;
    if (dataDetailed.value) {
      params.detailed = true;
      params.option = selectedOption.value;
    }

    const response = await axios.get("/finances/transactions/export/excel", {
      params,
      responseType: "blob",
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", `flujo-caja-${new Date().toISOString().slice(0, 10)}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    toast.success("Reporte descargado correctamente.");
  } catch (error) {
    console.error("Error al exportar:", error);
    toast.error("Error al exportar el reporte.");
  } finally {
    isExporting.value = false;
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
  ([pg, items, date, currency, detailed, option], [oldPg, oldItems, oldDate, oldCurrency, oldDetailed, oldOption]) => {
    if (!isInitialized.value) return;

    // Si el cambio fue por hacer clic en una tarjeta (currency/option) o paginar, ejecutar de inmediato sin debounce (0ms)
    const isInstant = currency !== oldCurrency || option !== oldOption || pg !== oldPg || items !== oldItems;
    const delay = isInstant ? 0 : 300;

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      fetchTransactions({ date, currency, detailed, option });
      if (pg === 1) {
        fetchTransactionsGroupped({ date, currency, detailed });
      }
      // Las tarjetas superiores de wallets y estado solo se recargan si cambia la fecha
      if (date !== oldDate) {
        fetchWallets(date);
      }
    }, delay);
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

// ─── Lógica y Opciones de Transferencia entre Cajas ──────────────────────────
const walletOptionsMap = {
  USD: [
    { title: "Efectivo (USD)", value: "CASH" },
    { title: "Binance (USD)", value: "BINANCE" },
    { title: "PayPal (USD)", value: "PAYPAL" },
    { title: "Crédito (USD)", value: "CREDIT" },
    { title: "Cambista (USD)", value: "CAMBISTA" },
  ],
  BS: [
    { title: "Banco / Transferencia (Bs.)", value: "TRANSFER" },
    { title: "Pago Móvil (Bs.)", value: "MOBILE" },
    { title: "Efectivo (Bs.)", value: "CASH" },
    { title: "Punto de Venta / Tarjeta (Bs.)", value: "CARD" },
  ],
  COP: [
    { title: "Efectivo (COP)", value: "CASH" },
    { title: "Transferencia (COP)", value: "TRANSFER" },
    { title: "Cambista (COP)", value: "CAMBISTA" },
  ],
};

const isSameCurrencyTransfer = computed(
  () => transferForm.source_currency === transferForm.destination_currency
);

const openTransferModal = () => {
  transferForm.source_currency = "COP";
  transferForm.source_type = "CASH";
  transferForm.source_amount = null;
  transferForm.destination_currency = "COP";
  transferForm.destination_type = "CAMBISTA";
  transferForm.destination_amount = null;
  transferForm.notes = "";
  transferForm.exchange_rate = 1.0;
  isTransferModalOpen.value = true;
};

// Recalcular montos al cambiar moneda o tasa
const onSourceCurrencyChange = () => {
  const opts = walletOptionsMap[transferForm.source_currency] || [];
  if (!opts.find((o) => o.value === transferForm.source_type)) {
    transferForm.source_type = opts[0]?.value || "CASH";
  }
  recalculateTransfer();
};

const onDestinationCurrencyChange = () => {
  const opts = walletOptionsMap[transferForm.destination_currency] || [];
  if (!opts.find((o) => o.value === transferForm.destination_type)) {
    transferForm.destination_type = opts[0]?.value || "TRANSFER";
  }
  recalculateTransfer();
};

const recalculateTransfer = () => {
  if (isSameCurrencyTransfer.value) {
    transferForm.exchange_rate = 1.0;
    transferForm.destination_amount = transferForm.source_amount;
    return;
  }

  // Si son monedas distintas, sugerir tasa si está vacía
  const bcv = parseFloat(rates.value?.bcv?.rate) || 1;
  const cop = parseFloat(rates.value?.cop?.rate) || 1;

  if (
    !transferForm.exchange_rate ||
    transferForm.exchange_rate === 1.0
  ) {
    if (transferForm.source_currency === "USD" && transferForm.destination_currency === "BS") {
      transferForm.exchange_rate = bcv;
    } else if (transferForm.source_currency === "BS" && transferForm.destination_currency === "USD") {
      transferForm.exchange_rate = bcv;
    } else if (transferForm.source_currency === "USD" && transferForm.destination_currency === "COP") {
      transferForm.exchange_rate = cop;
    } else if (transferForm.source_currency === "COP" && transferForm.destination_currency === "USD") {
      transferForm.exchange_rate = cop;
    } else if (transferForm.source_currency === "COP" && transferForm.destination_currency === "BS") {
      transferForm.exchange_rate = cop > 0 ? bcv / cop : 1;
    } else if (transferForm.source_currency === "BS" && transferForm.destination_currency === "COP") {
      transferForm.exchange_rate = bcv > 0 ? cop / bcv : 1;
    }
  }

  onSourceAmountChange();
};

const onSourceAmountChange = () => {
  const sAmt = parseFloat(transferForm.source_amount) || 0;
  if (sAmt <= 0) {
    transferForm.destination_amount = null;
    return;
  }

  if (isSameCurrencyTransfer.value) {
    transferForm.destination_amount = sAmt;
    return;
  }

  const rate = parseFloat(transferForm.exchange_rate) || 0;
  if (rate <= 0) return;

  if (transferForm.source_currency === "USD") {
    // USD a BS o COP: multiplicar por tasa
    const res = sAmt * rate;
    transferForm.destination_amount = transferForm.destination_currency === "COP" ? Math.round(res) : Math.round(res * 100) / 100;
  } else if (transferForm.destination_currency === "USD") {
    // BS o COP a USD: dividir por tasa
    transferForm.destination_amount = Math.round((sAmt / rate) * 100) / 100;
  } else {
    // Monedas cruzadas BS <-> COP
    const res = sAmt * rate;
    transferForm.destination_amount = transferForm.destination_currency === "COP" ? Math.round(res) : Math.round(res * 100) / 100;
  }
};

const onDestinationAmountChange = () => {
  const dAmt = parseFloat(transferForm.destination_amount) || 0;
  const sAmt = parseFloat(transferForm.source_amount) || 0;
  if (dAmt > 0 && sAmt > 0 && !isSameCurrencyTransfer.value) {
    if (transferForm.source_currency === "USD") {
      transferForm.exchange_rate = Math.round((dAmt / sAmt) * 10000) / 10000;
    } else if (transferForm.destination_currency === "USD") {
      transferForm.exchange_rate = Math.round((sAmt / dAmt) * 10000) / 10000;
    }
  }
};

const submitTransfer = async () => {
  if (!transferForm.source_amount || transferForm.source_amount <= 0) {
    toast.error("Indica el monto a transferir.");
    return;
  }

  if (!transferForm.destination_amount || transferForm.destination_amount <= 0) {
    toast.error("El monto de destino debe ser mayor a 0.");
    return;
  }

  if (
    isSameCurrencyTransfer.value &&
    transferForm.source_type === transferForm.destination_type
  ) {
    toast.error("La caja de origen y la caja de destino no pueden ser la misma.");
    return;
  }

  if (!isSameCurrencyTransfer.value && (!transferForm.exchange_rate || transferForm.exchange_rate <= 0)) {
    toast.error("Debes indicar la tasa de cambio para la conversión.");
    return;
  }

  isTransferring.value = true;
  try {
    await axios.post("/finances/transactions/transfer", {
      source_currency: transferForm.source_currency,
      source_type: transferForm.source_type,
      source_amount: transferForm.source_amount,
      destination_currency: transferForm.destination_currency,
      destination_type: transferForm.destination_type,
      destination_amount: transferForm.destination_amount,
      exchange_rate: isSameCurrencyTransfer.value ? 1.0 : transferForm.exchange_rate,
      notes: transferForm.notes,
    });

    toast.success("Transferencia entre cajas procesada exitosamente.");
    isTransferModalOpen.value = false;

    // Refrescar transacciones y cajas
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
    fetchCashStatus();
  } catch (error) {
    console.error("Error al transferir entre cajas:", error);
    toast.error(error.response?.data?.message || "Error al procesar la transferencia.");
  } finally {
    isTransferring.value = false;
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
    fetchWallets(),
    fetchCashStatus(),
  ]);
  isInitialized.value = true;
});

onUnmounted(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer);
  }
});
</script>

<template>
  <div class="cashout-page pb-12">
    <div class="d-flex flex-column gap-6 mt-2">
      <!-- Filtros y Wallets -->
      <CashoutFilters
        v-model:dateRange="dateRange"
        v-model:dataDetailed="dataDetailed"
        v-model:selectedCurrency="selectedCurrency"
        v-model:selectedOption="selectedOption"
        :stats="groupedTransactions"
        :wallets="wallets"
        :wallets-loading="walletsLoading"
        :rates="rates"
        :cash-status="cashStatus"
        :is-exporting="isExporting"
        @clear="handleClearFilters"
        @adjust="handleAdjustRequest"
        @transfer="openTransferModal"
        @export="exportExcel"
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
        @clear="handleClearFilters"
      />
    </div>

    <!-- Modal de Transferencia entre Cajas -->
    <VDialog v-model="isTransferModalOpen" max-width="580" persistent>
      <VCard class="rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
        <VCardTitle class="pa-0">
          <div class="header-gradient pa-4 d-flex align-center shadow-sm">
            <VAvatar
              size="40"
              color="white"
              variant="flat"
              class="me-3 shadow-sm rounded-lg elevation-1"
            >
              <VIcon icon="tabler-arrows-left-right" color="primary" size="22" />
            </VAvatar>
            <div class="d-flex flex-column leading-none">
              <h3 class="text-h6 font-weight-black text-white leading-tight mb-0">
                Transferir entre Cajas
              </h3>
              <div class="d-flex align-center gap-2 mt-1">
                <span
                  class="text-white opacity-75 uppercase font-weight-bold"
                  style="font-size: 0.65rem; letter-spacing: 0.05em;"
                >
                  Movimiento interno y conversión de divisas
                </span>
              </div>
            </div>
            <VSpacer />
            <IconBtn
              variant="tonal"
              color="white"
              size="small"
              class="rounded-lg"
              @click="isTransferModalOpen = false"
              :disabled="isTransferring"
            >
              <VIcon icon="tabler-x" size="20" />
              <VTooltip activator="parent" location="top">Cerrar</VTooltip>
            </IconBtn>
          </div>
        </VCardTitle>

        <VCardText class="pa-6 bg-light">
          <!-- Caja de Origen (Egreso) -->
          <VCard variant="flat" class="pa-4 rounded-xl border bg-surface-variant-light mb-4">
            <div class="d-flex align-center gap-2 mb-3">
              <VIcon icon="tabler-arrow-up-right" color="error" size="18" />
              <span class="text-xs font-weight-black uppercase text-error letter-spacing-1">Caja de Origen (Salida)</span>
            </div>
            <VRow dense>
              <VCol cols="12" sm="5">
                <VSelect
                  v-model="transferForm.source_currency"
                  :items="['USD', 'BS', 'COP']"
                  label="Moneda Origen"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  class="rounded-lg mb-2 mb-sm-0"
                  @update:model-value="onSourceCurrencyChange"
                />
              </VCol>
              <VCol cols="12" sm="7">
                <VSelect
                  v-model="transferForm.source_type"
                  :items="walletOptionsMap[transferForm.source_currency]"
                  item-title="title"
                  item-value="value"
                  label="Método / Cuenta"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  class="rounded-lg"
                />
              </VCol>
              <VCol cols="12" class="mt-2">
                <VTextField
                  v-model="transferForm.source_amount"
                  label="Monto a Transferir"
                  type="number"
                  variant="outlined"
                  density="compact"
                  placeholder="0.00"
                  :prefix="transferForm.source_currency"
                  hide-details="auto"
                  class="rounded-lg"
                  @input="onSourceAmountChange"
                />
              </VCol>
            </VRow>
          </VCard>

          <!-- Tasa de Cambio (Si es entre monedas distintas) -->
          <VCard
            v-if="!isSameCurrencyTransfer"
            variant="flat"
            class="pa-4 rounded-xl border border-primary bg-primary-lighten-5 mb-4"
          >
            <div class="d-flex align-center gap-2 mb-2">
              <VIcon icon="tabler-calculator" color="primary" size="18" />
              <span class="text-xs font-weight-black uppercase text-primary letter-spacing-1">Tasa de Conversión Obligatoria</span>
            </div>
            <p class="text-xs text-medium-emphasis mb-3">
              Conversión de <strong>{{ transferForm.source_currency }}</strong> a <strong>{{ transferForm.destination_currency }}</strong>
            </p>
            <VTextField
              v-model="transferForm.exchange_rate"
              label="Tasa Aplicada"
              type="number"
              variant="outlined"
              density="compact"
              placeholder="Ej: 36.50 o 4100"
              prepend-inner-icon="tabler-chart-dots"
              hide-details="auto"
              class="rounded-lg"
              @input="onSourceAmountChange"
            />
          </VCard>

          <!-- Caja de Destino (Ingreso) -->
          <VCard variant="flat" class="pa-4 rounded-xl border bg-surface-variant-light mb-4">
            <div class="d-flex align-center gap-2 mb-3">
              <VIcon icon="tabler-arrow-down-left" color="success" size="18" />
              <span class="text-xs font-weight-black uppercase text-success letter-spacing-1">Caja de Destino (Entrada)</span>
            </div>
            <VRow dense>
              <VCol cols="12" sm="5">
                <VSelect
                  v-model="transferForm.destination_currency"
                  :items="['USD', 'BS', 'COP']"
                  label="Moneda Destino"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  class="rounded-lg mb-2 mb-sm-0"
                  @update:model-value="onDestinationCurrencyChange"
                />
              </VCol>
              <VCol cols="12" sm="7">
                <VSelect
                  v-model="transferForm.destination_type"
                  :items="walletOptionsMap[transferForm.destination_currency]"
                  item-title="title"
                  item-value="value"
                  label="Método / Cuenta"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  class="rounded-lg"
                />
              </VCol>
              <VCol cols="12" class="mt-2">
                <VTextField
                  v-model="transferForm.destination_amount"
                  label="Monto a Recibir"
                  type="number"
                  variant="outlined"
                  density="compact"
                  placeholder="0.00"
                  :prefix="transferForm.destination_currency"
                  :readonly="isSameCurrencyTransfer"
                  hide-details="auto"
                  class="rounded-lg"
                  @input="onDestinationAmountChange"
                />
              </VCol>
            </VRow>
          </VCard>

          <!-- Observaciones / Motivo -->
          <VTextField
            v-model="transferForm.notes"
            label="Concepto / Observaciones (Opcional)"
            variant="outlined"
            density="compact"
            prepend-inner-icon="tabler-note"
            placeholder="Ej: Cambio de divisas con cambista, fondeo de caja chica..."
            hide-details="auto"
            class="rounded-lg"
          />
        </VCardText>

        <VCardActions class="px-0 pb-0 pt-4 gap-2">
          <VBtn color="secondary" variant="outlined" class="flex-grow-1 font-weight-black rounded-lg" @click="isTransferModalOpen = false" :disabled="isTransferring">
            CANCELAR
          </VBtn>
          <VBtn color="primary" variant="elevated" class="flex-grow-1 font-weight-black rounded-lg shadow-primary" :loading="isTransferring" @click="submitTransfer">
            CONFIRMAR TRANSFERENCIA
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

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
          <VBtn color="secondary" variant="outlined" class="flex-grow-1 font-weight-black" @click="isAdjustmentModalOpen = false" :disabled="isAdjusting">
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

.header-gradient {
  background: var(--brand-gradient) !important;
}

.bg-light {
  background-color: #f8faff !important;
}

.leading-none {
  line-height: 1 !important;
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
