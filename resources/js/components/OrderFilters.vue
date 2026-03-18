<script setup>
import { computed, defineProps, ref } from "vue";

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
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "update:stockStatusFilter",
  "update:isStrictSearch",
  "clear",
  "clear-sort",
  "back",
  "sort",
]);

const isAdvancedFiltersVisible = ref(false);

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

const selectedSort = computed(() => {
  if (!props.sortBy) return null;
  return sortOptions.find(o => o.key === props.sortBy && o.order === props.orderBy) || 
         { key: props.sortBy, order: props.orderBy, title: props.sortBy, icon: "tabler-arrow-up" };
});

const isOptionSelected = (option) => props.sortBy === option.key && props.orderBy === option.order;
const getSelectedSortTitle = () => selectedSort.value?.title || props.sortBy;
const getSelectedSortIcon = () => selectedSort.value?.icon || "tabler-arrow-up";

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const clearFilters = () => {
  emit('clear');
};

const clearSortFilter = () => emit("clear-sort");

const handleSortClick = (option) => {
  emit("sort", { key: option.key, order: option.order });
};

const handleBack = () => {
  emit("back");
};
</script>

<template>
  <VCard variant="flat" border class="mb-6 rounded-xl overflow-hidden shadow-sm">
    <VCardText class="pa-4">
      <!-- Fila Principal: Búsqueda y Acciones Rápidas -->
      <VRow align="center" no-gutters class="gap-3">
        <VCol class="flex-grow-1">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por Producto, Cód. Barra, C. Activo..."
            prepend-inner-icon="tabler-search"
            clearable
            hide-details
            density="compact"
            class="filter-search-input font-weight-bold"
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <VCol cols="auto" class="d-flex gap-1 mt-1">
          <VTooltip location="top" text="Filtros Avanzados">
            <template #activator="{ props: tooltipProps }">
              <VBtn
                v-bind="tooltipProps"
                :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
                variant="tonal"
                density="comfortable"
                class="rounded-lg"
                icon="tabler-filter"
                @click="toggleAdvancedFilters"
              />
            </template>
          </VTooltip>

          <VMenu>
            <template #activator="{ props: menuProps }">
              <VTooltip location="top" text="Ordenar Resultados">
                <template #activator="{ props: tooltipProps }">
                  <VBtn 
                    v-bind="{ ...menuProps, ...tooltipProps }" 
                    variant="tonal" 
                    color="secondary" 
                    density="comfortable"
                    class="rounded-lg"
                    icon="tabler-sort-ascending"
                  />
                </template>
              </VTooltip>
            </template>
            <VList density="compact" class="rounded-xl mt-1 py-2 shadow-lg border">
              <VListItem
                v-for="(option, index) in sortOptions"
                :key="index"
                :active="isOptionSelected(option)"
                color="primary"
                @click="handleSortClick(option)"
              >
                <template #prepend>
                  <VIcon :icon="option.icon" size="18" class="me-3" />
                </template>
                <VListItemTitle class="font-weight-bold text-caption uppercase">{{ option.title }}</VListItemTitle>
                <template #append v-if="isOptionSelected(option)">
                  <VIcon icon="tabler-check" size="16" color="primary" />
                </template>
              </VListItem>
            </VList>
          </VMenu>

          <VTooltip location="top" text="Limpiar Filtros">
            <template #activator="{ props: tooltipProps }">
              <VBtn
                v-bind="tooltipProps"
                color="secondary"
                variant="tonal"
                density="comfortable"
                class="rounded-lg"
                icon="tabler-trash"
                @click="clearFilters"
              />
            </template>
          </VTooltip>

          <VTooltip location="top" text="Volver">
            <template #activator="{ props: tooltipProps }">
              <VBtn
                v-bind="tooltipProps"
                color="primary"
                variant="tonal"
                density="comfortable"
                class="rounded-lg"
                icon="tabler-arrow-back"
                @click="handleBack"
              />
            </template>
          </VTooltip>
        </VCol>
      </VRow>

      <!-- Panel de Filtros Avanzados -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-4 border-opacity-10" />
          <VRow>
            <VCol cols="12" sm="4">
              <VSelect
                :model-value="props.stockStatusFilter"
                label="Estado de Stock"
                :items="stockOptions"
                density="compact"
                variant="outlined"
                clearable
                hide-details
                prepend-inner-icon="tabler-box"
                class="font-weight-bold"
                @update:model-value="emit('update:stockStatusFilter', $event)"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VSelect
                :model-value="props.selectedLaboratory"
                label="Laboratorio"
                :items="props.laboratories"
                item-title="name"
                item-value="id"
                density="compact"
                variant="outlined"
                clearable
                hide-details
                prepend-inner-icon="tabler-flask"
                class="font-weight-bold"
                @update:model-value="emit('update:selectedLaboratory', $event)"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VSelect
                :model-value="props.selectedOrigin"
                label="Origen"
                :items="props.origins"
                item-title="name"
                item-value="id"
                density="compact"
                variant="outlined"
                clearable
                hide-details
                prepend-inner-icon="tabler-world"
                class="font-weight-bold"
                @update:model-value="emit('update:selectedOrigin', $event)"
              />
            </VCol>
          </VRow>

          <div class="mt-4 d-flex align-center gap-4">
            <div class="d-flex align-center gap-2">
              <VSwitch
                :model-value="props.isStrictSearch"
                density="compact"
                color="primary"
                hide-details
                @update:model-value="emit('update:isStrictSearch', $event)"
              />
              <span class="text-caption font-weight-black uppercase letter-spacing-1">Búsqueda Estricta</span>
            </div>

            <VChipGroup v-if="selectedSort" class="ms-auto" selected-class="text-primary">
              <VChip
                color="primary"
                variant="tonal"
                size="small"
                closable
                label
                class="font-weight-black uppercase"
                @click:close="clearSortFilter"
              >
                <VIcon :icon="getSelectedSortIcon()" size="14" class="me-1" />
                {{ getSelectedSortTitle() }}
              </VChip>
            </VChipGroup>
          </div>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.filter-search-input :deep(.v-field__input) {
  font-size: 0.875rem !important;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
.gap-4 { gap: 16px !important; }

.text-caption {
  font-size: 0.7rem !important;
}
</style>
