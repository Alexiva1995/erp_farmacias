<script setup>
// Filtros IA Asistente Pedidos (Proveedores)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  selectConDescuento: Boolean,
  tipo_de_vista:      Boolean,
  tipo_de_filtracion: String,
  lapso_de_tiempo:    String,
  stock:              String,
  selectedLaboratory: { type: Array,   default: () => [] },
  selectedGroup:      { type: Array,   default: () => [] },
  laboratories:       { type: Array,   default: () => [] },
  groups:             { type: Array,   default: () => [] },
  isColombian:        Boolean,
});

const emit = defineEmits([
  "update:selectConDescuento",
  "update:tipo_de_vista",
  "update:tipo_de_filtracion",
  "update:lapso_de_tiempo",
  "update:stock",
  "update:selectedLaboratory",
  "update:selectedGroup",
  "update:isColombian",
  "clear",
  "generarPedido",
]);

const precio = [
  { title: "Full",      value: true  },
  { title: "Descuento", value: false },
];

const tipoDeVistaOpcion = [
  { title: "Grupal",     value: true  },
  { title: "Individual", value: false },
];

const tipoFiltracionOpcion = [
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
  { title: "Exceso", value: "exceso" },
  { title: "Fallas", value: "fallas" },
  { title: "Todos",  value: "all"    },
];

const hasAdvancedFilters = computed(() => true); // Siempre forzado para IA
</script>

<template>
  <AppFilterBase
    :search="''"
    :force-advanced="true"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="..."
    @clear="emit('clear')"
  >
    <!-- Sustituir barra de búsqueda por botón de IA / parámetros base -->
    <template #search>
      <div class="d-flex align-center flex-grow-1 w-100 gap-2">
        <!-- Laboratorio (múltiple) -->
        <VAutocomplete
          :model-value="props.selectedLaboratory"
          :items="props.laboratories"
          placeholder="Laboratorio (Múltiple)"
          item-title="name"
          item-value="id"
          clearable
          multiple
          chips
          closable-chips
          density="compact"
          hide-details
          class="flex-grow-1"
          style="min-width: 150px"
          @update:model-value="emit('update:selectedLaboratory', $event)"
        />

        <!-- Lapso Tiempo -->
        <VSelect
          :model-value="props.lapso_de_tiempo"
          :items="lapsoDeTiempoOpciones"
          placeholder="Lapso Tiempo"
          clearable
          density="compact"
          hide-details
          class="flex-grow-1"
          style="max-width: 200px"
          @update:model-value="emit('update:lapso_de_tiempo', $event)"
        />
      </div>
    </template>

    <template #actions-extra>
      <VBtn
        icon
        color="primary"
        variant="elevated"
        size="38"
        class="ml-1 shadow-sm rounded-lg"
        @click="emit('generarPedido')"
      >
        <VIcon icon="tabler-shopping-cart-plus" size="20" />
        <VTooltip activator="parent" location="top">Generar Pedido IA</VTooltip>
      </VBtn>
    </template>

    <template #advanced-filters>
      <!-- Grupo (múltiple) -->
      <VCol cols="12" sm="6" md="3">
        <VAutocomplete
          :model-value="props.selectedGroup"
          :items="props.groups"
          placeholder="Grupos"
          item-title="name"
          item-value="id"
          clearable
          multiple
          chips
          closable-chips
          density="compact"
          hide-details
          variant="outlined"
          @update:model-value="emit('update:selectedGroup', $event)"
        />
      </VCol>

      <!-- Calcular Por -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.tipo_de_filtracion"
          :items="tipoFiltracionOpcion"
          placeholder="Calcular Por"
          clearable
          density="compact"
          hide-details
          variant="outlined"
          @update:model-value="emit('update:tipo_de_filtracion', $event)"
        />
      </VCol>

      <!-- Vista -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.tipo_de_vista"
          :items="tipoDeVistaOpcion"
          placeholder="Vista (Agrupado)"
          clearable
          density="compact"
          hide-details
          variant="outlined"
          @update:model-value="emit('update:tipo_de_vista', $event)"
        />
      </VCol>

      <!-- Precio -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.selectConDescuento"
          :items="precio"
          placeholder="Precio"
          clearable
          density="compact"
          hide-details
          variant="outlined"
          @update:model-value="emit('update:selectConDescuento', $event)"
        />
      </VCol>

      <!-- Stock -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.stock"
          :items="stockOpciones"
          placeholder="Stock Analizado"
          clearable
          density="compact"
          hide-details
          variant="outlined"
          @update:model-value="emit('update:stock', $event)"
        />
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <div class="d-flex align-center h-100 px-2 rounded border border-opacity-10 bg-var-theme-background">
          <VSwitch
            :model-value="props.isColombian"
            label="Solo Colombia"
            color="info"
            hide-details
            density="compact"
            class="ms-1 font-weight-bold text-xs"
            @update:model-value="emit('update:isColombian', $event)"
          />
          <VTooltip activator="parent" location="top">Filtrar solo por productos de origen Colombia</VTooltip>
        </div>
      </VCol>
    </template>
  </AppFilterBase>
</template>
