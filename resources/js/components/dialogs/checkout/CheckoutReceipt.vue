<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { formatCurrency } from "@/utils/currencyFormatter";
import { formatDateTime } from "@/utils/formatDateTime";
import { defineEmits, defineProps } from "vue";

const props = defineProps({
  orderData: Object,
  orderProducts: Array,
  selectedCurrency: String,
  getPaymentMethodLabel: Function,
  payments: Array,
  getProductPrice: Function,
  activeDiscountDisplay: Object,
  expirationDiscountTotal: Number,
  appliesSpecialTax: Boolean,
  specialTaxAmount: Number,
  roundedTotalAmountToPay: Number,
  totalSPESavings: Number,
  hasCreditPayment: Boolean,
  showChangeAmount: Boolean,
  changeAmount: Number,
  changeAmountInCop: Number,
  exchangeRates: Object,
});

const emit = defineEmits(["print", "cancel", "print-fiscal"]);

const logoSrc = BASE64_LOGO_DATA;

/**
 * Convierte un monto a Bolívares (BS) usando las tasas de cambio proporcionadas.
 */
const convertToBS = (amount, fromCurrency) => {
  const numAmount = Number(amount) || 0;
  if (fromCurrency === "BS") return numAmount;

  // Intentar obtener tasa directa (ej: COP -> BS o USD -> BS)
  const rates = props.exchangeRates?.[fromCurrency];
  let rateToBs = rates?.["BS"] || 0;

  // Si no hay tasa directa, intentar vía USD (fromCurrency -> USD -> BS)
  if (rateToBs <= 0) {
    const rateToUsd = rates?.["USD"] || 0;
    const usdRates = props.exchangeRates?.["USD"];
    const usdToBs = usdRates?.["BS"] || 0;
    
    if (rateToUsd > 0 && usdToBs > 0) {
      rateToBs = rateToUsd * usdToBs;
    }
  }

  if (rateToBs > 0) {
    return numAmount * rateToBs;
  }

  // Si no se encuentra ninguna tasa, registrar advertencia y retornar original (evitar mostrar 0 si no hay red)
  console.warn(`No se encontró tasa de conversión para ${fromCurrency} a BS`);
  return numAmount;
};

/**
 * Formatea un monto siempre en BS, realizando la conversión si es necesario.
 */
const formatAsBS = (amount, fromCurrency = "BS") => {
  const amountInBs = convertToBS(amount, fromCurrency);
  return formatCurrency(amountInBs, "BS");
};
</script>

<template>
  <div class="checkout-receipt-container pa-4">
    <div class="d-flex justify-center">
      <div class="receipt-paper" id="printable-ticket">
        <div class="text-center mb-4">
          <img width="120" :src="logoSrc" alt="Logotipo" />
        </div>
        
        <div class="d-flex justify-space-between mb-1">
          <span class="font-weight-bold text-caption text-uppercase">Orden N° {{ orderData?.id }}</span>
          <span class="text-caption">
            {{ formatDateTime(orderData?.created_at, "date") }} {{ formatDateTime(orderData?.created_at, "time") }}
          </span>
        </div>

        <div class="d-flex justify-space-between text-caption mb-1">
          <span class="font-weight-bold">Cajero:</span>
          <span>{{ orderData?.seller?.username || "N/A" }}</span>
        </div>

        <div class="d-flex justify-space-between text-caption mb-1">
          <span class="font-weight-bold">Cliente:</span>
          <span class="text-right">
            {{ orderData?.client?.name }} {{ orderData?.client?.last_name }}
            <div v-if="orderData?.client?.is_spe" class="text-success font-weight-bold text-tiny">Beneficio SPE Activo</div>
          </span>
        </div>

        <VDivider class="my-2 border-dashed" />

        <div class="receipt-items mb-2">
          <div v-for="product in orderProducts" :key="product.id" class="receipt-item mb-1">
            <div class="d-flex flex-column text-caption border-b pb-1">
              <span class="font-weight-medium leading-tight">
                {{ product.selectedQuantity }} x {{ product.title }}
              </span>
              <span v-if="product.laboratory || product.laboratory_name" class="text-tiny text-disabled font-weight-bold uppercase">
                LAB: {{ product.laboratory || product.laboratory_name }}
              </span>
            </div>
          </div>
        </div>

        <div class="receipt-totals text-caption mt-3">
          <div v-if="activeDiscountDisplay" class="d-flex justify-space-between">
            <span>{{ activeDiscountDisplay.label }}:</span>
            <span class="text-error">- {{ formatAsBS(activeDiscountDisplay.amount, selectedCurrency) }}</span>
          </div>
          
          <div v-if="expirationDiscountTotal > 0" class="d-flex justify-space-between">
            <span>Dto. Vencimiento:</span>
            <span class="text-error">- {{ formatAsBS(expirationDiscountTotal, selectedCurrency) }}</span>
          </div>

          <div v-if="appliesSpecialTax" class="d-flex justify-space-between">
            <span>Recargo SPE (3%):</span>
            <span>{{ formatAsBS(specialTaxAmount, selectedCurrency) }}</span>
          </div>

          <div class="d-flex justify-space-between font-weight-black text-subtitle-1 mt-1 py-1 border-y">
            <span>TOTAL A PAGAR:</span>
            <span>{{ formatAsBS(roundedTotalAmountToPay, selectedCurrency) }}</span>
          </div>

          <div v-if="orderData?.client?.is_spe" class="d-flex justify-space-between text-success font-weight-bold mt-1">
            <span>Ahorro SPE (75% IVA):</span>
            <span>-{{ formatAsBS(totalSPESavings, selectedCurrency) }}</span>
          </div>

          <VDivider class="my-2 border-dashed" />
        </div>

        <div class="text-center mt-6 text-success font-weight-black text-caption border-t pt-2">
          ¡GRACIAS POR SU COMPRA!
        </div>
        <div class="text-center font-weight-black text-caption mt-1">
          DOCUMENTO NO FISCAL
        </div>
      </div>
    </div>

    <!-- Acciones -->
    <VCardActions class="px-0 pt-6 d-flex flex-column gap-2 no-print">
      <VBtn color="primary" variant="flat" block size="large" class="rounded-lg font-weight-black" @click="emit('print')">
        <VIcon icon="tabler-printer" class="me-2" />
        IMPRIMIR TICKET
      </VBtn>
      <VBtn color="success" variant="flat" block size="large" class="rounded-lg font-weight-black" @click="emit('print-fiscal')">
        <VIcon icon="tabler-file-invoice" class="me-2" />
        IMPRIMIR FISCAL
      </VBtn>
      <VBtn color="secondary" variant="tonal" block class="rounded-lg font-weight-bold" @click="emit('cancel')">
        FINALIZAR Y CERRAR
      </VBtn>
    </VCardActions>
  </div>
</template>

<style scoped>
.receipt-paper {
  padding: 30px;
  background: white;
  color: black;
  inline-size: 100%;
  max-inline-size: 380px;
  font-family: 'Courier New', Courier, monospace;
}

.text-tiny {
  font-size: 0.75rem;
}

.border-dashed {
  border-style: dashed !important;
}

@media print {
  .no-print {
    display: none !important;
  }
  .receipt-paper {
    padding: 0;
    box-shadow: none;
    border: none;
  }
}
</style>
