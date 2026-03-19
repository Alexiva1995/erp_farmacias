<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedStatus: [String, null],
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedStatus",
  "clear",
  "add-assignment",
  "sort",
]);

const isFilterVisible = ref(false);

const statusOptions = [
  { title: "Pendiente", value: "Pendiente" },
  { title: "Completada", value: "Completada" },
  { title: "Cancelada", value: "Cancelada" },
];

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
    title: "Más Actividades",
    icon: "tabler-sort-descending",
    key: "activities_count",
    order: "desc",
  },
  {
    title: "Menos Actividades",
    icon: "tabler-sort-ascending",
    key: "activities_count",
    order: "asc",
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

const getSelectedSortTitle = () => {
  if (!selectedSort.value) return null;
  const option = sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order,
  );
  return option ? option.title : null;
};

const getSelectedSortIcon = () => {
  if (!selectedSort.value) return null;
  const option = sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order,
  );
  return option ? option.icon : null;
};

const isOptionSelected = (option) => {
  return (
    selectedSort.value &&
    selectedSort.value.key === option.key &&
    selectedSort.value.order === option.order
  );
};

const activeFiltersCount = computed(() => {
  let count = 0;
  if (props.searchQuery) count++;
  if (props.selectedStatus) count++;
  return count;
});

const handleClear = () => {
  emit("clear");
  clearSortFilter();
};
</script>

<template>
  <VCard class="mb-6 rounded-xl border-0 shadow-sm overflow-hidden bg-surface">
    <!-- Barra de Acciones Principal -->
    <VCardActions class="pa-4 px-6 d-flex align-center bg-surface">
      <div class="d-flex align-center gap-2">
        <VAvatar color="primary" variant="tonal" size="38" class="rounded-lg">
          <VIcon icon="tabler-filter" size="20" />
        </VAvatar>
        <div class="d-flex flex-column">
          <span class="text-sm font-weight-black uppercase leading-none mb-1">Filtros</span>
          <span class="text-super-xs text-disabled font-weight-medium">Gestionar actividades</span>
        </div>
      </div>

      <VSpacer />

      <div class="d-flex align-center gap-2">
        <!-- Toggle Filtros -->
        <VBtn
          icon
          variant="tonal"
          :color="isFilterVisible ? 'primary' : 'secondary'"
          size="38"
          @click="isFilterVisible = !isFilterVisible"
          class="rounded-lg"
        >
          <VBadge
            :model-value="activeFiltersCount > 0"
            :content="activeFiltersCount"
            color="error"
            offset-x="3"
            offset-y="3"
          >
            <VIcon :icon="isFilterVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
          </VBadge>
          <VTooltip activator="parent" location="top">{{ isFilterVisible ? 'Ocultar Filtros' : 'Mostrar Filtros' }}</VTooltip>
        </VBtn>

        <!-- Ordenar -->
        <VMenu transition="scale-transition">
          <template #activator="{ props: menuProps }">
            <VBtn
              v-bind="menuProps"
              icon
              variant="tonal"
              color="secondary"
              size="38"
              class="rounded-lg"
            >
              <VIcon :icon="getSelectedSortIcon() || 'tabler-sort-ascending'" size="20" />
              <VTooltip activator="parent" location="top">Ordenar Lista</VTooltip>
            </VBtn>
          </template>
          <VList class="rounded-lg shadow-lg border-0 pa-2" min-width="200">
            <VListItem
              v-for="(option, index) in sortOptions"
              :key="index"
              :value="option"
              class="rounded-md mb-1"
              :active="isOptionSelected(option)"
              color="primary"
              @click="handleSortClick(option)"
            >
              <template #prepend>
                <VIcon :icon="option.icon" size="18" class="me-3" />
              </template>
              <VListItemTitle class="text-xs font-weight-bold">{{ option.title }}</VListItemTitle>
            </VListItem>
            <VDivider v-if="selectedSort" class="my-2 opacity-10" />
            <VListItem
              v-if="selectedSort"
              class="rounded-md"
              color="error"
              @click="clearSortFilter"
            >
              <template #prepend>
                <VIcon icon="tabler-sort-ascending" size="18" class="me-3" />
              </template>
              <VListItemTitle class="text-xs font-weight-bold text-error">Limpiar Orden</VListItemTitle>
            </VListItem>
          </VList>
        </VMenu>

        <VDivider vertical class="mx-1 my-2" />

        <!-- Agregar Asignación -->
        <VBtn
          icon
          color="primary"
          variant="flat"
          size="38"
          class="rounded-lg"
          @click="emit('add-assignment')"
        >
          <VIcon icon="tabler-plus" size="20" />
          <VTooltip activator="parent" location="top">Asignar Actividades</VTooltip>
        </VBtn>

        <!-- Limpiar Todo -->
        <VBtn
          icon
          variant="tonal"
          color="error"
          size="38"
          class="rounded-lg"
          @click="handleClear"
          :disabled="activeFiltersCount === 0 && !selectedSort"
        >
          <VIcon icon="tabler-filter-x" size="20" />
          <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
        </VBtn>
      </div>
    </VCardActions>

    <!-- Panel de Filtros Colapsable -->
    <VExpandTransition>
      <div v-show="isFilterVisible">
        <VDivider class="opacity-10" />
        <VCardText class="pa-6 pt-4 bg-surface-variant-opacity-2">
          <VRow>
            <!-- Búsqueda -->
            <VCol cols="12" md="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Buscar Empleado</span>
              <VTextField
                :model-value="props.searchQuery"
                placeholder="Nombre o ID..."
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                clearable
                class="premium-input"
                @update:model-value="emit('update:searchQuery', $event)"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-search" size="18" color="disabled" class="me-2" />
                </template>
              </VTextField>
            </VCol>

            <!-- Estado -->
            <VCol cols="12" md="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Filtrar por Estado</span>
              <VSelect
                :model-value="props.selectedStatus"
                :items="statusOptions"
                :loading="props.loading"
                placeholder="Todos los estados"
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                clearable
                class="premium-input"
                @update:model-value="emit('update:selectedStatus', $event)"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-list-check" size="18" color="disabled" class="me-2" />
                </template>
              </VSelect>
            </VCol>
          </VRow>
        </VCardText>
      </div>
    </VExpandTransition>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.leading-none {
  line-height: 1;
}

.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

:deep(.premium-input) {
  .v-field__outline {
    --v-field-border-opacity: 0.1;
  }
}
</style>
