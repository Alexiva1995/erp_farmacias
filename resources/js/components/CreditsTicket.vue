<script setup>
import { computed } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { useAuthStore } from "@/stores/auth";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { formatDateTime } from "@/utils/formatDateTime";
import { translateMethod } from "@/utils/paymentMethods";

const props = defineProps({
  creditsData: {
    type: Object,
    default: () => ({}),
  },
  selectedCurrency: {
    type: String,
    default: "USD",
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
});

const authStore = useAuthStore();
const brandingStore = useBrandingStore();
const today = new Date();

const userUsername = computed(() => authStore.user?.username || "N/A");
const businessName = computed(
  () => brandingStore.settings?.business_name || brandingStore.settings?.company_name || ""
);
const logoSrc = computed(() => brandingStore.settings?.app_logo || BASE64_LOGO_DATA);

const previousBalance = computed(() => parseFloat(props.creditsData?.total_pending_amount || 0));
const showChangeAmount = computed(() => props.changeAmount > 0);
</script>

<template>
  <div class="col-12 col-md-8 mx-auto ticket-black-text">
    <VCard variant="outlined" class="pa-2 text-start border-0 text-black">
      <div class="text-center pa-2 mb-1">
        <img width="130" :src="logoSrc" alt="Logotipo de la marca" />
        <div v-if="businessName" class="font-weight-bold text-black mt-1">
          {{ businessName }}
        </div>
        <div class="text-caption font-weight-bold text-black">COMPROBANTE DE ABONO</div>
      </div>

      <div class="ticket-header d-flex justify-space-between align-start mt-1">
        <span class="textoPrint text-black">Fecha:</span>
        <span class="text-black font-weight-regular textoPrint">
          {{ formatDateTime(today, "date") }} {{ formatDateTime(today, "time") }}
        </span>
      </div>

      <div class="d-flex justify-space-between align-start textoPrint mb-1">
        <span class="textoPrint text-black">Cajero:</span>
        <span class="text-black">{{ userUsername }}</span>
      </div>

      <div class="d-flex justify-space-between align-start textoPrint mb-1">
        <span class="textoPrint text-black">Cliente:</span>
        <span class="textoPrint text-black">
          {{ creditsData?.client?.name }} {{ creditsData?.client?.last_name }}
        </span>
      </div>

      <div class="d-flex justify-space-between align-start textoPrint mb-2">
        <span class="textoPrint text-black">Documento:</span>
        <span class="textoPrint text-black">
          {{ creditsData?.client?.identification_type }}{{ creditsData?.client?.identification }}
        </span>
      </div>

      <hr />

      <div class="ticket-body mt-2">
        <div class="d-flex justify-space-between align-center mb-1">
          <span class="font-weight-bold text-black">SALDO ANTERIOR:</span>
          <span class="text-end font-weight-bold text-black">
            {{ formatCurrency(previousBalance, props.selectedCurrency) }}
          </span>
        </div>

        <div class="mt-2">
          <p class="font-weight-bold text-black mb-1">ABONOS RECIBIDOS:</p>
          <div
            v-for="(payment, pIndex) in payments"
            :key="`ticket-payment-${pIndex}`"
            class="d-flex justify-space-between align-center my-1 text-black"
          >
            <span>
              {{ translateMethod(payment.method) }} ({{ payment.currency }})
              <span v-if="payment.reference"> - Ref: {{ payment.reference }}</span>
            </span>
            <span class="font-weight-bold">
              {{ formatCurrency(payment.amount || 0, payment.currency) }}
            </span>
          </div>
        </div>

        <div
          v-if="showChangeAmount"
          class="d-flex justify-space-between align-center mt-2"
        >
          <span class="font-weight-bold text-black">VUELTO ENTREGADO:</span>
          <span class="text-end font-weight-black text-black">
            {{ formatCurrency(changeAmount, "COP") }}
          </span>
        </div>

        <hr class="my-2" />

        <div
          v-if="creditAmount >= 0"
          class="d-flex justify-space-between align-center"
        >
          <span class="font-weight-black text-black">SALDO RESTANTE:</span>
          <span class="text-end font-weight-black text-black">
            {{ formatCurrency(creditAmount, props.selectedCurrency) }}
          </span>
        </div>
      </div>

      <p class="font-weight-bold text-center text-black mt-3">
        ¡GRACIAS POR PREFERIRNOS!
      </p>
    </VCard>
  </div>
</template>

<style scoped>
.ticket-black-text,
.ticket-black-text * {
  color: #000000 !important;
}
</style>
