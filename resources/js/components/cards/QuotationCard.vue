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
  <VCard min-height="280" class="d-flex flex-column">
    <VCardItem>
      <VCardTitle>Cotización</VCardTitle>
      <template #append>
        <VMenu>
          <template #activator="{ props: menuProps }">
            <VBtn
              type="button"
              color="primary"
              variant="tonal"
              density="default"
              size="small"
              class="mx-auto"
              v-bind="menuProps"
            >
              <span>{{ props.selectedDisplayCurrency }}</span>

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

    <VCardText class="flex-grow-1 d-flex flex-column">
      <VList class="card-list" density="compact" nav>
        <VListItem
          v-for="item in breakdownItems"
          :key="item.title"
          class="rounded-0"
        >
          <VListItemTitle class="font-weight-medium">{{
            item.title
          }}</VListItemTitle>
          <template #append>
            <div class="d-flex align-center">
              <span
                class="me-3"
                :class="item.isDiscount ? 'text-error' : 'text-medium-emphasis'"
                >{{
                  formatCurrency(item.amount, props.selectedDisplayCurrency)
                }}</span
              >
            </div>
          </template>
        </VListItem>
      </VList>

      <VDivider class="mt-auto" />
      <div class="d-flex align-center justify-space-between gap-x-2 mt-3">
        <h4 class="text-h4 text-center">Total Cotización</h4>
        <div class="text-h4 text-success">
          {{ formattedTotalQuotation }}
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
