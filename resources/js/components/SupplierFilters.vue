<script setup>
const props = defineProps({
  searchQuery: String,
});

const emit = defineEmits([
  "update:searchQuery",
  "clear",
  "sort",
  "add-supplier",
]);

import { ref } from "vue";

const isFiltersVisible = ref(false);
const localSearchQuery = ref(props.searchQuery);

const sortOptions = [
  {
    title: "Deuda mayor",
    icon: "tabler-arrow-up",
    key: "debt",
    order: "desc",
  },
  {
    title: "Deuda menor",
    icon: "tabler-arrow-down",
    key: "debt",
    order: "asc",
  },
  {
    title: "Más Calificación",
    icon: "tabler-plus",
    key: "latestScore.score",
    order: "desc",
  },
  {
    title: "Menos Calificación",
    icon: "tabler-minus",
    key: "latestScore.score",
    order: "asc",
  },
];

const handleSortClick = (option) => {
  emit("sort", { key: option.key, order: option.order });
};
</script>

<template>
  <div class="supplier-filters-container">
    <VCard class="mb-4 shadow-sm border-0">
      <VCardText class="pa-4">
        <div class="d-flex align-center flex-wrap gap-4">
          <!-- Buscador Principal -->
          <div class="flex-grow-1" style="min-inline-size: 260px;">
            <AppTextField
              v-model="localSearchQuery"
              placeholder="Buscar por ID, Nombre de Proveedor..."
              prepend-inner-icon="tabler-search"
              clearable
              hide-details
              density="comfortable"
              @update:model-value="emit('update:searchQuery', $event)"
            />
          </div>

          <!-- Acciones de Filtro -->
          <div class="d-flex align-center gap-2">
            <VBtn
              :color="isFiltersVisible ? 'primary' : 'secondary'"
              variant="tonal"
              @click="isFiltersVisible = !isFiltersVisible"
              prepend-icon="tabler-filter"
            >
              Filtros
              <VIcon end :icon="isFiltersVisible ? 'tabler-chevron-up' : 'tabler-chevron-down'" />
            </VBtn>

            <VBtn
              color="primary"
              variant="flat"
              prepend-icon="tabler-plus"
              @click="emit('add-supplier')"
            >
              <span class="d-none d-sm-inline">Añadir Proveedor</span>
              <VIcon icon="tabler-plus" class="d-sm-none" />
            </VBtn>
          </div>
        </div>

        <!-- Sección Colapsable -->
        <VExpandTransition>
          <div v-show="isFiltersVisible">
            <VDivider class="my-4" />
            <div class="d-flex flex-wrap gap-4 align-center">
              <VMenu>
                <template #activator="{ props: menuProps }">
                  <VBtn v-bind="menuProps" variant="outlined" color="secondary" prepend-icon="tabler-sort-ascending">
                    Ordenar Por
                    <VIcon end icon="tabler-chevron-down" size="16" />
                  </VBtn>
                </template>
                <VList density="compact" min-width="180">
                  <VListItem
                    v-for="(option, index) in sortOptions"
                    :key="index"
                    @click="handleSortClick(option)"
                  >
                    <template #prepend>
                      <VIcon :icon="option.icon" size="18" class="me-2" :color="option.order === 'desc' ? 'primary' : 'secondary'" />
                    </template>
                    <VListItemTitle class="text-body-2">{{ option.title }}</VListItemTitle>
                  </VListItem>
                </VList>
              </VMenu>

              <VBtn
                v-if="props.searchQuery"
                color="error"
                variant="text"
                size="small"
                prepend-icon="tabler-filter-off"
                @click="emit('clear')"
              >
                Limpiar Filtros
              </VBtn>

              <VSpacer />

              <div class="text-caption text-disabled d-none d-md-block">
                <VIcon icon="tabler-info-circle" size="14" class="me-1" />
                Usa el buscador para filtrar rápidamente por nombre o RIF.
              </div>
            </div>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <!-- Indicador de búsqueda activa (Mobile) -->
    <div v-if="props.searchQuery && !isFiltersVisible" class="d-flex gap-2 mb-4 px-2 overflow-x-auto">
      <VChip
        closable
        size="small"
        color="primary"
        variant="tonal"
        @click:close="emit('clear')"
      >
        Búsqueda: {{ props.searchQuery }}
      </VChip>
    </div>
  </div>
</template>

<style scoped>
.supplier-filters-container :deep(.v-card) {
  border-radius: 12px;
}

.shadow-sm {
  box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.05) !important;
}
</style>
