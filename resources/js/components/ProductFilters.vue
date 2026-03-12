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
      <!-- Fila Principal: Búsqueda y Toggle -->
      <VRow align="center" no-gutters class="gap-3">
        <VCol cols="12" sm="true">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por ID, Producto, C. Activo..."
            clearable
            prepend-inner-icon="tabler-search"
            class="search-bar"
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>
        
        <VCol cols="auto" class="d-flex gap-2">
          <VBtn
            variant="tonal"
            :color="hasActiveAdvancedFilters ? 'primary' : 'secondary'"
            :prepend-icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'"
            @click="toggleAdvancedFilters"
          >
            {{ isAdvancedFiltersVisible ? 'Ocultar Filtros' : 'Más Filtros' }}
            <VBadge
              v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
              color="error"
              dot
              offset-x="10"
              offset-y="-5"
            />
          </VBtn>

          <template v-if="mode === 'products' || mode === 'minimal'">
            <VBtn
              v-if="props.showAddButton"
              color="success"
              icon="tabler-plus"
              class="d-sm-none"
              @click="emit('add-product')"
            />
            <VBtn
              v-if="props.showAddButton"
              color="success"
              prepend-icon="tabler-plus"
              class="d-none d-sm-flex"
              @click="emit('add-product')"
            >
              {{ props.mode === 'inventory' ? 'Añadir' : props.addButtonText }}
            </VBtn>
          </template>
        </VCol>
      </VRow>

      <!-- Sección Colapsable -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-4" />
          
          <VRow>
            <VCol cols="12" sm="6" md="3">
              <VAutocomplete
                :model-value="props.selectedLaboratory"
                :items="props.laboratories"
                :loading="props.loading"
                label="Laboratorio"
                placeholder="Seleccionar"
                item-title="name"
                item-value="id"
                clearable
                density="compact"
                hide-details
                @update:model-value="emit('update:selectedLaboratory', $event)"
              />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <VAutocomplete
                :model-value="props.selectedOrigin"
                :items="props.origins"
                :loading="props.loading"
                label="Origen"
                placeholder="Seleccionar"
                item-title="name"
                item-value="id"
                clearable
                density="compact"
                hide-details
                @update:model-value="emit('update:selectedOrigin', $event)"
              />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <VSelect
                :model-value="props.stockStatusFilter"
                label="Estado Stock"
                :items="stockOptions"
                placeholder="Todos"
                clearable
                density="compact"
                hide-details
                @update:model-value="emit('update:stockStatusFilter', $event)"
              />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <div class="d-flex align-center h-100 ps-2">
                <VCheckbox
                  :model-value="props.isStrictSearch"
                  label="Búsqueda Estricta"
                  color="primary"
                  density="compact"
                  hide-details
                  @update:model-value="emit('update:isStrictSearch', $event)"
                />
              </div>
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.startDate"
                placeholder="Desde"
                clearable
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                density="compact"
                hide-details
                @update:model-value="emit('update:startDate', $event)"
              />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.endDate"
                placeholder="Hasta"
                clearable
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                density="compact"
                hide-details
                @update:model-value="emit('update:endDate', $event)"
              />
            </VCol>
            
            <VCol cols="12" sm="6" md="3">
              <VMenu>
                <template #activator="{ props: menuProps }">
                  <VBtn v-bind="menuProps" variant="outlined" block density="compact" class="h-100">
                    <VIcon start :icon="getSelectedSortIcon() || 'tabler-sort-ascending'" />
                    {{ getSelectedSortTitle() || 'Ordenar Por' }}
                    <VIcon end icon="tabler-chevron-down" />
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
            </VCol>

            <VCol cols="12" sm="6" md="3" class="d-flex gap-2">
              <VBtn color="secondary" variant="tonal" block @click="handleClear">
                Limpiar
              </VBtn>
            </VCol>
          </VRow>

          <!-- Acciones de Exportación -->
          <div v-if="mode === 'products'" class="d-flex justify-end gap-2 mt-4">
            <VBtn
              color="success"
              variant="flat"
              prepend-icon="tabler-download"
              size="small"
              @click="emit('export', 'xlsx')"
            >
              Excel
            </VBtn>
            <VBtn
              color="error"
              variant="flat"
              prepend-icon="tabler-file-type-pdf"
              size="small"
              @click="emit('export', 'pdf')"
            >
              PDF
            </VBtn>
          </div>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>

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
  </VCard>
</template>
