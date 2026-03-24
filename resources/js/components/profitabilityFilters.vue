<script setup>
// Filtros de Rentabilidad
import AppFilterBase from "@/components/AppFilterBase.vue";
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  searchQuery:        String,
  selectedLaboratory: [Number, String, null],
  selectedOrigin:     [Number, String, null],
  stockStatusFilter:  [Boolean, null],
  startDate:          [String, null],
  endDate:            [String, null],
  laboratories:       { type: Array,   default: () => [] },
  loading:            { type: Boolean, default: false },
  lockedValue:        { type: Number,  default: null },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "update:stockStatusFilter",
  "update:startDate",
  "update:endDate",
  "update:lockedValue",
  "add-profitability",
  "clear",
  "export",
  "sort",
]);

const stockOptions = [
  { title: "Con Stock", value: true },
  { title: "Sin Stock", value: false },
];

const lockedOptions = [
  { title: "Bloqueado",    value: 2 },
  { title: "No bloqueado", value: 1 },
];

const sortOptions = [
  { title: "Precio mayor",    icon: "tabler-arrow-narrow-up",   key: "sale_price",      order: "desc" },
  { title: "Precio Menor",    icon: "tabler-arrow-narrow-down", key: "sale_price",      order: "asc"  },
  { title: "Más Unidades",    icon: "tabler-plus",              key: "valid_stock",     order: "desc" },
  { title: "Menos Unidades",  icon: "tabler-minus",             key: "valid_stock",     order: "asc"  },
  { title: "Pronto a Vencer", icon: "tabler-calendar-stats",    key: "next_expiration", order: "asc"  },
  { title: "Más Vendidos",    icon: "tabler-trending-up",       key: "most_sold",       order: "desc" },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);
const selectedSort = ref(null);

const getStorageKey = () => `product_profitability_sort_user_${currentUser.value?.id || "anonymous"}`;

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
    props.stockStatusFilter !== null ||
    props.lockedValue !== null ||
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
    search-placeholder="ID, Producto, C. Activo..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @sort="handleSortClick"
  >
    <template #actions-extra>
      <VBtn
        color="primary"
        variant="flat"
        size="38"
        class="rounded-lg ml-1 font-weight-black shadow-primary"
        @click="emit('add-profitability')"
      >
        <VIcon icon="tabler-percentage" size="20" class="d-sm-none" />
        <span class="d-none d-sm-flex px-2">
          <VIcon icon="tabler-percentage" size="18" class="mr-2" />
          ASIGNAR RENTABILIDAD
        </span>
        <VTooltip activator="parent" location="top" class="d-sm-none">Asignar Rentabilidad</VTooltip>
      </VBtn>
    </template>

    <template #advanced-filters>
      <!-- Laboratorio -->
      <VCol cols="12" sm="6" md="3">
        <VAutocomplete
          :model-value="props.selectedLaboratory"
          :items="props.laboratories"
          :loading="props.loading"
          placeholder="Laboratorio"
          item-title="name"
          item-value="id"
          density="compact"
          hide-details
          variant="outlined"
          clearable
          prepend-inner-icon="tabler-flask"
          @update:model-value="emit('update:selectedLaboratory', $event)"
        />
      </VCol>

      <!-- Disponibilidad Stock -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.stockStatusFilter"
          :items="stockOptions"
          placeholder="Stock"
          density="compact"
          hide-details
          variant="outlined"
          clearable
          prepend-inner-icon="tabler-package"
          @update:model-value="emit('update:stockStatusFilter', $event)"
        />
      </VCol>

      <!-- Estado Bloqueo -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.lockedValue"
          :items="lockedOptions"
          item-title="title"
          item-value="value"
          placeholder="Estado Bloqueo"
          density="compact"
          hide-details
          variant="outlined"
          clearable
          prepend-inner-icon="tabler-lock"
          @update:model-value="emit('update:lockedValue', $event)"
        />
      </VCol>

      <!-- Rango Vencimiento -->
      <VCol cols="12" sm="6" md="2">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Vence Desde"
          clearable
          density="compact"
          hide-details
          :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Vence Hasta"
          clearable
          density="compact"
          hide-details
          :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-check"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>

<style scoped>
.shadow-primary {
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.2) !important;
}
</style>
