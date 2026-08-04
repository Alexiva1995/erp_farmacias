<script setup>
import CreditsTicket from "@/components/CreditsTicket.vue";
import CreditsOrderTicketThermal54 from "@/components/CreditsOrderTicketThermal54.vue";

defineProps({
  isPrinting: { type: Boolean, default: false },
  isPrintingCreditOrder: { type: Boolean, default: false },
  creditsData: { type: Object, default: null },
  paymentsForPrint: { type: Array, default: () => [] },
  changeAmountForPrint: { type: Number, default: 0 },
  creditAmountForPrint: { type: Number, default: 0 },
});
</script>

<template>
  <div>
    <div
      id="CreditPrint"
      :class="{ 'd-none': !isPrinting, 'print-container': true }"
    >
      <CreditsTicket
        v-if="isPrinting && creditsData"
        :credits-data="creditsData"
        :payments="paymentsForPrint"
        :change-amount="changeAmountForPrint"
        :credit-amount="creditAmountForPrint"
      />
    </div>

    <div
      id="CreditOrderPrintThermal54"
      :class="{ 'd-none': !isPrintingCreditOrder, 'print-container': true }"
    >
      <CreditsOrderTicketThermal54
        v-if="isPrintingCreditOrder && creditsData"
        :credits-data="creditsData"
      />
    </div>
  </div>
</template>
