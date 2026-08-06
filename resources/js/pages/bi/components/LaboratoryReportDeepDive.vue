<script setup>
import { useCurrencyConverter } from '@/components/useCurrencyConverter';

const props = defineProps({
  selectedLabId: {
    type: [Number, String, null],
    default: null
  },
  laboratories: {
    type: Array,
    default: () => []
  },
  deepDiveData: {
    type: Object,
    required: true
  },
  loadingDeepDive: {
    type: Boolean,
    default: false
  }
});

const { formatCurrency } = useCurrencyConverter();
</script>

<template>
  <VCard v-if="selectedLabId" border class="mt-4 rounded-lg shadow-sm">
    <VCardTitle class="pa-4 border-b d-flex align-center bg-light-warning">
      <VIcon icon="tabler-zoom-in" class="me-2 text-warning" />
      <span class="font-weight-bold text-uppercase">
        Productos Vendidos de {{ laboratories.find(l => l.id === selectedLabId)?.name || 'Laboratorio' }}
      </span>
    </VCardTitle>

    <VCardText class="pa-0">
      <div v-if="loadingDeepDive" class="pa-10">
        <VSkeletonLoader type="table" />
      </div>
      <template v-else>
        <VTable v-if="deepDiveData.top_products?.length" density="compact">
          <thead>
            <tr>
              <th class="text-left font-weight-black">PRODUCTO</th>
              <th class="text-center font-weight-black">UNIDADES</th>
              <th class="text-right font-weight-black">VENTA BRUTA</th>
              <th class="text-right font-weight-black">MARGEN EST.</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="product in deepDiveData.top_products" :key="product.id">
              <td class="text-caption font-weight-bold text-uppercase px-2 pa-2">{{ product.name }}</td>
              <td class="text-center font-weight-black">{{ Math.round(product.units) }}</td>
              <td class="text-right font-weight-bold text-success">{{ formatCurrency(product.revenue) }}</td>
              <td class="text-right">
                <VChip size="x-small" color="primary" variant="flat">
                  {{ formatCurrency(product.estimated_margin) }}
                </VChip>
              </td>
            </tr>
          </tbody>
        </VTable>
        <VEmptyState
          v-else
          icon="tabler-package-off"
          title="Sin productos"
          text="No se registraron productos vendidos para este laboratorio en el periodo seleccionado"
          class="py-8"
        />
      </template>
    </VCardText>
  </VCard>
</template>

<style scoped>
.bg-light-warning { background-color: rgba(255, 159, 67, 0.12); }
</style>
