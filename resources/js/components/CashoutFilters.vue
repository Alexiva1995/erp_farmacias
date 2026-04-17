<script setup>
// Filtros de Flujo de Caja
import AppFilterBase from "@/components/AppFilterBase.vue";
import CashWallets from "@/components/CashWallets.vue";

const props = defineProps({
  stats: { type: Object, default: () => ({}) },
  wallets: { type: Object, default: () => ({ sections: [], total_usd: 0 }) },
  walletsLoading: { type: Boolean, default: false },
  dateRange: { type: String, default: "" },
  dataDetailed: { type: Boolean, default: false },
  selectedCurrency: { type: String, default: "" },
  selectedOption: { type: String, default: "" },
});

const emit = defineEmits([
  "update:dateRange",
  "update:dataDetailed",
  "update:selectedCurrency",
  "update:selectedTab",
  "update:selectedOption",
  "adjust",
  "clear",
]);

const options = {
  BS: [
    { title: "Efectivo", value: "CASH_BS" },
    { title: "Tarjeta", value: "CARD_BS" },
    { title: "Pago móvil", value: "MOBILE_BS" },
    { title: "Transferencia", value: "TRANSFER_BS" },
  ],
  COP: [
    { title: "Efectivo", value: "CASH_COP" },
    { title: "Transferencia", value: "TRANSFER_COP" },
  ],
  USD: [
    { title: "Efectivo", value: "CASH_USD" },
    { title: "Binance", value: "BINANCE_USD" },
    { title: "PayPal", value: "PAYPAL_USD" },
    { title: "Crédito", value: "CREDIT_USD" },
  ],
};

function handleWalletSelect({ currency, option }) {
  emit("update:selectedCurrency", currency);
  emit("update:selectedOption", option);
  emit("update:dataDetailed", true);
}
</script>

<template>
  <div class="cashout-filters-wrapper">
    <!-- Panel de wallets vivas -->
    <CashWallets
      :sections="wallets.sections"
      :total-usd="wallets.total_usd"
      :loading="walletsLoading"
      :date-filtered="!!dateRange"
      :selected-currency="selectedCurrency"
      :selected-option="selectedOption"
      :selected-option="selectedOption"
      @select="handleWalletSelect"
      @adjust="emit('adjust', $event)"
      class="mb-7"
    />

    <AppFilterBase
      :show-search="false"
      :has-advanced-filters="!!dateRange || dataDetailed"
      search-md-cols="5"
      search-lg-cols="5"
      class="mb-0 py-1"
      @update:search="emit('update:searchQuery', $event)"
      @clear="emit('clear')"
    >
      <template #search>
        <AppDateTimePicker
          :model-value="props.dateRange"
          @update:model-value="emit('update:dateRange', $event)"
          placeholder="Rango de fechas"
          density="compact"
          class="w-100"
          hide-details
          :config="{ mode: 'range', altInput: true, altFormat: 'Y-m-d' }"
        >
          <template #prepend-inner>
            <VIcon
              icon="tabler-calendar"
              size="18"
              color="disabled"
              class="me-2"
            />
          </template>
        </AppDateTimePicker>
      </template>

      <template #advanced-filters>
        <VCol cols="12" sm="6" md="4">
          <div class="d-flex align-center gap-3">
            <VSwitch
              :model-value="props.dataDetailed"
              @update:model-value="
                emit('update:dataDetailed', $event);
                if ($event && props.selectedCurrency === '') {
                  emit('update:selectedCurrency', 'USD');
                  emit('update:selectedOption', options.USD.at(0).value);
                }
              "
              color="primary"
              density="compact"
              hide-details
              inset
            />
            <div class="d-flex flex-column">
              <span
                class="text-sm font-weight-black uppercase leading-none mb-1"
                >Vista Detallada</span
              >
              <span class="text-super-xs text-disabled font-weight-medium"
                >Desglosar por sub-métodos</span
              >
            </div>
          </div>
        </VCol>

        <VCol v-if="props.dataDetailed" cols="12" sm="6" md="8">
          <VSelect
            :model-value="props.selectedOption"
            :items="options[props.selectedCurrency ?? 'USD']"
            placeholder="Sub-Método de Pago"
            density="compact"
            hide-details
            variant="outlined"
            prepend-inner-icon="tabler-wallet"
            @update:model-value="emit('update:selectedOption', $event)"
          />
        </VCol>
      </template>
    </AppFilterBase>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.leading-none {
  line-height: 1;
}
</style>
