<script setup>
import { defineProps } from 'vue';
import { formatCurrency } from '@/utils/currencyFormatter';

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  references: {
    type: Array,
    required: true,
  },
});
</script>

<template>
  <template v-if="references.length > 0">
    <div style="text-align: center;">
      <span style="padding: 0 10px; display: inline-block; vertical-align: middle;">{{ title }}</span>
    </div>
    <table style="width: 100%;">
      <tbody>
        <tr v-for="ref in references" :key="ref.referencia">
          <td style="text-align: left;"><span>{{ ref.referencia }}</span></td>
          <td style="text-align: right;"><span>{{ formatCurrency(ref.monto, ref.currency) }}</span></td>
        </tr>
      </tbody>
    </table>
  </template>
</template>
