<script setup>
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery: { type: String, default: "" },
  statusFilter: { type: String, default: "pending" },
});

const emit = defineEmits(["update:searchQuery", "update:statusFilter", "clear"]);

const statusOptions = [
  { title: "Con deuda pendiente", value: "pending" },
  { title: "Con deuda vencida", value: "overdue" },
  { title: "Saldados ($0)", value: "paid" },
  { title: "Todos", value: "all" },
];

const hasAdvancedFilters = computed(() => props.statusFilter !== "pending");
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Buscar por cliente o identificación..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
  >
    <template #advanced-filters>
      <VCol cols="12" sm="6" md="4">
        <VSelect
          :model-value="props.statusFilter"
          :items="statusOptions"
          item-title="title"
          item-value="value"
          label="Estado de cartera"
          density="compact"
          hide-details
          variant="outlined"
          prepend-inner-icon="tabler-filter"
          @update:model-value="emit('update:statusFilter', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
