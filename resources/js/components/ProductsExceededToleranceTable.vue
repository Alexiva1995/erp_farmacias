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
  const filtered = props.list?.filter(pro => pro.increase == true) || [];
  
  // Ordenar por la mayor diferencia de precio (descendente)
  return [...filtered].sort((a, b) => {
    const diffA = getPriceDiff(a.product.unit_cost, a.precio_final_supplier);
    const diffB = getPriceDiff(b.product.unit_cost, b.precio_final_supplier);
    return diffB - diffA;
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
  <VCard variant="outlined" class="rounded-lg">
    <VTable
      v-if="productosTable.length > 0"
      density="compact"
      class="text-no-wrap"
    >
      <thead>
        <tr>
          <th class="ps-4">Proveedor</th>
          <th>ID</th>
          <th style="min-inline-size: 250px;">Producto</th>
          <th class="text-end">Ventas</th>
          <th class="text-end">Promedio</th>
          <th class="text-end text-primary">Demanda</th>
          <th class="text-center">Diferencia</th>
          <th class="text-end">Stock</th>
          <th class="text-end">Análisis</th>
          <th style="inline-size: 140px;" class="text-center pe-4">Sugerencia</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="item in productosTable" :key="item.uuid" :class="rowClass(item)">
          <td class="ps-4 py-2">
            <div class="d-flex flex-column" style="max-inline-size: 150px;">
              <span class="text-body-2 font-weight-bold text-truncate">{{ item.supplier.name }}</span>
              <span class="text-caption text-error text-truncate font-weight-bold">Precio Elevado</span>
            </div>
          </td>
          <td>
            <span class="text-caption text-disabled">#{{ item.product.id }}</span>
          </td>

          <td style="max-inline-size: 250px;">
            <div class="d-flex align-center py-1">
              <VAvatar
                v-if="item.product.photo_url"
                size="32"
                rounded
                variant="tonal"
                class="me-2"
                :image="item.product.photo_url"
              />
              <div class="d-flex flex-column overflow-hidden">
                <span class="text-body-2 font-weight-medium text-high-emphasis text-truncate" :title="item.product.name">
                  {{ item.product.name }}
                </span>
                <span class="text-caption text-disabled text-truncate">
                  {{ item.product.laboratory?.name || "Sin Laboratorio" }}
                </span>
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
                <span class="text-body-2 font-weight-bold text-error">${{ Number(item.precio_final_supplier || 0).toFixed(2) }}</span>
              </div>
              <VChip size="x-small" color="error" variant="tonal" label class="px-1 font-weight-bold">
                <VIcon start size="10" icon="tabler-trending-up" class="me-0" />
                +{{ getPriceDiff(item.product.unit_cost, item.precio_final_supplier).toFixed(1) }}%
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

          <td class="pe-4">
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
      <VIcon icon="tabler-trending-up-off" size="48" class="mb-2" />
      <p class="text-body-1 font-weight-medium">No se detectaron productos con incremento de precio crítico.</p>
    </div>
  </VCard>
</template>

<style scoped>
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
</style>

