<script setup>
const props = defineProps({
  tipo_de_filtracion: String,
  lapso_de_tiempo: String,
  laboratories: { type: Array, default: () => [] },
  products: { type: Array, default: () => [] },
});

const emit = defineEmits([
  "update:tipo_de_filtracion",
  "update:lapso_de_tiempo",
  "update:selectedLaboratory",
  "update:selectProducts",
  "clear",
  "consultar",
]);

const tipoFiltracionOpcion = [
  { title: "Promedio", value: "average" },
  { title: "Ventas", value: "sales" },
];

const lapsoDeTiempoOpciones = [
  { title: "15 Dias", value: "15 days" },
  { title: "1 Mes", value: "1 month" },
  { title: "3 Meses", value: "3 month" },
  { title: "6 Meses", value: "6 month" },
  { title: "12 Meses o 1 año", value: "1 year" },
  { title: "12 Meses", value: "12 month" },
  { title: "18 Meses", value: "18 month" },
  { title: "24 Meses", value: "24 month" },
];
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="12" md="12">
          <VAutocomplete
            :items="props.products"
            label="Productos"
            placeholder="Escribe para buscar un producto"
            item-title="name"
            item-value="id"
            clearable
            chips="true"
            multiple="true"
            @update:model-value="emit('update:selectProducts', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <VAutocomplete
            :items="props.laboratories"
            label="Laboratorio"
            placeholder="Escribe para buscar un laboratorio"
            item-title="name"
            item-value="id"
            clearable
            chips="true"
            multiple="true"
            @update:model-value="emit('update:selectedLaboratory', $event)"
          >
          </VAutocomplete>
        </VCol>
        <!-- <VCol cols="12" sm="6" md="4">
          <VAutocomplete
            :model-value="props.selectedGroup"
            :items="props.groups"
            label="Grupos"
            placeholder="Escribe para buscar un grupo"
            item-title="name"
            item-value="id"
            clearable
            multiple="true"
            @update:model-value="emit('update:selectedGroup', $event)"
          />
        </VCol> -->
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
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>

      <VSpacer />
      <VBtn color="success" variant="flat" @click="emit('consultar')">
        Generar Pedido
      </VBtn>
    </VCardActions>
  </VCard>
</template>
