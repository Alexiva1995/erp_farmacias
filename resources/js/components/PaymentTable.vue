<script setup>
import { formatCurrency } from '@/utils/currencyFormatter';
import { computed } from 'vue';

const props = defineProps({
  payments: {
    type: Array,
    required: true,
  },
});

const filteredPayments = computed(() => {
  return props.payments.filter(payment => parseFloat(payment.amount) !== 0);
});
</script>

<template>
  <table style="font-family: monospace; font-size: 13px; inline-size: 100%;">
    <tbody>
      <tr v-for="payment in filteredPayments" :key="payment.label">
        <td class="left-align-cell" style="padding-block: 1px; padding-inline: 0;">
          {{ payment.label }}
        </td>
        <td class="text-right" style=" font-weight: bold;padding-block: 1px; padding-inline: 0;">
          {{ formatCurrency(payment.amount, payment.currency) }}
        </td>
      </tr>

      <tr v-if="filteredPayments.length === 0">
        <td colspan="2" class="text-center text-muted" style="padding-block: 2px; padding-inline: 0;">-</td>
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
