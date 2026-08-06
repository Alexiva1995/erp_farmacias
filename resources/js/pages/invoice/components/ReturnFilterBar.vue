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
  <v-card class="elevation-2 rounded-lg pa-4 mb-4 border">
    <v-row density="compact" align="center">
      <v-col cols="12" md="4">
        <v-text-field
          :model-value="search"
          label="Buscar por N° Factura, Proveedor o Producto"
          prepend-inner-icon="tabler-search"
          density="compact"
          variant="outlined"
          hide-details
          clearable
          @update:model-value="emit('update:search', $event); emit('filter-change')"
        />
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-select
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
      </v-col>

      <v-col cols="12" sm="6" md="2">
        <v-text-field
          :model-value="dateFrom"
          type="date"
          label="Desde"
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="emit('update:dateFrom', $event); emit('filter-change')"
        />
      </v-col>

      <v-col cols="12" sm="6" md="2">
        <v-text-field
          :model-value="dateTo"
          type="date"
          label="Hasta"
          density="compact"
          variant="outlined"
          hide-details
          @update:model-value="emit('update:dateTo', $event); emit('filter-change')"
        />
      </v-col>

      <!-- Botón de Limpiar Filtros permanente al igual que el estándar AppFilterBase -->
      <v-col cols="12" md="1" class="d-flex align-center justify-end">
        <v-btn
          color="secondary"
          variant="tonal"
          icon="tabler-eraser"
          density="comfortable"
          @click="emit('clear')"
        >
          <v-icon icon="tabler-eraser" size="20" />
          <v-tooltip activator="parent" location="top">Limpiar Filtros</v-tooltip>
        </v-btn>
      </v-col>
    </v-row>
  </v-card>
</template>
