<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";
import { defineProps } from "vue";

const props = defineProps({
  products: {
    type: Array,
    required: true,
  },
  selectedCurrency: {
    type: String,
    required: true,
  },
  getProductPrice: {
    type: Function,
    required: true,
  },
  getProductPriceSinIva: {
    type: Function,
    required: true,
  },
  getIva: {
    type: Function,
    required: true,
  },
});
</script>

<template>
  <VCard variant="flat" border class="rounded-xl overflow-hidden glass-card">
    <VCardTitle class="pa-3 border-b d-flex align-center bg-primary">
      <VIcon icon="tabler-package" class="me-2" color="white" size="20" />
      <span class="text-subtitle-2 font-weight-black uppercase text-white">Detalle de Productos</span>
    </VCardTitle>
    <VCardText class="pa-0">
      <!-- Desktop Table -->
      <VTable v-if="$vuetify.display.mdAndUp" density="compact" hover class="compact-table">
        <thead>
          <tr class="bg-light">
            <th class="text-left py-2 font-weight-bold">Producto</th>
            <th class="text-right py-2 font-weight-bold">Precio</th>
            <th class="text-right py-2 font-weight-bold">IVA</th>
            <th class="text-right py-2 font-weight-bold">Total</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="product in products" :key="product.id">
            <td class="py-2">
              <div class="d-flex flex-column">
                <span class="font-weight-bold text-body-2 line-clamp-1">{{ product.title }}</span>
                <span class="text-caption text-medium-emphasis">{{ product.laboratory }} • {{ product.selectedQuantity }} UNID</span>
              </div>
            </td>
            <td class="text-right py-2 text-body-2">
              {{ formatCurrency(getProductPriceSinIva(product, selectedCurrency) * product.selectedQuantity, selectedCurrency) }}
            </td>
            <td class="text-right py-2 text-caption text-medium-emphasis">
              {{ formatCurrency(getIva(product, selectedCurrency), selectedCurrency) }}
            </td>
            <td class="text-right py-2 font-weight-black text-primary text-body-2">
              {{ formatCurrency(getProductPrice(product, selectedCurrency), selectedCurrency) }}
            </td>
          </tr>
        </tbody>
      </VTable>

      <!-- Mobile Cards -->
      <div v-else class="pa-2 d-flex flex-column gap-2">
        <div 
          v-for="product in products" 
          :key="product.id" 
          class="pa-2 border rounded-lg bg-surface product-card-compact"
        >
          <div class="d-flex justify-space-between mb-1 align-center">
            <span class="text-caption font-weight-black uppercase line-clamp-1 flex-grow-1">{{ product.title }}</span>
            <VChip size="x-small" color="primary" variant="tonal" label class="ms-2 font-weight-black">
              {{ product.selectedQuantity }} UNID
            </VChip>
          </div>
          <div class="d-flex justify-space-between align-end">
            <span class="text-tiny text-medium-emphasis">{{ product.laboratory }}</span>
            <span class="text-body-2 font-weight-black text-primary">
              {{ formatCurrency(getProductPrice(product, selectedCurrency), selectedCurrency) }}
            </span>
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.compact-table :deep(td) {
  block-size: 48px !important;
}

.text-tiny {
  font-size: 0.7rem;
}

.product-card-compact {
  transition: transform 0.2s ease;
}

.product-card-compact:active {
  transform: scale(0.98);
}
</style>
