<script setup>
// Filtros de Historial Fiscal
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery: String,
  startDate:   [String, null],
  endDate:     [String, null],
  origins:     { type: Array,   default: () => [] },
  loading:     { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:startDate",
  "update:endDate",
  "clear",
  "export",
  "sort",
]);

const sortOptions = [
  { title: "Precio Mayor",   icon: "tabler-arrow-up",      key: "total_amount", order: "desc" },
  { title: "Precio Menor",   icon: "tabler-arrow-down",    key: "total_amount", order: "asc"  },
  { title: "Fecha Reciente", icon: "tabler-calendar-up",   key: "invoice_date", order: "desc" },
  { title: "Fecha Antigua",  icon: "tabler-calendar-down", key: "invoice_date", order: "asc"  },
];

const hasAdvancedFilters = computed(() => !!(props.startDate || props.endDate));

// Utilidades para fechas (Paridad con orderGeneral.vue)
const toDateString = (date) => date.toISOString().split('T')[0];
const getToday = () => toDateString(new Date());

const setDateHoy = () => {
  const t = new Date();
  emit('update:startDate', toDateString(t));
  emit('update:endDate', toDateString(t));
};

const setDateAyer = () => {
  const a = new Date();
  a.setDate(a.getDate() - 1);
  const s = toDateString(a);
  emit('update:startDate', s);
  emit('update:endDate', s);
};

const setDateSemana = () => {
  const h = new Date();
  const inicio = new Date(h);
  const dia = inicio.getDay();
  const diff = inicio.getDate() - dia + (dia === 0 ? -6 : 1);
  inicio.setDate(diff);
  emit('update:startDate', toDateString(inicio));
  emit('update:endDate', toDateString(h));
};

const setDateMes = () => {
  const h = new Date();
  const inicio = new Date(h.getFullYear(), h.getMonth(), 1);
  emit('update:startDate', toDateString(inicio));
  emit('update:endDate', toDateString(h));
};

const setDateAno = () => {
  const h = new Date();
  const inicio = `${h.getFullYear()}-01-01`;
  emit('update:startDate', inicio);
  emit('update:endDate', toDateString(h));
};
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    :show-export="true"
    search-placeholder="Buscar por ID, Razón, Factura..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @export="(fmt) => emit('export', fmt)"
    @sort="emit('sort', $event)"
  >
    <!-- Slot extra: Rango Rápido de Fechas (Paridad Imagen 1) -->
    <template #search-extra>
      <div class="d-none d-lg-flex align-center gap-2 ms-4 border-s ps-4">
        <span class="text-caption font-weight-bold text-uppercase text-disabled me-1">RANGO:</span>
        <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateHoy">Hoy</VBtn>
        <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateAyer">Ayer</VBtn>
        <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateSemana">Semana</VBtn>
        <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateMes">Mes</VBtn>
        <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateAno">Año</VBtn>
      </div>
    </template>
    <template #advanced-filters>
      <!-- Fecha Desde -->
      <VCol cols="12" sm="6" md="4">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Desde"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <!-- Fecha Hasta -->
      <VCol cols="12" sm="6" md="4">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Hasta"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
