<script setup>
// Filtros para Mobiliario (Finance/Assets)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { useAuthStore } from "@/stores/auth";
import { computed, ref, onMounted, watch } from "vue";

const props = defineProps({
  searchQuery:        String,
  selectedYear:       [Number, String, null],
  depreciationFilter: [String, null],
  startDate:          [String, null],
  endDate:            [String, null],
  acquisitionYears:   { type: Array,   default: () => [] },
  loading:            { type: Boolean, default: false },
  showAddButton:      { type: Boolean, default: true },
  addButtonText:      { type: String,  default: "Añadir Mobiliario" },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedYear",
  "update:depreciationFilter",
  "update:startDate",
  "update:endDate",
  "clear",
  "add-furniture",
  "sort",
]);

const depreciationOptions = [
  { title: "Baja (0-20%)",           value: "low" },
  { title: "Media (21-50%)",         value: "medium" },
  { title: "Alta (51-80%)",          value: "high" },
  { title: "Muy Depreciado (81%+)",  value: "very_high" },
];

const sortOptions = [
  { title: "Costo Mayor",        icon: "tabler-arrow-up",            key: "cost",              order: "desc" },
  { title: "Costo Menor",        icon: "tabler-arrow-down",          key: "cost",              order: "asc"  },
  { title: "Más Reciente",       icon: "tabler-calendar-plus",       key: "acquisition_year",  order: "desc" },
  { title: "Más Antiguo",        icon: "tabler-calendar-minus",      key: "acquisition_year",  order: "asc"  },
  { title: "Mayor Depreciación", icon: "tabler-trending-down",       key: "depreciation_rate", order: "desc" },
  { title: "Menor Depreciación", icon: "tabler-trending-up",         key: "depreciation_rate", order: "asc"  },
  { title: "Valor Actual Mayor", icon: "tabler-currency-dollar",     key: "current_value",     order: "desc" },
  { title: "Valor Actual Menor", icon: "tabler-currency-dollar-off", key: "current_value",     order: "asc"  },
];

const hasAdvancedFilters = computed(() =>
  !!(props.selectedYear || props.depreciationFilter || props.startDate || props.endDate)
);

// Lógica para guardar el orden seleccionado
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);
const selectedSort = ref(null);

const getStorageKey = () => `furniture_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

const loadSavedSort = () => {
  try {
    const saved = localStorage.getItem(getStorageKey());
    if (saved) {
      const parsedSort = JSON.parse(saved);
      const isValidSort = sortOptions.find(
        (option) =>
          option.key === parsedSort.key && option.order === parsedSort.order
      );
      if (isValidSort) {
        selectedSort.value = parsedSort;
        emit("sort", parsedSort);
      }
    }
  } catch (error) {
    console.error("Error al cargar el filtro guardado:", error);
  }
};

const saveSortFilter = (sortFilter) => {
  try {
    localStorage.setItem(getStorageKey(), JSON.stringify(sortFilter));
  } catch (error) {
    console.error("Error al guardar el filtro:", error);
  }
};

const handleSortClick = (sortFilter) => {
  selectedSort.value = sortFilter;
  if(sortFilter.key !== undefined){
      saveSortFilter(sortFilter);
  } else {
      try {
        localStorage.removeItem(getStorageKey());
      } catch (error) {
        console.error("Error al limpiar el filtro:", error);
      }
  }
  emit("sort", sortFilter);
};

onMounted(() => loadSavedSort());

watch(() => currentUser.value?.id, () => {
  if (currentUser.value?.id) loadSavedSort();
}, { immediate: true });
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    :show-add="props.showAddButton"
    :add-button-text="props.addButtonText"
    search-placeholder="Buscar por nombre del mobiliario..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @add="emit('add-furniture')"
    @sort="handleSortClick"
  >
    <template #advanced-filters>
      <!-- Año de Adquisición -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.selectedYear"
          :items="props.acquisitionYears"
          :loading="props.loading"
          placeholder="Año de Adquisición"
          item-title="title"
          item-value="value"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-calendar"
          @update:model-value="emit('update:selectedYear', $event)"
        />
      </VCol>

      <!-- Nivel de Depreciación -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.depreciationFilter"
          :items="depreciationOptions"
          placeholder="Nivel de Depreciación"
          item-title="title"
          item-value="value"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-trending-down"
          @update:model-value="emit('update:depreciationFilter', $event)"
        />
      </VCol>

      <!-- Adquirido Desde -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Adquirido Desde"
          density="compact"
          hide-details
          clearable
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <!-- Adquirido Hasta -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Adquirido Hasta"
          density="compact"
          hide-details
          clearable
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
