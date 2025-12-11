<script setup lang="js">

const props=defineProps({
  buscardor_filtro: {type: String, requiered: true, default: () => "" },
  currency:{type: String,requiered: true},
  category_id_filtro:{type: String,requiered: true},
  categorias:{type: Array,requiered: true, default:() => []},
  fechaHasta_filtro: {type: String, requiered: true, default: () => "" },
  fechaDesde_filtro: {type: String, requiered: true, default: () => "" },
  isDeductible: Boolean,
  hasInvoice: Boolean,
  showAddButton: { type: Boolean, required: false, default: true }, 
})

const currencies=["BS","USD", "COP"];

const emit=defineEmits([
  "update:currency",
  "update:buscardor_filtro",
  "update:category_id_filtro",
  "update:fechaHasta_filtro",
  "update:fechaDesde_filtro",
  "clear",
  "export-excel",
  "export-pdf",
  "add",
  "update:isDeductible",
  "update:hasInvoice",
])
</script>
<template>
  <VCard title="Filtros de Gastos" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" md="6">
          <AppTextField
            :model-value="props.buscardor_filtro"
            placeholder="Buscar por nombre o id"
            clearable
            @update:model-value="emit('update:buscardor_filtro', $event)"
          />
        </VCol>
        <VCol cols="12" md="6">
          <VAutocomplete
            :model-value="props.category_id_filtro"
            label="Categoría"
            :items="props.categorias"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:category_id_filtro', $event)"
          />
        </VCol>
        <VCol cols="12" md="6">
          <VSelect
            :model-value="props.currency"
            label="Moneda"
            :items="currencies"
            clearable
            @update:model-value="emit('update:currency', $event)"
          />
        </VCol>
        <VCol cols="12" md="6">
          <AppDateTimePicker
            :model-value="props.fechaDesde_filtro"
            placeholder="Fecha Desde"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:fechaDesde_filtro', $event)"
          />
        </VCol>
        <VCol cols="12" md="6">
          <AppDateTimePicker
            :model-value="props.fechaHasta_filtro"
            placeholder="Fecha Hasta"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:fechaHasta_filtro', $event)"
          />
        </VCol>
        <VCol cols="12" md="6">
          <div class="d-flex align-center gap-4">
            <VCheckbox
              label="Deducibles"
              :model-value="props.isDeductible"
              @update:model-value="emit('update:isDeductible', $event)"
              hide-details
            />
            <VCheckbox
              label="Con Factura"
              :model-value="props.hasInvoice"
              @update:model-value="emit('update:hasInvoice', $event)"
              hide-details
            />
          </div>
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
      </VMenu>
      <VBtn 
        color="primary" 
        prepend-icon="tabler-plus" 
        @click="emit('add')" 
        v-if="props.showAddButton"
      >
        Agregar Gasto
      </VBtn>
    </VCardActions>
  </VCard>
</template>
