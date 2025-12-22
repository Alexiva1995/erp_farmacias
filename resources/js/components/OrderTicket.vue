<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { useAuthStore } from "@/stores/auth";
import { formatCurrency } from "@/utils/currencyFormatter";
import { formatDateTime } from "@/utils/formatDateTime";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { computed } from "vue";

const props = defineProps({
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
  companyDiscountTotal: {
    type: Number,
    default: 0,
  },
  selectedDiscountType: {
    type: String,
    default: null,
  },
  doctorDiscountTotal: {
    type: Number,
    default: 0,
  },
  recipeDiscountTotal: {
    type: Number,
    default: 0,
  },
});

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const getItemPriceByCurrency = (item, currency) => {
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
      { label: "Saldo", value: "balance" },
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
  // Si no se encuentra en la moneda específica, busca en todas (como en BuysModal)
  for (const key in paymentMethodsByCurrency) {
    const methods = paymentMethodsByCurrency[key];
    const foundMethod = methods.find((m) => m.value === methodValue);
    if (foundMethod) {
      return foundMethod.label;
    }
  }
  return methodValue.replace(/_/g, " ").toUpperCase();
};
const showChangeAmount = computed(() => {
  return props.changeAmount > 0;
});

const activeDiscountDisplay = computed(() => {
  const type = props.selectedDiscountType?.toLowerCase();
  const currency = props.selectedCurrency;

  const config = {
    empresa: {
      label: "Descuento Empresa",
      amount: props.companyDiscountTotal,
      formatted: formatCurrency(props.companyDiscountTotal, currency),
    },
    company: {
      label: "Descuento Empresa",
      amount: props.companyDiscountTotal,
      formatted: formatCurrency(props.companyDiscountTotal, currency),
    },
    medico: {
      label: "Descuento Médico",
      amount: props.doctorDiscountTotal,
      formatted: formatCurrency(props.doctorDiscountTotal, currency),
    },
    doctor: {
      label: "Descuento Médico",
      amount: props.doctorDiscountTotal,
      formatted: formatCurrency(props.doctorDiscountTotal, currency),
    },
    recipe: {
      label: "Descuento Recipe",
      amount: props.recipeDiscountTotal,
      formatted: formatCurrency(props.recipeDiscountTotal, currency),
    },
  };

  const current = config[type];
  if (current && current.amount > 0) {
    return current;
  }

  return null;
});
</script>
<template>
  <div class="col-12 col-md-8 mx-auto">
    <VCard variant="outlined" class="pa-2 text-start">
      <div class="text-center pa-2 mb-2">
        <a href="#">
          <img width="130" :src="logoSrc" alt="Logotipo de la marca" />
        </a>
      </div>
      <div class="text-center">
        <span class="headerPrint">J-50540695-7</span>
      </div>
      <div class="text-center">
        <span class="headerPrint">FARMACIA BARRIO SUCRE 2024, C.A.</span>
      </div>
      <div class="text-center">
        <span class="headerPrint">CALLE PRINCIPAL LOCAL 05 (L5)</span>
      </div>
      <div class="text-center">
        <span class="headerPrint">SECTOR BARRIO SUCRE LA FRIA TACHIRA</span>
      </div>
      <div class="text-center">
        <span class="headerPrint">ZONA POSTAL 5020</span>
      </div>
      <div class="ticket-header d-flex justify-space-between align-start mt-2">
        <span class="font-weight-bold tituloAzulPrint"
          >Order N° {{ orderData.id }}</span
        >
        <div class="text-right d-flex flex-column align-end">
          <p class="text-black font-weight-regular mb-0 textoPrint">
            {{ formatDateTime(props.orderData.created_at, "date") }}
            {{ formatDateTime(props.orderData.created_at, "time") }}
          </p>
        </div>
      </div>
      <div class="d-flex justify-space-between align-start textoPrint mb-1">
        <span class="textoPrint">Cajero:</span>
        <span>{{ orderData.seller.username }}</span>
      </div>

      <div class="d-flex justify-space-between align-start textoPrint mb-1">
        <span class="textoPrint">Cliente:</span>
        <span class="textoPrint"
          >{{ orderData.client.name }} {{ orderData.client.last_name }}</span
        >
      </div>

      <div class="d-flex justify-space-between align-start textoPrint mb-1">
        <span class="textoPrint">Documento:</span>
        <span class="textoPrint"
          >{{ orderData.client.identification_type }}
          {{ orderData.client.identification }}</span
        >
      </div>

      <div class="d-flex flex-wrap justify-space-between textoPrint">
        <p class="font-weight-bold text-h6">Métodos de Pago</p>
        <div class="text-end">
          <p
            v-for="(payment, pIndex) in payments"
            :key="`ticket-payment-${pIndex}`"
            class="font-weight-bold my-1"
          >
            <span
              >{{ getPaymentMethodLabel(payment.method, payment.currency) }} ({{
                payment.currency
              }})</span
            >
          </p>
        </div>
      </div>

      <div class="ticket-body mt-2">
        <div v-for="item in orderProducts" :key="item.id" class="ticket-item">
          <span class="ticket-item-qty">{{ item.selectedQuantity }}x</span>
          <span class="ticket-item-name">{{ item.title }}</span>
          <span class="ticket-item-price">
            {{
              formatCurrency(
                getItemPriceByCurrency(item, selectedCurrency) *
                  item.selectedQuantity,
                selectedCurrency
              )
            }}
          </span>
        </div>
        <hr />

        <div
          v-if="activeDiscountDisplay"
          class="ticket-total d-flex justify-space-between align-center"
        >
          <span class="font-weight-bold tituloAzulPrint">
            {{ activeDiscountDisplay.label }}:
          </span>
          <span class="text-end font-weight-black tituloAzulPrint">
            - {{ activeDiscountDisplay.formatted }}
          </span>
        </div>
        <div class="ticket-total d-flex justify-space-between align-center">
          <span class="font-weight-bold tituloAzulPrint">TOTAL VENTA:</span>
          <span class="text-end font-weight-black tituloAzulPrint">
            {{ formatCurrency(totalAmount, selectedCurrency) }}
          </span>
        </div>

        <div class="ticket-total d-flex flex-wrap justify-space-between">
          <p class="font-weight-bold text-h6 mt-2 tituloAzulPrint">PAGO:</p>
          <div class="text-end">
            <p
              v-for="(payment, pIndex) in payments"
              :key="`ticket-payment-${pIndex}`"
              class="font-weight-bold my-1 tituloAzulPrint"
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
          <span class="font-weight-bold tituloAzulPrint">{{ "CRÉDITO" }}:</span>
          <span class="text-end font-weight-black tituloAzulPrint">
            {{ formatCurrency(creditAmount, selectedCurrency) }}
          </span>
        </div>

        <div
          v-if="showChangeAmount"
          class="ticket-total d-flex justify-space-between align-center"
        >
          <span class="font-weight-bold tituloAzulPrint">DEVOLUCION:</span>
          <span class="text-end font-weight-black tituloAzulPrint">
            {{ formatCurrency(changeAmount, "COP") }}
          </span>
        </div>
      </div>

      <p class="font-weight-bold text-center text-success">
        ¡GRACIAS POR SU COMPRA!
      </p>
    </VCard>
  </div>
</template>
