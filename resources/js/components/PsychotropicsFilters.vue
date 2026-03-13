<script setup>
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedLaboratory: [Number, String, null],
  stockStatusFilter: [Boolean, null],
  startDate: [String, null],
  endDate: [String, null],
  laboratories: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:stockStatusFilter",
  "update:startDate",
  "update:endDate",
  "clear",
  "export",
  "add-product",
  "sort",
]);

const isAdvancedFiltersVisible = ref(false);

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

const handleClear = () => {
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
    <VCardText class="pa-4">
      <VRow align="center" no-gutters class="gap-3">
        <!-- Buscador Principal -->
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
          <!-- Filtros -->
          <VBtn
            variant="tonal"
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            :prepend-icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'"
            @click="isAdvancedFiltersVisible = !isAdvancedFiltersVisible"
          >
            Filtros
          </VBtn>

          <!-- Ordenar Por -->
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

          <VSpacer class="d-none d-md-block" />

          <!-- Exportar -->
          <VMenu>
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
        </VCol>
      </VRow>

      <!-- Panel de Filtros Colapsable -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-4 border-opacity-10" />
          
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
                @update:model-value="emit('update:selectedLaboratory', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <VSelect
                :model-value="props.stockStatusFilter"
                label="Estado de Stock"
                :items="stockOptions"
                placeholder="Todos"
                clearable
                density="compact"
                @update:model-value="emit('update:stockStatusFilter', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.startDate"
                label="Desde"
                placeholder="YYYY-MM-DD"
                clearable
                density="compact"
                :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:startDate', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.endDate"
                label="Hasta"
                placeholder="YYYY-MM-DD"
                clearable
                density="compact"
                :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:endDate', $event)"
              />
            </VCol>

            <VCol cols="12" class="d-flex justify-end gap-2 mt-2">
              <VBtn 
                color="secondary" 
                variant="outlined" 
                size="small" 
                prepend-icon="tabler-eraser"
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
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
</style>
