<script setup>
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedLaboratory: [Number, String, null],
  selectedOrigin: [Number, String, null],
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  stockStatusFilter: [Boolean, null],
  isStrictSearch: [Boolean, false],
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "update:stockStatusFilter",
  "update:isStrictSearch",
  "clear",
  "sort",
  "clear-sort",
]);

const stockOptions = [
  { title: "Con Stock", value: true },
  { title: "Sin Stock", value: false },
];

const selectedSort = ref(null);
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const sortOptions = [
  {
    title: "Precio mayor",
    icon: "tabler-arrow-up",
    key: "sale_price",
    order: "desc",
  },
  {
    title: "Precio Menor",
    icon: "tabler-arrow-down",
    key: "sale_price",
    order: "asc",
  },
  {
    title: "Más Unidades",
    icon: "tabler-plus",
    key: "valid_stock",
    order: "desc",
  },
  {
    title: "Menos Unidades",
    icon: "tabler-minus",
    key: "valid_stock",
    order: "asc",
  },
  {
    title: "Más Vendidos",
    icon: "tabler-plus",
    key: "sales_average",
    order: "desc",
  },
  {
    title: "Menos Vendidos",
    icon: "tabler-minus",
    key: "sales_average",
    order: "asc",
  },
  {
    title: "Fecha pronto a Vencer",
    icon: "tabler-calendar-time",
    key: "next_expiration",
    order: "asc",
  },
];

const getStorageKey = () =>
  `product_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

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

const handleSortClick = (option) => {
  const sortFilter = { key: option.key, order: option.order };
  selectedSort.value = sortFilter;
  saveSortFilter(sortFilter);
  emit("sort", sortFilter);
};

const clearSortFilter = () => {
  selectedSort.value = null;
  try {
    localStorage.removeItem(getStorageKey());
  } catch (error) {
    console.error("Error al limpiar el filtro:", error);
  }
  emit("clear-sort");
};

const saveSortFilter = (sortFilter) => {
  try {
    localStorage.setItem(getStorageKey(), JSON.stringify(sortFilter));
  } catch (error) {
    console.error("Error al guardar el filtro:", error);
  }
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

const getSelectedSortTitle = () => {
  if (!selectedSort.value) return null;
  const option = sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order
  );
  return option ? option.title : null;
};

const isOptionSelected = (option) => {
  return (
    selectedSort.value &&
    selectedSort.value.key === option.key &&
    selectedSort.value.order === option.order
  );
};

onMounted(() => {
  loadSavedSort();
});

watch(
  () => currentUser.value?.id,
  () => {
    if (currentUser.value?.id) {
      loadSavedSort();
    }
  },
  { immediate: true }
);

const isAdvancedFiltersVisible = ref(false);

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const hasActiveAdvancedFilters = computed(() => {
  return (
    props.selectedLaboratory ||
    props.selectedOrigin ||
    props.stockStatusFilter !== null ||
    props.isStrictSearch ||
    selectedSort.value
  );
});

const handleClear = () => {
  emit("clear");
  clearSortFilter();
};
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm elevation-1">
    <VCardText class="pa-3">
      <!-- Fila Principal: Búsqueda y Acciones Rápidas -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="4" lg="5">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por Producto, Cód. Barra, C. Activo..."
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
                <VIcon :icon="selectedSort ? getSelectedSortIcon() : 'tabler-sort-ascending'" />
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
            <VCol cols="12" sm="4">
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

            <VCol cols="12" sm="4">
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
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>
