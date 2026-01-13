<script setup>
const props = defineProps({
  selectConDescuento: Boolean,
  tipo_de_vista: Boolean,
  tipo_de_filtracion: String,
  lapso_de_tiempo: String,
  stock: String,
  selectedLaboratory: [Array, String, null],
  selectedGroup: [Array, String, null],
  laboratories: { type: Array, default: () => [] },
  groups: { type: Array, default: () => [] },
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
  "generarPedido",
]);

const precio = [
  { title: "Full", value: true },
  { title: "Descuento", value: false },
];

const tipoDeVistaOpcion = [
  { title: "Grupal", value: true },
  { title: "Individual", value: false },
];

const tipoFiltracionOpcion = [
  { title: "Promedio", value: "average" },
  { title: "Ventas", value: "sales" },
  { title: "Combinado", value: "combinado" },
];

const lapsoDeTiempoOpciones = [
  { title: "7 Dias", value: "7 days" },
  { title: "15 Dias", value: "15 days" },
  { title: "1 Mes", value: "1 month" },
  { title: "3 Meses", value: "3 month" },
  { title: "6 Meses", value: "6 month" },
  { title: "1 Año", value: "1 year" },
];

const stockOpciones = [
  { title: "Exceso", value: "exceso" },
  { title: "Fallas", value: "fallas" },
  { title: "Todos", value: "all" },
];
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <!-- FILA 1: Filtros Principales (3 items x 4 columnas = 12) -->

        <VCol cols="12" sm="6" md="4">
          <VAutocomplete
            :model-value="props.selectedLaboratory"
            :items="props.laboratories"
            label="Laboratorio"
            placeholder="Buscar..."
            item-title="name"
            item-value="id"
            clearable
            chips
            multiple
            closable-chips
            @update:model-value="emit('update:selectedLaboratory', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="4">
          <VAutocomplete
            :model-value="props.selectedGroup"
            :items="props.groups"
            label="Grupos"
            placeholder="Buscar..."
            item-title="name"
            item-value="id"
            clearable
            chips
            multiple
            closable-chips
            @update:model-value="emit('update:selectedGroup', $event)"
          />
        </VCol>

        <VCol cols="12" sm="12" md="4">
          <VSelect
            :model-value="props.lapso_de_tiempo"
            label="Lapso de tiempo"
            :items="lapsoDeTiempoOpciones"
            clearable
            @update:model-value="emit('update:lapso_de_tiempo', $event)"
          />
        </VCol>

        <!-- FILA 2: Configuraciones (4 items x 3 columnas = 12) -->

        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.tipo_de_filtracion"
            label="Calcular Por"
            :items="tipoFiltracionOpcion"
            clearable
            @update:model-value="emit('update:tipo_de_filtracion', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.tipo_de_vista"
            label="Tipo de vista"
            :items="tipoDeVistaOpcion"
            clearable
            @update:model-value="emit('update:tipo_de_vista', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.selectConDescuento"
            label="Precio"
            :items="precio"
            clearable
            @update:model-value="emit('update:selectConDescuento', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.stock"
            label="Stock"
            :items="stockOpciones"
            clearable
            @update:model-value="emit('update:stock', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>

      <VSpacer />

      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="emit('generarPedido')"
      >
        Generar Pedido
      </VBtn>
    </VCardActions>
  </VCard>
</template>
