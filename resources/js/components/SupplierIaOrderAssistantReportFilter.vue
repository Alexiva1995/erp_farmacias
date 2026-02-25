<script setup>
const props = defineProps({
  checkColombia: { type: Boolean, required: true },
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
  "update:checkColombia",
  "clear",
  "export-excel",
  "export-pdf",
]);

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
  { title: "12 Meses", value: "12 month" },
  { title: "18 Meses", value: "18 month" },
  { title: "24 Meses", value: "24 month" },
];
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow align="center">
        <VCol cols="12" sm="12" md="3">
          <AppAutocomplete
            :items="props.products"
            placeholder="Seleccionar Productos"
            item-title="name"
            item-value="id"
            clearable
            chips
            multiple
            hide-details="auto"
            @update:model-value="emit('update:selectProducts', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <AppAutocomplete
            :items="props.laboratories"
            placeholder="Seleccionar Laboratorio"
            item-title="name"
            item-value="id"
            clearable
            chips
            multiple
            hide-details="auto"
            @update:model-value="emit('update:selectedLaboratory', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="2">
          <AppSelect
            :model-value="props.tipo_de_filtracion"
            placeholder="Calcular Por"
            :items="tipoFiltracionOpcion"
            hide-details="auto"
            @update:model-value="emit('update:tipo_de_filtracion', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="2">
          <AppSelect
            :model-value="props.lapso_de_tiempo"
            placeholder="Lapso de tiempo"
            :items="lapsoDeTiempoOpciones"
            hide-details="auto"
            @update:model-value="emit('update:lapso_de_tiempo', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="2">
          <VCheckbox
            :model-value="props.checkColombia"
            label="Colombia"
            color="primary"
            density="compact"
            hide-details
            @update:model-value="emit('update:checkColombia', $event)"
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

      <VMenu>
        <template #activator="{ props: menuProps }">
          <VBtn
            color="success"
            variant="flat"
            prepend-icon="tabler-file-download"
            v-bind="menuProps"
          >
            Exportar
          </VBtn>
        </template>
        <VList>
          <VListItem @click="emit('export-excel', 'xlsx')">
            <template #prepend>
              <VIcon
                icon="tabler-file-spreadsheet"
                class="me-2"
                color="success"
              />
            </template>
            <VListItemTitle class="text-success">Excel</VListItemTitle>
          </VListItem>
          <VListItem @click="emit('export-pdf')">
            <template #prepend>
              <VIcon icon="tabler-file-type-pdf" class="me-2" color="error" />
            </template>
            <VListItemTitle class="text-error">PDF</VListItemTitle>
          </VListItem>
        </VList>
      </VMenu>
    </VCardActions>
  </VCard>
</template>
