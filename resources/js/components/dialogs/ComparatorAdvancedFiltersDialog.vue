<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  isDialogVisible: Boolean,
  
  // Filtros Compartidos
  selectedLaboratory: { type: Array, default: () => [] },
  selectedGroup: { type: Array, default: () => [] },
  
  // Filtros IA (Necesidades)
  tipo_de_filtracion: String,
  lapso_de_tiempo: String,
  stock: String,
  selectConDescuento: Boolean,
  
  // Filtros Catálogo (Configuraciones Visuales)
  enableDiscounts: Boolean,
  enableUsdAmountCol: Boolean,
  enableDiscountCol: Boolean,
  selectedOrigin: [Number, String, null],
  selectedSupplier: [Number, String, null],
  isStrictSearch: Boolean,

  // Opciones para los selects
  laboratories: { type: Array, default: () => [] },
  groups: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'update:selectedLaboratory',
  'update:selectedGroup',
  'update:tipo_de_filtracion',
  'update:lapso_de_tiempo',
  'update:stock',
  'update:selectConDescuento',
  'update:enableDiscounts',
  'update:enableUsdAmountCol',
  'update:enableDiscountCol',
  'update:selectedOrigin',
  'update:selectedSupplier',
  'update:isStrictSearch',
  'clear',
  'open-delete-dialog',
  'update-all-api'
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
    max-width="800"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard title="Filtros Avanzados y Configuración">
      <DialogCloseBtn @click="closeDialog" />

      <VCardText>
        <VRow>
          <!-- SECCIÓN: FILTROS GLOBALES -->
          <VCol cols="12">
            <h6 class="text-h6 mb-2">Filtros Globales</h6>
          </VCol>

          <VCol cols="12" md="6">
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

          <VCol cols="12" md="6">
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

          <VDivider class="my-4 w-100" />

          <!-- SECCIÓN: ANÁLISIS DE NECESIDADES (IA) -->
          <VCol cols="12">
            <h6 class="text-h6 mb-2">Análisis de Necesidades (IA)</h6>
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <VSelect
              :model-value="props.tipo_de_filtracion"
              label="Calcular Por"
              :items="tipoFiltracionOpciones"
              @update:model-value="emit('update:tipo_de_filtracion', $event)"
            />
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <VSelect
              :model-value="props.lapso_de_tiempo"
              label="Lapso Temporal"
              :items="lapsoDeTiempoOpciones"
              @update:model-value="emit('update:lapso_de_tiempo', $event)"
            />
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <VSelect
              :model-value="props.stock"
              label="Estado Stock"
              :items="stockOpciones"
              @update:model-value="emit('update:stock', $event)"
            />
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <VSelect
              :model-value="props.selectConDescuento"
              label="Tipo Precio"
              :items="precioOpciones"
              @update:model-value="emit('update:selectConDescuento', $event)"
            />
          </VCol>

          <VDivider class="my-4 w-100" />

          <!-- SECCIÓN: CATÁLOGO DE PROVEEDORES -->
          <VCol cols="12">
            <h6 class="text-h6 mb-2">Catálogo de Proveedores</h6>
          </VCol>

          <VCol cols="12" md="4">
            <VSelect
              :model-value="props.selectedOrigin"
              :items="props.origins"
              label="Origen"
              item-title="name"
              item-value="id"
              clearable
              @update:model-value="emit('update:selectedOrigin', $event)"
            />
          </VCol>

          <VCol cols="12" md="8">
            <VAutocomplete
              :model-value="props.selectedSupplier"
              :items="props.suppliers"
              label="Proveedor Específico"
              item-title="name"
              item-value="id"
              clearable
              @update:model-value="emit('update:selectedSupplier', $event)"
            />
          </VCol>

          <VDivider class="my-4 w-100" />

          <!-- SECCIÓN: CONFIGURACIONES VISUALES -->
          <VCol cols="12">
            <h6 class="text-h6 mb-2">Visualización</h6>
          </VCol>

          <VCol cols="12" class="d-flex flex-wrap gap-4">
            <VSwitch
              :model-value="props.enableDiscounts"
              label="Aplicar Descuento"
              color="primary"
              hide-details
              @update:model-value="emit('update:enableDiscounts', $event)"
            />
            <VSwitch
              :model-value="props.enableUsdAmountCol"
              label="Ver Divisas ($)"
              color="success"
              hide-details
              @update:model-value="emit('update:enableUsdAmountCol', $event)"
            />
            <VSwitch
              :model-value="props.enableDiscountCol"
              label="Ver % Desc."
              color="info"
              hide-details
              @update:model-value="emit('update:enableDiscountCol', $event)"
            />
            <VSwitch
              :model-value="props.isStrictSearch"
              label="Búsqueda Estricta"
              color="warning"
              hide-details
              @update:model-value="emit('update:isStrictSearch', $event)"
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
          Limpiar Productos Antiguos
        </VBtn>
        
        <VBtn
          color="info"
          variant="tonal"
          prepend-icon="tabler-cloud-download"
          @click="emit('update-all-api')"
        >
          Sincronizar APIs
        </VBtn>

        <VSpacer />
        
        <VBtn
          color="secondary"
          variant="outlined"
          @click="emit('clear')"
        >
          Valores por Defecto
        </VBtn>

        <VBtn
          color="primary"
          @click="closeDialog"
        >
          Aplicar y Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
