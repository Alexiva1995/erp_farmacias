<script setup>
// Filtros para conteo cíclico de inventario
import AppFilterBase from "@/components/AppFilterBase.vue";
import { useAuthStore } from "@/stores/auth";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { computed, ref, onMounted, watch } from "vue";

const props = defineProps({
  searchQuery:        String,
  selectedLaboratory: [Number, String, null],
  discrepancyFilter:  [String, null],
  selectedUser:       [Number, String, null],
  laboratories:       { type: Array,   default: () => [] },
  users:              { type: Array,   default: () => [] },
  loading:            { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:discrepancyFilter",
  "update:selectedUser",
  "clear",
  "sort",
]);

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => brandingStore.settings?.business_type === 'restaurant');

const sortOptions = computed(() => [
  { title: "Producto (A-Z)",         icon: "tabler-sort-ascending-letters",  key: "product.name",    order: "asc"  },
  { title: "Producto (Z-A)",         icon: "tabler-sort-descending-letters", key: "product.name",    order: "desc" },
  { title: isRestaurant.value ? "Marca (A-Z)" : "Laboratorio (A-Z)",      icon: "tabler-sort-ascending-letters",  key: "laboratory.name", order: "asc"  },
  { title: isRestaurant.value ? "Marca (Z-A)" : "Laboratorio (Z-A)",      icon: "tabler-sort-descending-letters", key: "laboratory.name", order: "desc" },
  { title: "Fecha (Reciente)",       icon: "tabler-calendar-time",           key: "created_at",      order: "desc" },
  { title: "Fecha (Antiguo)",        icon: "tabler-calendar-time",           key: "created_at",      order: "asc"  },
  { title: "Mayor Discrepancia",     icon: "tabler-alert-triangle",          key: "discrepancy",     order: "desc" },
]);

const discrepancyOptions = [
  { title: 'Con Discrepancia', value: 'with_discrepancy' },
  { title: 'Sobrantes',        value: 'surplus' },
  { title: 'Faltantes',        value: 'shortage' },
  { title: 'Sin Discrepancia', value: 'exact' }
];

const hasAdvancedFilters = computed(() =>
  !!(props.selectedLaboratory || props.discrepancyFilter || props.selectedUser)
);

// Petición y persistencia de ordenamiento
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);
const selectedSort = ref(null);

const getStorageKey = () => `inventory_cycle_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

const loadSavedSort = () => {
  try {
    const saved = localStorage.getItem(getStorageKey());
    if (saved) {
      const parsedSort = JSON.parse(saved);
      if (sortOptions.value.some(opt => opt.key === parsedSort.key && opt.order === parsedSort.order)) {
        selectedSort.value = parsedSort;
        emit("sort", parsedSort);
      }
    }
  } catch (error) {
    console.error("Error loading saved sort:", error);
  }
};

const saveSortFilter = (sortFilter) => {
  try {
    localStorage.setItem(getStorageKey(), JSON.stringify(sortFilter));
  } catch (error) {
    console.error("Error saving sort:", error);
  }
};

const handleSortClick = (sortFilter) => {
  selectedSort.value = sortFilter;
  if(sortFilter.key !== undefined){
      saveSortFilter(sortFilter);
  } else {
      localStorage.removeItem(getStorageKey());
  }
  emit("sort", sortFilter);
};

onMounted(() => {
  if (currentUser.value?.id) loadSavedSort();
});

watch(() => currentUser.value?.id, (newId) => {
  if (newId) loadSavedSort();
});
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions.value"
    :search-placeholder="isRestaurant ? 'Buscar Producto, ID...' : 'Buscar Producto, C. Activo, ID...'"
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @sort="handleSortClick"
  >
    <template #advanced-filters>
      <!-- Laboratorio / Marca -->
      <VCol cols="12" sm="4">
        <VAutocomplete
          :model-value="props.selectedLaboratory"
          :items="props.laboratories"
          :loading="props.loading"
          :placeholder="isRestaurant ? 'Marca' : 'Laboratorio'"
          item-title="name"
          item-value="id"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-building"
          @update:model-value="emit('update:selectedLaboratory', $event)"
        />
      </VCol>

      <!-- Discrepancia -->
      <VCol cols="12" sm="4">
        <VSelect
          :model-value="props.discrepancyFilter"
          :items="discrepancyOptions"
          placeholder="Tipo de Discrepancia"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-alert-circle"
          @update:model-value="emit('update:discrepancyFilter', $event)"
        />
      </VCol>

      <!-- Usuario del Conteo -->
      <VCol cols="12" sm="4">
        <VAutocomplete
          :model-value="props.selectedUser"
          :items="props.users"
          :loading="props.loading"
          placeholder="Usuario del Conteo"
          item-title="display_name"
          item-value="id"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-user"
          @update:model-value="emit('update:selectedUser', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
