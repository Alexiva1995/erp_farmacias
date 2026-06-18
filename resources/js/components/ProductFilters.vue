<script setup>
// Filtro principal de Productos (Configuración base global)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { useAuthStore } from "@/stores/auth";
import { computed, ref, onMounted, watch } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";

const props = defineProps({
  searchQuery:            String,
  selectedLaboratory:     [Number, String, null],
  selectedOrigin:         [Number, String, null],
  selectedGroup:          [Number, String, null],
  selectedSupplier:       [Number, String, null],
  stockStatusFilter:      [Boolean, null],
  productTypeFilter:      [String, null],
  selectedRestaurantType: [String, null],
  startDate:              [String, null],
  endDate:                [String, null],
  laboratories:           { type: Array,   default: () => [] },
  origins:            { type: Array,   default: () => [] },
  groups:             { type: Array,   default: () => [] },
  suppliers:          { type: Array,   default: () => [] },
  loading:            { type: Boolean, default: false },
  mode:               { type: String,  default: "products" },
  showAddButton:      { type: Boolean, default: true },
  addButtonText:      { type: String,  default: "Añadir Producto" },
  isStrictSearch:     Boolean,
  isScarce:           Boolean,
  onlyDeleted:        Boolean,
  flat:               { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "update:selectedGroup",
  "update:selectedSupplier",
  "update:stockStatusFilter",
  "update:productTypeFilter",
  "update:selectedRestaurantType",
  "update:startDate",
  "update:endDate",
  "update:isStrictSearch",
  "update:isScarce",
  "update:onlyDeleted",
  "clear",
  "export",
  "add-product",
  "sort",
]);

const stockOptions = [
  { title: "Con Stock", value: true  },
  { title: "Sin Stock", value: false },
];
const restaurantTypeOptions = [
  { title: "PVP", value: "pvp" },
  { title: "Ingredientes", value: "ingredients" },
  { title: "Mixto", value: "mixed" },
];
const productTypeOptions = [
  { title: "Todos", value: null },
  { title: "Redundantes", value: "redundantes" },
  { title: "Origen Colombiano", value: "col" },
  { title: "Con IVA (G)", value: "iva" },
  { title: "Exento", value: "exento" },
  { title: "Novaventa", value: "novaventa" },
  { title: "Eliminados", value: "eliminados" },
];
const sortOptions = [
  { title: "Precio mayor",    icon: "tabler-arrow-up",      key: "sale_price",      order: "desc" },
  { title: "Precio Menor",    icon: "tabler-arrow-down",    key: "sale_price",      order: "asc"  },
  { title: "Más Vendidos",    icon: "tabler-trending-up",   key: "most_sold",       order: "desc" },
  { title: "Mayor Cantidad",  icon: "tabler-arrow-up",      key: "valid_stock",     order: "desc" },
  { title: "Menor Cantidad",  icon: "tabler-arrow-down",    key: "valid_stock",     order: "asc"  },
  { title: "Pronto a Vencer", icon: "tabler-calendar-time", key: "next_expiration", order: "asc"  },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);
const selectedSort = ref(null);
const brandingStore = useBrandingStore();
const isRestaurant = computed(() => brandingStore.settings.business_type === 'restaurant');

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
  if(sortFilter.key !== undefined) {
    saveSortFilter(sortFilter);
  } else {
    try { localStorage.removeItem(getStorageKey()); } catch(e){}
  }
  emit("sort", sortFilter);
};

onMounted(() => loadSavedSort());
watch(() => currentUser.value?.id, (newVal) => { if(newVal) loadSavedSort(); }, { immediate: true });

// Sincronizar propiedad sortData externa si existe lógica padre-hijo (retrocompatibilidad)
watch(() => props.sortData, (newVal) => {
  if (!newVal || (newVal.key === undefined && newVal.order === undefined)) selectedSort.value = null;
}, { deep: true });

const hasAdvancedFilters = computed(() => {
  return !!(
    props.selectedLaboratory ||
    props.selectedOrigin ||
    props.selectedGroup ||
    props.selectedSupplier ||
    props.stockStatusFilter !== null ||
    props.productTypeFilter ||
    props.startDate ||
    props.endDate
  );
});

// Condiciones de props compuestas
const canAdd = computed(() => props.showAddButton && (props.mode === 'products' || props.mode === 'minimal'));
const showExport = computed(() => props.mode === 'products');
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    :show-export="showExport"
    :show-add="canAdd"
    :add-button-text="props.addButtonText"
    :search-placeholder="isRestaurant ? 'ID, Producto...' : 'ID, Producto, C. Activo...'"
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @export="(ext) => emit('export', ext)"
    @add="emit('add-product')"
    @sort="handleSortClick"
  >
    <template #search-extra>
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
    </template>

    <template #advanced-filters>
      <!-- Tipo (Restaurante) -->
      <VCol v-if="isRestaurant" cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.selectedRestaurantType"
          placeholder="Tipo"
          :items="restaurantTypeOptions"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-tags"
          @update:model-value="emit('update:selectedRestaurantType', $event)"
        />
      </VCol>

      <!-- Tipo (Farmacia) -->
      <VCol v-if="!isRestaurant" cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.productTypeFilter"
          placeholder="Tipo de Producto"
          :items="productTypeOptions"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-tags"
          @update:model-value="emit('update:productTypeFilter', $event)"
        />
      </VCol>

      <!-- Laboratorio / Marca -->
      <VCol cols="12" sm="6" md="2">
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
          prepend-inner-icon="tabler-flask"
          @update:model-value="emit('update:selectedLaboratory', $event)"
        />
      </VCol>

      <!-- Origen -->
      <VCol v-if="!isRestaurant" cols="12" sm="6" md="2">
        <VAutocomplete
          :model-value="props.selectedOrigin"
          :items="props.origins"
          :loading="props.loading"
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

      <!-- Grupo o Proveedor (si es restaurante) -->
      <VCol v-if="!isRestaurant" cols="12" sm="6" md="2">
        <VAutocomplete
          :model-value="props.selectedGroup"
          :items="props.groups"
          :loading="props.loading"
          placeholder="Grupo"
          item-title="name"
          item-value="id"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-category-2"
          @update:model-value="emit('update:selectedGroup', $event)"
        />
      </VCol>
      <VCol v-else cols="12" sm="6" md="2">
        <VAutocomplete
          :model-value="props.selectedSupplier"
          :items="props.suppliers"
          :loading="props.loading"
          placeholder="Proveedor"
          item-title="name"
          item-value="id"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-truck"
          @update:model-value="emit('update:selectedSupplier', $event)"
        />
      </VCol>

      <!-- Estado Stock -->
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

      <!-- Fecha Inicial -->
      <VCol cols="12" sm="6" md="2">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Fecha Inicial"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <!-- Fecha Final -->
      <VCol cols="12" sm="6" md="2">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Fecha Final"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
