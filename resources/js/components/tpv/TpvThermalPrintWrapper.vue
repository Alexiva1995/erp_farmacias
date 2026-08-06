<script setup>
import OrderTicketThermal54 from "@/components/OrderTicketThermal54.vue";
import { THERMAL_54MM_CSS } from "@/constants/thermalTicket54.js";

const props = defineProps({
  isPrinting: { type: Boolean, default: false },
  orderData: { type: Object, default: null },
  itemsForTicket: { type: Array, default: () => [] },
  TotalToPrint: { type: Number, default: 0 },
  selectedDisplayCurrency: { type: String, default: "USD" },
  paymentsForPrint: { type: Array, default: () => [] },
  changeAmountForPrint: { type: Number, default: 0 },
  changeAmountOriginForPrint: { type: Number, default: 0 },
  creditAmountForPrint: { type: Number, default: 0 },
  creditForPrint: { type: Boolean, default: false },
  companyDiscountForPrint: { type: Number, default: 0 },
  discountTypeForPrint: { type: String, default: null },
  doctorDiscountForPrint: { type: Number, default: 0 },
  recipeDiscountForPrint: { type: Number, default: 0 },
  isSpecialTaxpayer: { type: Boolean, default: false },
  speSurchargeAmountPrint: { type: Number, default: 0 },
});
</script>

<template>
  <div
    id="orderPrint"
    :style="
      isPrinting
        ? {
            position: 'fixed',
            left: '0',
            top: '0',
            zIndex: 9999,
            background: 'white',
            width: '54mm',
          }
        : { position: 'absolute', left: '-9999px' }
    "
  >
    <OrderTicketThermal54
      v-if="orderData"
      :order-data="orderData"
      :order-products="itemsForTicket || []"
      :total-amount="TotalToPrint || 0"
      :selected-currency="selectedDisplayCurrency || 'USD'"
      :payments="paymentsForPrint || []"
      :change-amount="changeAmountForPrint || 0"
      :change-amount-origin="changeAmountOriginForPrint || 0"
      :credit-amount="creditAmountForPrint || 0"
      :credit="creditForPrint || false"
      :company-discount-total="companyDiscountForPrint || 0"
      :selected-discount-type="discountTypeForPrint || null"
      :doctor-discount-total="doctorDiscountForPrint || 0"
      :recipe-discount-total="recipeDiscountForPrint || 0"
      :is-special-taxpayer="isSpecialTaxpayer || false"
      :spe-surcharge-amount="speSurchargeAmountPrint || 0"
    />
  </div>
</template>
