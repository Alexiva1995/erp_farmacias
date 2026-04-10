<script setup>
// Filtros para clientes CRM
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  buscador:                  String,
  tipo_identificacion_filtro:[String, null],
  company_id_filtro:         [String, null],
  client_type_filtro:        [String, null],
  fechaHasta_filtro:         [String, null],
  fechaDesde_filtro:         [String, null],
  companies:                 { type: Array, default: () => [] },
});

const emit = defineEmits([
  "update:buscador",
  "update:tipo_identificacion_filtro",
  "update:company_id_filtro",
  "update:client_type_filtro",
  "update:fechaHasta_filtro",
  "update:fechaDesde_filtro",
  "clear",
  "add-client",
  "export-pdf",
  "export-excel",
]);

const clientTypeOptions = [
  { title: "VIP",        value: "VIP"       },
  { title: "Frecuente",  value: "Frecuente" },
  { title: "Ocasional",  value: "Ocasional" },
  { title: "En Riesgo",  value: "En Riesgo" },
  { title: "Nuevo",      value: "Nuevo"     },
  { title: "Inactivo",   value: "Inactivo"  },
];

// Indicador de filtros avanzados activos
const hasAdvancedFilters = computed(() =>
  !!(props.tipo_identificacion_filtro || props.company_id_filtro ||
     props.client_type_filtro || props.fechaDesde_filtro || props.fechaHasta_filtro)
);
</script>

<template>
  <AppFilterBase
    :search="props.buscador"
    :has-advanced-filters="hasAdvancedFilters"
    :show-add="true"
    :show-export="true"
    add-button-text="Nuevo Cliente"
    search-placeholder="Buscar por nombre, ID o teléfono..."
    class="py-1"
    @update:search="emit('update:buscador', $event)"
    @clear="emit('clear')"
    @add="emit('add-client')"
    @export="(fmt) => fmt === 'pdf' ? emit('export-pdf') : emit('export-excel', fmt)"
  >
    <template #advanced-filters>
      <!-- Tipo de Identificación -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.tipo_identificacion_filtro"
          :items="['V-', 'J-', 'G-', 'E-']"
          placeholder="Tipo ID"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-id"
          @update:model-value="emit('update:tipo_identificacion_filtro', $event)"
        />
      </VCol>

      <!-- Empresa -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.company_id_filtro"
          :items="props.companies"
          item-title="name"
          item-value="id"
          placeholder="BUSCAR EMPRESA..."
          persistent-placeholder
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-building"
          @update:model-value="emit('update:company_id_filtro', $event)"
        />
      </VCol>

      <!-- Categoría de cliente -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.client_type_filtro"
          :items="clientTypeOptions"
          placeholder="Categoría"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-user-check"
          @update:model-value="emit('update:client_type_filtro', $event)"
        />
      </VCol>

      <!-- Fecha desde -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.fechaDesde_filtro"
          placeholder="Desde"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-calendar"
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:fechaDesde_filtro', $event)"
        />
      </VCol>

      <!-- Fecha hasta -->
      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.fechaHasta_filtro"
          placeholder="Hasta"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-calendar"
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:fechaHasta_filtro', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
