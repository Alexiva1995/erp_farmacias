<script setup>

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
      <span style=" display: inline-block;padding-block: 0; padding-inline: 10px; vertical-align: middle;">{{ title }}</span>
    </div>
    <table style="inline-size: 100%;">
      <tbody>
        <tr v-for="ref in references" :key="ref.referencia">
          <td style="text-align: start;"><span>{{ ref.referencia }}</span></td>
          <td style="text-align: end;"><span>{{ formatCurrency(ref.monto, ref.currency) }}</span></td>
        </tr>
      </tbody>
    </table>
  </template>
</template>
