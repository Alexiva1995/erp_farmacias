<script setup>
// Filtros de Proveedores
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery: String,
  debtFilter: [String, null],
  minScore: [Number, String, null],
  type: [String, null],
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:debtFilter",
  "update:minScore",
  "update:type",
  "clear",
  "sort",
  "add-supplier",
]);

const sortOptions = [
  { title: "Deuda mayor", icon: "tabler-arrow-up", key: "debt", order: "desc" },
  {
    title: "Deuda menor",
    icon: "tabler-arrow-down",
    key: "debt",
    order: "asc",
  },
  {
    title: "Más Calificación",
    icon: "tabler-plus",
    key: "latestScore.score",
    order: "desc",
  },
  {
    title: "Menos Calificación",
    icon: "tabler-minus",
    key: "latestScore.score",
    order: "asc",
  },
];

const debtOptions = [
  { title: "Todos", value: null },
  { title: "Con Deuda", value: "with_debt" },
  { title: "Sin Deuda", value: "no_debt" },
];

const scoreOptions = [
  { title: "Todas", value: null },
  { title: "4.0+ Estrellas", value: 80 },
  { title: "3.0+ Estrellas", value: 60 },
  { title: "2.0+ Estrellas", value: 40 },
];

const typeOptions = [
  { title: "Todos", value: null },
  { title: "Droguería", value: "drogueria" },
  { title: "Proveedor Externo", value: "externo" },
];

const hasAdvancedFilters = computed(
  () => !!(props.debtFilter || props.minScore || props.type),
);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-sort="true"
    :sort-options="sortOptions"
    :show-add="true"
    add-button-text="Añadir Proveedor"
    search-placeholder="Buscar por ID, Nombre o RIF..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @sort="(sortFilter) => emit('sort', sortFilter)"
    @add="emit('add-supplier', 'drogueria')"
    class="py-1"
  >
    <template #actions-extra>
      <!-- Botón para añadir proveedor externo -->
      <VBtn
        icon
        color="info"
        variant="tonal"
        size="38"
        class="rounded-circle shadow-sm"
        @click="emit('add-supplier', 'externo')"
      >
        <VIcon icon="tabler-building-store" />
        <VTooltip activator="parent" location="top">Añadir Proveedor Externo</VTooltip>
      </VBtn>
    </template>

    <template #advanced-filters>
      <!-- Filtro de Deuda -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.debtFilter"
          :items="debtOptions"
          placeholder="Estado de Deuda"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-coin"
          @update:model-value="emit('update:debtFilter', $event)"
        />
      </VCol>

      <!-- Filtro de Calificación -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.minScore"
          :items="scoreOptions"
          placeholder="Calificación Mínima"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-star"
          @update:model-value="emit('update:minScore', $event)"
        />
      </VCol>

      <!-- Filtro de Tipo -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.type"
          :items="typeOptions"
          placeholder="Tipo de Proveedor"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-tags"
          @update:model-value="emit('update:type', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
