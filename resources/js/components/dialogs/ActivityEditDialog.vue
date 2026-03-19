<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  activity: { type: Object, default: () => ({}) },
  frequencies: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

const { mobile } = useDisplay();

const formData = ref({});
const formErrors = ref({});

const isNewActivity = computed(() => !formData.value.id);

watch(
  () => props.errors,
  (newErrors) => {
    formErrors.value = newErrors || {};
  },
  { deep: true },
);

watch(
  () => props.activity,
  (newActivity) => {
    if (newActivity && Object.keys(newActivity).length > 0) {
      formData.value = JSON.parse(JSON.stringify(newActivity));
    } else {
      formData.value = {
        activity: "",
        description: "",
        frequency: null,
      };
    }
    formErrors.value = {};
  },
  { deep: true, immediate: true },
);

const closeDialog = () => {
  emit("update:modelValue", false);
  formErrors.value = {};
  emit("clearErrors");
};

const submitForm = () => {
  formErrors.value = {};
  emit("clearErrors");

  const payload = {
    activity: formData.value.activity,
    description: formData.value.description,
    frequency: formData.value.frequency,
  };

  emit("save", payload);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    :max-width="mobile ? undefined : '600px'"
    :fullscreen="mobile"
    persistent
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    @update:model-value="closeDialog"
  >
    <VCard v-if="formData" class="rounded-xl border-0 shadow-xl overflow-hidden d-flex flex-column">
      <!-- Header con Gradiente Premium -->
      <div class="premium-header pa-5 d-flex align-center bg-primary">
        <div class="d-flex align-center gap-3">
          <VAvatar color="white" variant="tonal" size="40" class="rounded-lg">
            <VIcon
              :icon="isNewActivity ? 'tabler-plus' : 'tabler-edit'"
              size="24"
              color="white"
            />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-h6 font-weight-black text-white leading-none mb-1">
              {{ isNewActivity ? "Nueva Actividad" : "Editar Actividad" }}
            </span>
            <span class="text-xs text-white opacity-70 font-weight-medium">
              {{ isNewActivity ? "Crea una nueva tarea de limpieza" : `Editando ID: #${formData.id}` }}
            </span>
          </div>
        </div>

        <VSpacer />
        
        <VBtn
          icon="tabler-x"
          variant="text"
          color="white"
          size="small"
          class="rounded-lg bg-white-opacity-10"
          @click="closeDialog"
        />
      </div>

      <VDivider class="opacity-10" />

      <VCardText class="pa-6 flex-grow-1" style="max-block-size: 70vh; overflow-y: auto;">
        <VForm @submit.prevent="submitForm">
          <!-- Campo Actividad -->
          <div class="mb-6">
            <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Nombre de la Actividad</span>
            <VTextField
              v-model="formData.activity"
              placeholder="Ej: Desinfección de estanterías"
              variant="outlined"
              color="primary"
              density="compact"
              :error-messages="formErrors.activity"
              class="premium-input"
              hide-details="auto"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-activity" size="18" color="disabled" class="me-2" />
              </template>
            </VTextField>
          </div>

          <!-- Campo Frecuencia -->
          <div class="mb-6">
            <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Frecuencia de Ejecución</span>
            <VSelect
              v-model="formData.frequency"
              :items="props.frequencies"
              placeholder="Seleccionar frecuencia"
              variant="outlined"
              color="primary"
              density="compact"
              :error-messages="formErrors.frequency"
              class="premium-input"
              hide-details="auto"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-calendar-stats" size="18" color="disabled" class="me-2" />
              </template>
            </VSelect>
          </div>

          <!-- Campo Descripción -->
          <div class="mb-2">
            <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Instrucciones Detalladas</span>
            <VTextarea
              v-model="formData.description"
              placeholder="Describe paso a paso cómo se debe realizar esta limpieza..."
              variant="outlined"
              color="primary"
              density="compact"
              rows="4"
              :error-messages="formErrors.description"
              class="premium-input"
              hide-details="auto"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-notes" size="18" color="disabled" class="me-2 mt-1" />
              </template>
            </VTextarea>
          </div>
        </VForm>
      </VCardText>

      <VDivider class="opacity-10" />

      <VCardActions class="pa-6 d-flex gap-3 mt-auto">
        <VBtn
          color="secondary"
          variant="tonal"
          class="rounded-lg font-weight-black flex-grow-1 h-44"
          @click="closeDialog"
        >
          CANCELAR
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          class="rounded-lg font-weight-black flex-grow-1 h-44 shadow-sm"
          @click="submitForm"
        >
          <VIcon start icon="tabler-device-floppy" size="18" />
          GUARDAR CAMBIOS
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.premium-header {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #2b3341 100%) !important;
}

.bg-white-opacity-10 {
  background-color: rgba(255, 255, 255, 10%) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.leading-none {
  line-height: 1;
}

.h-44 {
  block-size: 44px !important;
}

:deep(.premium-input) {
  .v-field__outline {
    --v-field-border-opacity: 0.15;
  }
}

.shadow-xl {
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 25%) !important;
}
</style>
