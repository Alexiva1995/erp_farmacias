<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { formatCurrency } from "@/utils/currencyFormatter";
import { computed, defineEmits, defineProps } from "vue";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  orderData: {
    type: Object,
    default: () => ({}),
  },
  totalAmount: {
    type: Number,
    default: 0,
  },
  selectedCurrency: {
    type: String,
    default: "COP",
  },
  orderProducts: {
    type: Array,
    default: () => [],
  },
  baseUrl: {
    type: String,
    default: "/",
  },
  payments: {
    type: Array,
    default: () => [],
  },
  changeAmount: {
    type: Number,
    default: 0,
  },
  creditAmount: {
    type: Number,
    default: 0,
  },
  credit: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:isDialogVisible"]);

const dialogVisible = computed({
  get() {
    return props.isDialogVisible;
  },
  set(value) {
    emit("update:isDialogVisible", value);
  },
});

const closeModal = () => {
  emit("update:isDialogVisible", false);
  emit("modal-closed");
};

const logoSrc = computed(() => {
  return BASE64_LOGO_DATA;
});

const getPaymentMethodLabel = (methodValue, currency) => {
  const paymentMethodsByCurrency = {
    COP: [
      { label: "Efectivo", value: "cash_cop" },
      { label: "Transferencia", value: "bank_transfer" },
    ],
    BS: [
      { label: "Efectivo", value: "cash_bs" },
      { label: "Pago Móvil", value: "mobile_payment" },
      { label: "Transferencia", value: "bank_transfer_bs" },
      { label: "Tarjeta", value: "card" },
    ],
    USD: [
      { label: "Efectivo", value: "cash_usd" },
      { label: "Binance", value: "binance" },
      { label: "PayPal", value: "paypal" },
      { label: "Crédito", value: "credit" },
    ],
  };

  if (!methodValue) return "N/A";
  const methodsForCurrency = paymentMethodsByCurrency[currency];
  if (methodsForCurrency) {
    const foundMethod = methodsForCurrency.find((m) => m.value === methodValue);
    if (foundMethod) {
      return foundMethod.label;
    }
  }
  for (const key in paymentMethodsByCurrency) {
    const methods = paymentMethodsByCurrency[key];
    const foundMethod = methods.find((m) => m.value === methodValue);
    if (foundMethod) {
      return foundMethod.label;
    }
  }
  return methodValue.replace(/_/g, " ").toUpperCase();
};
const getItemPriceByCurrency = (item, currency) => {
  if (item.fixed_price !== undefined && item.fixed_price !== null) {
    return item.fixed_price;
  }
  const taxRate = item.taxRate || 0;
  let basePrice = 0;
  if (currency === "BS") {
    basePrice = item.price_bs || 0;
  } else if (currency === "COP") {
    basePrice = item.price_cop || 0;
  } else {
    basePrice = item.price || 0;
  }
  let priceWithIva = basePrice * (1 + taxRate);
  if (currency === "COP") {
    priceWithIva = roundUpToNearestHundred(priceWithIva);
  }
  return priceWithIva;
};

const debtPayments = computed(() => {
  if (!props.payments) return [];
  return props.payments.filter((payment) => payment.isDebt === true);
});
const normalPayments = computed(() => {
  if (!props.payments) return [];
  return props.payments.filter(
    (payment) => payment.isDebt === false || payment.isDebt == null
  );
});

const hasCompanyDiscount = computed(() => {
  return (
    props.orderData.details?.some(
      (detail) => detail.discount_type === "company"
    ) || false
  );
});

const hasDoctorDiscount = computed(() => {
  return (
    props.orderData.details?.some(
      (detail) => detail.discount_type === "doctor"
    ) || false
  );
});

const hasRecipeDiscount = computed(() => {
  return (
    props.orderData.details?.some(
      (detail) => detail.discount_type === "recipe"
    ) || false
  );
});

