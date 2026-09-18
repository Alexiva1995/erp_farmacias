<script setup>
import { computed, ref, watch } from 'vue';
import { useDisplay } from 'vuetify';

const { mobile } = useDisplay();

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
    max-width="520"
    persistent
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    @update:model-value="close"
  >
    <VCard class="rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <!-- Header Premium con Degradado -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            size="40"
            color="white"
            variant="flat"
            class="me-3 shadow-sm rounded-lg elevation-1"
          >
            <VIcon icon="tabler-player-play-filled" color="primary" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h3 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Generar Nueva Nómina
            </h3>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.65rem; letter-spacing: 0.05em;"
              >
                Cálculo y apertura de período salarial
              </span>
            </div>
          </div>
          <VSpacer />
          <IconBtn
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="close"
            :disabled="props.loading"
          >
            <VIcon icon="tabler-x" size="20" />
            <VTooltip activator="parent" location="top">Cerrar</VTooltip>
          </IconBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-6 bg-light">
        <p class="text-caption text-medium-emphasis mb-4">
          Seleccione la fecha correspondiente al corte de nómina que desea procesar para los trabajadores.
        </p>

        <VRow dense>
          <VCol cols="12" class="mb-3">
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
                  <span class="text-super-xs font-weight-black uppercase d-block opacity-75">Período Detectado</span>
                  <span class="text-subtitle-2 font-weight-black d-block">{{ periodInfo.type }}</span>
                  <span class="text-caption font-weight-bold opacity-90">{{ periodInfo.range }}</span>
                </div>
              </div>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <!-- Botones de Acción -->
      <VCardActions class="pa-4 bg-light">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          class="rounded-lg px-6 font-weight-bold"
          :disabled="props.loading"
          @click="close"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          class="rounded-lg px-8 shadow-primary font-weight-black"
          :loading="props.loading"
          :disabled="props.loading"
          @click="submit"
        >
          <VIcon start icon="tabler-check" size="18" class="me-1" />
          Generar Ahora
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end, var(--v-theme-primary))) 100%
  );
}

.shadow-xl {
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1) !important;
}
.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}
.bg-light {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}
.leading-none {
  line-height: 1 !important;
}
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}
</style>
