<script setup>
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedLaboratory: [Number, String, null],
  discrepancyFilter: [String, null],
  selectedUser: [Number, String, null],
  laboratories: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:discrepancyFilter",
  "update:selectedUser",
  "clear",
  "sort",
]);

const sortOptions = [
  { title: "Producto (A-Z)", icon: "tabler-sort-ascending-letters", key: "product.name", order: "asc" },
  { title: "Producto (Z-A)", icon: "tabler-sort-descending-letters", key: "product.name", order: "desc" },
  { title: "Laboratorio (A-Z)", icon: "tabler-sort-ascending-letters", key: "laboratory.name", order: "asc" },
  { title: "Laboratorio (Z-A)", icon: "tabler-sort-descending-letters", key: "laboratory.name", order: "desc" },
  { title: "Fecha (Reciente)", icon: "tabler-calendar-time", key: "created_at", order: "desc" },
  { title: "Fecha (Antiguo)", icon: "tabler-calendar-time", key: "created_at", order: "asc" },
  { title: "Mayor Discrepancia", icon: "tabler-alert-triangle", key: "discrepancy", order: "desc" },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);
const selectedSort = ref(null);
const isAdvancedFiltersVisible = ref(false);

const getStorageKey = () => `inventory_cycle_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

const loadSavedSort = () => {
  try {
    const saved = localStorage.getItem(getStorageKey());
    if (saved) {
      const parsedSort = JSON.parse(saved);
      if (sortOptions.some(opt => opt.key === parsedSort.key && opt.order === parsedSort.order)) {
        selectedSort.value = parsedSort;
        emit("sort", parsedSort);
      }
    }
  } catch (error) {
    console.error("Error loading saved sort:", error);
  }
};

const saveSortFilter = (sortFilter) => {
  try {
    localStorage.setItem(getStorageKey(), JSON.stringify(sortFilter));
  } catch (error) {
    console.error("Error saving sort:", error);
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
  localStorage.removeItem(getStorageKey());
  emit("sort", { key: undefined, order: undefined });
};

const getSelectedSortTitle = computed(() => {
  if (!selectedSort.value) return null;
  return sortOptions.find(opt => opt.key === selectedSort.value.key && opt.order === selectedSort.value.order)?.title;
});

const getSelectedSortIcon = computed(() => {
  if (!selectedSort.value) return null;
  return sortOptions.find(opt => opt.key === selectedSort.value.key && opt.order === selectedSort.value.order)?.icon;
});

const isOptionSelected = (option) => {
  return selectedSort.value && selectedSort.value.key === option.key && selectedSort.value.order === option.order;
};

onMounted(() => {
  if (currentUser.value?.id) loadSavedSort();
});

watch(() => currentUser.value?.id, (newId) => { if (newId) loadSavedSort(); });
</script>

<template>
  <VCard class="mb-6 overflow-visible" variant="flat" border>
    <VCardText class="pa-3">
      <!-- Fila Principal -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador -->
        <VCol cols="12" md="5" lg="6">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar Producto, C. Activo, ID..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            persistent-placeholder
            hide-details
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <VSpacer class="d-none d-md-block" />

        <!-- Acciones -->
        <VCol cols="auto" class="d-flex align-center gap-1">
          <IconBtn
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            variant="tonal"
            @click="isAdvancedFiltersVisible = !isAdvancedFiltersVisible"
          >
            <VIcon icon="tabler-filter" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
          </IconBtn>

          <VMenu>
            <template #activator="{ props: menuProps }">
              <IconBtn 
                v-bind="menuProps" 
                :color="selectedSort ? 'primary' : 'secondary'"
                variant="tonal"
              >
                <VIcon :icon="selectedSort ? getSelectedSortIcon : 'tabler-sort-descending'" />
                <VTooltip activator="parent" location="top">
                  {{ selectedSort ? `Ordenado por: ${getSelectedSortTitle}` : 'Ordenar resultados' }}
                </VTooltip>
              </IconBtn>
            </template>
            <VList density="compact">
              <VListItem
                v-for="(option, index) in sortOptions"
                :key="index"
                :active="isOptionSelected(option)"
                color="primary"
                @click="handleSortClick(option)"
              >
                <template #prepend>
                  <VIcon :icon="option.icon" size="20" class="me-2" />
                </template>
                <VListItemTitle class="font-weight-bold text-xs">{{ option.title }}</VListItemTitle>
              </VListItem>
              <VDivider v-if="selectedSort" />
              <VListItem v-if="selectedSort" color="error" @click="clearSortFilter">
                <template #prepend>
                  <VIcon icon="tabler-trash" size="20" class="me-2 text-error" />
                </template>
                <VListItemTitle class="text-error font-weight-bold text-xs">Limpiar Orden</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <IconBtn color="secondary" variant="tonal" @click="emit('clear')">
            <VIcon icon="tabler-filter-off" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </IconBtn>
        </VCol>
      </VRow>

      <!-- Panel de Filtros Avanzados -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-3 border-opacity-10" />
          <VRow class="gap-y-3">
            <!-- Laboratorio -->
            <VCol cols="12" sm="4">
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
                variant="outlined"
                prepend-inner-icon="tabler-building"
                @update:model-value="emit('update:selectedLaboratory', $event)"
              />
            </VCol>

            <!-- Discrepancia -->
            <VCol cols="12" sm="4">
              <VSelect
                :model-value="props.discrepancyFilter"
                :items="[
                  { title: 'Con Discrepancia', value: 'with_discrepancy' },
                  { title: 'Sobrantes', value: 'surplus' },
                  { title: 'Faltantes', value: 'shortage' },
                  { title: 'Sin Discrepancia', value: 'exact' }
                ]"
                placeholder="Tipo de Discrepancia"
                clearable
                density="compact"
                hide-details
                variant="outlined"
                prepend-inner-icon="tabler-alert-circle"
                @update:model-value="emit('update:discrepancyFilter', $event)"
              />
            </VCol>

            <!-- Usuario -->
            <VCol cols="12" sm="4">
              <VAutocomplete
                :model-value="props.selectedUser"
                :items="props.users"
                :loading="props.loading"
                placeholder="Usuario del Conteo"
                item-title="display_name"
                item-value="id"
                clearable
                density="compact"
                hide-details
                variant="outlined"
                prepend-inner-icon="tabler-user"
                @update:model-value="emit('update:selectedUser', $event)"
              />
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
.gap-y-3 { row-gap: 12px !important; }

.v-list-item {
  min-block-size: 32px !important;
}

.text-xs {
  font-size: 0.75rem !important;
}
</style>
