<script setup>
// Filtros Estado de Resultados (Income Statement)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  startDate: { type: String, default: null },
  endDate:   { type: String, default: null },
});

const emit = defineEmits(["update:startDate", "update:endDate", "clear"]);

const hasAdvancedFilters = computed(() => !!(props.startDate || props.endDate));

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
    :search="''"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Filtros del período..."
    @clear="emit('clear')"
  >
    <!-- Para IncomeStatement no hay buscador de texto, usamos slot search para fechas y quick actions -->
    <template #search>
      <div class="d-flex align-center gap-2 flex-grow-1 min-width-0 w-100">
        <!-- Vence Desde -->
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Desde"
          clearable
          density="compact"
          hide-details
          class="flex-grow-1"
          style="min-width: 130px;"
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:startDate', $event)"
        />
        
        <span class="text-disabled d-none d-sm-inline">—</span>
        
        <!-- Vence Hasta -->
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Hasta"
          clearable
          density="compact"
          hide-details
          class="flex-grow-1"
          style="min-width: 130px;"
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:endDate', $event)"
        />
      </div>
    </template>

    <template #search-extra>
      <!-- Menú de Accesos Rápidos -->
      <VMenu location="bottom end">
        <template #activator="{ props: menuProps }">
          <VBtn
            v-bind="menuProps"
            variant="tonal"
            color="primary"
            class="d-none d-md-flex rounded-lg h-100"
            prepend-icon="tabler-calendar-time"
          >
            Rápido
          </VBtn>
        </template>
        <VList density="compact">
          <VListItem @click="setQuickFilter('all')"><VListItemTitle>Todo</VListItemTitle></VListItem>
          <VListItem @click="setQuickFilter(15)"><VListItemTitle>15 días</VListItemTitle></VListItem>
          <VListItem @click="setQuickFilter(30)"><VListItemTitle>30 días</VListItemTitle></VListItem>
          <VListItem @click="setQuickFilter(60)"><VListItemTitle>60 días</VListItemTitle></VListItem>
          <VListItem @click="setQuickFilter('current_month')"><VListItemTitle>Mes Actual</VListItemTitle></VListItem>
          <VListItem @click="setQuickFilter('last_month')"><VListItemTitle>Mes Pasado</VListItemTitle></VListItem>
        </VList>
      </VMenu>
    </template>
  </AppFilterBase>
</template>
