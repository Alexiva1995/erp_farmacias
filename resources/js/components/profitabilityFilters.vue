<script setup>
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedLaboratory: [Number, String, null],
  selectedOrigin: [Number, String, null],
  stockStatusFilter: [Boolean, null],
  startDate: [String, null],
  endDate: [String, null],
  laboratories: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  lockedValue: { type: Number, default: null },
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

const isFiltersVisible = ref(false);

const stockOptions = [
  { title: "Con Stock", value: true },
  { title: "Sin Stock", value: false },
];

const lockedOptions = [
  { title: "Bloqueado", value: 2 },
  { title: "No bloqueado", value: 1 },
];

const sortOptions = [
  {
    title: "Precio mayor",
    icon: "tabler-arrow-narrow-up",
    key: "sale_price",
    order: "desc",
  },
  {
    title: "Precio Menor",
    icon: "tabler-arrow-narrow-down",
    key: "sale_price",
    order: "asc",
  },
  {
    title: "Más Unidades",
    icon: "tabler-plus",
    key: "valid_stock",
    order: "desc",
  },
  {
    title: "Menos Unidades",
    icon: "tabler-minus",
    key: "valid_stock",
    order: "asc",
  },
  {
    title: "Pronto a Vencer",
    icon: "tabler-calendar-stats",
    key: "next_expiration",
    order: "asc",
  },
  {
    title: "Más Vendidos",
    icon: "tabler-trending-up",
    key: "most_sold",
    order: "desc",
  },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const selectedSort = ref(null);

const getStorageKey = () =>
  `product_profitability_sort_user_${currentUser.value?.id || "anonymous"}`;

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

const handleClear = () => {
  emit("clear");
  clearSortFilter();
};

onMounted(() => {
  loadSavedSort();
});
</script>

<template>
  <VCard class="mb-6 border-0 shadow-sm overflow-hidden">
    <VCardText class="pa-3">
      <!-- Barra de Búsqueda Principal (Siempre Visible) -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="6" lg="5">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por ID, Producto, C. Activo..."
            prepend-inner-icon="tabler-search"
            class="premium-input-compact"
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
            :color="isFiltersVisible ? 'primary' : 'secondary'"
            size="38"
            @click="isFiltersVisible = !isFiltersVisible"
          >
            <VIcon :icon="isFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
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
                <VIcon
                  :icon="
                    selectedSort
                      ? sortOptions.find(
                          (o) =>
                            o.key === selectedSort.key &&
                            o.order === selectedSort.order
                        )?.icon || 'tabler-sort-ascending'
                      : 'tabler-sort-ascending'
                  "
                  size="20"
                />
                <VTooltip activator="parent" location="top">Ordenar Por</VTooltip>
              </VBtn>
            </template>
            <VList density="compact" class="rounded-lg py-1 border shadow-lg">
              <VListItem
                v-for="(option, index) in sortOptions"
                :key="index"
                :active="
                  selectedSort?.key === option.key &&
                  selectedSort?.order === option.order
                "
                color="primary"
                @click="handleSortClick(option)"
              >
                <template #prepend>
                  <VIcon :icon="option.icon" size="20" class="me-3" />
                </template>
                <VListItemTitle class="text-xs font-weight-bold">{{
                  option.title
                }}</VListItemTitle>
              </VListItem>
              <VDivider v-if="selectedSort" class="my-1 opacity-10" />
              <VListItem v-if="selectedSort" color="error" @click="clearSortFilter">
                <template #prepend>
                  <VIcon icon="tabler-sort-ascending" size="20" class="me-3" />
                </template>
                <VListItemTitle class="text-xs font-weight-bold text-error"
                  >Limpiar Orden</VListItemTitle
                >
              </VListItem>
            </VList>
          </VMenu>

          <VDivider vertical class="mx-1 my-2 border-opacity-10" />

          <!-- Asignar Rentabilidad -->
          <VBtn
            icon
            color="primary"
            variant="flat"
            size="38"
            @click="emit('add-profitability')"
          >
            <VIcon icon="tabler-percentage" size="20" />
            <VTooltip activator="parent" location="top"
              >Asignar Rentabilidad</VTooltip
            >
          </VBtn>

          <!-- Exportar -->
          <VBtn
            icon
            color="success"
            variant="tonal"
            size="38"
            @click="emit('export')"
          >
            <VIcon icon="tabler-file-export" size="20" />
            <VTooltip activator="parent" location="top">Exportar</VTooltip>
          </VBtn>

          <!-- Limpiar Filtros -->
          <VBtn icon variant="text" color="secondary" size="38" @click="handleClear">
            <VIcon icon="tabler-eraser" size="20" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </div>
      </VRow>

      <!-- Panel de Filtros Colapsable -->
      <VExpandTransition>
        <div v-show="isFiltersVisible">
          <VDivider class="my-4 border-opacity-10" />
          
          <VRow>
            <VCol cols="12" sm="6" md="3">
              <span
                class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1"
                >Laboratorio</span
              >
              <VAutocomplete
                :model-value="props.selectedLaboratory"
                :items="props.laboratories"
                :loading="props.loading"
                placeholder="Todos los laboratorios"
                item-title="name"
                item-value="id"
                density="compact"
                hide-details
                variant="outlined"
                class="premium-select-compact"
                clearable
                @update:model-value="emit('update:selectedLaboratory', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <span
                class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1"
                >Disponibilidad Stock</span
              >
              <VSelect
                :model-value="props.stockStatusFilter"
                :items="stockOptions"
                placeholder="Cualquier estado"
                density="compact"
                hide-details
                variant="outlined"
                class="premium-select-compact"
                clearable
                @update:model-value="emit('update:stockStatusFilter', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <span
                class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1"
                >Estado de Bloqueo</span
              >
              <VSelect
                :model-value="props.lockedValue"
                :items="lockedOptions"
                item-title="title"
                item-value="value"
                placeholder="Todos los estados"
                density="compact"
                hide-details
                variant="outlined"
                class="premium-select-compact"
                clearable
                @update:model-value="emit('update:lockedValue', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <span
                class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1"
                >Rango Vencimiento</span
              >
              <div class="d-flex gap-2">
                <AppDateTimePicker
                  :model-value="props.startDate"
                  placeholder="Desde"
                  clearable
                  class="premium-input-compact"
                  :config="{
                    altInput: true,
                    altFormat: 'Y-m-d',
                    dateFormat: 'Y-m-d',
                  }"
                  @update:model-value="emit('update:startDate', $event)"
                />
                <AppDateTimePicker
                  :model-value="props.endDate"
                  placeholder="Hasta"
                  clearable
                  class="premium-input-compact"
                  :config="{
                    altInput: true,
                    altFormat: 'Y-m-d',
                    dateFormat: 'Y-m-d',
                  }"
                  @update:model-value="emit('update:endDate', $event)"
                />
              </div>
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.h-38 {
  block-size: 38px !important;
}

:deep(.premium-input-compact) {
  .v-field__input {
    font-size: 0.8125rem !important;
    min-block-size: 38px !important;
    padding-block: 0 !important;
  }
}

:deep(.premium-select-compact) {
  .v-field__input {
    font-size: 0.8125rem !important;
    min-block-size: 38px !important;
    padding-block: 0 !important;
  }
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 0.03);
}
</style>
