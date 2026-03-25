<script setup>
// Filtros de Devoluciones
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  search:    String,
  status:    String,
  supplier:  String,
  startDate: String,
  endDate:   String,
  seller:    String,
  sellers:   Array,
});

const emit = defineEmits([
  "update:search",
  "update:status",
  "update:supplier",
  "update:startDate",
  "update:endDate",
  "update:seller",
  "clear",
]);

const statuses = [
  { title: "Todas",     value: "" },
  { title: "Pendiente", value: "pending" },
  { title: "Aprobado",  value: "Approved" },
  { title: "Rechazado", value: "Rejected" },
];

const hasAdvancedFilters = computed(() =>
  !!(props.startDate || props.endDate || props.status || props.seller)
);
</script>

<template>
  <AppFilterBase
    :search="props.search"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Buscar devolución, producto o N° Orden..."
    @update:search="emit('update:search', $event)"
    @clear="emit('clear')"
  >
    <template #advanced-filters>
      <!-- Fecha Inicial -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Fecha Inicial"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-calendar-event"
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <!-- Fecha Final -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Fecha Final"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-calendar-event"
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>

      <!-- Estado -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.status"
          :items="statuses"
          placeholder="Estado"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-filter-cog"
          variant="outlined"
          @update:model-value="emit('update:status', $event)"
        />
      </VCol>

      <!-- Vendedor / Responsable -->
      <VCol cols="12" sm="6" md="4">
        <VSelect
          :model-value="props.seller"
          :items="props.sellers ?? []"
          item-title="username"
          item-value="id"
          placeholder="Vendedor / Responsable"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-user-search"
          variant="outlined"
          @update:model-value="emit('update:seller', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
