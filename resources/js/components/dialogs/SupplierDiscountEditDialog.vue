<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
  supplierDiscount: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

const formData = ref({
  discounts: [], // array de rangos
});
const formErrors = ref({});

const addDiscount = () => {
  formData.value.discounts.push({
    discount_percentage: null,
    name: null,
  });
};

const removeDiscount = (index) => {
  formData.value.discounts.splice(index, 1);
};

const submitForm = () => {
  formErrors.value = {};
  emit("clearErrors");
  emit("save", {
    discounts: formData.value.discounts,
  });
  formData.value.discounts = [];
};

const closeDialog = () => {
  emit("update:modelValue", false);
  formErrors.value = {};
  emit("clearErrors");
  formData.value.discounts = [];
};

watch(
  () => props.errors,
  (newErrors) => {
    formErrors.value = newErrors || {};
    formData.value.discounts = [];
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
        <span class="text-h5 font-weight-bold">Descuentos</span>

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
            <div class="mt-2">Cargando descuentos...</div>
          </div>
        </template>
        <template v-else>
          <VForm @submit.prevent="submitForm">
            <VRow
              v-for="(discount, index) in formData.discounts"
              :key="index"
              class="mb-4"
            >
              <VCol cols="12" md="5">
                <VTextField
                  v-model="discount.name"
                  label="Nombre"
                  type="text"
                  variant="outlined"
                  :error-messages="formErrors[`discounts.${index}.name`]"
                />
              </VCol>
              <VCol cols="12" md="5">
                <VTextField
                  v-model="discount.discount_percentage"
                  label="% de Descuento"
                  type="number"
                  variant="outlined"
                  :error-messages="formErrors[`discounts.${index}.discount_percentage`]"
                />
              </VCol>
              <VCol cols="12" md="2" class="d-flex align-center">
                <VBtn icon variant="text" color="error" @click="removeDiscount(index)">
                  <VIcon>tabler-trash</VIcon>
                </VBtn>
              </VCol>
            </VRow>
            <VBtn variant="tonal" color="primary" class="mt-2" @click="addDiscount">
              Agregar Descuento
            </VBtn>

            <VDivider class="my-6" />

            <div class="d-flex align-center mb-4">
              <p class="text-h6 font-weight-medium">Descuentos existentes</p>
              <VSpacer />
            </div>

            <VDataTable
              :headers="[
                { title: 'Nombre', key: 'name' },
                { title: '% de Descuento', key: 'discount_percentage' },
              ]"
              :items="props.supplierDiscount"
              density="compact"
              no-data-text="No hay descuentos registrados para este proveedor."
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
