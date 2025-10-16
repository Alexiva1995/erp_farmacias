<script s <script setup>
import { ref } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedStatus: [String, null],
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedStatus",
  "clear",
  "sort",
]);

const statusOptions = [
  { title: "Pendiente", value: "Pendiente" },
  { title: "Completada", value: "Completada" },
  { title: "Cancelada", value: "Cancelada" },
];

const sortOptions = [
  {
    title: "Actividad A-Z",
    icon: "tabler-sort-ascending-letters",
    key: "activity_name",
    order: "asc",
  },
  {
    title: "Actividad Z-A",
    icon: "tabler-sort-descending-letters",
    key: "activity_name",
    order: "desc",
  },
  {
    title: "Más Recientes",
    icon: "tabler-clock",
    key: "assigned_date",
    order: "desc",
  },
  {
    title: "Más Antiguas",
    icon: "tabler-clock",
    key: "assigned_date",
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
      opt.order === selectedSort.value.order
  );
  return option ? option.title : null;
};

const getSelectedSortIcon = () => {
  if (!selectedSort.value) return null;
  const option = sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order
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

const handleClear = () => {
  emit("clear");
  clearSortFilter();
};
</script>

<template>
  <VCard title="Mis Actividades de Limpieza" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="6">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar actividad..."
            prepend-inner-icon="tabler-search"
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="6">
          <VSelect
            :model-value="props.selectedStatus"
            :items="statusOptions"
            :loading="props.loading"
            label="Estado"
            placeholder="Filtrar por estado"
            prepend-inner-icon="tabler-checkbox"
            clearable
            @update:model-value="emit('update:selectedStatus', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn
        color="secondary"
        variant="outlined"
        prepend-icon="tabler-filter-off"
        @click="handleClear"
      >
        Limpiar Filtros
      </VBtn>

      <div class="d-flex align-center gap-2">
        <VMenu>
          <template #activator="{ props: menuProps }">
            <VBtn v-bind="menuProps" variant="tonal">
              Ordenar Por
              <VIcon end icon="tabler-chevron-down" />
            </VBtn>
          </template>
          <VList>
            <VListItem
              v-for="(option, index) in sortOptions"
              :key="index"
              :class="{ 'bg-primary-lighten-5': isOptionSelected(option) }"
              @click="handleSortClick(option)"
            >
              <template #prepend>
                <VIcon :icon="option.icon" size="20" class="me-2" />
              </template>
              <VListItemTitle>{{ option.title }}</VListItemTitle>
              <template #append>
                <VIcon
                  v-if="isOptionSelected(option)"
                  icon="tabler-check"
                  size="16"
                  color="primary"
                />
              </template>
            </VListItem>
          </VList>
        </VMenu>

        <VChip
          v-if="selectedSort"
          color="primary"
          variant="tonal"
          size="small"
          closable
          @click:close="clearSortFilter"
        >
          <VIcon :icon="getSelectedSortIcon()" size="14" class="me-1" />
          {{ getSelectedSortTitle() }}
        </VChip>
      </div>

      <VSpacer />
    </VCardActions>
  </VCard>
</template>
