<script setup>
import { ref, watch } from "vue"

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
  laboratoryLinks: { type: Array, default: () => [] },
  discountRules: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(["update:modelValue", "save", "clearErrors"])

const formData = ref({
  scale_type: "units", // default
  rules: [], // array de rangos
})

const formErrors = ref({})

const addRule = () => {
  formData.value.rules.push({
    supplier_laboratory_id: null,
    min: null,
    max: null,
    discount_percentage: null,
  })
}

const removeRule = (index) => {
  formData.value.rules.splice(index, 1)
}

const submitForm = () => {
  formErrors.value = {}
  emit("clearErrors")
  emit("save", {
    scale_type: formData.value.scale_type,
    rules: formData.value.rules,
  })
  formData.value.rules = []
  formData.value.scale_type = "units"
}

const closeDialog = () => {
  emit("update:modelValue", false)
  formErrors.value = {}
  emit("clearErrors")
  formData.value.rules = []
  formData.value.scale_type = "units"
}

watch(
  () => props.errors,
  (newErrors) => {
    formErrors.value = newErrors || {}
    formData.value.rules = []
  },
  { deep: true }
)
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
    persistent
    @update:model-value="closeDialog"
    scrollable
    content-class="d-flex"
  >
    <VCard class="d-flex flex-column">
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold">Reglas de Descuento</span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText style="overflow-y: auto" class="px-6 py-4">
        <template v-if="props.loading">
            <div class="text-center text-medium-emphasis py-8">
                <VProgressCircular indeterminate color="primary" size="32" />
                <div class="mt-2">Cargando reglas de descuento...</div>
            </div>
        </template>

        <template v-else>
          <VForm @submit.prevent="submitForm">

            <VRadioGroup
              v-model="formData.scale_type"
              label="Tipo de escala"
              :error-messages="formErrors.scale_type"
              inline
            >
              <VRadio label="Por unidades" value="units" />
              <VRadio label="Por dólares" value="amount" />
            </VRadioGroup>

            <VDivider class="my-4" />

            <p class="text-subtitle-1 font-weight-medium">Rangos de descuento</p>

            <VRow
              v-for="(rule, index) in formData.rules"
              :key="index"
              class="mb-4"
            >
              <VCol cols="4">
                <VSelect
                  v-model="rule.supplier_laboratory_id"
                  label="Laboratorio"
                  :items="props.laboratoryLinks"
                  item-title="laboratory.name"
                  item-value="id"
                  variant="outlined"
                  :error-messages="formErrors[`rules.${index}.supplier_laboratory_id`]"
                />
              </VCol>
              <VCol cols="2">
                <VTextField
                  v-model="rule.min"
                  label="Desde"
                  type="number"
                  :error-messages="formErrors[`rules.${index}.min`]"
                />
              </VCol>
              <VCol cols="2">
                <VTextField
                  v-model="rule.max"
                  label="Hasta"
                  type="number"
                  :error-messages="formErrors[`rules.${index}.max`]"
                />
              </VCol>
              <VCol cols="3">
                <VTextField
                  v-model="rule.discount_percentage"
                  label="Descuento %"
                  type="number"
                  :error-messages="formErrors[`rules.${index}.discount_percentage`]"
                />
              </VCol>
              <VCol cols="1" class="d-flex align-center">
                <VBtn icon variant="text" color="error" @click="removeRule(index)">
                  <VIcon>tabler-trash</VIcon>
                </VBtn>
              </VCol>
            </VRow>

            <VBtn
              variant="tonal"
              color="primary"
              class="mt-2"
              @click="addRule"
            >
              Agregar rango
            </VBtn>

            <VDivider class="my-6" />

            <div class="d-flex align-center mb-4">
              <p class="text-h6 font-weight-medium">Reglas existentes</p>
              <VSpacer />
            </div>

            <VDataTable
              :headers="[
                { title: 'Laboratorio', key: 'laboratory_name' },
                { title: 'Tipo', key: 'scale_type' },
                { title: 'Desde', key: 'min' },
                { title: 'Hasta', key: 'max' },
                { title: 'Descuento %', key: 'discount_percentage' }
              ]"
              :items="props.discountRules"
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
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          class="flex-grow-1 w-0"
        >
          Guardar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
