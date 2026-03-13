<script setup>
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  searchQuery: { type: String, required: true },
  itemsPerPage: { type: Number, required: true },
  selectedLaboratory: [Number, String, null],
  selectedOrigin: [Number, String, null],
  stockStatusFilter: [Boolean, null],
  startDate: [String, null],
  endDate: [String, null],
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  addLotLoading: { type: Boolean, default: false },
  isStrictSearch: Boolean,
  isAdmin: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:itemsPerPage",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "update:stockStatusFilter",
  "update:startDate",
  "update:endDate",
  "update:isStrictSearch",
  "clear",
  "add-lot",
  "sort",
  "clean-zero-quantity",
]);

const stockOptions = [
  { title: "Con Stock", value: true },
  { title: "Sin Stock", value: false },
];

const sortOptions = [
  {
    title: "Más Recientes",
    icon: "tabler-calendar-plus",
    key: "created_at",
    order: "desc",
  },
  {
    title: "Más Antiguos",
    icon: "tabler-calendar-minus",
    key: "created_at",
    order: "asc",
  },
  {
    title: "Mayor Cantidad",
    icon: "tabler-arrow-up",
    key: "quantity",
    order: "desc",
  },
  {
    title: "Menor Cantidad",
    icon: "tabler-arrow-down",
    key: "quantity",
    order: "asc",
  },
  {
    title: "Pronto a Vencer",
    icon: "tabler-calendar-time",
    key: "expiration_date",
    order: "asc",
  },
  {
    title: "Producto A-Z",
    icon: "tabler-sort-ascending-letters",
    key: "product.name",
    order: "asc",
  },
  {
    title: "Producto Z-A",
    icon: "tabler-sort-descending-letters",
    key: "product.name",
    order: "desc",
  },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const selectedSort = ref(null);

const getStorageKey = () =>
  `product_lots_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

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

const isAdvancedFiltersVisible = ref(false);

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const hasActiveAdvancedFilters = computed(() => {
  return (
    props.selectedLaboratory ||
    props.selectedOrigin ||
    props.stockStatusFilter !== null ||
    props.startDate ||
    props.endDate ||
    props.isStrictSearch ||
    selectedSort.value
  );
});

const handleClear = () => {
  // clearSortFilter();
  emit("clear");
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
</script>
<template>
  <VCard class="mb-6 overflow-visible" variant="flat" border>
    <VCardText class="pa-3">
      <!-- Fila Principal: Búsqueda y Acciones Rápidas -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="4" lg="4">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Lote, Producto, Proveedor..."
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
          <!-- Añadir Lote (Solo Admin) -->
          <VBtn
            v-if="props.isAdmin"
            icon
            color="success"
            variant="tonal"
            size="38"
            :loading="props.addLotLoading"
            @click="emit('add-lot')"
          >
            <VIcon icon="tabler-plus" />
            <VTooltip activator="parent" location="top">Añadir Lote</VTooltip>
          </VBtn>

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
          </VBtn>

          <!-- Ordenar Por (Icono) -->
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

          <!-- Limpiar Cantidades Cero (Solo Admin) -->
          <VBtn
            v-if="props.isAdmin"
            icon
            color="error"
            variant="tonal"
            size="38"
            @click="emit('clean-zero-quantity')"
          >
            <VIcon icon="tabler-trash-x" />
            <VTooltip activator="parent" location="top">Limpiar Lotes en Cero</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2" />

          <!-- Limpiar Filtros UI -->
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
            <VCol cols="12" sm="6" md="2">
              <VAutocomplete
                :model-value="props.selectedLaboratory"
                :items="props.laboratories"
                :loading="props.loading"
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

            <VCol cols="12" sm="6" md="2">
              <VSelect
                :model-value="props.selectedOrigin"
                placeholder="Origen"
                :items="props.origins"
                item-title="name"
                item-value="id"
                clearable
                density="compact"
                hide-details
                prepend-inner-icon="tabler-world"
                @update:model-value="emit('update:selectedOrigin', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="2">
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

            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.startDate"
                placeholder="Vencimiento Desde"
                clearable
                density="compact"
                hide-details
                :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                prepend-inner-icon="tabler-calendar-event"
                @update:model-value="emit('update:startDate', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.endDate"
                placeholder="Vencimiento Hasta"
                clearable
                density="compact"
                hide-details
                :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                prepend-inner-icon="tabler-calendar-event"
                @update:model-value="emit('update:endDate', $event)"
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>

@media (max-width: 600px) {
  .search-bar :deep(.v-field__input) {
    font-size: 0.8rem;
  }
}
</style>
