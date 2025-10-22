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
  <!-- <h1>desuwa</h1> -->
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="8">
          <AppTextField
            :model-value="props.buscardor_filtro"
            placeholder="Buscar por nombre o id"
            clearable
            @update:model-value="emit('update:buscardor_filtro', $event)"
          />
          <div class="d-flex align-center gap-4">
            <VCheckbox
              label="Deducibles"
              :model-value="props.isDeductible"
              @update:model-value="emit('update:isDeductible', $event)"
              hide-details
            />
            
            <VCheckbox
              label="Facturas"
              :model-value="props.hasInvoice"
              @update:model-value="emit('update:hasInvoice', $event)"
              hide-details
            />
          </div>
        </VCol>
        <VCol cols="12" sm="6" md="2">
          <VSelect
            :model-value="props.currency"
            label="Moneda"
            :items="currencies"
            clearable
            @update:model-value="emit('update:currency', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="2">
          <VSelect
            :model-value="props.category_id_filtro"
            label="Categoria"
            :items="props.categorias"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:category_id_filtro', $event)"
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
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
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
      <VBtn color="primary" prepend-icon="tabler-plus" @click="emit('add')">
        Agregar Gasto
      </VBtn>
    </VCardActions>
  </VCard>
</template>
