<script setup>
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedLaboratory: [Number, String, null],
  discrepancyFilter: [String, null],
  selectedUser: [Number, String, null],
  laboratories: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:discrepancyFilter",
  "update:selectedUser",
  "clear",
  "sort",
]);

const sortOptions = [
  {
    title: "Nombre Producto (A-Z)",
    icon: "tabler-sort-ascending-letters",
    key: "product.name",
    order: "asc",
  },
  {
    title: "Nombre Producto (Z-A)",
    icon: "tabler-sort-descending-letters",
    key: "product.name",
    order: "desc",
  },
  {
    title: "Laboratorio (A-Z)",
    icon: "tabler-sort-ascending-letters",
    key: "laboratory.name",
    order: "asc",
  },
  {
    title: "Laboratorio (Z-A)",
    icon: "tabler-sort-descending-letters",
    key: "laboratory.name",
    order: "desc",
  },
  {
    title: "Fecha Conteo (Reciente)",
    icon: "tabler-calendar-time",
    key: "created_at",
    order: "desc",
  },
  {
    title: "Fecha Conteo (Antiguo)",
    icon: "tabler-calendar-time",
    key: "created_at",
    order: "asc",
  },
  {
    title: "Mayor Discrepancia",
    icon: "tabler-alert-triangle",
    key: "discrepancy",
    order: "desc",
  },
  {
    title: "Mayor Cantidad Contada",
    icon: "tabler-arrow-up",
    key: "counted_quantity",
    order: "desc",
  },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);
const selectedSort = ref(null);

const getStorageKey = () =>
  `inventory_cycle_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

const loadSavedSort = () => {
  try {
    const saved = localStorage.getItem(getStorageKey());
    if (saved) {
      const parsedSort = JSON.parse(saved);
      if (
        sortOptions.some(
          (opt) => opt.key === parsedSort.key && opt.order === parsedSort.order
        )
      ) {
        selectedSort.value = parsedSort;
        emit("sort", parsedSort);
      }
    }
  } catch (error) {
    console.error("Error al cargar el filtro de ordenamiento guardado:", error);
  }
};

const saveSortFilter = (sortFilter) => {
  try {
    localStorage.setItem(getStorageKey(), JSON.stringify(sortFilter));
  } catch (error) {
    console.error("Error al guardar el filtro de ordenamiento:", error);
  }
};

const handleSortClick = (option) => {
  const sortFilter = { key: option.key, order: option.order };
  selectedSort.value = sortFilter;
  saveSortFilter(sortFilter);
  emit("sort", sortFilter);
};

const clearSortFilter = () => {
  selectedSort.value = null;
  localStorage.removeItem(getStorageKey());
  emit("sort", { key: undefined, order: undefined });
};

const getSelectedSortTitle = computed(() => {
  if (!selectedSort.value) return null;
  return sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order
  )?.title;
});

const getSelectedSortIcon = computed(() => {
  if (!selectedSort.value) return null;
  return sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order
  )?.icon;
});

const isOptionSelected = (option) => {
  return (
    selectedSort.value &&
    selectedSort.value.key === option.key &&
    selectedSort.value.order === option.order
  );
};

onMounted(() => {
  if (currentUser.value?.id) {
    loadSavedSort();
  }
});

watch(
  () => currentUser.value?.id,
  (newId) => {
    if (newId) {
      loadSavedSort();
    }
  }
);
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="3">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por Producto, C. Activo..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VAutocomplete
            :model-value="props.selectedLaboratory"
            :items="props.laboratories"
            :loading="props.loading"
            label="Laboratorio"
            placeholder="Buscar un laboratorio"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:selectedLaboratory', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.discrepancyFilter"
            :items="[
              { title: 'Con Discrepancia', value: 'with_discrepancy' },
              { title: 'Sobrantes', value: 'surplus' },
              { title: 'Faltantes', value: 'shortage' },
              { title: 'Sin Discrepancia', value: 'exact' }
            ]"
            label="Filtrar por Discrepancia"
            placeholder="Todas"
            clearable
            @update:model-value="emit('update:discrepancyFilter', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VAutocomplete
            :model-value="props.selectedUser"
            :items="props.users"
            :loading="props.loading"
            label="Usuario que hizo el conteo"
            placeholder="Buscar un usuario"
            item-title="display_name"
            item-value="id"
            clearable
            @update:model-value="emit('update:selectedUser', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
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
          <VIcon :icon="getSelectedSortIcon" size="14" class="me-1" />
          {{ getSelectedSortTitle }}
        </VChip>
      </div>

      <VSpacer />
    </VCardActions>
  </VCard>
</template>
