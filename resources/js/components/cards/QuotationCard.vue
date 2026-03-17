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
  <VCard min-height="280" class="d-flex flex-column glass-card elevation-0">
    <VCardItem class="header-glass pa-4">
      <div class="d-flex align-center justify-space-between w-100">
        <div class="d-flex align-center gap-2">
          <VAvatar color="primary" variant="tonal" size="32">
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

    <VCardText class="flex-grow-1 d-flex flex-column pa-4 bg-glass-surface">
      <VList class="card-list bg-transparent" density="compact" nav>
        <VListItem
          v-for="item in breakdownItems"
          :key="item.title"
          class="rounded-lg mb-1 transition-all"
          :class="item.isDiscount ? 'bg-error-lighten-5' : 'bg-white-opacity-50'"
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
      
      <div class="total-section mt-4 pa-4 rounded-xl shadow-primary-glass transition-all">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex flex-column">
            <span class="text-super-xs font-weight-black text-primary-lighten-2 uppercase letter-spacing-1">Total a Pagar</span>
            <h4 class="text-h4 font-weight-950 text-white leading-none mt-1">Total</h4>
          </div>
          <div class="text-h3 font-weight-950 text-white text-shadow-sm">
            {{ formattedTotalQuotation }}
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.glass-card {
  backdrop-filter: blur(12px) !important;
  border: 1px solid rgba(var(--v-theme-primary), 10%) !important;
  background: rgba(255, 255, 255, 70%) !important;
  border-radius: 24px !important;
  overflow: hidden;
}

.header-glass {
  border-block-end: 1px solid rgba(var(--v-theme-primary), 5%);
}

.bg-glass-surface {
  background: rgba(var(--v-theme-primary), 2%);
}

.bg-white-opacity-50 {
  background: rgba(255, 255, 255, 50%) !important;
}

.total-section {
  position: relative;
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
  overflow: hidden;
}

.total-section::before {
  position: absolute;
  inset-block-start: -50%;
  inset-inline-start: -50%;
  content: "";
  inline-size: 200%;
  block-size: 200%;
  background: radial-gradient(circle, rgba(255, 255, 255, 10%) 0%, transparent 70%);
  pointer-events: none;
}

.shadow-primary-glass {
  box-shadow: 0 12px 24px -8px rgba(var(--v-theme-primary), 40%) !important;
}

.text-shadow-sm {
  text-shadow: 0 2px 4px rgba(0, 0, 0, 10%);
}

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

.total-section:hover {
  box-shadow: 0 16px 32px -12px rgba(var(--v-theme-primary), 50%) !important;
  transform: translateY(-2px);
}
</style>
