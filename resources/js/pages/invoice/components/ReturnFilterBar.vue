<script setup>
defineProps({
  search: { type: String, default: '' },
  status: { type: String, default: '' },
  dateFrom: { type: String, default: '' },
  dateTo: { type: String, default: '' },
  statusOptions: { type: Array, required: true },
})

const emit = defineEmits([
  'update:search',
  'update:status',
  'update:dateFrom',
  'update:dateTo',
  'clear',
  'filter-change',
])
</script>

<template>
  <VCard class="elevation-2 rounded-lg pa-4 mb-4 border bg-surface">
    <VRow density="compact" align="center">
      <VCol cols="12" md="4">
        <VTextField
          :model-value="search"
          label="Buscar por Factura, Proveedor o Producto"
          placeholder="Buscar..."
          prepend-inner-icon="tabler-search"
          density="compact"
          variant="outlined"
          hide-details
          clearable
          @update:model-value="emit('update:search', $event); emit('filter-change')"
        />
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="status"
          :items="statusOptions"
          item-title="title"
          item-value="value"
          label="Filtrar por Estado"
          prepend-inner-icon="tabler-filter"
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="emit('update:status', $event); emit('filter-change')"
        />
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <VTextField
          :model-value="dateFrom"
          type="date"
          label="Desde"
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="emit('update:dateFrom', $event); emit('filter-change')"
        />
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <VTextField
          :model-value="dateTo"
          type="date"
          label="Hasta"
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="emit('update:dateTo', $event); emit('filter-change')"
        />
      </VCol>

      <VCol cols="12" md="1" class="d-flex align-center justify-end">
        <IconBtn
          color="secondary"
          variant="tonal"
          @click="emit('clear')"
        >
          <VIcon icon="tabler-eraser" size="20" />
          <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
        </IconBtn>
      </VCol>
    </VRow>
  </VCard>
</template>