const orderDiscounts = computed(() => {
  const totals = { company: 0, doctor: 0, recipe: 0 };
  if (!props.orderData?.details) return totals;
  props.orderData.details.forEach((detail) => {
    const type = detail.discount_type?.toLowerCase();
    const price = parseFloat(detail.price) || 0;
    const quantity = parseInt(detail.quantity) || 0;
    const percentage = parseFloat(detail.discount_percentage) || 0;
    const discountAmount = price * quantity * (percentage / 100);
    if (type === "Empresa" || type === "company") {
      totals.company += discountAmount;
    } else if (type === "Medico" || type === "doctor") {
      totals.doctor += discountAmount;
    } else if (type === "Recipe" || type === "recipe") {
      totals.recipe += discountAmount;
    }
  });
  return totals;
});

const activeDiscount = computed(() => {
  const discounts = orderDiscounts.value;
  if (discounts.company > 0)
    return { label: "Descuento Empresa", amount: discounts.company };
  if (discounts.doctor > 0)
    return { label: "Descuento Médico", amount: discounts.doctor };
  if (discounts.recipe > 0)
    return { label: "Descuento Recipe", amount: discounts.recipe };
  return null;
});


const appliesSpecialTax = computed(() => {
    const hasStoredAmount = parseFloat(props.orderData?.spe_surcharge_amount) > 0;
    const hasStoredRate = parseFloat(props.orderData?.spe_surcharge_rate) > 0;
    return hasStoredAmount || hasStoredRate;
});
</script>

