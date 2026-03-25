<script setup>
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

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const hasActiveAdvancedFilters = computed(() => {
  return (
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
  <VCard class="mb-6 rounded-lg">
    <VCardText class="pa-3">
      <!-- Fila Principal -->
      <VRow align="center" no-gutters class="gap-2">
        <VCol cols="12" md="4">
          <h3 class="text-lg font-weight-black text-high-emphasis mb-0">Historial de Ciclos</h3>
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
                <VIcon :icon="selectedSort ? getSelectedSortIcon : 'tabler-sort-ascending'" />
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

          <VDivider vertical class="mx-1 my-2" />

          <!-- Limpiar Filtros -->
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

            <VCol cols="12" sm="6" md="4">
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

            <VCol cols="12" sm="12" md="4">
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
</style>
