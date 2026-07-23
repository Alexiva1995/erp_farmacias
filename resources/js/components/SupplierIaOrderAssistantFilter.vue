<script setup>
// Filtros IA Asistente Pedidos (Proveedores)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { computed } from "vue";

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => false);

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
  isNovaventa:        Boolean,
  showIgnored:        { type: Boolean, default: false },
  showGraphs:         { type: Boolean, default: false },
  selectedSupplier:   { type: [Number, String, Object, null], default: null },
  suppliers:          { type: Array,   default: () => [] },
  hasStock:           { type: String,  default: "all" },
  ordenarAhorro:      Boolean,
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectConDescuento",
  "update:tipo_de_vista",
  "update:tipo_de_filtracion",
  "update:lapso_de_tiempo",
  "update:stock",
  "update:hasStock",
  "update:selectedLaboratory",
  "update:selectedGroup",
  "update:isColombian",
  "update:isNovaventa",
  "update:showIgnored",
  "update:showGraphs",
  "update:selectedSupplier",
  "update:ordenarAhorro",
  "clear",
  "clear-ignore",
  "pedirAhorro",
  "fetchSuppliers",
  "exportarColombianos",
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

const hasStockOpciones = [
  { title: "Todos",       value: "all"     },
  { title: "Con Stock",   value: "with"    },
  { title: "Sin Stock",   value: "without" },
];

const hasAdvancedFilters = computed(() => (
  !!(props.selectedGroup?.length || props.isColombian || props.isNovaventa || props.tipo_de_filtracion !== 'combinado' || props.stock !== 'fallas' || props.selectedSupplier || props.hasStock !== 'all')
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
      <!-- Mostrar Gráficos (Toggle) -->
      <VBtn
        icon
        variant="tonal"
        :color="props.showGraphs ? 'info' : 'primary'"
        size="38"
        class="ml-1 shadow-sm rounded-circle"
        @click="emit('update:showGraphs', !props.showGraphs)"
      >
        <VIcon icon="tabler-chart-line" />
        <VTooltip activator="parent" location="top">{{ props.showGraphs ? 'Ocultar Gráficos' : 'Mostrar Gráficos' }}</VTooltip>
      </VBtn>

      <!-- Limpiar Ignore -->
      <VBtn
        icon
        variant="text"
        color="warning"
        size="38"
        class="ml-1 shadow-sm rounded-circle"
        @click="emit('clear-ignore')"
      >
        <VIcon icon="tabler-eye-check" />
        <VTooltip activator="parent" location="top">Restaurar Ocultos (Ignore)</VTooltip>
      </VBtn>

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
        @click="emit('pedirAhorro')"
      >
        <VIcon icon="tabler-shopping-cart-plus" size="20" />
        <VTooltip activator="parent" location="top">Pedir Todo Ahorro</VTooltip>
      </VBtn>

      <!-- Exportar Colombia: visible solo cuando el toggle Colombia está activo -->
      <VBtn
        v-if="props.isColombian"
        icon
        color="success"
        variant="flat"
        size="38"
        class="ml-1 shadow-sm rounded-circle"
        @click="emit('exportarColombianos')"
      >
        <VIcon icon="tabler-file-spreadsheet" size="20" />
        <VTooltip activator="parent" location="top">Exportar Colombia por Laboratorio (Excel)</VTooltip>
      </VBtn>


    </template>

    <template #advanced-filters>
      <!-- Proveedor Destino -->
      <VCol cols="12" sm="6" md="3">
        <VAutocomplete
          :model-value="props.selectedSupplier"
          :items="props.suppliers"
          label="Proveedor Destino"
          item-title="name"
          item-value="id"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-truck"
          @update:model-value="emit('update:selectedSupplier', $event)"
        />
      </VCol>

      <!-- Laboratorio (múltiple) -->
      <VCol cols="12" sm="6" md="3">
        <VAutocomplete
          :model-value="props.selectedLaboratory"
          :items="props.laboratories"
          :label="isRestaurant ? 'Marcas' : 'Laboratorios'"
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
          label="Grupos"
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
          label="Periodo"
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
          label="Cálculo"
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
          label="Vista"
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
          label="Precios"
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
          label="Análisis Stock"
          density="compact"
          hide-details
          prepend-inner-icon="tabler-box"
          @update:model-value="emit('update:stock', $event)"
        />
      </VCol>

      <!-- Disponibilidad Inventario -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.hasStock"
          :items="hasStockOpciones"
          label="Disponibilidad"
          density="compact"
          hide-details
          prepend-inner-icon="tabler-package"
          @update:model-value="emit('update:hasStock', $event)"
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
            :model-value="props.isNovaventa"
            label="Novaventa"
            color="secondary"
            hide-details
            density="compact"
            class="ms-1 font-weight-bold text-xs"
            @update:model-value="emit('update:isNovaventa', $event)"
          />
          <VTooltip activator="parent" location="top">Filtrar solo productos Novaventa</VTooltip>
        </div>
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <div class="d-flex align-center h-100 px-3 rounded-lg border bg-var-theme-background">
          <VSwitch
            :model-value="props.ordenarAhorro"
            label="Ordenar Ahorro"
            color="success"
            hide-details
            density="compact"
            class="ms-1 font-weight-bold text-xs"
            @update:model-value="emit('update:ordenarAhorro', $event)"
          />
          <VTooltip activator="parent" location="top">Ordenar por mayor ahorro (descuento/variación de precio)</VTooltip>
        </div>
      </VCol>
    </template>
  </AppFilterBase>
</template>
