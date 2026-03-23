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
    <VCard class="mb-4 border-0 shadow-sm overflow-hidden">
      <VCardText class="pa-3">
        <!-- Barra de Búsqueda Principal (Siempre Visible) -->
        <VRow align="center" no-gutters class="gap-2">
          <!-- Buscador Principal -->
          <VCol cols="12" md="6" lg="5">
            <AppTextField
              v-model="localSearchQuery"
              placeholder="Buscar por ID, Nombre de Proveedor..."
              prepend-inner-icon="tabler-search"
              clearable
              hide-details
              density="compact"
              class="premium-input-compact"
              @update:model-value="emit('update:searchQuery', $event)"
            />
          </VCol>

          <VSpacer />

          <div class="d-flex align-center gap-1">
            <!-- Toggle Filtros -->
            <VBtn
              icon
              variant="tonal"
              :color="isFiltersVisible ? 'primary' : 'secondary'"
              size="38"
              @click="isFiltersVisible = !isFiltersVisible"
            >
              <VIcon :icon="isFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
              <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
            </VBtn>

            <!-- Menú Ordenación -->
            <VMenu>
              <template #activator="{ props: menuProps }">
                <VBtn
                  v-bind="menuProps"
                  icon
                  variant="tonal"
                  color="secondary"
                  size="38"
                >
                  <VIcon icon="tabler-sort-ascending" size="20" />
                  <VTooltip activator="parent" location="top">Ordenar Por</VTooltip>
                </VBtn>
              </template>
              <VList density="compact" min-width="180" class="rounded-lg">
                <VListItem
                  v-for="(option, index) in sortOptions"
                  :key="index"
                  @click="handleSortClick(option)"
                >
                  <template #prepend>
                    <VIcon :icon="option.icon" size="18" class="me-2" :color="option.order === 'desc' ? 'primary' : 'secondary'" />
                  </template>
                  <VListItemTitle class="text-caption font-weight-bold uppercase">{{ option.title }}</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>

            <!-- Añadir Proveedor -->
            <VBtn
              icon
              color="primary"
              size="38"
              @click="emit('add-supplier')"
            >
              <VIcon icon="tabler-plus" size="20" />
              <VTooltip activator="parent" location="top">Añadir Proveedor</VTooltip>
            </VBtn>

            <VDivider vertical class="mx-1 my-2 border-opacity-10" />

            <!-- Limpiar Filtros (Siempre Visible) -->
            <VBtn
              icon
              variant="text"
              color="secondary"
              size="38"
              @click="emit('clear')"
            >
              <VIcon icon="tabler-eraser" size="20" />
              <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
            </VBtn>
          </div>
        </VRow>

        <!-- Panel de Filtros Avanzado (Información adicional si es necesario) -->
        <VExpandTransition>
          <div v-show="isFiltersVisible">
            <VDivider class="my-3 border-opacity-10" />
            <div class="d-flex align-center">
              <VIcon icon="tabler-info-circle" size="16" color="secondary" class="me-2" />
              <span class="text-caption text-disabled font-weight-medium">
                Usa el buscador para filtrar rápidamente por nombre o RIF. Los cambios se reflejan en tiempo real.
              </span>
            </div>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <!-- Indicador de búsqueda activa (Móvil - Chips opcionales) -->
    <div v-if="props.searchQuery && !isFiltersVisible" class="d-flex gap-2 mb-4 px-2 overflow-x-auto">
      <VChip
        closable
        size="small"
        color="primary"
        variant="tonal"
        class="rounded-lg"
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
