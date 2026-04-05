<script setup>
// Filtro Alternativo (Productos sin Proveedor)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  selectConDescuento: Boolean,
  tipo_de_vista:      Boolean, // deprecada visualmente pero con soporte
  tipo_de_filtracion: String,
  lapso_de_tiempo:    String,
  stock:              String,
  selectedLaboratory: [Array, String, null],
  selectedGroup:      [Array, String, null],
  laboratories:       { type: Array, default: () => [] },
  groups:             { type: Array, default: () => [] },
});

const emit = defineEmits([
  "update:selectConDescuento",
  "update:tipo_de_vista",
  "update:tipo_de_filtracion",
  "update:lapso_de_tiempo",
  "update:stock",
  "update:selectedLaboratory",
  "update:selectedGroup",
  "clear",
]);

const precioOptions = [
  { title: "Full",      value: true  },
  { title: "Descuento", value: false },
];

const tipoFiltracionOpciones = [
  { title: "Promedio",  value: "average"   },
  { title: "Ventas",    value: "sales"     },
  { title: "Combinado", value: "combinado" },
];

const lapsoDeTiempoOpciones = [
  { title: "7 Dias",  value: "7 days"  },
  { title: "15 Dias", value: "15 days" },
  { title: "1 Mes",   value: "1 month" },
  { title: "3 Meses", value: "3 month" },
  { title: "6 Meses", value: "6 month" },
  { title: "1 Año",   value: "1 year"  },
];

const stockOpciones = [
  { title: "Todos",   value: "all"    },
  { title: "Exceso",  value: "exceso" },
  { title: "Fallas",  value: "fallas" },
];

// No tiene buscador libre, siempre mostramos los advanced filters activos o forzados
const hasAdvancedFilters = computed(() => true);
</script>

<template>
  <AppFilterBase
    :search="''"
    :has-advanced-filters="hasAdvancedFilters"
    :force-advanced="true"
    search-placeholder="Filtrar comparador..."
    class="py-1"
    @clear="emit('clear')"
  >
    <!-- Slot oculto de búsqueda dado que todo son dropdowns -->
    <template #search>
      <div class="d-flex align-center flex-grow-1 min-width-0">
        <span class="text-sm font-weight-black uppercase text-disabled ms-2">
          Cálculo Analítico de Reabastecimiento
        </span>
      </div>
    </template>

    <template #advanced-filters>
      <!-- Laboratorio Múltiple -->
      <VCol cols="12" sm="6" md="4">
        <VAutocomplete
          :model-value="props.selectedLaboratory"
          :items="props.laboratories"
          item-title="name"
          item-value="id"
          placeholder="Laboratorios..."
          variant="outlined"
          density="compact"
          hide-details
          clearable
          chips
          multiple
          closable-chips
          prepend-inner-icon="tabler-flask"
          @update:model-value="emit('update:selectedLaboratory', $event)"
        />
      </VCol>

      <!-- Grupos Múltiple -->
      <VCol cols="12" sm="6" md="4">
        <VAutocomplete
          :model-value="props.selectedGroup"
          :items="props.groups"
          item-title="name"
          item-value="id"
          placeholder="Grupos..."
          variant="outlined"
          density="compact"
          hide-details
          clearable
          chips
          multiple
          closable-chips
          prepend-inner-icon="tabler-category"
          @update:model-value="emit('update:selectedGroup', $event)"
        />
      </VCol>

      <!-- Lapso de Tiempo -->
      <VCol cols="12" sm="12" md="4">
        <VSelect
          :model-value="props.lapso_de_tiempo"
          :items="lapsoDeTiempoOpciones"
          placeholder="Lapso de Tiempo"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-calendar-stats"
          @update:model-value="emit('update:lapso_de_tiempo', $event)"
        />
      </VCol>

      <VCol cols="12">
        <VDivider class="border-dashed my-2" />
      </VCol>

      <!-- Calcular Por -->
      <VCol cols="12" sm="4">
        <VSelect
          :model-value="props.tipo_de_filtracion"
          :items="tipoFiltracionOpciones"
          placeholder="Calcular Por"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-math-function"
          @update:model-value="emit('update:tipo_de_filtracion', $event)"
        />
      </VCol>

      <!-- Precio (Con Descuento / Full) -->
      <VCol cols="12" sm="4">
        <VSelect
          :model-value="props.selectConDescuento"
          :items="precioOptions"
          placeholder="Tipo de Precio"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-currency-dollar"
          @update:model-value="emit('update:selectConDescuento', $event)"
        />
      </VCol>

      <!-- Stock Target -->
      <VCol cols="12" sm="4">
        <VSelect
          :model-value="props.stock"
          :items="stockOpciones"
          placeholder="Nivel de Stock"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-brand-databricks"
          @update:model-value="emit('update:stock', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>

<style scoped>
.border-dashed {
  border: 1px dashed rgba(var(--v-border-color), 0.4);
}
</style>
