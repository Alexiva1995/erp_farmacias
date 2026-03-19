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
  <div class="profitability-filters-container">
    <!-- Barra de Búsqueda Principal (Siempre Visible) -->
    <div class="d-flex flex-wrap align-center gap-3 mb-6">
      <div class="flex-grow-1" style="min-inline-size: 240px;">
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
      </div>

      <div class="d-flex gap-2 flex-grow-1 flex-sm-grow-0 justify-sm-end">
        <VBtn
          :color="isFiltersVisible ? 'primary' : 'secondary'"
          variant="tonal"
          class="rounded-lg px-4 font-weight-black flex-grow-1 flex-sm-grow-0 h-38"
          @click="isFiltersVisible = !isFiltersVisible"
        >
          <VIcon start icon="tabler-adjustments-horizontal" size="18" />
          <span class="d-none d-sm-inline">FILTROS</span>
          <VIcon end :icon="isFiltersVisible ? 'tabler-chevron-up' : 'tabler-chevron-down'" size="16" />
        </VBtn>

        <VBtn
          color="primary"
          variant="flat"
          class="rounded-lg px-4 font-weight-black shadow-sm flex-grow-1 flex-sm-grow-0 h-38"
          prepend-icon="tabler-percentage"
          @click="emit('add-profitability')"
        >
          ASIGNAR RENTABILIDAD
        </VBtn>
      </div>
    </div>

    <!-- Panel de Filtros Colapsable -->
    <VExpandTransition>
      <VCard v-if="isFiltersVisible" class="rounded-xl border-0 shadow-sm mb-6 bg-surface-variant-light overflow-hidden">
        <VCardText class="pa-5">
          <VRow>
            <VCol cols="12" sm="6" md="3">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">Laboratorio</span>
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
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">Disponibilidad Stock</span>
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
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">Estado de Bloqueo</span>
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
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">Criterio Orden</span>
              <VMenu>
                <template #activator="{ props: menuProps }">
                  <VBtn v-bind="menuProps" variant="outlined" color="primary" density="compact" block class="rounded-lg h-38 font-weight-black">
                    {{ sortOptions.find(o => o.key === selectedSort?.key && o.order === selectedSort?.order)?.title || 'ORDENAR POR' }}
                    <VIcon end icon="tabler-chevron-down" size="16" />
                  </VBtn>
                </template>
                <VList density="compact" class="rounded-lg py-1 border shadow-lg">
                  <VListItem
                    v-for="(option, index) in sortOptions"
                    :key="index"
                    :class="{ 'bg-primary-lighten-5': selectedSort?.key === option.key && selectedSort?.order === option.order }"
                    @click="handleSortClick(option)"
                  >
                    <template #prepend>
                      <VIcon :icon="option.icon" size="18" class="me-2" />
                    </template>
                    <VListItemTitle class="text-xs font-weight-bold">{{ option.title }}</VListItemTitle>
                  </VListItem>
                </VList>
              </VMenu>
            </VCol>

            <VCol cols="12" sm="6" md="4">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">Rango Vencimiento</span>
              <div class="d-flex gap-2">
                <AppDateTimePicker
                  :model-value="props.startDate"
                  placeholder="Desde"
                  clearable
                  class="premium-input-compact"
                  :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                  @update:model-value="emit('update:startDate', $event)"
                />
                <AppDateTimePicker
                  :model-value="props.endDate"
                  placeholder="Hasta"
                  clearable
                  class="premium-input-compact"
                  :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                  @update:model-value="emit('update:endDate', $event)"
                />
              </div>
            </VCol>
          </VRow>

          <div class="d-flex justify-end mt-4">
            <VBtn
              variant="text"
              color="secondary"
              size="small"
              class="font-weight-black"
              @click="handleClear"
            >
              LIMPIAR FILTROS
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VExpandTransition>
  </div>
</template>

<style scoped>
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
