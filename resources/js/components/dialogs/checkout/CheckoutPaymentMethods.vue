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
  <div class="d-flex flex-column currency-cards-container">
    <VCard
      v-for="currency in currencies"
      :key="getCleanCurrencyKey(currency)"
      variant="flat"
      border
      class="rounded-xl overflow-hidden glass-card currency-card mb-4"
    >
      <!-- Encabezado con buen aire interno (padding 16px) -->
      <VCardTitle class="pa-3 border-b d-flex align-center justify-space-between bg-grey-lighten-4 flex-wrap gap-2">
        <div class="d-flex align-center gap-2 flex-wrap">
          <span class="text-subtitle-2 font-weight-black me-1 text-high-emphasis">
            {{ getCleanCurrencyKey(currency) }}
          </span>
          
          <!-- Métodos de Pago: Pestañas blancas con borde outline, activas resaltadas -->
          <div class="d-flex align-center gap-1.5 flex-wrap">
            <VBtn
              v-for="method in getAvailableMethodsForCurrency(getCleanCurrencyKey(currency))"
              :key="method.value"
              :data-shortcut="method.value"
              :variant="isPaymentMethodActive(method.value, getCleanCurrencyKey(currency)) ? 'flat' : 'outlined'"
              :color="isPaymentMethodActive(method.value, getCleanCurrencyKey(currency)) ? 'primary' : 'secondary'"
              size="x-small"
              class="rounded-lg font-weight-bold px-2.5 method-btn"
              :class="{ 'bg-surface': !isPaymentMethodActive(method.value, getCleanCurrencyKey(currency)) }"
              height="28"
              @click="onSelectMethod(method.value, getCleanCurrencyKey(currency))"
            >
              <VIcon :icon="getPaymentMethodIcon(method.value)" class="me-1" size="14" />
              {{ method.label }}
            </VBtn>
          </div>
        </div>

        <!-- Badges de totales por moneda en azul marino / gris azulado elegante -->
        <VChip 
          size="x-small" 
          variant="flat" 
          class="font-weight-bold ms-auto currency-total-chip"
        >
          {{ formatCurrency(getConvertedRemainingAmount(getCleanCurrencyKey(currency)), getCleanCurrencyKey(currency)) }}
        </VChip>
      </VCardTitle>

      <!-- Botones de Billetes Rápidos en Grilla con Gap uniforme (10-12px) y Paleta Azul Acero -->
      <VCardText v-if="getQuickCashForCurrency(getCleanCurrencyKey(currency)).length > 0" class="pa-3 bg-surface">
        <div class="d-flex flex-wrap quick-cash-grid">
          <button
            v-for="cash in getQuickCashForCurrency(getCleanCurrencyKey(currency))"
            :key="cash.value"
            type="button"
            class="cash-bill-btn"
            @click="onQuickCash(cash.value, getCleanCurrencyKey(currency))"
          >
            {{ cash.label }}
          </button>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.currency-cards-container {
  gap: 8px;
}

.currency-card {
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.currency-card:hover {
  border-color: rgba(var(--v-theme-primary), 0.25) !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}

.method-btn {
  letter-spacing: 0.2px;
  border-color: rgba(var(--v-theme-on-surface), 0.18) !important;
}

.currency-total-chip {
  background-color: #1e293b !important; /* Azul marino / slate oscuro elegante */
  color: #ffffff !important;
}

.quick-cash-grid {
  gap: 10px;
}

/* Billetes con Paleta Azul Acero Suave (#EBF3FE / #1E40AF) y Microinteracción Activa */
.cash-bill-btn {
  background-color: #ebf3fe;
  color: #1e40af;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  padding: 5px 10px;
  font-size: 0.75rem;
  font-weight: 800;
  line-height: 1.2;
  cursor: pointer;
  user-select: none;
  transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 2px rgba(30, 64, 175, 0.05);
}

.cash-bill-btn:hover {
  background-color: #dbeafe;
  border-color: #93c5fd;
  color: #1d4ed8;
  transform: translateY(-1px);
}

.cash-bill-btn:active {
  background-color: #1e40af;
  border-color: #1e40af;
  color: #ffffff;
  transform: translateY(1px);
}
</style>
