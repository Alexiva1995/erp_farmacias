<script setup>
import CashWallets from '@/components/CashWallets.vue';

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
  <!-- Panel de wallets vivas -->
  <CashWallets
    :sections="wallets.sections"
    :total-usd="wallets.total_usd"
    :loading="walletsLoading"
    :date-filtered="!!dateRange"
    :selected-currency="selectedCurrency"
    :selected-option="selectedOption"
    @select="handleWalletSelect"
    class="mb-2"
  />

  <VCard class="mb-4">
    <VCardText>
      <VCardTitle>Flujo de caja</VCardTitle>

      <VRow class="align-center justify-between">
        <VCol>
          <AppDateTimePicker
            :model-value="props.dateRange"
            @update:model-value="emit('update:dateRange', $event)"
            label="Fechas"
            placeholder="Seleccionar rango"
            :config="{ mode: 'range' }"
          />
        </VCol>

        <VCol class="mt-5">
          <VSwitch
            :model-value="props.dataDetailed"
            @update:model-value="
              emit('update:dataDetailed', $event);
              if ($event && props.selectedCurrency === '') {
                emit('update:selectedCurrency', 'USD');
                emit('update:selectedOption', options.USD.at(0).value);
              }
            "
            label="Detallado"
            inset
          />
        </VCol>
        <VCol class="mt-5">
          <VSelect
            v-if="props.dataDetailed"
            label="Seleccione una pestaña"
            :model-value="props.selectedOption"
            :items="options[props.selectedCurrency ?? 'USD']"
            @update:model-value="emit('update:selectedOption', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>
    </VCardActions>
  </VCard>
</template>
