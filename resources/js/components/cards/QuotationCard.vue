<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { computed, ref } from "vue";

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
    default: () => [],
  },
  selectedDisplayCurrency: {
    type: String,
    default: "USD",
  },
  companyDiscountTotal: {
    type: Number,
    default: 0,
  },
  doctorDiscountTotal: {
    type: Number,
    default: 0,
  },
  recipeDiscountTotal: {
    type: Number,
    default: 0,
  },
  otherDiscountsTotal: {
    type: Number,
    default: 0,
  },
  selectedDiscountType: {
    type: String,
    default: null,
  },
});

const availableCurrency = ref(["USD", "BS", "COP"]);

const emit = defineEmits(["currency-changed"]);

const exemptAmount = computed(() => {
  let totalExempt = 0;
  props.quotationItems.forEach((item) => {
    if (item.taxRate === 0) {
      const price = item.price || 0;
      const quantity = item.selectedQuantity || 0;
      totalExempt += price * quantity;
    }
  });
  return totalExempt;
});

const breakdownItems = computed(() => {
  let ivaAmount = props.totalIvaAmount;

  // Aplica el redondeo solo si la moneda es 'COP'
  if (props.selectedDisplayCurrency === "COP") {
    ivaAmount = roundUpToNearestHundred(props.totalIvaAmount);
  }

  const items = [
    { title: "Subtotal", amount: props.totalProductsAmount },
    { title: "IVA", amount: ivaAmount }, // Usa la cantidad de IVA condicional
  ];

  if (
    props.selectedDiscountType === "Empresa" &&
    props.companyDiscountTotal > 0
  ) {
    items.push({
      title: "Descuento Empresa",
      amount: -props.companyDiscountTotal,
      isDiscount: true,
    });
  } else if (
    props.selectedDiscountType === "Medico" &&
    props.doctorDiscountTotal > 0
  ) {
    items.push({
      title: "Descuento Médico",
      amount: -props.doctorDiscountTotal,
      isDiscount: true,
    });
  } else if (
    props.selectedDiscountType === "Recipe" &&
    props.recipeDiscountTotal > 0
  ) {
    items.push({
      title: "Descuento Recipe",
      amount: -props.recipeDiscountTotal,
      isDiscount: true,
    });
  }

  if (props.otherDiscountsTotal > 0) {
    items.push({
      title: "Otros Descuentos (Venc/Indiv)",
      amount: -props.otherDiscountsTotal,
      isDiscount: true,
    });
  }

  return items;
});

const selectCurrency = (currency) => {
  emit("currency-changed", currency);
};

const formattedTotalQuotation = computed(() => {
  let amountToFormat = props.totalQuotationAmount;
  if (props.selectedDisplayCurrency === "COP") {
    amountToFormat = Math.ceil(amountToFormat / 100) * 100;
  }
  return formatCurrency(amountToFormat, props.selectedDisplayCurrency);
});
</script>

<template>
  <VCard min-height="280" variant="flat" border class="d-flex flex-column rounded-lg overflow-hidden shadow-sm bg-surface">
    <VCardItem class="pa-4 border-b">
      <div class="d-flex align-center justify-space-between w-100">
        <div class="d-flex align-center gap-2">
          <VAvatar color="primary" variant="tonal" size="32" class="rounded-lg">
            <VIcon icon="tabler-calculator" size="18" />
          </VAvatar>
          <VCardTitle class="text-h6 font-weight-black text-primary">Cotización</VCardTitle>
        </div>
        
        <VMenu>
          <template #activator="{ props: menuProps }">
            <VBtn
              v-bind="menuProps"
              type="button"
              color="primary"
              variant="tonal"
              size="small"
              class="rounded-lg font-weight-bold"
            >
              <span>{{ props.selectedDisplayCurrency }}</span>
              <VIcon end icon="tabler-chevron-down" size="16" />
            </VBtn>
          </template>

          <VList density="compact" class="rounded-lg shadow-lg">
            <VListItem
              v-for="currencyOption in availableCurrency"
              :key="currencyOption"
              :value="currencyOption"
              :active="props.selectedDisplayCurrency === currencyOption"
              color="primary"
              @click="selectCurrency(currencyOption)"
            >
              <VListItemTitle class="font-weight-bold">{{ currencyOption }}</VListItemTitle>
            </VListItem>
          </VList>
        </VMenu>
      </div>
    </VCardItem>

    <VCardText class="flex-grow-1 d-flex flex-column pa-4">
      <VList class="card-list bg-transparent" density="compact" nav>
        <VListItem
          v-for="item in breakdownItems"
          :key="item.title"
          class="rounded-lg mb-2 border transition-all"
          :class="item.isDiscount ? 'bg-error-lighten-5 border-error border-opacity-25' : 'bg-surface border-opacity-10'"
        >
          <VListItemTitle class="text-subtitle-2 font-weight-bold" :class="item.isDiscount ? 'text-error' : 'text-high-emphasis'">
            {{ item.title }}
          </VListItemTitle>
          <template #append>
            <div class="d-flex align-center">
              <span
                class="font-weight-black text-subtitle-2"
                :class="item.isDiscount ? 'text-error' : 'text-primary'"
              >
                {{ formatCurrency(item.amount, props.selectedDisplayCurrency) }}
              </span>
            </div>
          </template>
        </VListItem>
      </VList>

      <VSpacer />
      
      <div class="total-section mt-4 pa-5 rounded-lg shadow-sm transition-all bg-primary">
        <div class="d-flex align-center justify-space-between text-white">
          <div class="d-flex flex-column text-white">
            <span class="text-super-xs font-weight-bold uppercase letter-spacing-1 opacity-75 text-white">Total a Pagar</span>
            <h4 class="text-h4 font-weight-black leading-none mt-1 text-white">Total</h4>
          </div>
          <div class="text-h3 font-weight-black text-white">
            {{ formattedTotalQuotation }}
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
}

.letter-spacing-1 {
  letter-spacing: 1.5px !important;
}

.leading-none {
  line-height: 1 !important;
}

.transition-all {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.total-section {
  position: relative;
  overflow: hidden;
}

.total-section::before {
  position: absolute;
  inset-block-start: -50%;
  inset-inline-start: -50%;
  content: "";
  inline-size: 200%;
  block-size: 200%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
  pointer-events: none;
}
</style>
