<script setup>
import { computed } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { useAuthStore } from "@/stores/auth";
import { formatDateTime } from "@/utils/formatDateTime";

const props = defineProps({
  creditsData: {
    type: Object,
    default: () => ({}),
  },
  selectedCurrency: {
    type: String,
    default: "USD",
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
});

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const userUsername = computed(() => {
  return currentUser.value?.username || "N/A";
});

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

const showChangeAmount = computed(() => {
  return props.changeAmount > 0;
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
        <span class="font-weight-bold tituloAzulPrint"></span>
        <div class="text-right d-flex flex-column align-end">
          <p class="text-black font-weight-regular mb-0 textoPrint">
            Fecha: {{ formatDateTime(today, "date") }}
            {{ formatDateTime(today, "time") }}
          </p>
        </div>
      </div>
      <div class="d-flex justify-space-between align-start textoPrint mb-1">
        <span class="textoPrint">Cajero:</span>
        <span>{{ userUsername }}</span>
      </div>

      <div class="d-flex justify-space-between align-start textoPrint mb-1">
        <span class="textoPrint">Cliente:</span>
        <span class="textoPrint"
          >{{ creditsData?.client?.name }}
          {{ creditsData?.client?.last_name }}</span
        >
      </div>

      <div class="d-flex justify-space-between align-start textoPrint mb-1">
        <span class="textoPrint">Documento:</span>
        <span class="textoPrint"
          >{{ creditsData?.client?.identification_type }}
          {{ creditsData?.client?.identification }}</span
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
        <div class="ticket-item">
          <span class="ticket-item-qty"></span>
          <span class="ticket-item-name">Créditos</span>
          <span class="ticket-item-total">{{formatCurrency(parseFloat(creditsData.total_pending_amount), props.selectedCurrency)}}</span>
        </div>
        <hr />

        <div class="ticket-total d-flex justify-space-between align-center">
          <span class="font-weight-bold tituloAzulPrint">TOTAL:</span>
          <span class="text-end font-weight-black tituloAzulPrint">
          {{ creditsData.total_pending_amount }} {{ props.selectedCurrency }}</span>
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
        ¡GRACIAS POR PREFERIRNOS!
      </p>
    </VCard>
  </div>
</template>