<template>
  <VDialog v-model="dialogVisible" max-width="500px">
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline"></span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText>
        <div class="text-center">
          <img width="130" :src="logoSrc" alt="Logotipo de la marca" />
        </div>
        <div class="text-center">
          <span class="font-weight-regular">J-50540695-7</span>
        </div>
        <div class="text-center">
          <span class="font-weight-regular"
            >FARMACIA BARRIO SUCRE 2024, C.A.</span
          >
        </div>
        <div class="text-center">
          <span class="font-weight-regular">CALLE PRINCIPAL LOCAL 05 (L5)</span>
        </div>
        <div class="text-center">
          <span class="font-weight-regular"
            >SECTOR BARRIO SUCRE LA FRIA TACHIRA</span
          >
        </div>
        <div class="text-center">
          <span class="font-weight-regular">ZONA POSTAL 5020</span>
        </div>
        <div
          class="ticket-header d-flex justify-space-between align-start mt-2"
        >
          <span class="font-weight-bold text-h6"
            >Order N° {{ orderData.id }}</span
          >
          <div class="text-right d-flex flex-column align-end">
            <p class="text-black font-weight-regular text-h6 mb-0">
              Fecha:
              {{
                orderData.created_at
                  ? formatDateTime(props.orderData.created_at, "date")
                  : "N/A"
              }}
              {{
                orderData.created_at
                  ? formatDateTime(props.orderData.created_at, "time")
                  : ""
              }}
            </p>
          </div>
        </div>
        <div class="d-flex justify-space-between align-start mb-1">
          <span class="font-weight-bold text-h6">Cajero:</span>
          <span class="font-weight-bold text-h6">{{
            orderData.seller?.username || "N/A"
          }}</span>
        </div>

        <div class="d-flex justify-space-between align-start mb-1">
          <span class="font-weight-bold text-h6">Cliente:</span>
          <span class="font-weight-bold text-h6"
            >{{ orderData.client?.name || "" }}
            {{ orderData.client?.last_name || "" }}</span
          >
        </div>

        <div class="d-flex justify-space-between align-start mb-1">
          <span class="font-weight-bold text-h6">Documento:</span>
          <span class="font-weight-bold text-h6"
            >{{ orderData.client?.identification_type || "" }}
            {{ orderData.client?.identification || "" }}</span
          >
        </div>

        <div class="d-flex flex-wrap justify-space-between textoPrint">
          <p class="font-weight-bold text-h6">Métodos de Pago</p>
          <div class="text-end">
            <template v-if="normalPayments.length">
              <p
                v-for="(payment, pIndex) in normalPayments"
                :key="`ticket-payment-${pIndex}`"
                class="font-weight-bold text-h6 my-1"
              >
                <span
                  >{{
                    getPaymentMethodLabel(payment.method, payment.currency)
                  }}
                  ({{ payment.currency }})</span
                >
              </p>
            </template>
            <template v-else>
              <p class="font-weight-bold text-h6 my-1">N/A</p>
            </template>
          </div>
        </div>

        <div
          class="scrollable-list-container"
          :class="{ 'show-scroll': orderProducts.length > 2 }"
        >
          <VList class="card-list" density="compact" nav>
            <VListItem
              v-for="product in orderProducts"
              :key="product.id"
              class="rounded-0"
            >
              <template #prepend>
                <span>{{ product.selectedQuantity }} x</span>
              </template>

              <VListItemTitle class="font-weight-medium me-4 mx-2 text-wrap">{{
                `${product.title} ${
                  product.laboratory ? "(" + product.laboratory + ")" : ""
                }`
              }}</VListItemTitle>
              <!-- Subtitle removed/merged as history might not have explicit lab separate -->

              <template #append>
                <div class="d-flex align-center">
                   <span
                        v-if="activeDiscount"
                        class="text-caption text-decoration-line-through text-error"
                        style="margin-top: -4px"
                      >
                        {{formatCurrency(product.price_before_discount,
                        selectedCurrency
                      )
                    
                        }}</span>
                  <span class="text-body-1 me-2">
                    {{
                      formatCurrency(
                        getItemPriceByCurrency(product, selectedCurrency),
                        selectedCurrency
                      )
                    }}</span
                  >
                </div>
              </template>
            </VListItem>
          </VList>
        </div>
        <hr />
        <div
          v-if="activeDiscount"
          class="ticket-total d-flex justify-space-between align-center"
        >
          <span class="font-weight-bold text-h6"
            >{{ activeDiscount.label }}:</span
          >
          <span class="text-end font-weight-bold text-h6">
            - {{ formatCurrency(activeDiscount.amount, selectedCurrency) }}
          </span>
        </div>


        <div
          v-if="appliesSpecialTax"
          class="ticket-total d-flex justify-space-between align-center"
        >
          <span class="font-weight-bold text-h6"
            >Recargo Sujeto Pasivo Especial (3%):</span
          >
          <span class="text-end font-weight-bold text-h6">
             {{props.orderData?.spe_surcharge_amount}} {{selectedCurrency}}
          </span>
        </div>

        <div class="ticket-total d-flex justify-space-between align-center">
          <span class="font-weight-bold text-h6">TOTAL VENTA:</span>
          <span class="text-end font-weight-bold text-h6">
            {{ formatCurrency(totalAmount, selectedCurrency) }}
          </span>
        </div>
        <div
          class="ticket-total d-flex flex-wrap justify-space-between"
          v-if="normalPayments.length"
        >
          <p class="font-weight-bold text-h6 mt-2">PAGO:</p>
          <div class="text-end">
            <p
              v-for="(payment, pIndex) in normalPayments"
              :key="`ticket-payment-${pIndex}`"
              class="font-weight-bold text-h6 my-1"
            >
              <span>
                {{ formatCurrency(payment.amount || 0, payment.currency) }}
              </span>
            </p>
          </div>
        </div>
        <div
          v-if="debtPayments.length > 0"
          class="ticket-total d-flex flex-wrap justify-space-between"
        >
          <p class="font-weight-bold text-h6 mt-2">SALDO:</p>
          <div class="text-end">
            <p
              v-for="(payment, pIndex) in debtPayments"
              :key="`ticket-payment-${pIndex}`"
              class="font-weight-bold text-h6 my-1"
            >
              <span>
                {{ formatCurrency(payment.amount || 0, payment.currency) }}
              </span>
            </p>
          </div>
        </div>
        <div
          v-if="credit"
          class="ticket-total d-flex justify-space-between align-center"
        >
          <span class="font-weight-bold text-h6">{{ "CRÉDITO" }}:</span>
          <span class="text-end font-weight-bold text-h6">
            {{ formatCurrency(creditAmount, selectedCurrency) }}
          </span>
        </div>

        <div
          v-if="changeAmount"
          class="ticket-total d-flex justify-space-between align-center"
        >
          <span class="font-weight-bold text-h6">DEVOLUCION:</span>
          <span class="text-end font-weight-bold text-h6">
            {{ formatCurrency(changeAmount, "COP") }}
          </span>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.scrollable-list-container {
  max-height: 95px;
  overflow-y: hidden;
  transition: overflow-y 0.3s ease-in-out;
}
.scrollable-list-container.show-scroll {
  overflow-y: auto;
}
</style>
