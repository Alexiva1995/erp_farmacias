<script setup>
import { ref, watch, computed } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
  supplierDiscount: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);
/*
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
);*/


const editableRules = ref([]);
const tempIdCounter = ref(-1);
const internalErrors = ref({});

watch(
  () => props.errors,
  (newErrors) => {
    internalErrors.value = { ...internalErrors.value, ...newErrors };
  },
  { deep: true }
);


watch(
  () => props.supplierDiscount,
  (newRules) => {
    if (!newRules || newRules.length === 0) {
      editableRules.value = [];
      return;
    }
    editableRules.value = newRules.map((rule) => {
      return {
        ...rule,
        _markedForDeletion: false,
        _markedNew: false,
      };
    });
  },
  { deep: true, immediate: true }
);

const closeDialog = () => {
  internalErrors.value = {};
  emit("update:modelValue", false);
};

const addNewRuleRow = () => {
  editableRules.value.push({
    id: tempIdCounter.value,
    name: null,
    discount_percentage:1,
    _markedForDeletion: true,
     _markedNew: true,
  });
  tempIdCounter.value--;
};

const removeRule = (rule) => {
  const index = editableRules.value.findIndex(r => r === rule);
  if (index === -1) return;
  editableRules.value.splice(index, 1);
  Object.keys(internalErrors.value).forEach((key) => {
    if (key.endsWith(`_${index}`)) {
      delete internalErrors.value[key];
    }
  });
};

const getFieldError = (field, index) => {
  return internalErrors.value[`${field}_${index}`];
};


const validateRuleDiscountPercentage = (rule, index) => {
  const discount_percentage = parseInt(rule.discount_percentage);
  if (rule._markedForDeletion) {
    delete internalErrors.value[`discount_percentage_${index}`];
    return true;
  }
  if (isNaN(discount_percentage) || discount_percentage <= 0) {
    internalErrors.value[`discount_percentage_${index}`] =
      "La cantidad debe ser mayor 0";
    return false;
  }
  delete internalErrors.value[`discount_percentage_${index}`];
  return true;
};

const validateRule = (rule, index) => {
  if (rule._markedForDeletion || parseInt(rule.discount_percentage) === 0) {
    delete internalErrors.value[`days_${index}`];
    return true;
  }
  let isValid = true;

  if (!rule.name) {
    internalErrors.value[`name_${index}`] = "El nombre es requerido";
    isValid = false;
  } else {
    delete internalErrors.value[`name_${index}`];
  }

  if (!validateRuleDiscountPercentage(rule, index)) {
    isValid = false;
  }
  return isValid;
};

const canSave = computed(() => {
  if (editableRules.value.length === 0) {
    return true;
  }
  let allRulesAreValid = true;
  editableRules.value.forEach((rule, index) => {
    if (!rule._markedForDeletion) {
        if (!validateRule(rule, index)) {
            allRulesAreValid = false;
        }
    }
  });
  if (Object.keys(internalErrors.value).length > 0) {
      return false;
  }
  const hasActiveRules = editableRules.value.some(rule => !rule._markedForDeletion);
  return allRulesAreValid && (hasActiveRules || editableRules.value.every(rule => rule._markedForDeletion));
});

const onSave = () => {
  let allFormFieldsValid = true;
  internalErrors.value = {};
  editableRules.value.forEach((rule, index) => {
    if (!rule._markedForDeletion) {
      if (!validateRule(rule, index)) {
        allFormFieldsValid = false;
      }
    }
  });
  if (allFormFieldsValid) {
    const rulesToSave = editableRules.value.map((rule) => ({
      id: rule.id && rule.id > 0 ? rule.id : undefined,
      name: rule.name,
      discount_percentage: parseFloat(rule.discount_percentage),
    }));
    emit("save", rulesToSave);
  }
};

const onDiscountPercentageChange = (rule, index) => {
  validateRuleDiscountPercentage(rule, index);
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
    <VCard class="d-flex flex-column">
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold">Descuentos</span>

        <VSpacer />

        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1" style="overflow-y: auto">
        <div class="d-flex align-center mb-4">
          <VSpacer />
          <VBtn
            prepend-icon="tabler-plus"
            color="primary"
            variant="flat"
            @click="addNewRuleRow"
          >
            Agregar Regla
          </VBtn>
        </div>
        <VDataTable
          :headers="[
            { title: 'Nombre', key: 'name', sortable: false },
            {
              title: '% de Descuento',
              key: 'discount_percentage',
              sortable: false,
            },
            { title: 'Acciones', key: 'actions', sortable: false },
          ]"
          :items="editableRules"
          density="compact"
          class="rounded-lg"
          no-data-text="No hay reglas registradas para este proveedor."
        >
          <template #item="{ item, index }">
            <tr :class="{ 'bg-grey-100 opacity-80': !item._markedNew }">
              <td>
                <VTextField
                  v-model="item.name"
                  type="text"
                  variant="plane"
                  :error-messages="getFieldError('name', index)"
                  hide-details="auto"
                  density="compact"
                  min="0"
                  :disabled="!item._markedNew"
                />
              </td>
              <td>
                <VTextField
                  v-model="item.discount_percentage"
                  type="number"
                  variant="plane"
                  :error-messages="getFieldError('discount_percentage', index)"
                  hide-details="auto"
                  density="compact"
                  @input="onDiscountPercentageChange(item, index)"
                  :disabled="!item._markedNew"
                />
              </td>
              <td>
                <div class="d-flex gap-1">
                  <VBtn
                  if
                    icon="tabler-trash"
                    variant="text"
                    color="error"
                    size="small"
                    @click="removeRule(item)"
                    :disabled="!item._markedNew"
                  />
                </div>
              </td>
            </tr>
          </template>
        </VDataTable>
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
          @click="onSave"
          :disabled="!canSave"
          class="flex-grow-1 w-0"
        >
          Guardar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
