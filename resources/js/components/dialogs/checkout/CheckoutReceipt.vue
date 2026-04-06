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
});

const emit = defineEmits(["print", "cancel", "print-fiscal"]);

const logoSrc = BASE64_LOGO_DATA;
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
          <div v-for="product in orderProducts" :key="product.id" class="receipt-item mb-2">
            <div class="d-flex justify-space-between align-start text-caption">
              <span class="flex-grow-1 font-weight-medium">
                {{ product.selectedQuantity }} x {{ product.title }}
              </span>
              <span class="font-weight-black ms-2">{{ formatCurrency(getProductPrice(product, selectedCurrency), selectedCurrency) }}</span>
            </div>
          </div>
        </div>

        <VDivider class="my-2 border-dashed" />

        <div class="receipt-totals text-caption">
          <div v-if="activeDiscountDisplay" class="d-flex justify-space-between">
            <span>{{ activeDiscountDisplay.label }}:</span>
            <span class="text-error">- {{ activeDiscountDisplay.formatted }}</span>
          </div>
          
          <div v-if="expirationDiscountTotal > 0" class="d-flex justify-space-between">
            <span>Dto. Vencimiento:</span>
            <span class="text-error">- {{ formatCurrency(expirationDiscountTotal, selectedCurrency) }}</span>
          </div>

          <div v-if="appliesSpecialTax" class="d-flex justify-space-between">
            <span>Recargo SPE (3%):</span>
            <span>{{ formatCurrency(specialTaxAmount, selectedCurrency) }}</span>
          </div>

          <div class="d-flex justify-space-between font-weight-black text-subtitle-2 mt-1 py-1 border-y">
            <span>TOTAL A PAGAR:</span>
            <span>{{ formatCurrency(roundedTotalAmountToPay, selectedCurrency) }}</span>
          </div>

          <div v-if="orderData?.client?.is_spe" class="d-flex justify-space-between text-success font-weight-bold mt-1">
            <span>Ahorro SPE (75% IVA):</span>
            <span>-{{ formatCurrency(totalSPESavings, selectedCurrency) }}</span>
          </div>

          <VDivider class="my-2 border-dashed" />

          <div class="mb-2">
            <div class="font-weight-bold text-tiny uppercase mb-1">Detalle de Pagos:</div>
            <div v-for="payment in payments.filter(p => p.amount > 0)" :key="payment.method" class="d-flex justify-space-between text-tiny">
              <span>{{ getPaymentMethodLabel(payment.method, payment.currency) }}:</span>
              <span>{{ formatCurrency(payment.amount, payment.currency) }}</span>
            </div>
            <div v-if="hasCreditPayment" class="d-flex justify-space-between font-weight-bold text-tiny">
              <span>Crédito Aplicado:</span>
              <span>{{ formatCurrency(roundedTotalAmountToPay, selectedCurrency) }}</span>
            </div>
          </div>

          <div v-if="showChangeAmount" class="border-t pt-1">
            <div class="d-flex justify-space-between font-weight-black text-success">
              <span>DEVOLUCIÓN (COP):</span>
              <span>{{ formatCurrency(changeAmountInCop, 'COP') }}</span>
            </div>
            <div v-if="selectedCurrency !== 'COP'" class="d-flex justify-space-between text-tiny mt-1">
              <span>VUELTO EN {{ selectedCurrency }}:</span>
              <span>{{ formatCurrency(changeAmount, selectedCurrency) }}</span>
            </div>
          </div>
        </div>

        <div class="text-center mt-6 text-success font-weight-black text-caption border-t pt-2">
          ¡GRACIAS POR SU PREFERENCIA!
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
