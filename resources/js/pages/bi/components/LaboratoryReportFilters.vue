<script setup>
defineProps({
  groupByCorporate: {
    type: Boolean,
    required: true
  },
  startDate: {
    type: String,
    required: true
  },
  endDate: {
    type: String,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits([
  'update:groupByCorporate',
  'update:startDate',
  'update:endDate',
  'refresh'
]);
</script>

<template>
  <VCard border class="mb-4 rounded-lg">
    <VCardText class="pa-4">
      <VRow align="center">
        <!-- Toggle Individual / Corporativo -->
        <VCol cols="12" md="4">
          <VBtnToggle
            :model-value="groupByCorporate"
            @update:model-value="emit('update:groupByCorporate', $event)"
            mandatory
            color="primary"
            variant="outlined"
            density="comfortable"
            class="w-100"
            :disabled="loading"
          >
            <VBtn :value="false" class="flex-grow-1">
              <VIcon icon="tabler-building" class="me-1" size="18" />
              Individual
            </VBtn>
            <VBtn :value="true" class="flex-grow-1">
              <VIcon icon="tabler-building-community" class="me-1" size="18" />
              Corporativo
            </VBtn>
          </VBtnToggle>
        </VCol>

        <VSpacer />

        <!-- Rango de Fechas -->
        <VCol cols="12" sm="5" md="3">
          <VTextField
            :model-value="startDate"
            @update:model-value="emit('update:startDate', $event)"
            type="date"
            label="Fecha Inicio"
            variant="outlined"
            density="comfortable"
            hide-details="auto"
            prepend-inner-icon="tabler-calendar"
            :disabled="loading"
          />
        </VCol>

        <VCol cols="12" sm="5" md="3">
          <VTextField
            :model-value="endDate"
            @update:model-value="emit('update:endDate', $event)"
            type="date"
            label="Fecha Fin"
            variant="outlined"
            density="comfortable"
            hide-details="auto"
            prepend-inner-icon="tabler-calendar"
            :disabled="loading"
          />
        </VCol>

        <!-- Botón de Refresco -->
        <VCol cols="12" sm="2" md="1" class="d-flex justify-end">
          <VBtn
            icon="tabler-refresh"
            variant="tonal"
            color="primary"
            density="comfortable"
            :loading="loading"
            :disabled="loading"
            @click="emit('refresh')"
          />
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
