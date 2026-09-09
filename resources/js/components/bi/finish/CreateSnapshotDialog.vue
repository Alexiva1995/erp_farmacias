<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  errors: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(['update:modelValue', 'submit']);

const form = reactive({
  cutoff_date: new Date().toISOString().split('T')[0],
  period_days: 30,
  name: '',
});

watch(() => props.modelValue, (isOpen) => {
  if (isOpen) {
    const today = new Date().toISOString().split('T')[0];
    form.cutoff_date = today;
    form.period_days = 30;
    form.name = `Foto Finish ${today}`;
  }
});

const handleClose = () => {
  if (!props.loading) {
    emit('update:modelValue', false);
  }
};

const handleSubmit = () => {
  emit('submit', {
    cutoff_date: form.cutoff_date,
    period_days: Number(form.period_days),
    name: form.name?.trim() || null,
  });
};
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="500"
    persistent
    @update:model-value="handleClose"
  >
    <VCard class="rounded-xl overflow-hidden">
      <VCardItem class="bg-primary text-white pa-4">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-camera-plus" size="22" />
            <h3 class="text-h6 font-weight-bold text-white mb-0">Tomar Foto Finish</h3>
          </div>
          <VBtn
            icon
            variant="text"
            size="30"
            color="white"
            :disabled="loading"
            @click="handleClose"
          >
            <VIcon icon="tabler-x" size="20" />
          </VBtn>
        </div>
      </VCardItem>

      <VCardText class="pa-5">
        <p class="text-body-2 text-medium-emphasis mb-4">
          Selecciona la fecha de corte para congelar la radiografía de inventario, existencias, ventas y cálculo de sobrestock.
        </p>

        <VRow dense>
          <VCol cols="12">
            <label class="text-caption font-weight-bold mb-1 d-block">Fecha de Corte *</label>
            <AppTextField
              v-model="form.cutoff_date"
              type="date"
              :max="new Date().toISOString().split('T')[0]"
              density="compact"
              variant="outlined"
              :error-messages="errors.cutoff_date"
              :disabled="loading"
            />
          </VCol>

          <VCol cols="12">
            <label class="text-caption font-weight-bold mb-1 d-block">Periodo de Ventas (Días)</label>
            <AppTextField
              v-model="form.period_days"
              type="number"
              min="7"
              max="365"
              density="compact"
              variant="outlined"
              placeholder="30"
              :error-messages="errors.period_days"
              :disabled="loading"
            />
            <span class="text-super-xs text-medium-emphasis">Ventana de días hacia atrás desde la fecha de corte (por defecto 30 días).</span>
          </VCol>

          <VCol cols="12" class="mt-2">
            <label class="text-caption font-weight-bold mb-1 d-block">Nombre / Identificador (Opcional)</label>
            <AppTextField
              v-model="form.name"
              placeholder="Ej: Cierre Agosto 2026, Auditoría Q3..."
              density="compact"
              variant="outlined"
              :error-messages="errors.name"
              :disabled="loading"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider class="border-opacity-10" />

      <VCardActions class="pa-4 d-flex justify-end gap-2">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="loading"
          @click="handleClose"
        >
          Cancelar
        </VBtn>

        <VBtn
          variant="flat"
          color="primary"
          :loading="loading"
          :disabled="loading"
          @click="handleSubmit"
        >
          <VIcon icon="tabler-camera" size="18" class="me-1" />
          Congelar Foto Finish
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.6875rem !important;
  line-height: 0.875rem !important;
}
.gap-2 { gap: 8px !important; }
</style>
