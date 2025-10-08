<script setup>
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref, watch } from "vue";

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

const depreciationOptions = [
  { title: "Depreciación Baja (0-20%)", value: "low" },
  { title: "Depreciación Media (21-50%)", value: "medium" },
  { title: "Depreciación Alta (51-80%)", value: "high" },
  { title: "Muy Depreciado (81%+)", value: "very_high" },
];

const sortOptions = [
  {
    title: "Costo Mayor",
    icon: "tabler-arrow-up",
    key: "cost",
    order: "desc",
  },
  {
    title: "Costo Menor",
    icon: "tabler-arrow-down",
    key: "cost",
    order: "asc",
  },
  {
    title: "Más Reciente",
    icon: "tabler-calendar-plus",
    key: "acquisition_year",
    order: "desc",
  },
  {
    title: "Más Antiguo",
    icon: "tabler-calendar-minus",
    key: "acquisition_year",
    order: "asc",
  },
  {
    title: "Mayor Depreciación",
    icon: "tabler-trending-down",
    key: "depreciation_rate",
    order: "desc",
  },
  {
    title: "Menor Depreciación",
    icon: "tabler-trending-up",
    key: "depreciation_rate",
    order: "asc",
  },
  {
    title: "Valor Actual Mayor",
    icon: "tabler-currency-dollar",
    key: "current_value",
    order: "desc",
  },
  {
    title: "Valor Actual Menor",
    icon: "tabler-currency-dollar-off",
    key: "current_value",
    order: "asc",
  },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const selectedSort = ref(null);

const getStorageKey = () =>
  `furniture_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

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
  const option = sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order
  );
  return option ? option.title : null;
};

const getSelectedSortIcon = () => {
  if (!selectedSort.value) return null;
  const option = sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order
  );
  return option ? option.icon : null;
};

const isOptionSelected = (option) => {
  return (
    selectedSort.value &&
    selectedSort.value.key === option.key &&
    selectedSort.value.order === option.order
  );
};

const handleClear = () => {
  emit("clear");
};

onMounted(() => {
  loadSavedSort();
});

watch(
  () => currentUser.value?.id,
  () => {
    if (currentUser.value?.id) {
      loadSavedSort();
    }
  },
  { immediate: true }
);
</script>

<template>
  <VCard title="Filtros de Mobiliario" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="4">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por nombre del mobiliario..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="4">
          <VSelect
            :model-value="props.selectedYear"
            :items="props.acquisitionYears"
            :loading="props.loading"
            label="Año de Adquisición"
            placeholder="Seleccionar año"
            item-title="title"
            item-value="value"
            clearable
            @update:model-value="emit('update:selectedYear', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="4">
          <VSelect
            :model-value="props.depreciationFilter"
            :items="depreciationOptions"
            label="Nivel de Depreciación"
            placeholder="Seleccionar rango"
            item-title="title"
            item-value="value"
            clearable
            @update:model-value="emit('update:depreciationFilter', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="4">
          <AppDateTimePicker
            :model-value="props.startDate"
            placeholder="Fecha Desde"
            label="Fecha Desde"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:startDate', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="4">
          <AppDateTimePicker
            :model-value="props.endDate"
            placeholder="Fecha Hasta"
            label="Fecha Hasta"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:endDate', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="handleClear">
        Limpiar Filtros
      </VBtn>

      <div class="d-flex align-center gap-2">
        <VMenu>
          <template #activator="{ props: menuProps }">
            <VBtn v-bind="menuProps" variant="tonal">
              Ordenar Por
              <VIcon end icon="tabler-chevron-down" />
            </VBtn>
          </template>
          <VList>
            <VListItem
              v-for="(option, index) in sortOptions"
              :key="index"
              :class="{ 'bg-primary-lighten-5': isOptionSelected(option) }"
              @click="handleSortClick(option)"
            >
              <template #prepend>
                <VIcon :icon="option.icon" size="20" class="me-2" />
              </template>
              <VListItemTitle>{{ option.title }}</VListItemTitle>
              <template #append>
                <VIcon
                  v-if="isOptionSelected(option)"
                  icon="tabler-check"
                  size="16"
                  color="primary"
                />
              </template>
            </VListItem>
          </VList>
        </VMenu>

        <VChip
          v-if="selectedSort"
          color="primary"
          variant="tonal"
          size="small"
          closable
          @click:close="clearSortFilter"
        >
          <VIcon :icon="getSelectedSortIcon()" size="14" class="me-1" />
          {{ getSelectedSortTitle() }}
        </VChip>
      </div>

      <VSpacer />

      <VBtn
        v-if="props.showAddButton"
        color="primary"
        prepend-icon="tabler-plus"
        @click="emit('add-furniture')"
      >
        {{ props.addButtonText }}
      </VBtn>
    </VCardActions>
  </VCard>
</template>
