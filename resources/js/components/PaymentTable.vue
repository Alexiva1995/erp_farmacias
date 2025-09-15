<script setup>
import { defineProps } from 'vue';
import { formatCurrency } from '@/utils/currencyFormatter'; // Asume que tienes esta utilidad

const props = defineProps({
  payments: {
    type: Array,
    required: true,
  },
});
</script>

<template>
  <table class="payment-table">
    <tbody>
      <tr v-for="payment in payments" :key="payment.label" v-if="parseFloat(payment.amount) > 0">
        <td class="left-align-cell">
          <span>{{ payment.label }}</span>
        </td>
        <td class="right-align-cell">
          <span>{{ formatCurrency(payment.amount, payment.currency) }}</span>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<style scoped>
.payment-table {
  width: 100%;
  margin-bottom: 5px;
}

.left-align-cell {
  text-align: left;
}

.right-align-cell {
  text-align: right;
  font-weight: bold;
}
</style>
