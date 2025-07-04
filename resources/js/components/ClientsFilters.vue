<script setup>
const props = defineProps({
  buscador: String,
  tipo_identificacion_filtro: String,
  company_id_filtro: [String, null],
  fechaHasta_filtro: [String, null],
  fechaDesde_filtro: [String, null],
  companies: { type: Array, default: () => [] },
});

const emit = defineEmits([
  "update:buscador",
  "update:tipo_identificacion_filtro",
  "update:company_id_filtro",
  "update:fechaHasta_filtro",
  "update:fechaDesde_filtro",
  "clear",
  "add-client",
  "export-pdf",
]);
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="6">
          <AppTextField
            :model-value="props.buscador"
            placeholder="Buscar por nombre, apellido o identificación..."
            clearable
            @update:model-value="emit('update:buscador', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.tipo_identificacion_filtro"
            label="Tipo de identificación"
            :items="['V-', 'J-', 'G-', 'E-']"
            clearable
            @update:model-value="
              emit('update:tipo_identificacion_filtro', $event)
            "
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.company_id_filtro"
            label="Empresa"
            :items="props.companies"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:company_id_filtro', $event)"
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
          <!-- <VListItem @click="emit('export', 'xlsx')">
            <template #prepend>
              <VIcon icon="tabler-file-type-csv" class="me-2" color="success" />
            </template>
            <VListItemTitle class="text-success">Excel</VListItemTitle>
          </VListItem> -->
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
        @click="emit('add-client')"
      >
        Agregar Cliente
      </VBtn>
    </VCardActions>
  </VCard>
</template>
