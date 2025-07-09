<script setup>
const props = defineProps({
  buscador: String,
  tipo_empresa_filtro: String,
  fechaHasta_filtro: [String, null],
  fechaDesde_filtro: [String, null],
});

const emit = defineEmits([
  "update:buscador",
  "update:fechaHasta_filtro",
  "update:fechaDesde_filtro",
  "clear",
  "add-doctor",
  "export-pdf",
  "export-excel",
]);
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="12" md="12">
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
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="emit('add-doctor')"
      >
        Agregar Doctor
      </VBtn>
    </VCardActions>
  </VCard>
</template>
