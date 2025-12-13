<script setup>
import { defineProps, computed } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  payments: {
    type: Array,
    required: true,
  },
});

const filteredPayments = computed(() => {
  return props.payments.filter((payment) => parseFloat(payment.amount) !== 0);
});
</script>

<template>
  <table class="table table-borderless table-sm w-100 mx-auto center-block">
    <tbody>
      <tr v-for="payment in filteredPayments" :key="payment.label">
        <td class="text-start"><span></span></td>
        <td class="text-right font-weight-bold">
          <span>{{ payment.label }}</span>
        </td>
        <td class="text-right font-weight-bold" style="width:150px;">
          <span> {{ formatCurrency(payment.amount, payment.currency) }}</span>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<style scoped>
.right-align-cell {
  text-align: right;
  font-weight: bold;
}
</style>
