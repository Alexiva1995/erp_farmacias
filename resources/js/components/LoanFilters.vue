<script setup>
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedYear: [Number, String, null],
  statusFilter: [String, null],
  startDate: [String, null],
  endDate: [String, null],
  loanYears: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  showAddButton: { type: Boolean, default: true },
  addButtonText: { type: String, default: "Añadir Préstamo" },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedYear",
  "update:statusFilter",
  "update:startDate",
  "update:endDate",
  "clear",
  "add-loan",
  "sort",
]);

const statusOptions = [
  { title: "Préstamos Activos", value: "active" },
  { title: "Próximos a Vencer", value: "ending_soon" },
  { title: "Vencidos", value: "overdue" },
  { title: "Completados", value: "completed" },
];

const sortOptions = [
  {
    title: "Cuota Mayor",
    icon: "tabler-arrow-up",
    key: "monthly_payment",
    order: "desc",
  },
  {
    title: "Cuota Menor",
    icon: "tabler-arrow-down",
    key: "monthly_payment",
    order: "asc",
  },
  {
    title: "Más Reciente",
    icon: "tabler-calendar-plus",
    key: "loan_date",
    order: "desc",
  },
  {
    title: "Más Antiguo",
    icon: "tabler-calendar-minus",
    key: "loan_date",
    order: "asc",
  },
  {
    title: "Más Cuotas",
    icon: "tabler-hash",
    key: "total_installments",
    order: "desc",
  },
  {
    title: "Menos Cuotas",
    icon: "tabler-hash",
    key: "total_installments",
    order: "asc",
  },
  {
    title: "Saldo Mayor",
    icon: "tabler-currency-dollar",
    key: "remaining_balance",
    order: "desc",
  },
  {
    title: "Saldo Menor",
    icon: "tabler-currency-dollar-off",
    key: "remaining_balance",
    order: "asc",
  },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const selectedSort = ref(null);

const getStorageKey = () =>
  `loan_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

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
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="3" md="3">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar préstamos..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <VCol cols="12" sm="3" md="2">
          <VSelect
            :model-value="props.selectedYear"
            :items="props.loanYears"
            :loading="props.loading"
            label="Año del Préstamo"
            placeholder="Seleccionar año"
            item-title="title"
            item-value="value"
            clearable
            @update:model-value="emit('update:selectedYear', $event)"
          />
        </VCol>

        <VCol cols="12" sm="3" md="3">
          <VSelect
            :model-value="props.statusFilter"
            :items="statusOptions"
            label="Estado del Préstamo"
            placeholder="Seleccionar estado"
            item-title="title"
            item-value="value"
            clearable
            @update:model-value="emit('update:statusFilter', $event)"
          />
        </VCol>

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
        @click="emit('add-loan')"
      >
        {{ props.addButtonText }}
      </VBtn>
    </VCardActions>
  </VCard>
</template>
