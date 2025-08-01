<script setup>
const props = defineProps({
  tipo_de_vista: Boolean,
  tipo_de_filtracion: String,
  lapso_de_tiempo: String,
  stock: String,
});

const emit = defineEmits([
  "update:tipo_de_vista",
  "update:tipo_de_filtracion",
  "update:lapso_de_tiempo",
  "update:stock",
  "clear",
]);

const tipoDeVistaOpcion = [
  { title: "Grupal", value: true },
  { title: "Individual", value: false },
];

const tipoFiltracionOpcion = [
  { title: "Promedio", value: "average" },
  { title: "Ventas", value: "sales" },
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
          <VSelect
            :model-value="props.tipo_de_vista"
            label="Tipo de vista"
            :items="tipoDeVistaOpcion"
            @update:model-value="emit('update:tipo_de_vista', $event)"
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
        <!-- <VCol cols="12" sm="12" md="12">
          <AppTextField
            :model-value="props.buscador"
            placeholder="Buscar por nombre, identificación o dirección..."
            clearable
            @update:model-value="emit('update:buscador', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="6">
          <AppDateTimePicker
            :model-value="props.fechaDesde_filtro"
            label="Desde"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:fechaDesde_filtro', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="6">
          <AppDateTimePicker
            :model-value="props.fechaHasta_filtro"
            label="Hasta"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:fechaHasta_filtro', $event)"
          />
        </VCol> -->
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>

      <VSpacer />
      <!-- <VMenu>
        <template #activator="{ props: menuProps }">
          <VBtn
            color="success"
            variant="flat"
            prepend-icon="tabler-upload"
            v-bind="menuProps"
          >
            Exportar
          </VBtn>
        </template>
        <VList>
          <VListItem @click="emit('export-excel', 'xlsx')">
            <template #prepend>
              <VIcon icon="tabler-file-type-csv" class="me-2" color="success" />
            </template>
            <VListItemTitle class="text-success">Excel</VListItemTitle>
          </VListItem>
          <VListItem @click="emit('export-pdf')">
            <template #prepend>
              <VIcon icon="tabler-file-type-pdf" class="me-2" />
            </template>
            <VListItemTitle>PDF</VListItemTitle>
          </VListItem>
        </VList>
      </VMenu> -->
      <VBtn color="success" variant="flat"> Cargar </VBtn>
    </VCardActions>
  </VCard>
</template>
