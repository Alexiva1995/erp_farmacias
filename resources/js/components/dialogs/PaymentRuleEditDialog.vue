<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
  paymentRules: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

const formData = ref({
  rules: [], // array de rangos
});
const formErrors = ref({});

const addRule = () => {
  formData.value.rules.push({
    discount_percentage: null,
    days: null,
  });
};

const removeRule = (index) => {
  formData.value.rules.splice(index, 1);
};

const submitForm = () => {
  formErrors.value = {};
  emit("clearErrors");
  emit("save", {
    rules: formData.value.rules,
  });
  formData.value.rules = [];
};

const closeDialog = () => {
  emit("update:modelValue", false);
  formErrors.value = {};
  emit("clearErrors");
  formData.value.rules = [];
};

watch(
  () => props.errors,
  (newErrors) => {
    formErrors.value = newErrors || {};
    formData.value.rules = [];
  },
  { deep: true }
);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard class="d-flex flex-column">
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold">Regla de Pronto Pago</span>

        <VSpacer />

        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="px-6 py-4" style="overflow-y: auto">
        <template v-if="props.loading">
          <div class="text-center text-medium-emphasis py-8">
            <VProgressCircular indeterminate color="primary" size="32" />
            <div class="mt-2">Cargando reglas de pronto pago...</div>
          </div>
        </template>
        <template v-else>
          <VForm @submit.prevent="submitForm">
            <VRow v-for="(rule, index) in formData.rules" :key="index" class="mb-4">
              <VCol cols="12" md="5">
                <VTextField
                  v-model="rule.days"
                  label="Días"
                  type="number"
                  variant="outlined"
                  :error-messages="formErrors[`rules.${index}.days`]"
                />
              </VCol>
              <VCol cols="12" md="5">
                <VTextField
                  v-model="rule.discount_percentage"
                  label="% de Descuento"
                  type="number"
                  variant="outlined"
                  :error-messages="formErrors[`rules.${index}.discount_percentage`]"
                />
              </VCol>
              <VCol cols="12" md="2" class="d-flex align-center">
                <VBtn icon variant="text" color="error" @click="removeRule(index)">
                  <VIcon>tabler-trash</VIcon>
                </VBtn>
              </VCol>
            </VRow>
            <VBtn variant="tonal" color="primary" class="mt-2" @click="addRule">
              Agregar Regla
            </VBtn>

            <VDivider class="my-6" />

            <div class="d-flex align-center mb-4">
              <p class="text-h6 font-weight-medium">Reglas existentes</p>
              <VSpacer />
            </div>

            <VDataTable
              :headers="[
                { title: 'Días', key: 'days' },
                { title: '% de Descuento', key: 'discount_percentage' },
              ]"
              :items="props.paymentRules"
              density="compact"
              no-data-text="No hay reglas registradas para este proveedor."
            />
          </VForm>
        </template>
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
        <VBtn color="primary" variant="flat" @click="submitForm" class="flex-grow-1 w-0">
          Guardar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
