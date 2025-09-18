<script setup>
import { defineProps, computed } from 'vue';
import { formatCurrency } from '@/utils/currencyFormatter';

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
  <table style="width: 100%;">
    <tbody>
      <tr v-for="payment in filteredPayments" :key="payment.label">
        <td class="left-align-cell">
          <span>{{ payment.label }}</span>
        </td>
        <td class="text-right">
          <span>{{ formatCurrency(payment.amount, payment.currency) }}</span>
        </td>
      </tr>
      <tr v-if="filteredPayments.length === 0">
        <td colspan="2" class="text-center text-muted">No hay pagos registrados para esta moneda.</td>
      </tr>
    </tbody>
  </table>
</template>

<style scoped>
.left-align-cell {
  text-align: left;
}
.right-align-cell {
  text-align: right; 
  font-weight: bold;
}
</style>
