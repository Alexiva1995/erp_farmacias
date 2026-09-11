<script setup>
const props = defineProps({
  item: { type: Object, required: true },
  enableBrandGroups: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
});

const emit = defineEmits(["view", "edit", "delete"]);

const formatUnits = (units) => {
  const num = Number(units || 0);
  return num % 1 === 0 ? num.toString() : num.toFixed(2).replace(".", ",");
};
</script>

<template>
  <VCard variant="flat" class="laboratory-mobile-card border mb-1 rounded-lg">
    <div class="pa-2 pa-sm-3">
      <div class="d-flex justify-space-between align-center mb-1">
        <div class="d-flex align-center gap-2">
          <span class="text-xs font-weight-black text-primary">{{ item.id }}</span>
          <span class="mx-1 text-disabled font-weight-regular">|</span>
          <span class="text-sm font-weight-black text-high-emphasis text-uppercase">{{ item.name }}</span>
        </div>
        <VChip
          v-if="item.group && enableBrandGroups"
          color="primary"
          size="x-small"
          variant="tonal"
          class="font-weight-bold uppercase"
        >
          {{ item.group.name }}
        </VChip>
      </div>

      <!-- Caja compacta de Referencias y Unidades -->
      <div class="d-flex align-center justify-space-between bg-var-theme-background px-2 py-1 mt-2 rounded border-dashed-thin">
        <div class="d-flex align-center gap-2">
          <span class="text-super-xs text-disabled text-uppercase font-weight-bold letter-spacing-1">Productos:</span>
          <VChip
            :color="item.products_count > 0 ? 'primary' : 'secondary'"
            size="x-small"
            variant="tonal"
            label
            class="font-weight-black"
          >
            {{ item.products_count }} {{ item.products_count === 1 ? 'REF' : 'REFS' }}
          </VChip>
        </div>
        <div class="d-flex align-center gap-2">
          <span class="text-super-xs text-disabled text-uppercase font-weight-bold letter-spacing-1">Unidades:</span>
          <VChip
            :color="item.units_count > 0 ? 'success' : 'secondary'"
            size="x-small"
            variant="tonal"
            label
            class="font-weight-black"
          >
            {{ formatUnits(item.units_count) }} UNDS
          </VChip>
        </div>
      </div>

      <VDivider class="my-2 border-opacity-10" />

      <div class="d-flex gap-2">
        <VBtn
          color="info"
          variant="text"
          class="flex-grow-1 rounded-0"
          height="36"
          prepend-icon="tabler-eye"
          @click="emit('view', item)"
        >
          Ver
        </VBtn>
        <VDivider vertical class="border-opacity-10" />
        <VBtn
          color="warning"
          variant="text"
          class="flex-grow-1 rounded-0"
          height="36"
          prepend-icon="tabler-edit"
          @click="emit('edit', item)"
        >
          Editar
        </VBtn>
        <template v-if="props.canDelete">
          <VDivider vertical class="border-opacity-10" />
          <VBtn
            color="error"
            variant="text"
            class="flex-grow-1 rounded-0"
            height="36"
            prepend-icon="tabler-trash"
            @click="emit('delete', item.id)"
          >
            Eliminar
          </VBtn>
        </template>
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.laboratory-mobile-card {
  overflow: hidden;
  border-radius: 8px !important;
  background: rgb(var(--v-theme-surface));
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.15);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs {
  font-size: 0.75rem !important;
}

.gap-2 {
  gap: 8px !important;
}
</style>
