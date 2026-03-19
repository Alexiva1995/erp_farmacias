<script setup>
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  searchQuery: String,
  selectedYear: [Number, String, null],
  depreciationFilter: [String, null],
  startDate: [String, null],
  endDate: [String, null],
  acquisitionYears: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  showAddButton: { type: Boolean, default: true },
  addButtonText: { type: String, default: "Añadir Mobiliario" },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedYear",
  "update:depreciationFilter",
  "update:startDate",
  "update:endDate",
  "clear",
  "add-furniture",
  "sort",
]);

const { mobile } = useDisplay();
const isFiltersExpanded = ref(false);

const depreciationOptions = [
  { title: "Baja (0-20%)", value: "low" },
  { title: "Media (21-50%)", value: "medium" },
  { title: "Alta (51-80%)", value: "high" },
  { title: "Muy Depreciado (81%+)", value: "very_high" },
];

const sortOptions = [
  { title: "Costo Mayor", icon: "tabler-arrow-up", key: "cost", order: "desc" },
  { title: "Costo Menor", icon: "tabler-arrow-down", key: "cost", order: "asc" },
  { title: "Más Reciente", icon: "tabler-calendar-plus", key: "acquisition_year", order: "desc" },
  { title: "Más Antiguo", icon: "tabler-calendar-minus", key: "acquisition_year", order: "asc" },
  { title: "Mayor Depreciación", icon: "tabler-trending-down", key: "depreciation_rate", order: "desc" },
  { title: "Menor Depreciación", icon: "tabler-trending-up", key: "depreciation_rate", order: "asc" },
  { title: "Valor Actual Mayor", icon: "tabler-currency-dollar", key: "current_value", order: "desc" },
  { title: "Valor Actual Menor", icon: "tabler-currency-dollar-off", key: "current_value", order: "asc" },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);
const selectedSort = ref(null);

const activeFiltersCount = computed(() => {
  let count = 0;
  if (props.selectedYear) count++;
  if (props.depreciationFilter) count++;
  if (props.startDate) count++;
  if (props.endDate) count++;
  return count;
});

