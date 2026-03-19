<script setup>
import CashWallets from '@/components/CashWallets.vue';
import { ref } from 'vue';

const props = defineProps({
  stats:            { type: Object,  default: () => ({}) },
  wallets:          { type: Object,  default: () => ({ sections: [], total_usd: 0 }) },
  walletsLoading:   { type: Boolean, default: false },
  dateRange:        { type: String,  default: '' },
  dataDetailed:     { type: Boolean, default: false },
  selectedCurrency: { type: String,  default: '' },
  selectedOption:   { type: String,  default: '' },
});

const emit = defineEmits([
  'update:dateRange',
  'update:dataDetailed',
  'update:selectedCurrency',
  'update:selectedTab',
  'update:selectedOption',
  'clear',
]);

const isFiltersVisible = ref(false);

const options = {
  BS:  [
    { title: 'Efectivo',      value: 'CASH_BS'     },
    { title: 'Tarjeta',       value: 'CARD_BS'     },
    { title: 'Pago móvil',    value: 'MOBILE_BS'   },
    { title: 'Transferencia', value: 'TRANSFER_BS'  },
  ],
  COP: [
    { title: 'Efectivo',      value: 'CASH_COP'    },
    { title: 'Transferencia', value: 'TRANSFER_COP' },
  ],
  USD: [
    { title: 'Efectivo',      value: 'CASH_USD'    },
    { title: 'Binance',       value: 'BINANCE_USD'  },
    { title: 'PayPal',        value: 'PAYPAL_USD'   },
    { title: 'Crédito',       value: 'CREDIT_USD'   },
  ],
};

function handleWalletSelect({ currency, option }) {
  emit('update:selectedCurrency', currency);
  emit('update:selectedOption', option);
  emit('update:dataDetailed', true);
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
      @select="handleWalletSelect"
      class="mb-6"
    />

    <div class="d-flex align-center gap-3 mb-6">
      <div class="flex-grow-1">
        <VBtn
          :color="isFiltersVisible ? 'primary' : 'secondary'"
          variant="tonal"
          class="rounded-lg px-4 font-weight-black h-38"
          @click="isFiltersVisible = !isFiltersVisible"
        >
          <VIcon start icon="tabler-adjustments-horizontal" size="18" />
          FILTROS DE FLUJO
          <VIcon end :icon="isFiltersVisible ? 'tabler-chevron-up' : 'tabler-chevron-down'" size="16" />
        </VBtn>
      </div>

      <VBtn
        variant="text"
        color="secondary"
        size="small"
        class="font-weight-black"
        @click="emit('clear')"
      >
        LIMPIAR CRITERIOS
      </VBtn>
    </div>

    <VExpandTransition>
      <VCard v-if="isFiltersVisible" class="rounded-xl border-0 shadow-sm mb-6 bg-surface-variant-light overflow-hidden">
        <VCardText class="pa-5">
          <VRow align="center">
            <VCol cols="12" md="4">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">Rango de Fechas</span>
              <AppDateTimePicker
                :model-value="props.dateRange"
                @update:model-value="emit('update:dateRange', $event)"
                placeholder="Seleccionar rango"
                class="premium-input-compact"
                hide-details
                :config="{ mode: 'range', altInput: true, altFormat: 'Y-m-d' }"
              />
            </VCol>

            <VCol cols="6" md="3">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">Vista Detallada</span>
              <div class="d-flex align-center h-38">
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
                  class="ms-2"
                />
              </div>
            </VCol>

            <VCol v-if="props.dataDetailed" cols="6" md="5">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">Sub-Método de Pago</span>
              <VSelect
                :model-value="props.selectedOption"
                :items="options[props.selectedCurrency ?? 'USD']"
                placeholder="Seleccione una opción"
                density="compact"
                hide-details
                variant="outlined"
                class="premium-select-compact"
                @update:model-value="emit('update:selectedOption', $event)"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VExpandTransition>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.h-38 {
  block-size: 38px !important;
}

:deep(.premium-input-compact) {
  .v-field__input {
    font-size: 0.8125rem !important;
    min-block-size: 38px !important;
    padding-block: 0 !important;
  }
}

:deep(.premium-select-compact) {
  .v-field__input {
    font-size: 0.8125rem !important;
    min-block-size: 38px !important;
    padding-block: 0 !important;
  }
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 0.03);
}
</style>
