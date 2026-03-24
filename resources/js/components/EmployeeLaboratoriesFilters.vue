<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedLaboratory: [Number, null],
  laboratories: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "clear",
  "add-assignment",
  "sort",
]);

const sortOptions = [
  {
    title: "Empleado A-Z",
    icon: "tabler-sort-ascending-letters",
    key: "employee_name",
    order: "asc",
  },
  {
    title: "Empleado Z-A",
    icon: "tabler-sort-descending-letters",
    key: "employee_name",
    order: "desc",
  },
  {
    title: "Más Laboratorios",
    icon: "tabler-sort-descending",
    key: "laboratories_count",
    order: "desc",
  },
  {
    title: "Menos Laboratorios",
    icon: "tabler-sort-ascending",
    key: "laboratories_count",
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
const isAdvancedFiltersVisible = ref(false);

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
      opt.order === selectedSort.value.order
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

const handleClear = () => {
  emit("clear");
  clearSortFilter();
};

const hasActiveAdvancedFilters = computed(() => {
  return props.selectedLaboratory;
});

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};
</script>

<template>
  <VCard class="mb-6 border shadow-sm overflow-hidden">
    <VCardText class="pa-3">
      <!-- Fila Principal: Búsqueda y Acciones Rápidas -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="5" lg="4">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por nombre de empleado..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            hide-details
            class="premium-input"
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-1">
          <!-- Toggle Filtros Avanzados -->
          <VBtn
            icon
            variant="tonal"
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            size="38"
            @click="toggleAdvancedFilters"
          >
            <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
            <VBadge
              v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
              color="error"
              dot
              offset-x="2"
              offset-y="-2"
            />
          </VBtn>

          <!-- Ordenar Por -->
          <VMenu>
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon
                variant="tonal"
                color="secondary"
                size="38"
              >
                <VIcon :icon="getSelectedSortIcon()" size="20" />
                <VTooltip activator="parent" location="top">Ordenar Por</VTooltip>
              </VBtn>
            </template>
            <VList density="compact" class="rounded-lg py-1 border shadow-lg">
              <VListItem
                v-for="(option, index) in sortOptions"
                :key="index"
                :active="isOptionSelected(option)"
                color="primary"
                @click="handleSortClick(option)"
              >
                <template #prepend>
                  <VIcon :icon="option.icon" size="20" class="me-3" />
                </template>
                <VListItemTitle class="text-xs font-weight-bold">{{ option.title }}</VListItemTitle>
              </VListItem>
              <VDivider v-if="selectedSort" class="my-1 opacity-10" />
              <VListItem v-if="selectedSort" color="error" @click="clearSortFilter">
                <template #prepend>
                  <VIcon icon="tabler-sort-ascending" size="20" class="me-3" />
                </template>
                <VListItemTitle class="text-xs font-weight-bold text-error">Limpiar Orden</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <VDivider vertical class="mx-1 my-2 border-opacity-10" />

          <!-- Agregar Asignación -->
          <VBtn
            icon
            color="primary"
            variant="flat"
            size="38"
            @click="emit('add-assignment')"
          >
            <VIcon icon="tabler-plus" size="20" />
            <VTooltip activator="parent" location="top">Asignar Laboratorio</VTooltip>
          </VBtn>

          <!-- Limpiar Filtros -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            @click="handleClear"
          >
            <VIcon icon="tabler-eraser" size="20" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </div>
      </VRow>

      <!-- Panel de Filtros Avanzados Colapsable -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-3 border-opacity-10" />

          <VRow dense>
            <VCol cols="12" sm="6" md="4">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Laboratorio</span>
              <VAutocomplete
                :model-value="props.selectedLaboratory"
                :items="props.laboratories"
                :loading="props.loading"
                placeholder="Seleccionar laboratorio"
                clearable
                density="compact"
                hide-details
                class="premium-input-compact"
                prepend-inner-icon="tabler-flask"
                @update:model-value="emit('update:selectedLaboratory', $event)"
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.premium-input :deep(.v-field__outline) {
  --v-field-border-opacity: 0.1;
}

.premium-input-compact :deep(.v-field__input) {
  font-size: 0.8125rem !important;
  min-block-size: 38px !important;
  padding-block: 0 !important;
}

.premium-input-compact :deep(.v-field__outline) {
  --v-field-border-opacity: 0.1;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
</style>
