<script setup>
// Filtros Estado de Resultados (Income Statement)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery: { type: String, default: "" },
  startDate: { type: String, default: null },
  endDate:   { type: String, default: null },
  selectedType: { type: String, default: null },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:startDate",
  "update:endDate",
  "update:selectedType",
  "clear"
]);

const hasAdvancedFilters = computed(() => !!(props.startDate || props.endDate || props.selectedType));

const typeOptions = [
  { title: "Ingresos", value: "sale"    },
  { title: "Egresos",  value: "expense" },
];

function setQuickFilter(days) {
  const today = new Date();
  const start = new Date(today);

  if (days === 'all') {
    emit('update:startDate', null);
    emit('update:endDate', null);
  } else if (days === 'current_month') {
    start.setDate(1);
    emit('update:startDate', start.toISOString().split('T')[0]);
    emit('update:endDate', today.toISOString().split('T')[0]);
  } else if (days === 'last_month') {
    const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
    emit('update:startDate', lastMonth.toISOString().split('T')[0]);
    emit('update:endDate', lastDay.toISOString().split('T')[0]);
  } else {
    start.setDate(today.getDate() - days);
    emit('update:startDate', start.toISOString().split('T')[0]);
    emit('update:endDate', today.toISOString().split('T')[0]);
  }
}
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Buscar registro..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
  >
    <template #advanced-filters>
      <!-- Rango de Fechas -->
      <VCol cols="12" sm="6" md="4">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Fecha Inicial"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <VCol cols="12" sm="6" md="4">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Fecha Final"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-check"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>

      <!-- Tipo de Transacción -->
      <VCol cols="12" sm="6" md="4">
        <VSelect
          :model-value="props.selectedType"
          :items="typeOptions"
          placeholder="Tipo de Operación"
          clearable
          density="compact"
          hide-details
          variant="outlined"
          prepend-inner-icon="tabler-arrows-left-right"
          @update:model-value="emit('update:selectedType', $event)"
        />
      </VCol>
    </template>

    <template #actions-extra>
      <!-- Menú de Accesos Rápidos -->
      <VMenu location="bottom end">
        <template #activator="{ props: menuProps }">
          <VBtn
            v-bind="menuProps"
            variant="tonal"
            color="info"
            size="38"
            icon
            class="rounded-circle shadow-sm ms-1"
          >
            <VIcon icon="tabler-calendar-time" size="20" />
            <VTooltip activator="parent" location="top">Períodos Rápidos</VTooltip>
          </VBtn>
        </template>
        <VList density="compact" class="rounded-lg shadow-lg border-0 pa-2">
          <VListItem @click="setQuickFilter('all')"><VListItemTitle class="text-xs font-weight-bold">Todo</VListItemTitle></VListItem>
          <VListItem @click="setQuickFilter(15)"><VListItemTitle class="text-xs font-weight-bold">15 días</VListItemTitle></VListItem>
          <VListItem @click="setQuickFilter(30)"><VListItemTitle class="text-xs font-weight-bold">30 días</VListItemTitle></VListItem>
          <VListItem @click="setQuickFilter(60)"><VListItemTitle class="text-xs font-weight-bold">60 días</VListItemTitle></VListItem>
          <VListItem @click="setQuickFilter('current_month')"><VListItemTitle class="text-xs font-weight-bold">Mes Actual</VListItemTitle></VListItem>
          <VListItem @click="setQuickFilter('last_month')"><VListItemTitle class="text-xs font-weight-bold">Mes Pasado</VListItemTitle></VListItem>
        </VList>
      </VMenu>
    </template>
  </AppFilterBase>
</template>

<style scoped>
.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}
</style>
