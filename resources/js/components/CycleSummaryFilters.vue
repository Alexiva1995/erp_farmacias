<script setup>
import AppFilterBase from "@/components/AppFilterBase.vue";
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  startDate: [String, null],
  endDate: [String, null],
  cycleStatus: [String, null],
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:startDate",
  "update:endDate",
  "update:cycleStatus",
  "clear",
  "sort",
]);

const sortOptions = [
  {
    title: "Ciclo ID (Reciente)",
    icon: "tabler-sort-descending",
    key: "cycle_id",
    order: "desc",
  },
  {
    title: "Ciclo ID (Antiguo)",
    icon: "tabler-sort-ascending",
    key: "cycle_id",
    order: "asc",
  },
  {
    title: "Más Recientes",
    icon: "tabler-calendar-plus",
    key: "start_date",
    order: "desc",
  },
  {
    title: "Más Antiguos",
    icon: "tabler-calendar-minus",
    key: "start_date",
    order: "asc",
  },
  {
    title: "Mayor Total Sobrante",
    icon: "tabler-trending-up",
    key: "total_surplus",
    order: "desc",
  },
  {
    title: "Mayor Total Faltante",
    icon: "tabler-trending-down",
    key: "total_shortage",
    order: "desc",
  },
  {
    title: "Mayor Total Neto",
    icon: "tabler-arrow-up",
    key: "net_total",
    order: "desc",
  },
  {
    title: "Menor Total Neto",
    icon: "tabler-arrow-down",
    key: "net_total",
    order: "asc",
  },
];

const cycleStatusOptions = [
  { title: "Activo", value: "active" },
  { title: "Cerrado", value: "closed" },
  { title: "Cancelado", value: "cancelled" },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);
const selectedSort = ref(null);
const isAdvancedFiltersVisible = ref(false);

const getStorageKey = () =>
  `cycle_summary_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

const loadSavedSort = () => {
  try {
    const saved = localStorage.getItem(getStorageKey());
    if (saved) {
      const parsedSort = JSON.parse(saved);
      const isValidSort = sortOptions.find(
        (opt) => opt.key === parsedSort.key && opt.order === parsedSort.order
      );
      if (isValidSort) {
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

const hasActiveAdvancedFilters = computed(() => {
  return !!(
    props.startDate ||
    props.endDate ||
    props.cycleStatus ||
    selectedSort.value
  );
});

const handleClear = () => {
  emit("clear");
  clearSortFilter();
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
  <AppFilterBase
    :has-advanced-filters="hasActiveAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    @clear="handleClear"
    @sort="handleSortClick"
  >
    <!-- Reemplazamos el buscador por el filtro de Estado del Ciclo -->
    <template #search>
      <VAutocomplete
        :model-value="props.cycleStatus"
        :items="cycleStatusOptions"
        :loading="props.loading"
        placeholder="Estado del Ciclo"
        item-title="title"
        item-value="value"
        clearable
        density="compact"
        hide-details
        prepend-inner-icon="tabler-list-check"
        @update:model-value="emit('update:cycleStatus', $event)"
      />
    </template>

    <template #advanced-filters>
      <VCol cols="12" sm="6" md="6">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Fecha Inicial"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <VCol cols="12" sm="6" md="6">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Fecha Final"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>

<style scoped>
.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
</style>
