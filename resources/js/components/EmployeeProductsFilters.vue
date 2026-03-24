<script setup>
// Filtros para productos asignados a empleados (productividad)
// Incluye búsqueda remota de productos via axios debounced
import AppFilterBase from "@/components/AppFilterBase.vue";
import axios from "@/plugins/axios";
import { computed, ref, watch } from "vue";

// ── Búsqueda remota de productos ──────────────────────────────────────────
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
  searchDebounce = setTimeout(() => loadRemoteProducts(val), 400);
});

const props = defineProps({
  searchQuery:     String,
  selectedProduct: [Number, null],
  products:        { type: Array,   default: () => [] },
  loading:         { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedProduct",
  "clear",
  "add-assignment",
  "sort",
]);

const sortOptions = [
  { title: "Empleado A-Z",    icon: "tabler-sort-ascending-letters",  key: "employee_name",  order: "asc"  },
  { title: "Empleado Z-A",    icon: "tabler-sort-descending-letters", key: "employee_name",  order: "desc" },
  { title: "Más Productos",   icon: "tabler-sort-descending",         key: "products_count", order: "desc" },
  { title: "Menos Productos", icon: "tabler-sort-ascending",          key: "products_count", order: "asc"  },
  { title: "Más Recientes",   icon: "tabler-clock",                   key: "created_at",     order: "desc" },
];

const hasAdvancedFilters = computed(() => !!props.selectedProduct);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    :show-add="true"
    add-button-text="Asignar Producto"
    search-placeholder="Buscar empleado por nombre..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @add="emit('add-assignment')"
    @sort="emit('sort', $event)"
  >
    <template #advanced-filters>
      <!-- Autocomplete remoto de productos -->
      <VCol cols="12" sm="6" md="5">
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
    </template>
  </AppFilterBase>
</template>
