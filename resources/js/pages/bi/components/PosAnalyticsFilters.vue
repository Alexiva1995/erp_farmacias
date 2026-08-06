<script setup>
import { computed } from 'vue';

const props = defineProps({
  loading: { type: Boolean, default: false },
  startDate: { type: String, required: true },
  endDate: { type: String, required: true },
});

const emit = defineEmits(['update:startDate', 'update:endDate', 'fetch', 'reset']);

const localStartDate = computed({
  get: () => props.startDate,
  set: (val) => emit('update:startDate', val),
});

const localEndDate = computed({
  get: () => props.endDate,
  set: (val) => emit('update:endDate', val),
});
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
    <VCardText class="pa-4">
      <VRow align="center" no-gutters class="gap-2">
        <VCol cols="12" md="6">
          <VRow dense align="center">
            <VCol cols="5" sm="4">
              <AppTextField
                v-model="localStartDate"
                type="date"
                :disabled="loading"
                density="compact"
                hide-details
                prepend-inner-icon="tabler-calendar"
                class="premium-input-compact"
              />
            </VCol>
            <VCol cols="1" class="text-center text-disabled font-weight-bold">al</VCol>
            <VCol cols="5" sm="4">
              <AppTextField
                v-model="localEndDate"
                type="date"
                :disabled="loading"
                density="compact"
                hide-details
                prepend-inner-icon="tabler-calendar"
                class="premium-input-compact"
              />
            </VCol>
          </VRow>
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-2">
          <VBtn
            icon
            variant="flat"
            color="primary"
            size="38"
            class="rounded-circle shadow-sm"
            :loading="loading"
            :disabled="loading"
            @click="emit('fetch')"
          >
            <VIcon icon="tabler-refresh" size="20" />
            <VTooltip activator="parent" location="top">Sincronizar Datos</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2 border-opacity-10" />

          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            class="rounded-circle shadow-sm"
            :disabled="loading"
            @click="emit('reset')"
          >
            <VIcon icon="tabler-eraser" size="20" />
            <VTooltip activator="parent" location="top">Restablecer Periodo</VTooltip>
          </VBtn>
        </div>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.gap-2 { gap: 8px; }
.premium-input-compact :deep(.v-field__input) {
  font-size: 0.85rem !important;
}
</style>
