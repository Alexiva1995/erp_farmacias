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
  <VCard border class="mb-4 rounded-lg shadow-sm">
    <VCardText class="pa-4">
      <VRow align="center">
        <VCol cols="12" md="4" class="d-flex align-center gap-2">
          <VBtn 
            :color="!groupByCorporate ? 'primary' : 'secondary'" 
            variant="tonal" 
            @click="emit('update:groupByCorporate', false)"
            class="flex-grow-1"
            :disabled="loading"
          >
            Individual
          </VBtn>
          <VBtn 
            :color="groupByCorporate ? 'primary' : 'secondary'" 
            variant="tonal" 
            @click="emit('update:groupByCorporate', true)"
            class="flex-grow-1"
            :disabled="loading"
          >
            Corporativo
          </VBtn>
        </VCol>

        <VSpacer />

        <VCol cols="12" sm="5" md="3">
          <AppTextField 
            :model-value="startDate" 
            @update:model-value="emit('update:startDate', $event)" 
            type="date" 
            label="Desde" 
            density="compact" 
            hide-details 
            :disabled="loading" 
          />
        </VCol>

        <VCol cols="12" sm="5" md="3">
          <AppTextField 
            :model-value="endDate" 
            @update:model-value="emit('update:endDate', $event)" 
            type="date" 
            label="Hasta" 
            density="compact" 
            hide-details 
            :disabled="loading" 
          />
        </VCol>

        <VCol cols="12" sm="2" md="1" class="text-right">
          <VBtn 
            icon 
            variant="tonal" 
            color="primary" 
            @click="emit('refresh')" 
            :loading="loading" 
            :disabled="loading"
          >
            <VIcon icon="tabler-refresh" />
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
