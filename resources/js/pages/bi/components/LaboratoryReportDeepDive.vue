<script setup>
import { computed, ref } from 'vue';
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
const search = ref('');

const currentLabName = computed(() => {
  return props.laboratories.find(l => l.id === props.selectedLabId)?.name || 'Laboratorio Seleccionado';
});

const filteredProducts = computed(() => {
  const products = props.deepDiveData.top_products || [];
  if (!search.value.trim()) return products;
  const term = search.value.toLowerCase().trim();
  return products.filter(p => p.name?.toLowerCase().includes(term));
});

const totalUnits = computed(() => {
  return (props.deepDiveData.top_products || []).reduce((acc, p) => acc + (parseFloat(p.units) || 0), 0);
});

const totalRevenue = computed(() => {
  return (props.deepDiveData.top_products || []).reduce((acc, p) => acc + (parseFloat(p.revenue) || 0), 0);
});
</script>

<template>
  <VCard v-if="selectedLabId" border class="mt-4 rounded-lg overflow-hidden">
    <VCardTitle class="pa-4 border-b d-flex flex-wrap align-center justify-space-between gap-2">
      <div class="d-flex align-center">
        <VAvatar color="warning" variant="tonal" size="36" class="me-2">
          <VIcon icon="tabler-zoom-in" size="20" />
        </VAvatar>
        <div>
          <span class="text-subtitle-1 font-weight-bold text-uppercase">
            Detalle de Productos: {{ currentLabName }}
          </span>
          <div class="text-caption text-medium-emphasis">
            Total: {{ filteredProducts.length }} productos | {{ Math.round(totalUnits).toLocaleString() }} unidades | {{ formatCurrency(totalRevenue) }}
          </div>
        </div>
      </div>

      <div class="d-flex align-center" style="max-width: 280px; width: 100%;">
        <VTextField
          v-model="search"
          placeholder="Buscar producto..."
          prepend-inner-icon="tabler-search"
          variant="outlined"
          density="compact"
          hide-details
          clearable
        />
      </div>
    </VCardTitle>

    <VCardText class="pa-0">
      <div v-if="loadingDeepDive" class="pa-10">
        <VSkeletonLoader type="table-row-divider@6" />
      </div>
      <template v-else>
        <VTable v-if="filteredProducts.length" density="comfortable" hover class="border-t">
          <thead>
            <tr>
              <th class="text-left font-weight-bold">PRODUCTO</th>
              <th class="text-center font-weight-bold">UNIDADES</th>
              <th class="text-right font-weight-bold">VENTA BRUTA</th>
              <th class="text-right font-weight-bold">MARGEN ESTIMADO</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="product in filteredProducts" :key="product.id">
              <td class="text-caption font-weight-bold text-uppercase py-2">
                {{ product.name }}
              </td>
              <td class="text-center font-weight-bold">
                {{ Math.round(product.units).toLocaleString() }}
              </td>
              <td class="text-right font-weight-bold text-success">
                {{ formatCurrency(product.revenue) }}
              </td>
              <td class="text-right">
                <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
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
          :text="search ? 'No se encontraron productos coincidentes con el criterio de búsqueda' : 'No se registraron productos vendidos para este laboratorio en el periodo'"
          class="py-8"
        />
      </template>
    </VCardText>
  </VCard>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
