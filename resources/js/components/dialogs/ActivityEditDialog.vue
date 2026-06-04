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
    <VCard v-if="formData" class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon
              :icon="isNewActivity ? 'tabler-circle-plus' : 'tabler-edit'"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ isNewActivity ? "Nueva Actividad" : "Editar Actividad" }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.65rem;">
                {{ isNewActivity ? "Registro de gestión" : `Editando ID: #${formData.id}` }}
              </span>
            </div>
          </div>

          <VSpacer />
          
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="closeDialog"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light flex-grow-1 overflow-y-auto">
        <VForm @submit.prevent="submitForm" class="d-flex flex-column gap-6">
          <section>
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary shadow-sm"></div>
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Detalles de Actividad</span>
            </div>

            <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border">
              <!-- Campo Actividad -->
              <VRow>
                <VCol cols="12">
                  <AppTextField
                    v-model="formData.activity"
                    label="Nombre de la Actividad"
                    placeholder="Ej: Desinfección de estanterías"
                    variant="outlined"
                    density="comfortable"
                    :error-messages="formErrors.activity"
                    prepend-inner-icon="tabler-activity"
                    class="shadow-sm"
                    hide-details="auto"
                  />
                </VCol>

                <!-- Campo Frecuencia -->
                <VCol cols="12">
                  <AppSelect
                    v-model="formData.frequency"
                    label="Frecuencia de Ejecución"
                    :items="props.frequencies"
                    placeholder="Seleccionar frecuencia"
                    variant="outlined"
                    density="comfortable"
                    :error-messages="formErrors.frequency"
                    prepend-inner-icon="tabler-calendar-stats"
                    class="shadow-sm"
                    hide-details="auto"
                  />
                </VCol>

                <!-- Campo Descripción -->
                <VCol cols="12">
                  <AppTextarea
                    v-model="formData.description"
                    label="Instrucciones Detalladas"
                    placeholder="Describe paso a paso cómo se debe realizar esta limpieza..."
                    variant="outlined"
                    density="comfortable"
                    rows="4"
                    :error-messages="formErrors.description"
                    prepend-inner-icon="tabler-notes"
                    class="shadow-sm"
                    hide-details="auto"
                  />
                </VCol>
              </VRow>
            </VCard>
          </section>
        </VForm>
      </VCardText>

      <VCardActions class="pa-4 bg-light border-t">
        <VRow no-gutters class="w-100">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="closeDialog"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="submitForm"
            >
              <VIcon start icon="tabler-device-floppy" size="18" class="me-2" />
              Guardar Cambios
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
