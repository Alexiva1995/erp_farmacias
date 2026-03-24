<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedFrequency: [String, null],
  isStrictSearch: Boolean,
  frequencies: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedFrequency",
  "update:isStrictSearch",
  "clear",
  "add-activity",
  "sort",
]);

const sortOptions = [
  {
    title: "Nombre A-Z",
    icon: "tabler-sort-ascending-letters",
    key: "activity",
    order: "asc",
  },
  {
    title: "Nombre Z-A",
    icon: "tabler-sort-descending-letters",
    key: "activity",
    order: "desc",
  },
  {
    title: "Frecuencia A-Z",
    icon: "tabler-calendar",
    key: "frequency",
    order: "asc",
  },
  {
    title: "Más Recientes",
    icon: "tabler-clock",
    key: "created_at",
    order: "desc",
  },
];

const selectedSort = ref(null);

const handleSortClick = (option) => {
  const sortFilter = { key: option.key, order: option.order };
  selectedSort.value = sortFilter;
  emit("sort", sortFilter);
};

const clearSortFilter = () => {
  selectedSort.value = null;
  emit("sort", { key: undefined, order: undefined });
};

const getSelectedSortIcon = () => {
  if (!selectedSort.value) return "tabler-sort-ascending";
  const option = sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order,
  );
  return option ? option.icon : "tabler-sort-ascending";
};

const isOptionSelected = (option) => {
  return (
    selectedSort.value &&
    selectedSort.value.key === option.key &&
    selectedSort.value.order === option.order
  );
};

const isAdvancedFiltersVisible = ref(false);

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const hasActiveAdvancedFilters = computed(() => {
  return props.selectedFrequency || selectedSort.value;
});

const handleClear = () => {
  emit("clear");
  clearSortFilter();
  isAdvancedFiltersVisible.value = false;
};
</script>

<template>
  <VCard class="mb-6 border shadow-sm">
    <VCardText class="pa-3">
      <!-- Fila Principal: Búsqueda y Acciones Rápidas -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="4" lg="4">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar actividad o descripción..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            persistent-placeholder
            hide-details
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <!-- Búsqueda Estricta -->
        <VCol cols="auto" class="d-none d-sm-flex">
          <VCheckbox
            :model-value="props.isStrictSearch"
            label="Estricta"
            color="primary"
            density="compact"
            hide-details
            @update:model-value="emit('update:isStrictSearch', $event)"
          />
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-1">
          <!-- Toggle Filtros -->
          <VBtn
            icon
            variant="tonal"
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            size="38"
            @click="toggleAdvancedFilters"
          >
            <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
            <VBadge
              v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
              color="error"
              dot
              offset-x="3"
              offset-y="-3"
            />
          </VBtn>

          <!-- Ordenar Por (Solo Icono) -->
          <VMenu>
            <template #activator="{ props: menuProps }">
              <VBtn 
                v-bind="menuProps" 
                icon
                variant="tonal" 
                color="secondary"
                size="38"
              >
                <VIcon :icon="getSelectedSortIcon()" />
                <VTooltip activator="parent" location="top">Ordenar Por</VTooltip>
              </VBtn>
            </template>
            <VList density="compact">
              <VListItem
                v-for="(option, index) in sortOptions"
                :key="index"
                :active="isOptionSelected(option)"
                color="primary"
                @click="handleSortClick(option)"
              >
                <template #prepend>
                  <VIcon :icon="option.icon" size="20" />
                </template>
                <VListItemTitle>{{ option.title }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <!-- Añadir Actividad -->
          <VBtn
            icon
            color="primary"
            variant="flat"
            size="38"
            @click="emit('add-activity')"
          >
            <VIcon icon="tabler-plus" />
            <VTooltip activator="parent" location="top">Añadir Actividad</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2" />

          <!-- Limpiar Filtros (Solo Icono) -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            @click="handleClear"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </div>
      </VRow>

      <!-- Panel de Filtros Colapsable -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-3 border-opacity-10" />
          
          <VRow dense>
            <VCol cols="12" sm="6" md="4">
              <VSelect
                :model-value="props.selectedFrequency"
                :items="props.frequencies"
                :loading="props.loading"
                placeholder="Filtrar por Frecuencia"
                density="compact"
                hide-details
                clearable
                prepend-inner-icon="tabler-calendar"
                @update:model-value="emit('update:selectedFrequency', $event)"
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

:deep(.v-field__input) {
  font-size: 0.8125rem !important;
}
</style>
