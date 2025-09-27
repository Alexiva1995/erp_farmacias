<script setup>
import { defineProps, computed } from 'vue';

const props = defineProps({
  averageAmount: {
    type: [String, Number],
    required: true,
  },
  lastMonthAverage: { 
    type: [String, Number],
    required: true,
  },
  percentageChange: {
    type: [String, Number],
    default: 0
  },
  isPositive: {
    type: Boolean,
    default: true
  }
});
const changeClass = computed(() => props.isPositive ? 'text-success' : 'text-error');
const changeIcon = computed(() => props.isPositive ? 'tabler-chevron-up' : 'tabler-chevron-down');
</script>
<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Resumen de Caja</VCardTitle>
    </VCardItem>
    <VCardText>
      <div class="d-flex align-center justify-space-between py-2">
        <div class="d-flex align-center gap-x-2">
          <VIcon
            :icon="'tabler-shopping-cart'"  :style="{ color:'#D9D9D9'}"
            size="24"
          />
          <span class="text-body-1 text-high-emphasis">Promedio de Ventas</span>
        </div>

        <div class="d-flex align-center gap-x-4">
          <div>
            <i 
              :class="[changeIcon, 'v-icon notranslate v-theme--light v-icon--size-default me-1', changeClass]" 
              aria-hidden="true"
            ></i>
            <span :class="['font-weight-medium', changeClass]">
              {{ props.lastMonthAverage }} USD
            </span>
          </div>
          <h3 class="text-h3">{{ props.averageAmount }} USD</h3>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
