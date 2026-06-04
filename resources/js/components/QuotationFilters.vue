<script setup>
// Filtros de Cotizaciones
import AppFilterBase from "@/components/AppFilterBase.vue";
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  searchQuery:        String,
  selectedLaboratory: [Number, String, null],
  selectedOrigin:     [Number, String, null],
  laboratories:       { type: Array,   default: () => [] },
  origins:            { type: Array,   default: () => [] },
  stockStatusFilter:  [Boolean, null],
  isStrictSearch:     [Boolean, false],
  isRestaurant:       { type: Boolean, default: false },
  selectedCategory:   [Number, String, null],
  categories:         { type: Array,   default: () => [] },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "update:stockStatusFilter",
  "update:isStrictSearch",
  "update:selectedCategory",
  "clear",
  "sort",
  "clear-sort",
]);

const stockOptions = [
  { title: "Con Stock", value: true  },
  { title: "Sin Stock", value: false },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);
const selectedSort = ref(null);

const sortOptions = [
  { title: "Precio mayor",          icon: "tabler-arrow-up",      key: "sale_price",      order: "desc" },
  { title: "Precio Menor",          icon: "tabler-arrow-down",    key: "sale_price",      order: "asc"  },
  { title: "Más Unidades",          icon: "tabler-plus",          key: "valid_stock",     order: "desc" },
  { title: "Menos Unidades",        icon: "tabler-minus",         key: "valid_stock",     order: "asc"  },
  { title: "Más Vendidos",          icon: "tabler-trending-up",   key: "sales_average",   order: "desc" },
  { title: "Menos Vendidos",        icon: "tabler-trending-down", key: "sales_average",   order: "asc"  },
  { title: "Fecha pronto a Vencer", icon: "tabler-calendar-time", key: "next_expiration", order: "asc"  },
];

const getStorageKey = () => `product_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

const loadSavedSort = () => {
  try {
    const saved = localStorage.getItem(getStorageKey());
    if (saved) {
      const parsedSort = JSON.parse(saved);
      const isValidSort = sortOptions.find(opt => opt.key === parsedSort.key && opt.order === parsedSort.order);
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
  if(sortFilter.key !== undefined) saveSortFilter(sortFilter);
  else { try { localStorage.removeItem(getStorageKey()); } catch(e){} }
  emit("sort", sortFilter);
};

const handleClearSort = () => {
  selectedSort.value = null;
  try { localStorage.removeItem(getStorageKey()); } catch(e){}
  emit("clear-sort");
};

onMounted(() => loadSavedSort());
watch(() => currentUser.value?.id, (newVal) => { if(newVal) loadSavedSort(); }, { immediate: true });

const hasAdvancedFilters = computed(() => {
  return !!(
    props.selectedLaboratory ||
    props.selectedOrigin ||
    props.stockStatusFilter !== null
  );
});

const handleClearAndSort = () => {
  emit("clear");
  handleClearSort();
};
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    search-placeholder="Producto, Cód. Barra, C. Activo..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="handleClearAndSort"
    @sort="handleSortClick"
  >
    <template #search-extra>
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
    </template>

    <template #advanced-filters>
      <!-- Laboratorio -->
      <VCol v-if="!props.isRestaurant" cols="12" sm="4">
        <VAutocomplete
          :model-value="props.selectedLaboratory"
          :items="props.laboratories"
          placeholder="Laboratorio"
          item-title="name"
          item-value="id"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-flask"
          @update:model-value="emit('update:selectedLaboratory', $event)"
        />
      </VCol>

      <!-- Origen -->
      <VCol v-if="!props.isRestaurant" cols="12" sm="4">
        <VAutocomplete
          :model-value="props.selectedOrigin"
          :items="props.origins"
          placeholder="Origen"
          item-title="name"
          item-value="id"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-world"
          @update:model-value="emit('update:selectedOrigin', $event)"
        />
      </VCol>

      <!-- Categoría de Platos si es Restaurante -->
      <VCol v-if="props.isRestaurant" cols="12" sm="4">
        <VSelect
          :model-value="props.selectedCategory"
          :items="props.categories"
          placeholder="Categoría"
          item-title="name"
          item-value="id"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-category"
          @update:model-value="emit('update:selectedCategory', $event)"
        />
      </VCol>

      <!-- Estado Stock -->
      <VCol cols="12" sm="4">
        <VSelect
          :model-value="props.stockStatusFilter"
          placeholder="Estado Stock"
          :items="stockOptions"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-package"
          @update:model-value="emit('update:stockStatusFilter', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
