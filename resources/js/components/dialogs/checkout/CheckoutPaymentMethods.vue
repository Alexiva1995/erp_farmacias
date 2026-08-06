<script setup>
import { computed } from 'vue';
import { formatCurrency } from '@/utils/currencyFormatter';

const props = defineProps({
  selectedCurrencyTab: {
    type: String,
    required: true,
  },
  currencies: {
    type: Array,
    required: true,
  },
  paymentMethodsByCurrency: {
    type: Object,
    required: true,
  },
  remainingAmount: {
    type: Number,
    required: true,
  },
  getConvertedRemainingAmount: {
    type: Function,
    required: true,
  },
  isPaymentMethodActive: {
    type: Function,
    required: true,
  },
  isPaymentMethodAdded: {
    type: Function,
    required: true,
  },
  getPaymentMethodIcon: {
    type: Function,
    required: true,
  },
  getAvailableMethodsForCurrency: {
    type: Function,
    required: true,
  },
});

const emit = defineEmits(["update:selectedCurrencyTab", "selectPaymentMethod", "selectQuickCash"]);

const getQuickCashForCurrency = (currency) => {
  if (currency === 'COP') {
    return [
      { label: '$100.000', value: 100000 },
      { label: '$50.000', value: 50000 },
      { label: '$20.000', value: 20000 },
      { label: '$10.000', value: 10000 },
      { label: '$5.000', value: 5000 },
      { label: '$2.000', value: 2000 },
      { label: '$1.000', value: 1000 },
      { label: '$500', value: 500 },
      { label: '$200', value: 200 },
      { label: '$100', value: 100 },
    ];
  }
  if (currency === 'USD') {
    return [
      { label: '$100', value: 100 },
      { label: '$50', value: 50 },
      { label: '$20', value: 20 },
      { label: '$10', value: 10 },
      { label: '$5', value: 5 },
      { label: '$1', value: 1 },
    ];
  }
  if (currency === 'BS') {
    return [];
  }
  return [];
};

const onSelectMethod = (methodValue, currencyValue) => {
  emit("selectPaymentMethod", methodValue, currencyValue);
};

const onQuickCash = (amount, currencyValue) => {
  emit("selectQuickCash", amount, currencyValue);
};

const currencyFlag = (currency) => {
  if (currency === 'COP') return '🇨🇴';
  if (currency === 'USD') return '🇺🇸';
  if (currency === 'BS') return '🇻🇪';
  return '🪙';
};

const getCleanCurrencyKey = (currencyObj) => {
  if (!currencyObj) return '';
  let str = typeof currencyObj === 'object' ? (currencyObj.value || '') : String(currencyObj);
  str = str.toUpperCase().trim();
  
  const match = str.match(/(USD|COP|BS)/);
  if (match) {
    return match[1];
  }
  return str;
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <VCard
      v-for="currency in currencies"
      :key="getCleanCurrencyKey(currency)"
      variant="flat"
      border
      class="rounded-xl overflow-hidden glass-card"
    >
      <!-- Encabezado Compacto: Moneda (Limpia) + Métodos Abreviados al Lado + Monto Equivalente -->
      <VCardTitle class="pa-2 border-b d-flex align-center justify-space-between bg-grey-lighten-4 flex-wrap gap-2">
        <div class="d-flex align-center gap-2 flex-wrap">
          <span class="text-subtitle-2 font-weight-black me-1">
            {{ getCleanCurrencyKey(currency) }}
          </span>
          
          <!-- Métodos Abreviados en la misma línea -->
          <div class="d-flex align-center gap-1 flex-wrap">
            <VBtn
              v-for="method in getAvailableMethodsForCurrency(getCleanCurrencyKey(currency))"
              :key="method.value"
              :data-shortcut="method.value"
              :variant="isPaymentMethodActive(method.value, getCleanCurrencyKey(currency)) ? 'flat' : 'tonal'"
              :color="isPaymentMethodActive(method.value, getCleanCurrencyKey(currency)) ? 'primary' : 'secondary'"
              size="x-small"
              class="rounded-md font-weight-black px-2"
              height="26"
              @click="onSelectMethod(method.value, getCleanCurrencyKey(currency))"
            >
              <VIcon :icon="getPaymentMethodIcon(method.value)" class="me-1" size="14" />
              {{ method.label }}
            </VBtn>
          </div>
        </div>

        <VChip size="x-small" color="primary" variant="flat" class="font-weight-black ms-auto">
          {{ formatCurrency(getConvertedRemainingAmount(getCleanCurrencyKey(currency)), getCleanCurrencyKey(currency)) }}
        </VChip>
      </VCardTitle>

      <!-- Botones de Billetes Rápidos directos (sin etiqueta de texto redundante) -->
      <VCardText v-if="getQuickCashForCurrency(getCleanCurrencyKey(currency)).length > 0" class="pa-2 bg-surface">
        <div class="d-flex flex-wrap gap-1">
          <VBtn
            v-for="cash in getQuickCashForCurrency(getCleanCurrencyKey(currency))"
            :key="cash.value"
            variant="flat"
            color="success"
            size="x-small"
            class="rounded-md font-weight-black text-white px-2"
            height="24"
            @click="onQuickCash(cash.value, getCleanCurrencyKey(currency))"
          >
            {{ cash.label }}
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 8px !important;
}
.gap-3 {
  gap: 12px !important;
}
</style>
