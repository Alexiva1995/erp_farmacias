<script setup>
import { defineProps, defineEmits } from "vue";

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

const emit = defineEmits(["update:selectedCurrencyTab", "selectPaymentMethod"]);

const onTabChange = (val) => {
  emit("update:selectedCurrencyTab", val);
};

const onSelectMethod = (methodValue, currencyValue) => {
  emit("selectPaymentMethod", methodValue, currencyValue);
};
</script>

<template>
  <VCard variant="flat" border class="rounded-xl overflow-hidden glass-card">
    <VCardTitle class="pa-3 border-b d-flex align-center bg-primary">
      <VIcon icon="tabler-wallet" class="me-2" color="white" size="20" />
      <span class="text-subtitle-2 font-weight-black uppercase text-white">Método de Pago</span>
    </VCardTitle>
    <VCardText class="pa-3">
      <VTabs 
        :model-value="selectedCurrencyTab" 
        @update:model-value="onTabChange"
        color="primary" 
        grow 
        density="compact"
        class="mb-3 compact-tabs"
        slider-color="primary"
      >
        <VTab v-for="currency in currencies" :key="currency.value" :value="currency.value" class="font-weight-black">
          <VIcon v-if="selectedCurrencyTab === currency.value" icon="tabler-coin" size="14" class="me-1" />
          {{ currency.value }}
        </VTab>
      </VTabs>

      <VTabsWindow :model-value="selectedCurrencyTab">
        <VTabsWindowItem v-for="currency in currencies" :key="currency.value" :value="currency.value">
          <div class="d-flex flex-wrap gap-1 mt-1 justify-sm-start justify-center">
            <VBtn
              v-for="method in getAvailableMethodsForCurrency(currency.value)"
              :key="method.value"
              :variant="isPaymentMethodActive(method.value, currency.value) ? 'flat' : 'outlined'"
              :color="isPaymentMethodActive(method.value, currency.value) ? 'primary' : 'secondary'"
              :disabled="remainingAmount <= 0.01 || isPaymentMethodAdded(method.value, currency.value)"
              size="x-small"
              class="rounded-lg font-weight-bold px-2"
              @click="onSelectMethod(method.value, currency.value)"
            >
              <VIcon :icon="getPaymentMethodIcon(method.value)" class="me-1" size="14" />
              {{ method.label }}
            </VBtn>
          </div>
        </VTabsWindowItem>
      </VTabsWindow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.compact-tabs :deep(.v-btn) {
  block-size: 38px !important;
  font-size: 0.75rem !important;
}

.gap-1 {
  gap: 6px !important;
}

.v-btn--size-x-small {
  block-size: 32px !important;
  min-inline-size: 80px !important;
}
</style>
