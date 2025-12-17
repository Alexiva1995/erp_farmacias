<script setup>
const props = defineProps({
  searchQuery: String,
  startDate: [String, null],
  endDate: [String, null],
  users: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:startDate",
  "update:endDate",
  "clear",
]);

const handleClear = () => {
  emit("clear");
};
</script>

<template>
  <VCard  class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="3" md="2">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por Producto, Usuario..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <VCol cols="12" sm="2" md="3">
          <AppDateTimePicker
            :model-value="props.startDate"
            placeholder="Desde"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:startDate', $event)"
          />
        </VCol>

        <VCol cols="12" sm="2" md="3">
          <AppDateTimePicker
            :model-value="props.endDate"
            placeholder="Hasta"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:endDate', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6">
      <VBtn color="secondary" variant="outlined" @click="handleClear">
        Limpiar Filtros
      </VBtn>
    </VCardActions>
  </VCard>
</template>
