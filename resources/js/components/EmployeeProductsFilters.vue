<script setup>
import axios from "@/plugins/axios";
import { computed, ref, watch } from "vue";

const searchProduct = ref("");
const remoteProducts = ref([]);
const isSearching = ref(false);

const loadRemoteProducts = async (query = "") => {
  if (query.length < 2 && !/^\d+$/.test(query)) {
    remoteProducts.value = [];
    return;
  }
  isSearching.value = true;
  try {
    const response = await axios.get("/products", {
      params: { q: query, itemsPerPage: 50 },
    });
    remoteProducts.value = response.data.data.map((p) => ({
      title: `${p.id} - ${p.name}`,
      value: p.id,
    }));
  } catch (error) {
    console.error("Error buscando productos:", error);
  } finally {
    isSearching.value = false;
  }
};

let searchDebounce;
watch(searchProduct, (val) => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    loadRemoteProducts(val);
  }, 400);
});

const props = defineProps({
  searchQuery: String,
  selectedProduct: [Number, null],
  products: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedProduct",
  "clear",
  "add-assignment",
  "sort",
]);

const sortOptions = [
  {
    title: "Empleado A-Z",
    icon: "tabler-sort-ascending-letters",
    key: "employee_name",
    order: "asc",
  },
  {
    title: "Empleado Z-A",
    icon: "tabler-sort-descending-letters",
    key: "employee_name",
    order: "desc",
  },
  {
    title: "Más Productos",
    icon: "tabler-sort-descending",
    key: "products_count",
    order: "desc",
  },
  {
    title: "Menos Productos",
    icon: "tabler-sort-ascending",
    key: "products_count",
    order: "asc",
  },
  {
    title: "Más Recientes",
    icon: "tabler-clock",
    key: "created_at",
    order: "desc",
  },
];

const selectedSort = ref(null);
const isAdvancedFiltersVisible = ref(false);

const handleSortClick = (option) => {
  const sortFilter = { key: option.key, order: option.order };
  selectedSort.value = sortFilter;
  emit("sort", sortFilter);
};

const clearSortFilter = () => {
  selectedSort.value = null;
  emit("sort", { key: undefined, order: undefined });
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
  isAdvancedFiltersVisible.value = false;
};

const hasActiveAdvancedFilters = computed(() => {
  return props.selectedProduct || selectedSort.value;
});
</script>

<template>
  <VCard class="mb-6">
    <VCardText class="pa-3">
      <!-- Fila Principal: Búsqueda y Acciones -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="5" lg="4">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar empleado por nombre..."
            prepend-inner-icon="tabler-search"
            density="compact"
            hide-details
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-1">
          <!-- Toggle Filtros Avanzados -->
          <VBtn
            icon
            variant="tonal"
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            size="38"
            @click="isAdvancedFiltersVisible = !isAdvancedFiltersVisible"
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

          <!-- Asignar -->
          <VBtn
            icon
            color="primary"
            variant="flat"
            size="38"
            @click="emit('add-assignment')"
          >
            <VIcon icon="tabler-plus" />
            <VTooltip activator="parent" location="top">Asignar Productos</VTooltip>
          </VBtn>

          <!-- Limpiar -->
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

      <!-- Panel Colapsable -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-3 border-opacity-10" />
          <VRow dense>
            <VCol cols="12" sm="6" md="4">
              <VAutocomplete
                :model-value="props.selectedProduct"
                v-model:search="searchProduct"
                :items="remoteProducts"
                :loading="isSearching || props.loading"
                item-title="title"
                item-value="value"
                placeholder="Filtrar por producto..."
                density="compact"
                hide-details
                clearable
                :no-filter="true"
                prepend-inner-icon="tabler-pill"
                @update:model-value="emit('update:selectedProduct', $event)"
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>
