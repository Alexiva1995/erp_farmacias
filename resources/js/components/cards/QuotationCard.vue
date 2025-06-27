<script setup>
import { ref , computed } from "vue";


const props = defineProps({
  totalProductsAmount: {
    type: Number,
    default: 0,
  },
  totalIvaAmount: {
    type: Number,
    default: 0,
  },
  totalQuotationAmount: {
    type: Number,
    default: 0,
  },
  quotationItems: {
    type: Array,
    default: () => []
  }
});

const selectedCurrency = ref("USD");
const availableCurrency = ref(["USD", "BS", "COP"]);


const emit = defineEmits(['currency-changed']);

const exemptAmount = computed(() => {
  let totalExempt = 0;
  console.log(props.totalIvaAmount);
  props.quotationItems.forEach(item => {
    if (item.taxRate === 0) {
      const price = item.price || 0;
      const quantity = item.selectedQuantity || 0;
      totalExempt += price * quantity;
    }
  });
  return totalExempt;
});

const breakdownItems = computed(() => [
  { title: "Exento", amount: props.totalProductsAmount },
  { title: "IVA", amount: props.totalIvaAmount },
]);


const formatCurrency = (value, currency = selectedCurrency.value) => {
  if (typeof value !== 'number' || isNaN(value)) {
    value = 0;
  }
  let locale = 'en-US';
  let currencyCode = currency;

  if (currency === 'BS') {
    locale = 'es-VE';
    currencyCode = 'VEF'; // Usar VEF o VES según la ISO que manejes para Bolívar
  } else if (currency === 'COP') {
    locale = 'es-CO';
    currencyCode = 'COP';
  } else if (currency === 'USD') {
    locale = 'en-US';
    currencyCode = 'USD';
  }

  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: currencyCode,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
};


const selectCurrency = (currency) => {
  selectedCurrency.value = currency;
  emit('currency-changed', currency);
};

// watch(selectedCurrency, (newCurrency) => {
//   console.log('Moneda seleccionada:', newCurrency);
// });

</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Cotización</VCardTitle>

      <template #append>
        <VMenu>
          <template #activator="{ props: props }">
            <VBtn
              type="button"
              color="primary"
              variant="tonal"
              density="default"
              size="small"
              class="mx-auto"
              v-bind="props"
            >
              <span>{{ selectedCurrency }}</span>

              <template #append>
                <VIcon icon="tabler-chevron-down" size="16" />
              </template>
            </VBtn>
          </template>

          <VList>
            <VListItem
              v-for="currencyOption in availableCurrency"
              :key="currencyOption"
              :value="currencyOption"
              @click="selectCurrency(currencyOption)"
            >
              <VListItemTitle>{{ currencyOption }}</VListItemTitle>
            </VListItem>
          </VList>
        </VMenu>
      </template>
    </VCardItem>
    <VCardText>
      <VList class="card-list" density="compact" nav>
        <VListItem
          v-for="item in breakdownItems"
          :key="item.title"
          class="rounded-0"
        >
          <VListItemTitle class="font-weight-medium">{{ item.title}}</VListItemTitle>
          <template #append>
            <div class="d-flex align-center">
              <span class="me-3 text-medium-emphasis">{{formatCurrency(item.amount)}}</span>
            </div>
          </template>
        </VListItem>
      </VList>

      <VDivider />
      <div class="d-flex align-center justify-space-between gap-x-2 mt-3">
        <h4 class="text-h4 text-center">Total Cotización</h4>

        <div class="text-h4 text-success">
          {{ formatCurrency(props.totalQuotationAmount) }}
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
