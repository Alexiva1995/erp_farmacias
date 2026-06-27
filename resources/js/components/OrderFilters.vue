<script setup>
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore();
const isMiniMarket = computed(() => brandingStore.settings?.business_type === 'minimarket');

const props = defineProps({
  searchQuery: String,
  selectedLaboratory: [Number, String, null],
  selectedOrigin: [Number, String, null],
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  stockStatusFilter: [Boolean, null],
  isStrictSearch: Boolean,
  sortBy: { type: [String, undefined], default: undefined },
  orderBy: { type: String, default: "asc" },
  loading: { type: Boolean, default: false },
  isRestaurant: { type: Boolean, default: false },
  selectedCategory: [Number, String, null],
  categories: { type: Array, default: () => [] },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "update:stockStatusFilter",
  "update:isStrictSearch",
  "update:selectedCategory",
  "clear",
  "clear-sort",
  "back",
  "sort",
]);

const stockOptions = [
  { title: "Con Stock", value: true },
  { title: "Sin Stock", value: false },
  { title: "Todos", value: null },
];

const sortOptions = [
  { title: "Precio mayor", icon: "tabler-arrow-up", key: "sale_price", order: "desc" },
  { title: "Precio Menor", icon: "tabler-arrow-down", key: "sale_price", order: "asc" },
  { title: "Más Unidades", icon: "tabler-plus", key: "valid_stock", order: "desc" },
  { title: "Menos Unidades", icon: "tabler-minus", key: "valid_stock", order: "asc" },
  { title: "Más Vendidos", icon: "tabler-plus", key: "sales_average", order: "desc" },
  { title: "Menos Vendidos", icon: "tabler-minus", key: "sales_average", order: "asc" },
  { title: "Fecha pronto a Vencer", icon: "tabler-calendar-time", key: "next_expiration", order: "asc" },
];

const hasAdvancedFilters = computed(() => {
  return (
    props.selectedLaboratory !== null ||
    props.selectedOrigin !== null ||
    props.selectedCategory !== null ||
    props.stockStatusFilter !== null ||
    props.isStrictSearch
  );
});

const handleSort = (sortData) => {
  emit("sort", sortData);
};

const handleClear = () => {
  emit("clear");
};

const handleBack = () => {
  emit("back");
};
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    search-placeholder="Buscar por Producto, Cód. Barra, C. Activo..."
    show-sort
    :sort-options="sortOptions"
    :has-advanced-filters="hasAdvancedFilters"
    :loading="props.loading"
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="handleClear"
    @sort="handleSort"
  >
    <!-- Slot extra: Búsqueda Estricta -->
    <template #search-extra>
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

    <!-- Slot acciones extra: Botón Volver -->
    <template #actions-extra>
      <VBtn
        icon="tabler-arrow-back"
        variant="tonal"
        color="primary"
        size="38"
        class="rounded-pill"
        @click="handleBack"
      >
        <VIcon icon="tabler-arrow-back" />
        <VTooltip activator="parent" location="top">Volver</VTooltip>
      </VBtn>
    </template>

    <!-- Slot Filtros Avanzados -->
    <template #advanced-filters>
      <VCol cols="12" sm="4">
        <VSelect
          :model-value="props.stockStatusFilter"
          placeholder="Estado de Stock"
          :items="stockOptions"
          density="compact"
          variant="outlined"
          clearable
          hide-details
          prepend-inner-icon="tabler-box"
          @update:model-value="emit('update:stockStatusFilter', $event)"
        />
      </VCol>
      <template v-if="props.isRestaurant">
        <VCol cols="12" sm="4">
          <VSelect
            :model-value="props.selectedCategory"
            placeholder="Categoría"
            :items="props.categories"
            item-title="name"
            item-value="id"
            density="compact"
            variant="outlined"
            clearable
            hide-details
            prepend-inner-icon="tabler-category"
            @update:model-value="emit('update:selectedCategory', $event)"
          />
        </VCol>
      </template>
      <template v-else>
        <VCol cols="12" sm="4">
          <VSelect
            :model-value="props.selectedLaboratory"
            :placeholder="isMiniMarket ? 'Marca' : 'Laboratorio'"
            :items="props.laboratories"
            item-title="name"
            item-value="id"
            density="compact"
            variant="outlined"
            clearable
            hide-details
            prepend-inner-icon="tabler-flask"
            @update:model-value="emit('update:selectedLaboratory', $event)"
          />
        </VCol>
        <VCol v-if="isMiniMarket" cols="12" sm="4">
          <VSelect
            :model-value="props.selectedCategory"
            placeholder="Categoría"
            :items="props.categories"
            item-title="name"
            item-value="id"
            density="compact"
            variant="outlined"
            clearable
            hide-details
            prepend-inner-icon="tabler-category"
            @update:model-value="emit('update:selectedCategory', $event)"
          />
        </VCol>
        <VCol v-if="!isMiniMarket" cols="12" sm="4">
          <VSelect
            :model-value="props.selectedOrigin"
            placeholder="Origen"
            :items="props.origins"
            item-title="name"
            item-value="id"
            density="compact"
            variant="outlined"
            clearable
            hide-details
            prepend-inner-icon="tabler-world"
            @update:model-value="emit('update:selectedOrigin', $event)"
          />
        </VCol>
      </template>
    </template>
  </AppFilterBase>
</template>

<style scoped>
.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.text-caption {
  font-size: 0.7rem !important;
}

.gap-2 { gap: 8px !important; }
</style>
