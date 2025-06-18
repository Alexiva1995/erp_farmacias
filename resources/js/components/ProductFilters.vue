<script setup>

const props = defineProps({
  searchQuery: String,
  selectedLaboratory: [Number, String, null],
  selectedOrigin: [Number, String, null],
  stockStatusFilter: [Boolean, null],
  startDate: [String, null],
  endDate: [String, null],
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
});

const emit = defineEmits([
  'update:searchQuery',
  'update:selectedLaboratory',
  'update:selectedOrigin',
  'update:stockStatusFilter',
  'update:startDate',
  'update:endDate',
  'clear',
  'export',
  'add-product',
]);

const stockOptions = [
  { title: 'Con Stock', value: true },
  { title: 'Sin Stock', value: false },
];
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="3">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar Producto, C. Activo, Código..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.selectedLaboratory"
            label="Laboratorio"
            :items="props.laboratories"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:selectedLaboratory', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.selectedOrigin"
            label="Origen"
            :items="props.origins"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:selectedOrigin', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.stockStatusFilter"
            label="Estado de Stock"
            :items="stockOptions"
            clearable
            @update:model-value="emit('update:stockStatusFilter', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="6">
          <AppDateTimePicker
            :model-value="props.startDate"
            label="Vencimiento Desde"
            clearable
            :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            @update:model-value="emit('update:startDate', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="6">
          <AppDateTimePicker
            :model-value="props.endDate"
            label="Vencimiento Hasta"
            clearable
            :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            @update:model-value="emit('update:endDate', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>
    
    <VDivider />

    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn
        color="secondary"
        variant="outlined"
        @click="emit('clear')"
      >
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
          <VListItem @click="emit('export', 'xlsx')">
            <template #prepend>
              <VIcon icon="tabler-file-type-csv" class="me-2" color="success" />
            </template>
            <VListItemTitle class="text-success">Excel</VListItemTitle>
          </VListItem>
          <VListItem @click="emit('export', 'pdf')">
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
        @click="emit('add-product')"
      >
        Añadir Producto
      </VBtn>
    </VCardActions>
  </VCard>
</template>
