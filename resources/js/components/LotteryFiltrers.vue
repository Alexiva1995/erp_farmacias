<script setup>
const props = defineProps({
  numero_de_premios: [String, null],
  fechaHasta_filtro: [String, null],
  fechaDesde_filtro: [String, null],
  laboratory_id: [String, null],
  monto_minimo: [String, null],
  laboratories: { type: Array, default: () => [] },
});

const emit = defineEmits([
  "update:numero_de_premios",
  "update:fechaHasta_filtro",
  "update:fechaDesde_filtro",
  "update:laboratory_id",
  "update:monto_minimo",
  "clear",
  "action-sortiar",
]);
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
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
        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.laboratory_id"
            label="Laboratorios"
            :items="props.laboratories"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:laboratory_id', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VNumberInput
            :model-value="props.monto_minimo"
            placeholder="Monto Minimo"
            clearable
            variant="outlined"
            precision="2"
            @update:model-value="emit('update:monto_minimo', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VNumberInput
            :model-value="props.numero_de_premios"
            placeholder="Numero de ganadores"
            clearable
            variant="outlined"
            @update:model-value="emit('update:numero_de_premios', $event)"
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
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="emit('action-sortiar', 'ok')"
      >
        Sortiar
      </VBtn>
    </VCardActions>
  </VCard>
</template>
