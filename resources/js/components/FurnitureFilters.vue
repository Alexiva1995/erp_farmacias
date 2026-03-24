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
  <VCard class="mb-6 border-0 shadow-sm overflow-hidden">
    <VCardText class="pa-3">
      <!-- Barra de Búsqueda Principal (Siempre Visible) -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="6" lg="5">
          <AppTextField
            :model-value="props.searchQuery"
            prepend-inner-icon="tabler-search"
            placeholder="Buscar por nombre del mobiliario..."
            density="compact"
            hide-details
            clearable
            class="premium-input-compact"
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-1">
          <!-- Toggle Filtros -->
          <VBtn
            icon
            variant="tonal"
            :color="isFiltersExpanded ? 'primary' : 'secondary'"
            size="38"
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
              <VIcon :icon="isFiltersExpanded ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
            </VBadge>
            <VIcon v-else :icon="isFiltersExpanded ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
          </VBtn>

          <!-- Menú Ordenación -->
          <VMenu>
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon
                variant="tonal"
                color="secondary"
                size="38"
              >
                <VIcon :icon="getSelectedSortIcon() || 'tabler-sort-ascending'" size="20" />
                <VTooltip activator="parent" location="top">Ordenar por: {{ getSelectedSortTitle() || 'Criterio' }}</VTooltip>
              </VBtn>
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

          <!-- Añadir Mobiliario -->
          <VBtn
            v-if="props.showAddButton"
            icon
            color="primary"
            size="38"
            @click="emit('add-furniture')"
          >
            <VIcon icon="tabler-plus" size="20" />
            <VTooltip activator="parent" location="top">{{ props.addButtonText }}</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2 border-opacity-10" />

          <!-- Limpiar Filtros (Siempre Visible) -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            @click="handleClear"
          >
            <VIcon icon="tabler-eraser" size="20" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </div>
      </VRow>

      <!-- Panel de Filtros Avanzado -->
      <VExpandTransition>
        <div v-show="isFiltersExpanded">
          <VDivider class="my-3 border-opacity-10" />
          
          <VRow>
            <VCol cols="12" sm="6" md="3">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">
                Año de Adquisición
              </span>
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
                class="premium-select-compact"
                @update:model-value="emit('update:selectedYear', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">
                Nivel de Depreciación
              </span>
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
                class="premium-select-compact"
                @update:model-value="emit('update:depreciationFilter', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">
                Adquirido Desde
              </span>
              <AppDateTimePicker
                :model-value="props.startDate"
                placeholder="Fecha inicio"
                density="compact"
                hide-details
                class="premium-input-compact"
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:startDate', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">
                Adquirido Hasta
              </span>
              <AppDateTimePicker
                :model-value="props.endDate"
                placeholder="Fecha fin"
                density="compact"
                hide-details
                class="premium-input-compact"
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:endDate', $event)"
              />
            </VCol>
          </VRow>

          <!-- Badges de Orden Activo -->
          <div v-if="selectedSort" class="mt-4 d-flex align-center gap-2">
            <span class="text-super-xs font-weight-black text-disabled uppercase">Orden Activo:</span>
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
