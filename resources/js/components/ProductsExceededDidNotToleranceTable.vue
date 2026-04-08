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

const headers = [
  { title: 'Proveedor', key: 'supplier.name', sortable: true },
  { title: 'ID', key: 'product.id', sortable: true },
  { title: 'Producto', key: 'product.name', sortable: true },
  { title: 'Ventas', key: 'product.total_sold_completed', align: 'end', sortable: true },
  { title: 'Promedio', key: 'product.promedio_calculado', align: 'end', sortable: true },
  { title: 'Demanda', key: 'product.demanda_ponderada', align: 'end', sortable: true },
  { title: 'Ahorro', key: 'diferencia', align: 'center', sortable: true },
  { title: 'Stock', key: 'product.stock', align: 'end', sortable: true },
  { title: 'Análisis', key: 'product.solicitar', align: 'end', sortable: true },
  { title: 'Sugerencia', key: 'reponer', align: 'center', sortable: false, width: '120px' },
];

const productosTable = computed(() => {
  const filtered = props.list?.filter(pro => pro.increase == false) || [];
  
  // Ordenar por el mayor ahorro (descendente en valor absoluto de ahorro)
  return [...filtered].sort((a, b) => {
    const diffA = getPriceDiff(a.product.unit_cost, a.precio_final_supplier);
    const diffB = getPriceDiff(b.product.unit_cost, b.precio_final_supplier);
    return diffA - diffB; // El más negativo (más ahorro) primero
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
    <VDataTable
        v-if="productosTable.length > 0"
        :headers="headers"
        :items="productosTable"
        :items-per-page="15"
        density="compact"
        class="text-no-wrap"
        hover
    >
        <!-- Proveedor -->
        <template #item.supplier.name="{ item }">
            <div class="d-flex flex-column py-2" style="max-inline-size: 150px;">
                <span class="text-sm font-weight-black text-uppercase truncate">{{ item.supplier.name }}</span>
                <span class="text-super-xs text-success font-weight-bold uppercase letter-spacing-widest">Oportunidad de Ahorro</span>
            </div>
        </template>

        <!-- ID -->
        <template #item.product.id="{ item }">
            <span class="text-sm font-weight-black text-primary">{{ item.product.id }}</span>
        </template>

        <!-- Producto -->
        <template #item.product.name="{ item }">
            <div class="d-flex align-center py-1 overflow-hidden" style="max-inline-size: 320px;">
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
        </template>

        <!-- Ventas -->
        <template #item.product.total_sold_completed="{ item }">
            <span class="font-weight-medium">{{ item.product.total_sold_completed ?? 0 }}</span>
        </template>

        <!-- Promedio -->
        <template #item.product.promedio_calculado="{ item }">
            <VTooltip text="Ventas promedio mensuales">
                <template #activator="{ props: tp }">
                    <span v-bind="tp" class="text-body-2">{{ Number(item.product.promedio_calculado || 0).toFixed(1) }}</span>
                </template>
            </VTooltip>
        </template>

        <!-- Demanda -->
        <template #item.product.demanda_ponderada="{ item }">
            <VTooltip text="Demanda proyectada (antes de stock)">
                <template #activator="{ props: tp }">
                    <span v-bind="tp" class="text-body-2 font-weight-bold text-primary">
                        {{ Number(item.product.demanda_ponderada || 0).toFixed(1) }}
                    </span>
                </template>
            </VTooltip>
        </template>

        <!-- Diferencia/Ahorro -->
        <template #item.diferencia="{ item }">
            <div class="d-flex flex-column align-center gap-1 py-1">
                <div class="d-flex align-center gap-1">
                    <span class="text-caption text-disabled line-through">${{ Number(item.product.unit_cost || 0).toFixed(2) }}</span>
                    <VIcon icon="tabler-arrow-right" size="12" class="text-disabled" />
                    <span class="text-body-2 font-weight-bold text-success">${{ Number(item.precio_final_supplier || 0).toFixed(2) }}</span>
                </div>
                <VChip size="x-small" color="success" variant="tonal" label class="px-1 font-weight-bold">
                    <VIcon start size="10" icon="tabler-trending-down" class="me-0" />
                    {{ getPriceDiff(item.product.unit_cost, item.precio_final_supplier).toFixed(1) }}%
                </VChip>
            </div>
        </template>

        <!-- Stock -->
        <template #item.product.stock="{ item }">
            <div class="d-flex flex-column align-end">
                <span class="text-body-2 font-weight-medium">{{ item.product.stock }}</span>
                <span v-if="item.product.totalQuantityInAutoOrder > 0" class="text-xs text-info font-weight-bold">
                    AO: {{ item.product.totalQuantityInAutoOrder }}
                </span>
            </div>
        </template>

        <!-- Análisis -->
        <template #item.product.solicitar="{ item }">
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
        </template>

        <!-- Sugerencia (Input) -->
        <template #item.reponer="{ item }">
            <VTextField
                type="number"
                v-model="item.reponer"
                density="compact"
                hide-details
                variant="outlined"
                class="reponer-input my-1"
                style="min-inline-size: 100px;"
                :max="item.productSupplier.quantity"
                :suffix="'/' + item.productSupplier.quantity"
            />
        </template>

    </VDataTable>

    <div v-else class="pa-12 text-center text-disabled">
      <VIcon icon="tabler-trending-down-off" size="48" class="mb-2" />
      <p class="text-body-1 font-weight-medium">No hay oportunidades de ahorro detectadas.</p>
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

