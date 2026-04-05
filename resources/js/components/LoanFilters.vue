<script setup>
// Filtros para Préstamos
import AppFilterBase from "@/components/AppFilterBase.vue";
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
  { title: "Activos", value: "active" },
  { title: "Próximos a Vencer", value: "ending_soon" },
  { title: "Vencidos", value: "overdue" },
  { title: "Completados", value: "completed" },
];

const sortOptions = [
  { title: "Cuota Mayor", icon: "tabler-arrow-up", key: "monthly_payment", order: "desc" },
  { title: "Cuota Menor", icon: "tabler-arrow-down", key: "monthly_payment", order: "asc" },
  { title: "Más Reciente", icon: "tabler-calendar-plus", key: "loan_date", order: "desc" },
  { title: "Más Antiguo", icon: "tabler-calendar-minus", key: "loan_date", order: "asc" },
  { title: "Saldo Mayor", icon: "tabler-currency-dollar", key: "remaining_balance", order: "desc" },
  { title: "Saldo Menor", icon: "tabler-currency-dollar-off", key: "remaining_balance", order: "asc" },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const hasAdvancedFilters = computed(() => (
  !!(props.selectedYear || props.statusFilter || props.startDate || props.endDate)
));

const getStorageKey = () => `loan_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

const handleSortClick = (sortFilter) => {
  if (sortFilter.key) {
    localStorage.setItem(getStorageKey(), JSON.stringify(sortFilter));
  } else {
    localStorage.removeItem(getStorageKey());
  }
  emit("sort", sortFilter);
};

onMounted(() => {
  try {
    const saved = localStorage.getItem(getStorageKey());
    if (saved) {
      const parsedSort = JSON.parse(saved);
      emit("sort", parsedSort);
    }
  } catch (e) {
    console.error("Error al cargar ordenación guardada", e);
  }
});
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    :show-add="props.showAddButton"
    :add-button-text="props.addButtonText"
    search-placeholder="ID, Referencia o Concepto..."
    class="mb-6 py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @add="emit('add-loan')"
    @sort="handleSortClick"
  >
    <template #advanced-filters>
      <!-- Año -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.selectedYear"
          :items="props.loanYears"
          :loading="props.loading"
          placeholder="Año del Préstamo"
          item-title="title"
          item-value="value"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-calendar"
          @update:model-value="emit('update:selectedYear', $event)"
        />
      </VCol>

      <!-- Estado -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.statusFilter"
          :items="statusOptions"
          placeholder="Estado del Préstamo"
          item-title="title"
          item-value="value"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-activity"
          @update:model-value="emit('update:statusFilter', $event)"
        />
      </VCol>

      <!-- Rango de Fechas -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Fecha Inicial"
          density="compact"
          hide-details
          clearable
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Fecha Final"
          density="compact"
          hide-details
          clearable
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-check"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}
</style>
