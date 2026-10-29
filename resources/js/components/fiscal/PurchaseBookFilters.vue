<script setup>
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  year: { type: [Number, String, null], default: () => new Date().getFullYear() },
  month: { type: [Number, String, null], default: null },
  status: { type: [String, null], default: null },
  loading: { type: Boolean, default: false },
  showAdd: { type: Boolean, default: true },
});

const emit = defineEmits([
  "update:year",
  "update:month",
  "update:status",
  "clear",
  "create-period",
  "refresh",
]);

const yearsList = [2024, 2025, 2026, 2027, 2028];

const monthsList = [
  { value: 1, title: "Enero" },
  { value: 2, title: "Febrero" },
  { value: 3, title: "Marzo" },
  { value: 4, title: "Abril" },
  { value: 5, title: "Mayo" },
  { value: 6, title: "Junio" },
  { value: 7, title: "Julio" },
  { value: 8, title: "Agosto" },
  { value: 9, title: "Septiembre" },
  { value: 10, title: "Octubre" },
  { value: 11, title: "Noviembre" },
  { value: 12, title: "Diciembre" },
];

const statusList = [
  { title: "Todos los Estados", value: null },
  { title: "Abiertos", value: "open" },
  { title: "Cerrados", value: "closed" },
];

const hasAdvancedFilters = computed(() => {
  return props.month !== null || props.status !== null || props.year !== new Date().getFullYear();
});
</script>

<template>
  <AppFilterBase
    :has-advanced-filters="hasAdvancedFilters"
    :loading="loading"
    :show-add="showAdd"
    add-button-text="Nuevo Período"
    search-placeholder="Buscar períodos fiscales..."
    :show-export="false"
    :show-sort="false"
    class="mb-4"
    @add="emit('create-period')"
    @clear="emit('clear')"
  >
    <!-- Filtros de la Barra Principal -->
    <template #filters>
      <VCol cols="12" sm="4" md="4">
        <VSelect
          :model-value="year"
          :items="yearsList"
          label="Año Fiscal"
          density="compact"
          variant="outlined"
          prepend-inner-icon="tabler-calendar"
          hide-details
          @update:model-value="emit('update:year', $event)"
        />
      </VCol>

      <VCol cols="12" sm="4" md="4">
        <VSelect
          :model-value="month"
          :items="monthsList"
          item-title="title"
          item-value="value"
          label="Mes"
          density="compact"
          variant="outlined"
          prepend-inner-icon="tabler-calendar-month"
          clearable
          hide-details
          @update:model-value="emit('update:month', $event)"
        />
      </VCol>

      <VCol cols="12" sm="4" md="4">
        <VSelect
          :model-value="status"
          :items="statusList"
          item-title="title"
          item-value="value"
          label="Estado"
          density="compact"
          variant="outlined"
          prepend-inner-icon="tabler-lock-access"
          hide-details
          @update:model-value="emit('update:status', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
