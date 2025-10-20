<script setup>
const props = defineProps({
  selectConDescuento: Boolean,
  tipo_de_vista: Boolean,
  tipo_de_filtracion: String,
  lapso_de_tiempo: String,
  stock: String,
  selectedLaboratory: String,
  selectedGroup: String,
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
  { title: "15 Dias", value: "15 days" },
  { title: "1 Mes", value: "1 month" },
  { title: "3 Meses", value: "3 month" },
  { title: "6 Meses", value: "6 month" },
  { title: "1 Año", value: "1 year" },
];

const stockOpciones = [
  { title: "Exceso", value: "exceso" },
  { title: "Fallas", value: "fallas" },
  { title: "All", value: "all" },
];
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="4">
          <VAutocomplete
            :model-value="props.selectedLaboratory"
            :items="props.laboratories"
            label="Laboratorio"
            placeholder="Escribe para buscar un laboratorio"
            item-title="name"
            item-value="id"
            clearable
            chips="true"
            multiple="true"
            @update:model-value="emit('update:selectedLaboratory', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <VAutocomplete
            :model-value="props.selectedGroup"
            :items="props.groups"
            label="Grupos"
            placeholder="Escribe para buscar un grupo"
            item-title="name"
            item-value="id"
            clearable
            chips="true"
            multiple="true"
            @update:model-value="emit('update:selectedGroup', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <VSelect
            :model-value="props.tipo_de_vista"
            label="Tipo de vista"
            :items="tipoDeVistaOpcion"
            @update:model-value="emit('update:tipo_de_vista', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <VSelect
            :model-value="props.selectConDescuento"
            label="Precio"
            :items="precio"
            @update:model-value="emit('update:selectConDescuento', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <VSelect
            :model-value="props.tipo_de_filtracion"
            label="Calcular Por"
            :items="tipoFiltracionOpcion"
            @update:model-value="emit('update:tipo_de_filtracion', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <VSelect
            :model-value="props.lapso_de_tiempo"
            label="Lapso de tiempo"
            :items="lapsoDeTiempoOpciones"
            @update:model-value="emit('update:lapso_de_tiempo', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <VSelect
            :model-value="props.stock"
            label="Stock"
            :items="stockOpciones"
            @update:model-value="emit('update:stock', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>

      <VSpacer />
      <VBtn
        prepend-icon="tabler-plus"
        color="primary"
        @click="emit('generarPedido')"
      >
        Generar Pedido
      </VBtn>
    </VCardActions>
  </VCard>
</template>
