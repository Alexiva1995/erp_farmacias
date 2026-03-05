<script setup lang="js">

const props = defineProps({
  list: { type: Array, required: true },
})

const emit = defineEmits(["eliminarItemOrden"])

const headers = [
  { title: 'Producto', key: 'product.name', minWidth: '250px' },
  { title: 'Cantidad', key: 'reponer', align: 'center', width: '150px' },
  { title: 'Costo Unit.', key: 'precio_final_supplier', align: 'end', width: '120px' },
  { title: 'Subtotal', key: 'totalPorveedor', align: 'end', width: '140px', sortable: false },
  { title: 'Acción', key: "action", align: 'center', width: '80px', sortable: false },
];

const groupBy = [{ key: 'supplier.name' }]
</script>

<template>
  <VCard variant="outlined" class="rounded-lg">
    <VDataTableVirtual
      :headers="headers"
      :items="list"
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
                {{ list.filter(i => i.supplier.name === item.value).length }} productos
              </VChip>
            </div>
          </td>
        </tr>
      </template>

      <!-- Producto -->
      <template #item.product.name="{ item }">
        <div class="d-flex flex-column py-1 overflow-hidden" style="max-inline-size: 250px;">
          <span class="text-body-2 font-weight-medium text-high-emphasis text-truncate" :title="item.product.name">
            {{ item.product.name }}
          </span>
          <span class="text-caption text-disabled text-truncate">
            ID: #{{ item.product.id }}
          </span>
        </div>
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
.premium-order-table :deep(.v-data-table-header) {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
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
</style>

