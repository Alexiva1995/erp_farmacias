<script setup>
import { ref } from "vue";

const props = defineProps({
  selectedProducts: {
    type: Array,
    required: true,
  },
  categories: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits([
  "change-category",
  "delete-selected",
  "clear-selection",
]);

const isBulkCategoryMenuOpen = ref(false);

const handleCategoryClick = (categoryId) => {
  emit("change-category", categoryId);
  isBulkCategoryMenuOpen.value = false;
};
</script>

<template>
  <Transition name="fade-slide">
    <div v-if="selectedProducts.length > 0" class="bulk-actions-wrapper">
      <VCard class="bulk-actions-bar px-6 py-3 d-flex align-center justify-space-between rounded-pill elevation-10">
        <div class="d-flex align-center gap-3">
          <VChip color="primary" class="font-weight-black">{{ selectedProducts.length }}</VChip>
          <span class="text-subtitle-2 font-weight-black text-high-emphasis">Productos seleccionados</span>
        </div>

        <div class="d-flex align-center gap-2">
          <!-- Cambiar Categoría Masivo -->
          <VMenu v-model="isBulkCategoryMenuOpen" :close-on-content-click="false" location="top center" offset="12px">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                color="secondary"
                variant="outlined"
                class="rounded-pill font-weight-black"
                size="small"
                prepend-icon="tabler-category"
              >
                Cambiar Categoría
              </VBtn>
            </template>
            <VCard class="rounded-xl border shadow-lg pa-3" min-width="240">
              <div class="text-xs font-weight-bold text-high-emphasis mb-2">Seleccionar Categoría:</div>
              <VList density="compact">
                <VListItem
                  v-for="cat in categories"
                  :key="cat.id"
                  :title="cat.name"
                  @click="handleCategoryClick(cat.id)"
                  class="rounded-lg"
                />
              </VList>
            </VCard>
          </VMenu>

          <!-- Eliminar Masivo -->
          <VBtn
            color="error"
            class="rounded-pill font-weight-black"
            size="small"
            prepend-icon="tabler-trash"
            @click="emit('delete-selected')"
          >
            Eliminar
          </VBtn>

          <VDivider vertical class="mx-2 border-opacity-20" />

          <!-- Deseleccionar Todo -->
          <VBtn
            icon="tabler-x"
            variant="text"
            density="compact"
            color="secondary"
            @click="emit('clear-selection')"
          />
        </div>
      </VCard>
    </div>
  </Transition>
</template>

<style scoped>
.bulk-actions-wrapper {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 1000;
  width: 100%;
  max-width: 680px;
  padding: 0 16px;
}

.bulk-actions-bar {
  background: rgba(var(--v-theme-surface), 0.85) !important;
  backdrop-filter: blur(12px) saturate(190%);
  border: 1px solid rgba(var(--v-border-color), 0.24) !important;
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.3s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translate(-50%, 30px);
}
</style>
