<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { formatDateTime } from "@/utils/formatDateTime";
import PaymentTable from './PaymentTable.vue';
import { computed, defineProps } from 'vue';

const props = defineProps({
  cashData: { type: Object, required: true },
});


const logoSrc = computed(() => {
  return BASE64_LOGO_DATA;
});

const getValue = (key) => parseFloat(props.cashData[key] || 0);

const usdPayments = computed(() => [
  { label: 'Efectivo', amount: getValue('usd_cash'), currency: 'USD' },
  { label: 'Binance', amount: getValue('usd_binance'), currency: 'USD' },
  { label: 'Paypal', amount: getValue('usd_paypal'), currency: 'USD' },
  { label: 'Conversión de COP', amount: getValue('usd_conversion'), currency: 'USD' },
]);


const cashAmountUsd = computed(() => getValue("usd_cash"));
const binanceAmount = computed(() => getValue("usd_binance"));
const paypalAmount = computed(() => getValue("usd_paypal"));
const amountGivenUsd = computed(() => getValue("usd_conversion"));

const bscashAmount = computed(() => getValue("bs_cash"));
const bsTransferAmount = computed(() => getValue("bs_transfer"));
const bsMobileAmount = computed(() => getValue("bs_mobile"));
const bsCardUsd = computed(() => getValue("bs_card"));

const copCashAmount = computed(() => getValue("cop_cash"));
const copTransferAmount = computed(() => getValue("cop_transfer"));
const copSpareAmount = computed(() => getValue("cop_spare"));
const copConversionUsd = computed(() => getValue("cop_conversion"));

const creditAmount = computed(() => getValue("usd_credit"));

