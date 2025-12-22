<script setup lang="js">
const props = defineProps({
  buscardor_filtro: { type: String, required: true, default: () => "" },
  currency: { type: String, required: true },
  category_id_filtro: { type: String, required: true },
  categorias: { type: Array, required: true, default: () => [] },
  fechaHasta_filtro: { type: String, required: true, default: () => "" },
  fechaDesde_filtro: { type: String, required: true, default: () => "" },
  isDeductible: Boolean,
  showAddButton: { type: Boolean, required: false, default: true },
  loading: { type: Boolean, default: false },
});

const currencies = ["BS", "USD", "COP"];

const emit = defineEmits([
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
]);
</script>
<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="3" md="2">
          <AppTextField
            :model-value="props.buscardor_filtro"
            placeholder="Buscar por nombre o id"
            clearable
            @update:model-value="emit('update:buscardor_filtro', $event)"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <VAutocomplete
            :model-value="props.category_id_filtro"
            :items="props.categorias"
            :loading="props.loading"
            label="Categoría"
            placeholder="Buscar una categoría"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:category_id_filtro', $event)"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <VSelect
            :model-value="props.currency"
            label="Moneda"
            :items="currencies"
            placeholder="Moneda"
            clearable
            @update:model-value="emit('update:currency', $event)"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <AppDateTimePicker
            :model-value="props.fechaDesde_filtro"
            placeholder="Desde"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:fechaDesde_filtro', $event)"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <AppDateTimePicker
            :model-value="props.fechaHasta_filtro"
            placeholder="Hasta"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:fechaHasta_filtro', $event)"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <VCheckbox
            label="Deducibles"
            :model-value="props.isDeductible"
            @update:model-value="emit('update:isDeductible', $event)"
            hide-details
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
            prepend-icon="tabler-upload"
            v-bind="menuProps"
          >
            Exportar
          </VBtn>
        </template>
        <VList>
          <VListItem @click="emit('export-excel', 'xlsx')">
            <template #prepend>
              <VIcon
                icon="tabler-file-type-csv"
                class="me-2"
                color="success"
              />
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
        v-if="props.showAddButton"
        color="primary"
        prepend-icon="tabler-plus"
        @click="emit('add')"
      >
        Agregar Gasto
      </VBtn>
    </VCardActions>
  </VCard>
</template>
