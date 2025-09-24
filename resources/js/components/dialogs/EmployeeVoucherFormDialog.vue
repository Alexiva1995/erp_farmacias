<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  selectedEmployee: { type: Object, default: null },
  vouchers: { type: Array, default: [] },
});

const emit = defineEmits(["update:modelValue", "refresh-table"]);

const errors = ref({});
const selectedOption = ref("");
const selectedType = ref("");
const selectedFrequency = ref("");
const amount = ref(null);

const options = [
  "Bono de Alimentación",
  "Bono de Transporte",
  "Bono de Rendimiento",
  "Bono de Facturas",
  "Bono de Ventas",
  "Bono de Crecimiento de Ventas",
  "Bono de Productos Asignados",
  "Salario Base",
  "Utilidades",
  "Vacaciones",
  "Bono Vacacional",
  "Liquidación",
  "Bono de Ayuda familiar",
  "Seguro Social",
  "Prestacional de Empleo",
  "Prestación Vivienda y Hacienda",
  "Dias no Trabajados",
  "Prestamos",
];

const closeDialog = () => {
  emit("update:modelValue", false);
};

const handleClearInputs = () => {
  errors.value = {};
  selectedOption.value = "";
  selectedType.value = "";
  selectedFrequency.value = "";
  amount.value = null;
};

const submitForm = async () => {
  errors.value = {};
  try {
    const form = new FormData();

    form.append("name", selectedOption.value);
    form.append("type", selectedType.value);
    form.append("frequency", selectedFrequency.value);
    form.append("amount", amount.value);

    await axios.post(
      `/rrhh/employees/${props.selectedEmployee.id}/voucher`,
      form
    );

    toast.success("El bono ha sido registrado exitosamente");

    closeDialog();
    handleClearInputs();
    emit("refresh-table");
  } catch (error) {
    toast.error("No se pudo registrar el bono para el empleado");

    if (error.response.status === 422) {
      errors.value = error.response.data.errors;
    }
  }
};
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
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline"> Nuevo bono </span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VContainer>
        <VRow>
          <VCol cols="12" sm="6">
            <VSelect
              v-model="selectedOption"
              label="Bono o deducción"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.name"
              :items="
                options.map((opt) => ({
                  title: opt,
                  value: opt,
                }))
              "
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VSelect
              v-model="selectedType"
              label="Tipo"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.type"
              :items="[
                { title: 'Bono', value: 'salary' },
                { title: 'Deducción', value: 'deduction' },
              ]"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VSelect
              v-model="selectedFrequency"
              label="Frecuencia"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.frequency"
              :items="[
                { title: 'Anual', value: 'annual' },
                { title: 'Mensual', value: 'monthly' },
                { title: 'Quincenal', value: 'fortnight' },
              ]"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VTextField
              v-model="amount"
              label="Monto"
              variant="outlined"
              type="number"
              hide-details="auto"
              :error-messages="errors.amount"
            />
          </VCol>
        </VRow>
      </VContainer>
      <VDivider />
      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          width="100%"
          class="flex-grow-1 w-0 mr-4"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          width="100%"
          class="flex-grow-1 w-0 mr-4"
        >
          Aceptar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
