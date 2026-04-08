<script setup>
// Filtros IA Asistente Pedidos (Proveedores)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery:        { type: String,  default: "" },
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
  showTrend:          { type: Boolean, default: true },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectConDescuento",
  "update:tipo_de_vista",
  "update:tipo_de_filtracion",
  "update:lapso_de_tiempo",
  "update:stock",
  "update:selectedLaboratory",
  "update:selectedGroup",
  "update:isColombian",
  "update:showTrend",
  "clear",
  "generarPedido",
  "fetchSuppliers",
]);

const precio = [
  { title: "Full",      value: false },
  { title: "Descuento", value: true  },
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

const hasAdvancedFilters = computed(() => (
  !!(props.selectedGroup?.length || props.isColombian || props.tipo_de_filtracion !== 'combinado' || props.stock !== 'all')
));
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Buscar producto..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
  >
    <!-- Barra Principal -->
    <template #search-extra>
      <!-- Solo búsqueda en la barra principal -->
    </template>

    <template #actions-extra>
      <VBtn
        icon
        color="warning"
        variant="tonal"
        size="38"
        class="ml-1 shadow-sm rounded-circle"
        @click="emit('fetchSuppliers')"
      >
        <VIcon icon="tabler-currency-dollar" size="20" />
        <VTooltip activator="parent" location="top">Comparar Precios Mas Bajos</VTooltip>
      </VBtn>

      <VBtn
        icon
        color="primary"
        variant="flat"
        size="38"
        class="ml-1 shadow-sm rounded-circle"
        @click="emit('generarPedido')"
      >
        <VIcon icon="tabler-shopping-cart-plus" size="20" />
        <VTooltip activator="parent" location="top">Generar Pedido IA</VTooltip>
      </VBtn>
    </template>

    <template #advanced-filters>
      <!-- Laboratorio (múltiple) -->
      <VCol cols="12" sm="6" md="3">
        <VAutocomplete
          :model-value="props.selectedLaboratory"
          :items="props.laboratories"
          placeholder="Laboratorios"
          item-title="name"
          item-value="id"
          clearable
          multiple
          chips
          closable-chips
          density="compact"
          hide-details
          prepend-inner-icon="tabler-building-factory-2"
          @update:model-value="emit('update:selectedLaboratory', $event)"
        />
      </VCol>

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
          prepend-inner-icon="tabler-category"
          @update:model-value="emit('update:selectedGroup', $event)"
        />
      </VCol>

      <!-- Lapso Tiempo -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.lapso_de_tiempo"
          :items="lapsoDeTiempoOpciones"
          placeholder="Periodo"
          density="compact"
          hide-details
          prepend-inner-icon="tabler-calendar-time"
          @update:model-value="emit('update:lapso_de_tiempo', $event)"
        />
      </VCol>

      <!-- Calcular Por -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.tipo_de_filtracion"
          :items="tipoFiltracionOpcion"
          placeholder="Cálculo"
          density="compact"
          hide-details
          prepend-inner-icon="tabler-math-function"
          @update:model-value="emit('update:tipo_de_filtracion', $event)"
        />
      </VCol>

      <!-- Vista -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.tipo_de_vista"
          :items="tipoDeVistaOpcion"
          placeholder="Vista"
          density="compact"
          hide-details
          prepend-inner-icon="tabler-layout-grid"
          @update:model-value="emit('update:tipo_de_vista', $event)"
        />
      </VCol>

      <!-- Precio -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.selectConDescuento"
          :items="precio"
          placeholder="Precios"
          density="compact"
          hide-details
          prepend-inner-icon="tabler-currency-dollar"
          @update:model-value="emit('update:selectConDescuento', $event)"
        />
      </VCol>

      <!-- Stock -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.stock"
          :items="stockOpciones"
          placeholder="Stock"
          density="compact"
          hide-details
          prepend-inner-icon="tabler-box"
          @update:model-value="emit('update:stock', $event)"
        />
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <div class="d-flex align-center h-100 px-3 rounded-lg border bg-var-theme-background">
          <VSwitch
            :model-value="props.isColombian"
            label="Colombia"
            color="info"
            hide-details
            density="compact"
            class="ms-1 font-weight-bold text-xs"
            @update:model-value="emit('update:isColombian', $event)"
          />
          <VTooltip activator="parent" location="top">Filtrar solo origen Colombia</VTooltip>
        </div>
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <div class="d-flex align-center h-100 px-3 rounded-lg border bg-var-theme-background">
          <VSwitch
            :model-value="props.showTrend"
            label="Tendencia"
            color="success"
            hide-details
            density="compact"
            class="ms-1 font-weight-bold text-xs"
            @update:model-value="emit('update:showTrend', $event)"
          />
          <VTooltip activator="parent" location="top">Mostrar/Ocultar gráficos de tendencia para mejor rendimiento</VTooltip>
        </div>
      </VCol>
    </template>
  </AppFilterBase>
</template>
