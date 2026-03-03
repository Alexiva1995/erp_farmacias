<script setup>
import { formatCurrency } from '@/utils/currencyFormatter';
import { computed, defineProps } from 'vue';

const props = defineProps({
  payments: {
    type: Array,
    required: true,
  },
});

const filteredPayments = computed(() => {
  return props.payments.filter(payment => parseFloat(payment.amount) !== 0);
});

const tableTotal = computed(() => {
  if (filteredPayments.value.length === 0) return 0;
  return filteredPayments.value.reduce((acc, curr) => acc + parseFloat(curr.amount), 0);
});

const defaultCurrency = computed(() => {
  return filteredPayments.value.length > 0 ? filteredPayments.value[0].currency : '';
});
</script>

<template>
  <table style=" font-family: monospace; font-size: 14px;inline-size: 100%;">
    <tbody>
      <tr v-for="payment in filteredPayments" :key="payment.label">
        <td class="left-align-cell" style="padding-block: 2px;padding-inline: 0;">
          <span>{{ payment.label }}</span>
        </td>
        <td class="text-right" style="padding-block: 2px;padding-inline: 0;">
          <span>{{ formatCurrency(payment.amount, payment.currency) }}</span>
        </td>
      </tr>
      <tr v-if="filteredPayments.length > 0">
        <td colspan="2">
          <hr style="border-block-start: 1px dashed #000; margin-block: 4px; margin-inline: 0;" />
        </td>
      </tr>
      <tr v-if="filteredPayments.length > 0">
         <td class="left-align-cell" style=" font-weight: bold;padding-block: 2px; padding-inline: 0;">
          <span>TOTAL</span>
        </td>
        <td class="right-align-cell" style=" font-size: 15px; font-weight: bold;padding-block: 2px; padding-inline: 0;">
           <span>{{ formatCurrency(tableTotal, defaultCurrency) }}</span>
        </td>
      </tr>

      <tr v-if="filteredPayments.length === 0">
        <td colspan="2" class="text-center text-muted" style="padding-block: 2px;padding-inline: 0;">No hay pagos.</td>
      </tr>
    </tbody>
  </table>
</template>

<style scoped>
.left-align-cell {
  text-align: start;
}

.right-align-cell {
  text-align: end;
}
</style>
