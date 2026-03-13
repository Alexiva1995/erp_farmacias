<script setup>
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedLaboratory: [Number, String, null],
  selectedOrigin: [Number, String, null],
  stockStatusFilter: [Boolean, null],
  startDate: [String, null],
  endDate: [String, null],
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  mode: { type: String, default: "products" },
  showAddButton: { type: Boolean, default: true },
  addButtonText: { type: String, default: "Añadir Producto" },
  isStrictSearch: Boolean,
  flat: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "update:stockStatusFilter",
  "update:startDate",
  "update:endDate",
  "update:isStrictSearch",
  "clear",
  "export",
  "add-product",
  "sort",
]);

const stockOptions = [
  { title: "Con Stock", value: true },
  { title: "Sin Stock", value: false },
];

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
    title: "Más Vendidos",
    icon: "tabler-trending-up",
    key: "most_sold",
    order: "desc",
  },
  {
    title: "Mayor Cantidad",
    icon: "tabler-arrow-up",
    key: "valid_stock",
    order: "desc",
  },
  {
    title: "Menor Cantidad",
    icon: "tabler-arrow-down",
    key: "valid_stock",
    order: "asc",
  },
  {
    title: "Pronto a Vencer",
    icon: "tabler-calendar-time",
    key: "next_expiration",
    order: "asc",
  },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const selectedSort = ref(null);

const getStorageKey = () =>
  `product_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

const loadSavedSort = () => {
  try {
    const saved = localStorage.getItem(getStorageKey());
    if (saved) {
      const parsedSort = JSON.parse(saved);
      const isValidSort = sortOptions.find(
        (option) =>
          option.key === parsedSort.key && option.order === parsedSort.order,
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

const handleClear = () => {
  emit("clear");
  clearSortFilter();
};

// Computed para título dinámico
const cardTitle = computed(() => {
  return props.mode === "inventory" ? "Filtros de Inventario" : "Filtros";
});

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
  { immediate: true },
);

watch(
  () => props.sortData,
  (newVal) => {
    if (!newVal || (newVal.key === undefined && newVal.order === undefined)) {
      selectedSort.value = null;
    }
  },
  { deep: true },
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
    props.startDate ||
    props.endDate ||
    props.isStrictSearch ||
    selectedSort.value
  );
});
</script>

<template>
  <VCard :class="{ 'mb-6': !flat, 'elevation-0': flat }">
    <VCardText class="pa-4">
      <!-- Fila Principal: Búsqueda y Botones de Acción -->
      <VRow align="center" no-gutters class="gap-3">
        <!-- Buscador -->
        <VCol cols="12" md="4" lg="5">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por ID, Producto, C. Activo..."
            prepend-inner-icon="tabler-search"
            clearable
            persistent-placeholder
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <VCol class="d-flex gap-2 flex-wrap flex-md-nowrap align-center">
          <!-- Toggle Filtros -->
          <VBtn
            variant="tonal"
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            :prepend-icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'"
            @click="toggleAdvancedFilters"
          >
            Filtros
            <VBadge
              v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
              color="error"
              dot
              offset-x="10"
              offset-y="-5"
            />
          </VBtn>

          <!-- Ordenar Por (Ahora en la principal) -->
          <VMenu>
            <template #activator="{ props: menuProps }">
              <VBtn 
                v-bind="menuProps" 
                variant="tonal" 
                color="secondary"
                :prepend-icon="selectedSort ? getSelectedSortIcon() : 'tabler-sort-ascending'"
              >
                {{ selectedSort ? getSelectedSortTitle() : 'Ordenar' }}
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

          <VSpacer class="d-none d-md-block" />

          <!-- Exportar (Menú unificado) -->
          <VMenu v-if="mode === 'products'">
            <template #activator="{ props: menuProps }">
              <VBtn
                color="success"
                variant="flat"
                prepend-icon="tabler-file-export"
                v-bind="menuProps"
              >
                Exportar
              </VBtn>
            </template>
            <VList>
              <VListItem @click="emit('export', 'xlsx')">
                <template #prepend>
                  <VIcon icon="tabler-file-type-csv" class="me-2" color="success" />
                </template>
                <VListItemTitle class="text-success">Excel</VListItemTitle>
              </VListItem>
              <VListItem @click="emit('export', 'pdf')">
                <template #prepend>
                  <VIcon icon="tabler-file-type-pdf" class="me-2" />
                </template>
                <VListItemTitle>PDF</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <!-- Añadir Producto -->
          <template v-if="mode === 'products' || mode === 'minimal'">
            <VBtn
              v-if="props.showAddButton"
              color="primary"
              variant="flat"
              prepend-icon="tabler-plus"
              @click="emit('add-product')"
            >
              {{ props.addButtonText }}
            </VBtn>
          </template>
        </VCol>
      </VRow>

      <!-- Panel de Filtros Colapsable -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-4 border-opacity-10" />
          
          <VRow>
            <!-- Primera Fila: Selectores Principales -->
            <VCol cols="12" sm="6" md="4">
              <VAutocomplete
                :model-value="props.selectedLaboratory"
                :items="props.laboratories"
                :loading="props.loading"
                label="Laboratorio"
                placeholder="Seleccionar Laboratorio"
                item-title="name"
                item-value="id"
                clearable
                density="compact"
                hide-details="auto"
                prepend-inner-icon="tabler-flask"
                @update:model-value="emit('update:selectedLaboratory', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="4">
              <VAutocomplete
                :model-value="props.selectedOrigin"
                :items="props.origins"
                :loading="props.loading"
                label="Origen"
                placeholder="Seleccionar Origen"
                item-title="name"
                item-value="id"
                clearable
                density="compact"
                hide-details="auto"
                prepend-inner-icon="tabler-world"
                @update:model-value="emit('update:selectedOrigin', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="4">
              <VSelect
                :model-value="props.stockStatusFilter"
                label="Estado Stock"
                :items="stockOptions"
                placeholder="Todos"
                clearable
                density="compact"
                hide-details="auto"
                prepend-inner-icon="tabler-package"
                @update:model-value="emit('update:stockStatusFilter', $event)"
              />
            </VCol>

            <!-- Segunda Fila: Fechas y Resto -->
            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.startDate"
                label="Desde"
                placeholder="Seleccionar Fecha"
                clearable
                density="compact"
                hide-details="auto"
                :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                prepend-inner-icon="tabler-calendar-event"
                @update:model-value="emit('update:startDate', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.endDate"
                label="Hasta"
                placeholder="Seleccionar Fecha"
                clearable
                density="compact"
                hide-details="auto"
                :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                prepend-inner-icon="tabler-calendar-event"
                @update:model-value="emit('update:endDate', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="4" class="d-flex align-center">
              <VCheckbox
                :model-value="props.isStrictSearch"
                label="Búsqueda Estricta"
                color="primary"
                density="compact"
                hide-details
                @update:model-value="emit('update:isStrictSearch', $event)"
              />
            </VCol>

            <!-- Botones de Acción Internos -->
            <VCol cols="12" sm="6" md="2" class="d-flex align-center justify-end">
              <VBtn 
                color="secondary" 
                variant="outlined" 
                size="small" 
                prepend-icon="tabler-eraser"
                block
                @click="handleClear"
              >
                Limpiar
              </VBtn>
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.search-bar :deep(.v-field__input) {
  font-size: 0.9rem;
}

.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }

@media (max-width: 600px) {
  .search-bar :deep(.v-field__input) {
    font-size: 0.8rem;
  }
}
</style>
