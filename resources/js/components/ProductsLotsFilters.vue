<script setup>
// Filtros Lotes de Productos (Inventario)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  searchQuery:        { type: String,  required: true },
  itemsPerPage:       { type: Number,  required: true },
  selectedLaboratory: [Number, String, null],
  selectedOrigin:     [Number, String, null],
  stockStatusFilter:  [Boolean, null],
  startDate:          [String, null],
  endDate:            [String, null],
  laboratories:       { type: Array,   default: () => [] },
  origins:            { type: Array,   default: () => [] },
  loading:            { type: Boolean, default: false },
  addLotLoading:      { type: Boolean, default: false },
  isStrictSearch:     Boolean,
  isAdmin:            { type: Boolean, default: false },
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
  { title: "Con Stock", value: true  },
  { title: "Sin Stock", value: false },
];

const sortOptions = [
  { title: "Más Recientes",   icon: "tabler-calendar-plus",           key: "created_at",      order: "desc" },
  { title: "Más Antiguos",    icon: "tabler-calendar-minus",          key: "created_at",      order: "asc"  },
  { title: "Mayor Cantidad",  icon: "tabler-arrow-up",                key: "quantity",        order: "desc" },
  { title: "Menor Cantidad",  icon: "tabler-arrow-down",              key: "quantity",        order: "asc"  },
  { title: "Pronto a Vencer", icon: "tabler-calendar-time",           key: "expiration_date", order: "asc"  },
  { title: "Producto A-Z",    icon: "tabler-sort-ascending-letters",  key: "product.name",    order: "asc"  },
  { title: "Producto Z-A",    icon: "tabler-sort-descending-letters", key: "product.name",    order: "desc" },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);
const selectedSort = ref(null);

const getStorageKey = () => `product_lots_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

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

onMounted(() => loadSavedSort());
watch(() => currentUser.value?.id, (newVal) => { if(newVal) loadSavedSort(); }, { immediate: true });

const hasAdvancedFilters = computed(() => {
  return !!(
    props.selectedLaboratory ||
    props.selectedOrigin ||
    props.stockStatusFilter !== null ||
    props.startDate ||
    props.endDate
  );
});
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    search-placeholder="Lote, Producto, Proveedor..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
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

    <template #actions-extra>
      <!-- Añadir Lote (Solo Admin) -->
      <VBtn
        v-if="props.isAdmin"
        icon
        color="primary"
        variant="flat"
        size="38"
        :loading="props.addLotLoading"
        @click="emit('add-lot')"
      >
        <VIcon icon="tabler-plus" />
        <VTooltip activator="parent" location="top">Añadir Lote</VTooltip>
      </VBtn>

      <!-- Limpiar Cantidades Cero (Solo Admin) -->
      <VBtn
        v-if="props.isAdmin"
        icon
        color="error"
        variant="tonal"
        size="38"
        class="rounded-lg ml-1"
        @click="emit('clean-zero-quantity')"
      >
        <VIcon icon="tabler-trash-x" />
        <VTooltip activator="parent" location="top">Limpiar Lotes en Cero</VTooltip>
      </VBtn>
    </template>

    <template #advanced-filters>
      <!-- Laboratorio -->
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

      <!-- Origen -->
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

      <!-- Vencimiento Desde -->
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

      <!-- Vencimiento Hasta -->
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
    </template>
  </AppFilterBase>
</template>
