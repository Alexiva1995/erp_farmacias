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
  // "Bono de Alimentación",
  // "Bono de Transporte",
  // "Bono de Rendimiento",
  // "Bono de Facturas",
  // "Bono de Ventas",
  // "Bono de Crecimiento de Ventas",
  // "Bono de Productos Asignados",
  "Salario Base",
  // "Utilidades",
  // "Vacaciones",
  // "Bono Vacacional",
  // "Liquidación",
  // "Bono de Ayuda familiar",
  // "Seguro Social",
  // "Prestacional de Empleo",
  // "Prestación Vivienda y Hacienda",
  // "Dias no Trabajados",
  // "Prestamos",
];

const closeDialog = () => {
  emit("update:modelValue", false);
  handleClearInputs();
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
    max-width="700px"
    persistent
    scrollable
    :retain-focus="false"
    @click:outside.prevent
    @keydown.esc.prevent="closeDialog"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-5 bg-primary">
        <div class="d-flex align-center gap-3">
          <VIcon icon="tabler-receipt" size="24" color="white" />
          <span class="text-h6 text-white">Nuevo bono</span>
        </div>
        <VBtn icon variant="text" color="white" size="small" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <VRow>
          <VCol cols="12" sm="6" md="6">
            <VSelect
              v-model="selectedOption"
              label="Bono o deducción"
              variant="outlined"
              :error-messages="errors.name"
              :items="
                options.map((opt) => ({
                  title: opt,
                  value: opt,
                }))
              "
            />
          </VCol>
          <VCol cols="12" sm="6" md="6">
            <VSelect
              v-model="selectedType"
              label="Tipo"
              variant="outlined"
              :error-messages="errors.type"
              :items="[
                { title: 'Bono', value: 'salary' },
                { title: 'Deducción', value: 'deduction' },
              ]"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6">
            <VSelect
              v-model="selectedFrequency"
              label="Frecuencia"
              variant="outlined"
              :error-messages="errors.frequency"
              :items="[
                { title: 'Anual', value: 'annual' },
                { title: 'Mensual', value: 'monthly' },
                { title: 'Quincenal', value: 'fortnight' },
              ]"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6">
            <VTextField
              v-model="amount"
              label="Monto"
              variant="outlined"
              type="number"
              :error-messages="errors.amount"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-5">
        <VRow class="w-100 ma-0">
          <VCol cols="6" class="pa-2">
            <VBtn
              color="secondary"
              variant="outlined"
              prepend-icon="tabler-x"
              block
              @click="closeDialog"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-2">
            <VBtn
              color="primary"
              variant="flat"
              prepend-icon="tabler-check"
              block
              @click="submitForm"
            >
              Guardar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
