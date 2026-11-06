<script setup lang="js">
// Filtros de Gastos Fijos basados en AppFilterBase
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  search: { type: String, default: "" },
  currency: { type: String, default: null },
  categoryId: { type: [Number, String], default: null },
  categories: { type: Array, default: () => [] },
  dateFrom: { type: String, default: "" },
  dateTo: { type: String, default: "" },
  isActive: { type: Boolean, default: null },
  loading: { type: Boolean, default: false },
  refreshing: { type: Boolean, default: false },
});

const currencies = ["BS", "USD", "COP"];

const statusOptions = [
  { title: "Activos", value: true },
  { title: "Pausados", value: false },
];

const emit = defineEmits([
  "update:search",
  "update:currency",
  "update:categoryId",
  "update:dateFrom",
  "update:dateTo",
  "update:isActive",
  "clear",
  "add",
  "refresh",
]);

const hasAdvancedFilters = computed(() => {
  return !!(
    props.categoryId ||
    props.currency ||
    props.dateFrom ||
    props.dateTo ||
    props.isActive !== null
  );
});

const formatDateString = (d) => {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
};

function setQuickFilter(days) {
  const today = new Date();

  if (days === "all") {
    emit("update:dateFrom", "");
    emit("update:dateTo", "");
  } else if (days === "current_month") {
    const start = new Date(today.getFullYear(), today.getMonth(), 1);
    const end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    emit("update:dateFrom", formatDateString(start));
    emit("update:dateTo", formatDateString(end));
  } else if (days === "last_month") {
    const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
    emit("update:dateFrom", formatDateString(lastMonth));
    emit("update:dateTo", formatDateString(lastDay));
  } else if (days === "next_7") {
    const next = new Date();
    next.setDate(today.getDate() + 7);
    emit("update:dateFrom", formatDateString(today));
    emit("update:dateTo", formatDateString(next));
  } else {
    const start = new Date();
    start.setDate(today.getDate() - days);
    emit("update:dateFrom", formatDateString(start));
    emit("update:dateTo", formatDateString(today));
  }
}
</script>

<template>
  <AppFilterBase
    :search="props.search"
    :has-advanced-filters="hasAdvancedFilters"
    :show-add="true"
    :show-export="false"
    add-button-text="Nuevo Gasto Fijo"
    search-placeholder="Nombre o Concepto del Gasto Fijo..."
    class="py-1"
    @update:search="emit('update:search', $event)"
    @clear="emit('clear')"
    @add="emit('add')"
  >
    <template #advanced-filters>
      <!-- Categoría -->
      <VCol cols="12" sm="6" md="3">
        <VAutocomplete
          :model-value="props.categoryId"
          :items="props.categories"
          :loading="props.loading"
          placeholder="Categoría"
          item-title="name"
          item-value="id"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-category"
          @update:model-value="emit('update:categoryId', $event)"
        />
      </VCol>

      <!-- Moneda -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.currency"
          :items="currencies"
          placeholder="Moneda"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-coin"
          @update:model-value="emit('update:currency', $event)"
        />
      </VCol>

      <!-- Rango de Fechas -->
      <VCol cols="12" sm="6" md="5">
        <div class="d-flex align-center gap-2">
          <AppDateTimePicker
            :model-value="props.dateFrom"
            placeholder="Desde"
            clearable
            density="compact"
            hide-details
            class="flex-grow-1"
            :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            prepend-inner-icon="tabler-calendar"
            @update:model-value="emit('update:dateFrom', $event)"
          />
          <AppDateTimePicker
            :model-value="props.dateTo"
            placeholder="Hasta"
            clearable
            density="compact"
            hide-details
            class="flex-grow-1"
            :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            prepend-inner-icon="tabler-calendar-check"
            @update:model-value="emit('update:dateTo', $event)"
          />
        </div>
      </VCol>

      <!-- Estado de Plantilla -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.isActive"
          :items="statusOptions"
          item-title="title"
          item-value="value"
          placeholder="Estado"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-toggle-left"
          @update:model-value="emit('update:isActive', $event)"
        />
      </VCol>
    </template>

    <template #actions-extra>
      <!-- Menú de Accesos Rápidos de Fecha -->
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
          <VListItem @click="setQuickFilter('next_7')"><VListItemTitle class="text-xs font-weight-bold">Próximos 7 días</VListItemTitle></VListItem>
          <VListItem @click="setQuickFilter('current_month')"><VListItemTitle class="text-xs font-weight-bold">Mes Actual</VListItemTitle></VListItem>
          <VListItem @click="setQuickFilter('last_month')"><VListItemTitle class="text-xs font-weight-bold">Mes Pasado</VListItemTitle></VListItem>
        </VList>
      </VMenu>

      <!-- Botón Actualizar Avisos -->
      <VBtn
        variant="tonal"
        color="warning"
        size="38"
        icon
        :loading="props.refreshing"
        class="rounded-circle shadow-sm ms-1"
        @click="emit('refresh')"
      >
        <VIcon icon="tabler-refresh" size="20" />
        <VTooltip activator="parent" location="top">Actualizar Avisos</VTooltip>
      </VBtn>
    </template>
  </AppFilterBase>
</template>
