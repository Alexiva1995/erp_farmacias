<template>
  <VDialog :model-value="modelValue" max-width="600" persistent @update:model-value="$emit('update:modelValue', $event)">
    <VCard>
      <VCardTitle class="d-flex align-center bg-primary text-white">
        <VIcon icon="tabler-file-plus" class="mr-2" />
        Crear Nueva Declaración ISLR
      </VCardTitle>

      <VCardText class="pt-6">
        <VForm ref="formRef" @submit.prevent="handleSubmit">
          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="formData.year"
                label="Año Fiscal"
                type="number"
                variant="outlined"
                :rules="[(v) => !!v || 'El año es requerido']"
                readonly
                disabled
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="formData.amount"
                label="Monto a Pagar"
                type="number"
                variant="outlined"
                prefix="Bs."
                :disabled="loading"
                :rules="[
                  (v) => !!v || 'El monto es requerido',
                  (v) => v >= 0 || 'El monto debe ser mayor o igual a 0',
                ]"
              />
            </VCol>

            <VCol cols="12">
              <VSelect
                v-model="formData.status"
                label="Estado"
                :items="[
                  { title: 'Pagado', value: 'paid' },
                  { title: 'No Pagado', value: 'unpaid' },
                ]"
                variant="outlined"
                :disabled="loading"
                :rules="[(v) => !!v || 'El estado es requerido']"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="formData.declaration_date"
                label="Fecha de Declaración"
                type="date"
                variant="outlined"
                :disabled="loading"
                :rules="[(v) => !!v || 'La fecha es requerida']"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardActions class="px-6 pb-6">
        <VSpacer />
        <VBtn
          color="secondary"
          variant="outlined"
          :disabled="loading"
          @click="$emit('update:modelValue', false)"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          :loading="loading"
          :disabled="loading"
          @click="handleSubmit"
        >
          Crear Declaración
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  initialYear: { type: Number, required: true },
  estimatedAmount: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "submit"]);

const formRef = ref(null);
const formData = ref({
  year: props.initialYear,
  amount: props.estimatedAmount,
  status: "unpaid",
  declaration_date: new Date().toISOString().split("T")[0],
});

watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      formData.value = {
        year: props.initialYear,
        amount: props.estimatedAmount,
        status: "unpaid",
        declaration_date: new Date().toISOString().split("T")[0],
      };
    }
  }
);

const handleSubmit = async () => {
  const { valid } = await formRef.value.validate();
  if (!valid) return;
  emit("submit", { ...formData.value });
};
</script>