const getStorageKey = () => `furniture_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

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
  const option = sortOptions.find(opt => opt.key === selectedSort.value.key && opt.order === selectedSort.value.order);
  return option ? option.title : null;
};

const getSelectedSortIcon = () => {
  if (!selectedSort.value) return null;
  const option = sortOptions.find(opt => opt.key === selectedSort.value.key && opt.order === selectedSort.value.order);
  return option ? option.icon : null;
};

const isOptionSelected = (option) => {
  return selectedSort.value && selectedSort.value.key === option.key && selectedSort.value.order === option.order;
};

const handleClear = () => {
  emit("clear");
};

onMounted(() => loadSavedSort());

watch(() => currentUser.value?.id, () => {
  if (currentUser.value?.id) loadSavedSort();
}, { immediate: true });
</script>

<template>
  <VCard class="mb-6 rounded-xl border shadow-sm overlap-overflow">
    <VCardText class="pa-4">
      <div class="d-flex align-center flex-wrap gap-4">
        <!-- Búsqueda Principal -->
        <div class="flex-grow-1 min-w-[240px]">
          <VTextField
            :model-value="props.searchQuery"
            prepend-inner-icon="tabler-search"
            placeholder="Buscar por nombre del mobiliario..."
            variant="outlined"
            density="compact"
            hide-details
            clearable
            class="bg-surface rounded-lg"
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </div>

        <!-- Acciones Rápidas -->
        <div class="d-flex align-center gap-2">
          <VTooltip text="Filtros Avanzados">
            <template #activator="{ props: tooltip }">
              <VBtn
                v-bind="tooltip"
                variant="tonal"
                :color="isFiltersExpanded ? 'primary' : 'secondary'"
                size="40"
                class="rounded-lg"
                @click="isFiltersExpanded = !isFiltersExpanded"
              >
                <VBadge
                  v-if="activeFiltersCount > 0"
                  :content="activeFiltersCount"
                  color="error"
                  location="top end"
                  offset-x="-2"
                  offset-y="-2"
                >
                  <VIcon icon="tabler-filter" size="20" />
                </VBadge>
                <VIcon v-else icon="tabler-filter" size="20" />
              </VBtn>
            </template>
          </VTooltip>

          <VMenu>
            <template #activator="{ props: menuProps }">
              <VTooltip text="Ordenar por">
                <template #activator="{ props: tooltip }">
                  <VBtn
                    v-bind="{ ...menuProps, ...tooltip }"
                    variant="tonal"
                    color="secondary"
                    size="40"
                    class="rounded-lg"
                  >
                    <VIcon icon="tabler-sort-ascending" size="20" />
                  </VBtn>
                </template>
              </VTooltip>
            </template>
            <VList density="compact" class="rounded-lg">
              <VListItem
                v-for="(option, index) in sortOptions"
                :key="index"
                :active="isOptionSelected(option)"
                color="primary"
                @click="handleSortClick(option)"
              >
                <template #prepend>
                  <VIcon :icon="option.icon" size="18" class="mr-2" />
                </template>
                <VListItemTitle class="text-caption font-weight-bold uppercase">{{ option.title }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <VBtn
            v-if="props.showAddButton"
            color="primary"
            class="rounded-lg"
            @click="emit('add-furniture')"
          >
            <VIcon start icon="tabler-plus" size="18" />
            <span v-if="!mobile">{{ props.addButtonText }}</span>
          </VBtn>

          <VBtn
            v-if="activeFiltersCount > 0 || props.searchQuery"
            variant="text"
            color="error"
            density="compact"
            class="text-caption font-weight-bold ml-2"
            @click="handleClear"
          >
            LIMPIAR
          </VBtn>
        </div>
      </div>

      <!-- Filtros Expandibles -->
      <VExpandTransition>
        <div v-show="isFiltersExpanded">
          <VDivider class="my-4 border-dashed" />
          <VRow>
            <VCol cols="12" sm="6" md="3">
              <div class="text-caption font-weight-bold mb-2 text-uppercase text-disabled">
                Año de Adquisición
              </div>
              <VSelect
                :model-value="props.selectedYear"
                :items="props.acquisitionYears"
                :loading="props.loading"
                placeholder="Seleccionar año"
                item-title="title"
                item-value="value"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                class="rounded-lg"
                @update:model-value="emit('update:selectedYear', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <div class="text-caption font-weight-bold mb-2 text-uppercase text-disabled">
                Nivel de Depreciación
              </div>
              <VSelect
                :model-value="props.depreciationFilter"
                :items="depreciationOptions"
                placeholder="Seleccionar rango"
                item-title="title"
                item-value="value"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                class="rounded-lg"
                @update:model-value="emit('update:depreciationFilter', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <div class="text-caption font-weight-bold mb-2 text-uppercase text-disabled">
                Adquirido Desde
              </div>
              <AppDateTimePicker
                :model-value="props.startDate"
                placeholder="Fecha inicio"
                variant="outlined"
                density="compact"
                hide-details
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:startDate', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <div class="text-caption font-weight-bold mb-2 text-uppercase text-disabled">
                Adquirido Hasta
              </div>
              <AppDateTimePicker
                :model-value="props.endDate"
                placeholder="Fecha fin"
                variant="outlined"
                density="compact"
                hide-details
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:endDate', $event)"
              />
            </VCol>
          </VRow>

          <!-- Badges de Orden Activo -->
          <div v-if="selectedSort" class="mt-4 d-flex align-center gap-2">
            <span class="text-caption text-disabled font-weight-bold uppercase">Orden:</span>
            <VChip
              color="primary"
              variant="tonal"
              size="small"
              closable
              class="rounded-lg font-weight-bold"
              @click:close="clearSortFilter"
            >
              <VIcon :icon="getSelectedSortIcon()" size="14" class="mr-1" />
              {{ getSelectedSortTitle() }}
            </VChip>
          </div>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.overlap-overflow {
  overflow: visible !important;
}

.border-dashed {
  border-style: dashed !important;
  opacity: 0.15;
}

:deep(.v-field__outline) {
  --v-field-border-opacity: 0.12;
}

:deep(.v-field--focused .v-field__outline) {
  --v-field-border-opacity: 1;
}

:deep(.flatpickr-input) {
  block-size: 40px !important;
}
</style>