const cashAmountUsdPayment = computed(() => getValue("usd_cash_payment_credit"));
const binanceAmountPayment = computed(() => getValue("usd_binance_payment_credit"));
const paypalAmountPayment = computed(() => getValue("usd_paypal_payment_credit"));
const bscashAmountPayment = computed(() => getValue("bs_cash_payment_credit"));
const bsTransferAmountPayment = computed(() => getValue("bs_transfer_payment_credit"));
const bsMobileAmountPayment = computed(() => getValue("bs_mobile_payment_credit"));
const bsCardPayment = computed(() => getValue("bs_card_payment_credit"));
const copCashPayment = computed(() => getValue("cop_cash_payment_credit"));
const copTransferPayment = computed(() => getValue("cop_transfer_payment_credit"));

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
      v-table
      <div class="d-flex justify-space-between align-start textoPrint mb-1" :class="{ 'd-flex-pdf': isPdf }">
        <span class="textoPrint"
          >Cierre de caja N°: {{ props.cashData.id }}</span
        >
        <span
          >Fecha:
          {{ formatDateTime(props.cashData.closing_date, "date") }}</span
        >
      </div>
      <div class="d-flex justify-space-between align-start textoPrint mb-1" :class="{ 'd-flex-pdf': isPdf }">
        <span class="textoPrint">Vendedor:</span>
        <span>{{ props.cashData.seller_id }}</span>
      </div>

      <div class="d-flex align-center my-4">
        <VDivider class="flex-grow-1" />
        <span class="px-3 text-subtitle-1 text-medium-emphasis">USD</span>
        <VDivider class="flex-grow-1" />
      </div>

      <PaymentTable :payments="usdPayments" />

      <div class="d-flex align-center my-4">
        <VDivider class="flex-grow-1" />
        <span class="px-3 text-subtitle-1 text-medium-emphasis">BS</span>
        <VDivider class="flex-grow-1" />
      </div>

      <VList density="compact">
        <VListItem v-if="bscashAmount > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Efectivo</span>
            <span class="font-weight-medium">{{
              formatCurrency(bscashAmount, "BS")
            }}</span>
          </VListItemTitle>
        </VListItem>

        <VListItem v-if="bsCardUsd > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Tarjeta</span>
            <span class="font-weight-medium">{{
              formatCurrency(bsCardUsd, "BS")
            }}</span>
          </VListItemTitle>
        </VListItem>

        <VListItem v-if="bsTransferAmount > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Transferencia</span>
            <span class="font-weight-medium">{{
              formatCurrency(bsTransferAmount, "BS")
            }}</span>
          </VListItemTitle>
        </VListItem>

        <VListItem v-if="bsMobileAmount > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Pago Móvil</span>
            <span class="font-weight-medium">{{
              formatCurrency(bsMobileAmount, "BS")
            }}</span>
          </VListItemTitle>
        </VListItem>
      </VList>

      <div class="d-flex align-center my-4">
        <VDivider class="flex-grow-1" />
        <span class="px-3 text-subtitle-1 text-medium-emphasis">COP</span>
        <VDivider class="flex-grow-1" />
      </div>

      <VList density="compact">
        <VListItem v-if="copCashAmount > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Efectivo</span>
            <span class="font-weight-medium">{{
              formatCurrency(copCashAmount, "COP")
            }}</span>
          </VListItemTitle>
        </VListItem>

        <VListItem v-if="copTransferAmount > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Transferencia</span>
            <span class="font-weight-medium">{{
              formatCurrency(copTransferAmount, "COP")
            }}</span>
          </VListItemTitle>
        </VListItem>

        <VListItem v-if="copSpareAmount > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Sobrante</span>
            <span class="font-weight-medium">{{
              formatCurrency(copSpareAmount, "COP")
            }}</span>
          </VListItemTitle>
        </VListItem>

        <VListItem v-if="copConversionUsd > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Conversión de USD</span>
            <span class="font-weight-medium">{{
              formatCurrency(copConversionUsd, "COP")
            }}</span>
          </VListItemTitle>
        </VListItem>
      </VList>

      <div class="d-flex align-center my-4">
        <VDivider class="flex-grow-1" />
        <span class="px-3 text-subtitle-1 text-medium-emphasis">Créditos</span>
        <VDivider class="flex-grow-1" />
      </div>

       <VList density="compact">
        <VListItem v-if="creditAmount > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Créditos</span>
            <span class="font-weight-medium">{{
              formatCurrency(creditAmount, "USD")
            }}</span>
          </VListItemTitle>
        </VListItem>
      </VList>

      <div class="d-flex align-center my-4">
        <VDivider class="flex-grow-1" />
        <span class="px-3 text-subtitle-1 text-medium-emphasis">Pagos</span>
        <VDivider class="flex-grow-1" />
      </div>

 <VList density="compact">
        <VListItem v-if="cashAmountUsdPayment > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Efectivo (USD)</span>
            <span class="font-weight-medium">{{
              formatCurrency(cashAmountUsdPayment, "USD")
            }}</span>
          </VListItemTitle>
        </VListItem>

        <VListItem v-if="binanceAmountPayment > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Binance (USD)</span>
            <span class="font-weight-medium">{{
              formatCurrency(binanceAmountPayment, "USD")
            }}</span>
          </VListItemTitle>
        </VListItem>

        <VListItem v-if="paypalAmountPayment > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Paypal (USD)</span>
            <span class="font-weight-medium">{{
              formatCurrency(paypalAmountPayment, "USD")
            }}</span>
          </VListItemTitle>
        </VListItem>

        <VListItem v-if="bscashAmountPayment > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Efectivo (Bs)</span>
            <span class="font-weight-medium">{{
              formatCurrency(bscashAmountPayment, "BS")
            }}</span>
          </VListItemTitle>
        </VListItem>

         <VListItem v-if="bsCardPayment > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Tarjeta (Bs)</span>
            <span class="font-weight-medium">{{
              formatCurrency(bsCardPayment, "BS")
            }}</span>
          </VListItemTitle>
        </VListItem>

         <VListItem v-if="bsTransferAmountPayment > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Transferencia (Bs)</span>
            <span class="font-weight-medium">{{
              formatCurrency(bsTransferAmountPayment, "BS")
            }}</span>
          </VListItemTitle>
        </VListItem>

         <VListItem v-if="bsMobileAmountPayment > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Pago Móvil (Bs)</span>
            <span class="font-weight-medium">{{
              formatCurrency(bsMobileAmountPayment, "BS")
            }}</span>
          </VListItemTitle>
        </VListItem>

         <VListItem v-if="copCashPayment > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Efectivo (COP)</span>
            <span class="font-weight-medium">{{
              formatCurrency(copCashPayment, "COP")
            }}</span>
          </VListItemTitle>
        </VListItem>

        <VListItem v-if="copTransferPayment > 0">
          <VListItemTitle class="d-flex justify-space-between" :class="{ 'd-flex-pdf': isPdf }">
            <span>Transferencia (COP)</span>
            <span class="font-weight-medium">{{
              formatCurrency(copTransferPayment, "COP")
            }}</span>
          </VListItemTitle>
        </VListItem>
      </VList>

 <div class="d-flex align-center my-4">
        <VDivider class="flex-grow-1" />
        <span class="px-3 text-subtitle-1 text-medium-emphasis">Entrega</span>
        <VDivider class="flex-grow-1" />
      </div>


       <div class="d-flex align-center my-4">
        <VDivider class="flex-grow-1" />
        <span class="px-3 text-subtitle-1 text-medium-emphasis">Referencias</span>
        <VDivider class="flex-grow-1" />
      </div>


    </VCard>
  </div>
</template>
<style scoped>
/* Estilos para el PDF - Asegúrate de que sean compatibles con Dompdf */
.d-flex-pdf {
    display: table; /* O 'block', o 'inline-block' */
    width: 100%;
}
.d-flex-pdf > * {
    display: table-cell; /* Para que actúen como celdas de tabla */
}
.d-flex-pdf > *:last-child {
    text-align: right;
}

/* El resto de tus estilos */
</style>
