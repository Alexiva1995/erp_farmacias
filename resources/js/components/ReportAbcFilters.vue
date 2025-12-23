<script setup>
const props = defineProps({
  filters: {
    type: Object,
    required: true,
  },
  abcOptions: {
    type: Array,
    required: true,
  },
});

const emit = defineEmits(["update:filters", "clear"]);

const updateFilter = (key, value) => {
  emit("update:filters", { ...props.filters, [key]: value });
};
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" md="4">
          <AppDateTimePicker
            :model-value="props.filters.startDate"
            placeholder="Desde"
            clearable
            prepend-inner-icon="tabler-calendar"
            @update:model-value="updateFilter('startDate', $event)"
          />
        </VCol>
        <VCol cols="12" md="4">
          <AppDateTimePicker
            :model-value="props.filters.endDate"
            placeholder="Hasta"
            clearable
            prepend-inner-icon="tabler-calendar"
            @update:model-value="updateFilter('endDate', $event)"
          />
        </VCol>
        <VCol cols="12" md="4">
          <VSelect
            :model-value="props.filters.classification"
            :items="props.abcOptions"
            label="Filtrar por Clase"
            placeholder="Todas las Clases"
            clearable
            prepend-inner-icon="tabler-filter"
            @update:model-value="updateFilter('classification', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>
    <VDivider />
    <VCardActions class="pa-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>
    </VCardActions>
  </VCard>
</template>
