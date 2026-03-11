<script setup>
const props = defineProps({
  isDialogVisible: Boolean,
  
  // Filtros de Necesidades (IA)
  selectedLaboratory: { type: Array, default: () => [] },
  selectedGroup: { type: Array, default: () => [] },
  tipo_de_filtracion: String,
  lapso_de_tiempo: String,
  stock: String,
  selectConDescuento: Boolean,

  // Opciones
  laboratories: { type: Array, default: () => [] },
  groups: { type: Array, default: () => [] },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'update:selectedLaboratory',
  'update:selectedGroup',
  'update:tipo_de_filtracion',
  'update:lapso_de_tiempo',
  'update:stock',
  'update:selectConDescuento',
  'clear',
  'open-delete-dialog'
])

const precioOpciones = [
  { title: "Full", value: true },
  { title: "Descuento", value: false },
]

const tipoFiltracionOpciones = [
  { title: "Promedio", value: "average" },
  { title: "Ventas", value: "sales" },
  { title: "Combinado", value: "combinado" },
]

const lapsoDeTiempoOpciones = [
  { title: "7 Dias", value: "7 days" },
  { title: "15 Dias", value: "15 days" },
  { title: "1 Mes", value: "1 month" },
  { title: "3 Meses", value: "3 month" },
  { title: "6 Meses", value: "6 month" },
  { title: "1 Año", value: "1 year" },
]

const stockOpciones = [
  { title: "Exceso", value: "exceso" },
  { title: "Fallas", value: "fallas" },
  { title: "Todos", value: "all" },
]

const closeDialog = () => {
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="600"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard title="Filtros de Análisis de Necesidades (IA)">
      <DialogCloseBtn @click="closeDialog" />

      <VCardText>
        <VRow>
          <VCol cols="12">
            <VAutocomplete
              :model-value="props.selectedLaboratory"
              :items="props.laboratories"
              label="Laboratorios"
              item-title="name"
              item-value="id"
              clearable
              chips
              multiple
              closable-chips
              @update:model-value="emit('update:selectedLaboratory', $event)"
            />
          </VCol>

          <VCol cols="12">
            <VAutocomplete
              :model-value="props.selectedGroup"
              :items="props.groups"
              label="Grupos"
              item-title="name"
              item-value="id"
              clearable
              chips
              multiple
              closable-chips
              @update:model-value="emit('update:selectedGroup', $event)"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VSelect
              :model-value="props.tipo_de_filtracion"
              label="Calcular Por"
              :items="tipoFiltracionOpciones"
              @update:model-value="emit('update:tipo_de_filtracion', $event)"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VSelect
              :model-value="props.lapso_de_tiempo"
              label="Lapso Temporal"
              :items="lapsoDeTiempoOpciones"
              @update:model-value="emit('update:lapso_de_tiempo', $event)"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VSelect
              :model-value="props.stock"
              label="Estado Stock"
              :items="stockOpciones"
              @update:model-value="emit('update:stock', $event)"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VSelect
              :model-value="props.selectConDescuento"
              label="Tipo Precio"
              :items="precioOpciones"
              @update:model-value="emit('update:selectConDescuento', $event)"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="pa-4">
        <VBtn
          color="error"
          variant="tonal"
          prepend-icon="tabler-trash"
          @click="emit('open-delete-dialog')"
        >
          Borrar Antiguos
        </VBtn>

        <VSpacer />
        
        <VBtn
          color="secondary"
          variant="outlined"
          @click="emit('clear')"
        >
          Limpiar
        </VBtn>

        <VBtn
          color="primary"
          @click="closeDialog"
        >
          Aplicar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
