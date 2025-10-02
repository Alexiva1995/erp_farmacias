<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  activity: { type: Object, default: () => ({}) },
  frequencies: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

const formData = ref({});
const formErrors = ref({});

const isNewActivity = computed(() => !formData.value.id);

watch(
  () => props.errors,
  (newErrors) => {
    formErrors.value = newErrors || {};
  },
  { deep: true }
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
  { deep: true, immediate: true }
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
    max-width="600px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard v-if="formData" class="d-flex flex-column">
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold">{{
          isNewActivity ? "Añadir Nueva Actividad" : "Editar Actividad"
        }}</span>

        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1" style="overflow-y: auto">
        <VForm @submit.prevent="submitForm">
          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="formData.activity"
                label="Nombre de la Actividad"
                variant="outlined"
                placeholder="Ej: Revisión de Inventario"
                :error-messages="formErrors.activity"
                required
              />
            </VCol>
          </VRow>

          <VRow>
            <VCol cols="12">
              <VTextarea
                v-model="formData.description"
                label="Descripción"
                variant="outlined"
                placeholder="Describe la actividad en detalle..."
                rows="4"
                :error-messages="formErrors.description"
              />
            </VCol>
          </VRow>

          <VRow>
            <VCol cols="12">
              <VSelect
                v-model="formData.frequency"
                label="Frecuencia"
                :items="props.frequencies"
                variant="outlined"
                placeholder="Selecciona la frecuencia"
                :error-messages="formErrors.frequency"
                required
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1 w-0 mr-4"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          class="flex-grow-1 w-0"
        >
          {{ isNewActivity ? "Crear" : "Guardar" }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
