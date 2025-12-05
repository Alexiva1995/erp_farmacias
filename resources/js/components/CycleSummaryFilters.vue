<script setup>
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  startDate: [String, null],
  endDate: [String, null],
  cycleStatus: [String, null],
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:startDate",
  "update:endDate",
  "update:cycleStatus",
  "clear",
  "sort",
]);

const sortOptions = [
  {
    title: "Ciclo ID (Reciente)",
    icon: "tabler-sort-descending",
    key: "cycle_id",
    order: "desc",
  },
  {
    title: "Ciclo ID (Antiguo)",
    icon: "tabler-sort-ascending",
    key: "cycle_id",
    order: "asc",
  },
  {
    title: "Más Recientes",
    icon: "tabler-calendar-plus",
    key: "start_date",
    order: "desc",
  },
  {
    title: "Más Antiguos",
    icon: "tabler-calendar-minus",
    key: "start_date",
    order: "asc",
  },
  {
    title: "Mayor Total Sobrante",
    icon: "tabler-trending-up",
    key: "total_surplus",
    order: "desc",
  },
  {
    title: "Mayor Total Faltante",
    icon: "tabler-trending-down",
    key: "total_shortage",
    order: "desc",
  },
  {
    title: "Mayor Total Neto",
    icon: "tabler-arrow-up",
    key: "net_total",
    order: "desc",
  },
  {
    title: "Menor Total Neto",
    icon: "tabler-arrow-down",
    key: "net_total",
    order: "asc",
  },
];

const cycleStatusOptions = [
  { title: "Activo", value: "active" },
  { title: "Cerrado", value: "closed" },
  { title: "Cancelado", value: "cancelled" },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);
const selectedSort = ref(null);

const getStorageKey = () =>
  `cycle_summary_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

const loadSavedSort = () => {
  try {
    const saved = localStorage.getItem(getStorageKey());
    if (saved) {
      const parsedSort = JSON.parse(saved);
      if (
        sortOptions.some(
          (opt) => opt.key === parsedSort.key && opt.order === parsedSort.order
        )
      ) {
        selectedSort.value = parsedSort;
        emit("sort", parsedSort);
      }
    }
  } catch (error) {
    console.error("Error al cargar el filtro de ordenamiento guardado:", error);
  }
};

const saveSortFilter = (sortFilter) => {
  try {
    localStorage.setItem(getStorageKey(), JSON.stringify(sortFilter));
  } catch (error) {
    console.error("Error al guardar el filtro de ordenamiento:", error);
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
  return sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order
  )?.title;
});

const getSelectedSortIcon = computed(() => {
  if (!selectedSort.value) return null;
  return sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order
  )?.icon;
});

const isOptionSelected = (option) => {
  return (
    selectedSort.value &&
    selectedSort.value.key === option.key &&
    selectedSort.value.order === option.order
  );
};

onMounted(() => {
  if (currentUser.value?.id) {
    loadSavedSort();
  }
});

watch(
  () => currentUser.value?.id,
  (newId) => {
    if (newId) {
      loadSavedSort();
    }
  }
);
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="3" md="2">
          <AppDateTimePicker
            :model-value="props.startDate"
            placeholder="Desde"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:startDate', $event)"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <AppDateTimePicker
            :model-value="props.endDate"
            placeholder="Hasta"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:endDate', $event)"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <VAutocomplete
            :model-value="props.cycleStatus"
            :items="cycleStatusOptions"
            :loading="props.loading"
            label="Estado del Ciclo"
            placeholder="Ciclo"
            item-title="title"
            item-value="value"
            clearable
            @update:model-value="emit('update:cycleStatus', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
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
          <VIcon :icon="getSelectedSortIcon" size="14" class="me-1" />
          {{ getSelectedSortTitle }}
        </VChip>
      </div>

      <VSpacer />
    </VCardActions>
  </VCard>
</template>
