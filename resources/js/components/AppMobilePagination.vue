<script setup>
/**
 * Componente de paginación reutilizable optimizado para dispositivos móviles.
 * Combina un selector de cantidad por página y la navegación de páginas.
 */
import { computed } from 'vue';

const props = defineProps({
  page: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  totalItems: { type: Number, required: true },
  itemsPerPageOptions: { type: Array, default: () => [10, 25, 50, 100] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:page", "update:itemsPerPage", "change"]);

const totalPages = computed(() => {
  const total = Math.ceil(props.totalItems / props.itemsPerPage);
  return isNaN(total) || total < 1 ? 1 : total;
});

const handlePageChange = (newPage) => {
  emit("update:page", newPage);
  emit("change", { page: newPage, itemsPerPage: props.itemsPerPage });
};

const handleItemsPerPageChange = (newItems) => {
  emit("update:itemsPerPage", newItems);
  emit("update:page", 1);
  emit("change", { page: 1, itemsPerPage: newItems });
};
</script>

<template>
  <div class="app-mobile-pagination d-flex flex-column gap-3 mt-4">
    <!-- Selector y Info -->
    <div class="d-flex align-center justify-space-between bg-surface pa-2 rounded-lg border border-dashed text-xs">
      <div class="d-flex align-center gap-2">
        <span class="text-disabled font-weight-bold text-uppercase letter-spacing-05">Filas:</span>
        <div style="inline-size: 70px;">
          <VSelect
            :model-value="props.itemsPerPage"
            :items="props.itemsPerPageOptions"
            density="compact"
            variant="plain"
            hide-details
            class="pagination-select font-weight-black"
            @update:model-value="handleItemsPerPageChange"
            :disabled="props.loading"
          />
        </div>
      </div>
      
      <div class="d-flex align-center gap-1">
        <span class="text-disabled font-weight-bold text-uppercase letter-spacing-05">Total:</span>
        <span class="font-weight-black text-primary">{{ props.totalItems.toLocaleString() }}</span>
      </div>
    </div>

    <!-- Navegación -->
    <div class="d-flex justify-center">
      <VPagination
        :model-value="props.page"
        :length="totalPages"
        :total-visible="3"
        density="compact"
        size="small"
        active-color="primary"
        variant="flat"
        @update:model-value="handlePageChange"
        :disabled="props.loading"
      />
    </div>
  </div>
</template>

<style scoped>
.app-mobile-pagination {
  inline-size: 100%;
}

.pagination-select :deep(.v-field__input) {
  block-size: 24px !important;
  min-block-size: auto !important;
  font-size: 0.85rem !important;
  padding-inline-start: 4px !important;
}

.letter-spacing-05 {
  font-size: 0.65rem !important;
  letter-spacing: 0.5px !important;
}

.border-dashed {
  border-style: dashed !important;
  border-color: rgba(var(--v-border-color), 0.2) !important;
}

/* Ajuste para modo oscuro usando la estandarización global */
.bg-surface {
  background-color: rgb(var(--v-theme-surface)) !important;
}
</style>
