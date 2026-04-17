<script setup>
import { ref } from 'vue';

const props = defineProps({
  modelValue: Boolean,
  loading: Boolean,
});

const emit = defineEmits(['update:modelValue', 'generate']);

const selectedDate = ref(new Date().toISOString().substr(0, 10));

const close = () => {
  emit('update:modelValue', false);
};

const submit = () => {
  emit('generate', selectedDate.value);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="450"
    persistent
    @update:model-value="close"
  >
    <VCard class="rounded-xl overflow-hidden shadow-xl">
      <VCardItem class="bg-primary text-white py-4">
        <div class="d-flex align-center gap-3">
          <VAvatar color="white" variant="tonal" size="44" rounded="lg">
            <VIcon icon="tabler-player-play-filled" color="white" />
          </VAvatar>
          <VCardTitle class="text-h6 font-weight-black">Generar Nueva Nómina</VCardTitle>
        </div>
        <template #append>
          <VBtn icon="tabler-x" variant="text" color="white" @click="close" />
        </template>
      </VCardItem>

      <VCardText class="pa-6">
        <p class="text-body-1 text-medium-emphasis mb-6">
          Seleccione la fecha correspondiente al periodo de nómina que desea generar. 
          <br>
          <span class="text-xs font-weight-bold text-primary">Nota: El sistema detectará automáticamente si es 1ra o 2da quincena.</span>
        </p>

        <VRow>
          <VCol cols="12">
            <AppDateTimePicker
              v-model="selectedDate"
              label="Fecha de Nómina"
              placeholder="Seleccionar fecha"
              :config="{ altFormat: 'd F, Y', dateFormat: 'Y-m-d' }"
              prepend-inner-icon="tabler-calendar-event"
              class="rounded-lg"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          class="rounded-lg px-6"
          @click="close"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          class="rounded-lg px-8 shadow-primary"
          :loading="props.loading"
          @click="submit"
        >
          Generar Ahora
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.shadow-xl {
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1) !important;
}
.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}
.bg-light {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}
</style>
