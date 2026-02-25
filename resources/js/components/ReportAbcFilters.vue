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
  coverageOptions: {
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
        <VCol cols="12" md="3">
          <AppDateTimePicker
            :model-value="props.filters.startDate"
            placeholder="Desde"
            clearable
            @update:model-value="updateFilter('startDate', $event)"
          />
        </VCol>
        <VCol cols="12" md="3">
          <AppDateTimePicker
            :model-value="props.filters.endDate"
            placeholder="Hasta"
            clearable
            @update:model-value="updateFilter('endDate', $event)"
          />
        </VCol>

        <VCol cols="12" md="3">
          <VSelect
            :model-value="props.filters.coverage_range"
            :items="props.coverageOptions"
            label="Filtro de Cobertura"
            placeholder="Todas"
            clearable
            @update:model-value="updateFilter('coverage_range', $event)"
          />
        </VCol>

        <VCol cols="12" md="3">
          <VSelect
            :model-value="props.filters.classification"
            :items="props.abcOptions"
            label="Clase"
            placeholder="Todas"
            clearable
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
