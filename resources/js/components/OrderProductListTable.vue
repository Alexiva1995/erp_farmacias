<script setup lang="js">

import { computed } from 'vue';

const props = defineProps({
  list: { type: Array, required: true },
})

const emit = defineEmits(["eliminarItemOrden"])

const sortedList = computed(() => {
  return [...props.list].sort((a, b) => {
    const subtotalA = (Number(a.reponer) || 0) * (Number(a.precio_final_supplier) || 0);
    const subtotalB = (Number(b.reponer) || 0) * (Number(b.precio_final_supplier) || 0);
    
    if (subtotalB !== subtotalA) {
      return subtotalB - subtotalA;
    }
    
    // Si el subtotal es igual, ordenar por precio unitario
    return (Number(b.precio_final_supplier) || 0) - (Number(a.precio_final_supplier) || 0);
  });
});

const headers = [
  { title: 'Producto', key: 'product.name', minWidth: '250px' },
  { title: 'Demanda', key: 'product.demanda_ponderada', align: 'end', width: '100px' },
  { title: 'Cantidad', key: 'reponer', align: 'center', width: '120px' },
  { title: 'Costo Unit.', key: 'precio_final_supplier', align: 'end', width: '120px' },
  { title: 'Subtotal', key: 'totalPorveedor', align: 'end', width: '140px', sortable: false },
  { title: 'Acción', key: "action", align: 'center', width: '80px', sortable: false },
];

const groupBy = [{ key: 'supplier.name' }]
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
    <VDataTableVirtual
      :headers="headers"
      :items="sortedList"
      height="500"
      item-value="uuid"
      :group-by="groupBy"
      fixed-header
      class="text-no-wrap premium-order-table"
    >
      <!-- Agrupación por Proveedor -->
      <template #group-header="{ item, columns, toggleGroup, isGroupOpen }">
        <tr class="bg-light-primary cursor-pointer" @click="toggleGroup(item)">
          <td :colspan="columns.length" class="ps-4">
            <div class="d-flex align-center gap-2 py-2">
              <VBtn
                size="x-small"
                variant="tonal"
                :icon="isGroupOpen(item) ? 'tabler-chevron-down' : 'tabler-chevron-right'"
                density="comfortable"
              />
              <VIcon icon="tabler-building-store" size="20" class="text-primary" />
              <span class="text-subtitle-2 font-weight-black text-uppercase">{{ item.value }}</span>
              <VChip size="x-small" color="primary" variant="tonal" class="ms-2">
                {{ sortedList.filter(i => i.supplier.name === item.value).length }} productos
              </VChip>
            </div>
          </td>
        </tr>
      </template>

      <!-- Producto -->
      <template #item.product.name="{ item }">
        <div class="d-flex align-center py-2" style="max-inline-size: 320px;">
          <div class="d-flex flex-column overflow-hidden">
            <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" :title="item.product.name">
              <span class="text-primary mr-1">{{ item.product.id }}</span>
              <span class="text-disabled mr-1">|</span>
              {{ item.product.name.toUpperCase() }}
            </span>
            <div class="d-flex align-center gap-1 text-super-xs">
              <span class="text-disabled truncate" style="max-inline-size: 150px;">{{ item.product.active_ingredient }}</span>
              <span class="text-disabled mx-1">|</span>
              <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 100px;">
                {{ item.product.laboratory?.name || 'S/L' }}
              </span>
            </div>
          </div>
        </div>
      </template>

      <!-- Demanda -->
      <template #item.product.demanda_ponderada="{ item }">
        <span class="text-body-2 font-weight-bold text-primary">
          {{ Number(item.product.demanda_ponderada || 0).toFixed(1) }}
        </span>
      </template>

      <!-- Cantidad Editable -->
      <template #item.reponer="{ item }">
        <div class="d-flex justify-center">
          <VTextField
            v-model="item.reponer"
            type="number"
            density="compact"
            hide-details
            variant="outlined"
            class="reponer-input-small"
            :max="item.productSupplier.quantity"
          />
        </div>
      </template>

      <!-- Costo Unitario -->
      <template #item.precio_final_supplier="{ item }">
        <span class="text-body-2 font-weight-medium">
          ${{ Number(item.precio_final_supplier || 0).toFixed(2) }}
        </span>
      </template>

      <!-- Subtotal -->
      <template #item.totalPorveedor="{ item }">
        <span class="text-body-2 font-weight-black text-primary">
          ${{ (Number(item.precio_final_supplier || 0) * (Number(item.reponer) || 0)).toFixed(2) }}
        </span>
      </template>

      <!-- Acciones -->
      <template #item.action="{ item }">
        <VTooltip text="Eliminar del pedido">
          <template #activator="{ props: tp }">
            <VBtn
              v-bind="tp"
              icon="tabler-trash"
              size="x-small"
              color="error"
              variant="tonal"
              @click="emit('eliminarItemOrden', item)"
            />
          </template>
        </VTooltip>
      </template>
    </VDataTableVirtual>
  </VCard>
</template>

<style scoped>
:deep(.v-data-table-header) {
  background-color: #fff !important;
}

:deep(.v-data-table-header th) {
  border-inline-end: 1px solid rgba(var(--v-border-color), 0.05);
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
}

:deep(.v-data-table-header th:last-child) {
  border-inline-end: none;
}

.reponer-input-small {
  inline-size: 100px;
}

.reponer-input-small :deep(.v-field__input) {
  padding-block: 4px;
  text-align: center;
}

.bg-light-primary {
  background-color: rgba(var(--v-theme-primary), 0.05);
}

:deep(.v-data-table-group-header-row) {
  background-color: rgba(var(--v-theme-primary), 0.03) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}
</style>

