<script setup>
import { computed, ref, watch } from 'vue';

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

const periodInfo = computed(() => {
  if (!selectedDate.value) return null;
  const dateParts = selectedDate.value.split('-');
  if (dateParts.length !== 3) return null;

  const year = dateParts[0];
  const month = dateParts[1];
  const day = parseInt(dateParts[2], 10);

  const monthsEs = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
  ];
  const monthName = monthsEs[parseInt(month, 10) - 1] || '';

  if (day <= 15) {
    return {
      type: '1ra Quincena',
      range: `01 al 15 de ${monthName} ${year}`,
      icon: 'tabler-calendar-event',
      color: 'info'
    };
  } else {
    const lastDay = new Date(year, parseInt(month, 10), 0).getDate();
    return {
      type: '2da Quincena (Fin de Mes)',
      range: `16 al ${lastDay} de ${monthName} ${year}`,
      icon: 'tabler-calendar-check',
      color: 'success'
    };
  }
});
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="500"
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
        <p class="text-body-2 text-medium-emphasis mb-4">
          Seleccione la fecha correspondiente al corte de nómina que desea procesar.
        </p>

        <VRow dense>
          <VCol cols="12" class="mb-4">
            <AppDateTimePicker
              v-model="selectedDate"
              label="Fecha de Corte"
              placeholder="Seleccionar fecha"
              :config="{ altFormat: 'd F, Y', dateFormat: 'Y-m-d' }"
              prepend-inner-icon="tabler-calendar-event"
              class="rounded-lg"
            />
          </VCol>

          <!-- Previsualización Clara del Período -->
          <VCol v-if="periodInfo" cols="12">
            <VCard variant="tonal" :color="periodInfo.color" class="pa-4 rounded-lg border">
              <div class="d-flex align-center gap-3">
                <VIcon :icon="periodInfo.icon" size="28" />
                <div>
                  <span class="text-xs font-weight-black uppercase d-block opacity-75">Período Detectado</span>
                  <span class="text-subtitle-2 font-weight-black d-block">{{ periodInfo.type }}</span>
                  <span class="text-caption font-weight-bold opacity-90">{{ periodInfo.range }}</span>
                </div>
              </div>
            </VCard>
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
