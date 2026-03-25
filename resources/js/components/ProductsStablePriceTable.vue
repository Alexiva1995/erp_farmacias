<script setup lang="js">
import { roundIaAnalysis } from "@/utils/iaAnalysisRounding";
import { computed } from 'vue';

const props = defineProps({
  list: { type: Array, required: true },
})

// Función para calcular la diferencia de precio (%)
function getPriceDiff(oldPrice, newPrice) {
  if (!oldPrice || oldPrice == 0) return 0;
  return ((newPrice - oldPrice) / oldPrice) * 100;
}

const productosTable = computed(() => {
  const filtered = props.list?.filter(pro => pro.increase === null) || [];
  
  // Ordenar por relevancia de pedido (los que más faltan primero)
  return [...filtered].sort((a, b) => {
    return roundIaAnalysis(b.product.solicitar) - roundIaAnalysis(a.product.solicitar);
  });
});

// Determinar clase de fila según el análisis
function rowClass(item) {
  const val = roundIaAnalysis(item.product.solicitar);
  if (val > 0) return 'row-needs';
  if (val < 0) return 'row-excess';
  return '';
}
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
    <VTable
      v-if="productosTable.length > 0"
      density="compact"
      class="text-no-wrap"
    >
      <thead>
        <tr>
          <th class="ps-6">Proveedor</th>
          <th>ID</th>
          <th style="min-inline-size: 250px;">Producto</th>
          <th class="text-end">Ventas</th>
          <th class="text-end">Promedio</th>
          <th class="text-end text-primary">Demanda</th>
          <th class="text-center">Diferencia</th>
          <th class="text-end">Stock</th>
          <th class="text-end">Análisis</th>
          <th style="inline-size: 140px;" class="text-center pe-6">Sugerencia</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="item in productosTable" :key="item.uuid || item.product.id" :class="rowClass(item)">
          <td class="ps-6 py-2">
            <div class="d-flex flex-column" style="max-inline-size: 150px;">
              <span class="text-sm font-weight-black text-uppercase truncate">{{ item.supplier.name }}</span>
              <span class="text-super-xs text-disabled text-truncate uppercase letter-spacing-widest">Precio Estable</span>
            </div>
          </td>
          <td>
            <span class="text-sm font-weight-black text-primary">{{ item.product.id }}</span>
          </td>

          <td style="max-inline-size: 320px;">
            <div class="d-flex align-center py-1">
              <VAvatar
                v-if="item.product.photo_url"
                size="32"
                rounded
                variant="tonal"
                class="me-3"
                :image="item.product.photo_url"
              />
              <div class="d-flex flex-column overflow-hidden">
                <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" :title="item.product.name">
                  {{ item.product.name.toUpperCase() }}
                </span>
                <div class="d-flex align-center gap-1 text-super-xs">
                  <span class="text-disabled truncate" style="max-inline-size: 180px;">{{ item.product.active_ingredient }}</span>
                  <span class="text-disabled mx-1">|</span>
                  <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 120px;">
                    {{ item.product.laboratory?.name || 'S/L' }}
                  </span>
                </div>
              </div>
            </div>
          </td>

          <td class="text-end font-weight-medium">
            {{ item.product.total_sold_completed ?? 0 }}
          </td>
          <td class="text-end">
            <VTooltip text="Ventas promedio mensuales">
              <template #activator="{ props: tp }">
                <span v-bind="tp" class="text-body-2">{{ Number(item.product.promedio_calculado || 0).toFixed(1) }}</span>
              </template>
            </VTooltip>
          </td>

          <td class="text-end">
            <VTooltip text="Demanda proyectada (antes de stock)">
              <template #activator="{ props: tp }">
                <span v-bind="tp" class="text-body-2 font-weight-bold text-primary">
                  {{ Number(item.product.demanda_ponderada || 0).toFixed(1) }}
                </span>
              </template>
            </VTooltip>
          </td>

          <td class="text-center">
            <div class="d-flex flex-column align-center gap-1 py-1">
              <div class="d-flex align-center gap-1">
                <span class="text-caption text-disabled line-through">${{ Number(item.product.unit_cost || 0).toFixed(2) }}</span>
                <VIcon icon="tabler-arrow-right" size="12" class="text-disabled" />
                <span class="text-body-2 font-weight-bold" :class="getPriceDiff(item.product.unit_cost, item.precio_final_supplier) >= 0 ? 'text-error' : 'text-success'">
                  ${{ Number(item.precio_final_supplier || 0).toFixed(2) }}
                </span>
              </div>
              <VChip size="x-small" :color="Math.abs(getPriceDiff(item.product.unit_cost, item.precio_final_supplier)) < 0.1 ? 'secondary' : (getPriceDiff(item.product.unit_cost, item.precio_final_supplier) > 0 ? 'error' : 'success')" variant="tonal" label class="px-1">
                <VIcon start size="10" :icon="getPriceDiff(item.product.unit_cost, item.precio_final_supplier) >= 0 ? 'tabler-trending-up' : 'tabler-trending-down'" class="me-0" />
                {{ Math.abs(getPriceDiff(item.product.unit_cost, item.precio_final_supplier)).toFixed(1) }}%
              </VChip>
            </div>
          </td>

          <td class="text-end">
            <div class="d-flex flex-column align-end">
              <span class="text-body-2 font-weight-medium">{{ item.product.stock }}</span>
              <span v-if="item.product.totalQuantityInAutoOrder > 0" class="text-xs text-info font-weight-bold">
                AO: {{ item.product.totalQuantityInAutoOrder }}
              </span>
            </div>
          </td>

          <td class="text-end">
            <VTooltip :text="roundIaAnalysis(item.product.solicitar) > 0 ? 'Sugerencia de compra' : 'Stock suficiente'">
              <template #activator="{ props: tp }">
                <span
                  v-bind="tp"
                  class="text-body-2 font-weight-black"
                  :style="roundIaAnalysis(item.product.solicitar) > 0 ? 'color:#28c76f' : roundIaAnalysis(item.product.solicitar) < 0 ? 'color:#ea5455' : 'color:inherit'"
                >
                  {{ roundIaAnalysis(item.product.solicitar) > 0 ? '+' : '' }}{{ roundIaAnalysis(item.product.solicitar) }}
                </span>
              </template>
            </VTooltip>
          </td>

          <td class="pe-6">
            <VTextField
              type="number"
              v-model="item.reponer"
              density="compact"
              hide-details
              variant="outlined"
              class="reponer-input"
              :max="item.productSupplier.quantity"
              :suffix="'/' + item.productSupplier.quantity"
            />
          </td>
        </tr>
      </tbody>
    </VTable>

    <div v-else class="pa-12 text-center text-disabled">
      <VIcon icon="tabler-minus-vertical" size="48" class="mb-2" />
      <p class="text-body-1 font-weight-medium">No hay productos con precio estable en la lista.</p>
    </div>
  </VCard>
</template>

<style scoped>
thead th {
  background-color: #fff !important;
  font-weight: 700 !important;
  font-size: 0.75rem !important;
  text-transform: uppercase;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

tbody td {
  font-size: 0.875rem !important;
}

.row-needs {
  background-color: rgba(40, 199, 111, 4%) !important;
}

.row-excess {
  background-color: rgba(234, 84, 85, 4%) !important;
}

.reponer-input :deep(.v-field__input) {
  padding-block: 4px;
  text-align: center;
}

.line-through {
  text-decoration: line-through;
}

.text-xs {
  font-size: 0.75rem;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.letter-spacing-widest { letter-spacing: 0.1em !important; }
</style>

