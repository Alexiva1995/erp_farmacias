<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  currentValue: {
    type: Number,
    required: true,
  },
});

const emit = defineEmits(["update:modelValue", "save"]);

const tempUT = ref(props.currentValue);
const effectiveDate = ref(new Date().toISOString().split("T")[0]);
const notes = ref("");

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      tempUT.value = props.currentValue;
      effectiveDate.value = new Date().toISOString().split("T")[0];
      notes.value = "";
    }
  }
);

const closeDialog = () => {
  emit("update:modelValue", false);
};

const saveUT = () => {
  const data = {
    value: tempUT.value,
    effective_date: effectiveDate.value,
    notes: notes.value || null,
  };
  emit("save", data);
  closeDialog();
};

const formatCurrency = (amount) => {
  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: "VES",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
};
</script>

<template>
  <VDialog
    :model-value="modelValue"
    @update:model-value="closeDialog"
    max-width="600"
  >
    <VCard>
      <VCardTitle class="bg-primary text-white">
        <VIcon class="mr-2">mdi-pencil</VIcon>
        {{ currentValue > 0 ? "Editar" : "Crear" }} Unidad Tributaria
      </VCardTitle>
      <VCardText class="pt-6">
        <VRow>
          <VCol cols="12">
            <VTextField
              v-model.number="tempUT"
              label="Valor de la Unidad Tributaria (Bs)"
              type="number"
              step="0.01"
              variant="outlined"
              prepend-inner-icon="mdi-currency-usd"
              hint="Ingrese el valor de la Unidad Tributaria en Bolívares"
              persistent-hint
              :rules="[(v) => v > 0 || 'El valor debe ser mayor a 0']"
            />
          </VCol>
          <VCol cols="12">
            <VTextField
              v-model="effectiveDate"
              label="Fecha Efectiva"
              type="date"
              variant="outlined"
              prepend-inner-icon="mdi-calendar"
              hint="Fecha desde la cual es válida esta Unidad Tributaria"
              persistent-hint
            />
          </VCol>
          <VCol cols="12">
            <VTextarea
              v-model="notes"
              label="Notas (Opcional)"
              variant="outlined"
              prepend-inner-icon="mdi-note-text"
              hint="Agregue notas o comentarios sobre esta actualización"
              persistent-hint
              rows="3"
              counter="500"
              :rules="[(v) => !v || v.length <= 500 || 'Máximo 500 caracteres']"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions class="px-4 pb-4">
        <VRow class="w-100">
          <VCol cols="6">
            <VBtn color="grey" variant="text" block @click="closeDialog">
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6">
            <VBtn
              color="primary"
              variant="flat"
              block
              @click="saveUT"
              :disabled="!tempUT || tempUT <= 0"
            >
              Guardar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
