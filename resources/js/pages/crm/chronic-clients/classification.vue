<script setup>
import ProductConsumptionConfigTab from '@/pages/crm/chronic-clients/components/ProductConsumptionConfigTab.vue'
</script>

<template>
  <div class="product-consumption-classification-page">
    <ProductConsumptionConfigTab />
  </div>
</template>

<style scoped>
.product-consumption-classification-page {
  animation: fadeIn 0.2s ease-in-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
