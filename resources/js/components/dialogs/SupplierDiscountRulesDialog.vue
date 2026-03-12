<script setup>
import { ref, watch, computed } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
  laboratories: { type: Array, default: () => [] },
  laboratoryLinks: { type: Array, default: () => [] },
  discountRules: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

const editableRange = ref([]);
const tempIdCounter = ref(-1);
const internalErrors = ref({});
const scale_type = ref([
  { id: 'units', name: 'Por unidades' },
  { id: 'amount', name: 'Por dólares' },
]);

watch(
  () => props.errors,
  (newErrors) => {
    internalErrors.value = { ...internalErrors.value, ...newErrors };
  },
  { deep: true }
);

watch(
  () => props.discountRules,
  (newRange) => {
    if (!newRange || newRange.length === 0) {
      editableRange.value = [];
      return;
    }
    
    editableRange.value = newRange.map((range) => {
      return {
        ...range, 
        _markedForDeletion: false,
        _markedNew: false, 
      };
    });
    console.log("Datos cargados en editableRange:", editableRange.value);
  },
  { deep: true, immediate: true }
);

const closeDialog = () => {
  internalErrors.value = {};
  emit("update:modelValue", false);
};

const addNewRange = () => {
  editableRange.value.push({
    id: tempIdCounter.value,
    laboratory: {
       id: null,
      name: null,
    },
     scale_type: {
       id: 'units',
      name: 'Por unidades',
    },
    min: 1,
    max: 1,
    discount_percentage:1,
    _markedNew: true,
    _markedForDeletion: false,
  });
  tempIdCounter.value--;
};

const removeRange = (range) => {
  const index = editableRange.value.findIndex(r => r === range);
  if (index === -1) return;
  editableRange.value.splice(index, 1);
  Object.keys(internalErrors.value).forEach((key) => {
    if (key.endsWith(`_${index}`)) {
      delete internalErrors.value[key];
    }
  });
};

const getFieldError = (field, index) => {
  return internalErrors.value[`${field}_${index}`];
};

const validateRangeDiscountPercentage = (range, index) => {
  const discount_percentage = parseInt(range.discount_percentage);
  if (range._markedForDeletion) {
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

const onDiscountPercentageChange = (range, index) => {
  validateRangeDiscountPercentage(range, index);
};


const validateRange = (range, index) => {
  if (range._markedForDeletion || parseInt(range.discount_percentage) === 0) {
     Object.keys(internalErrors.value).forEach((key) => {
            if (key.endsWith(`_${index}`)) {
                delete internalErrors.value[key];
            }
        });
    return true;
  }
  let isValid = true;

    const minVal = parseInt(range.min);
    if (isNaN(minVal) || minVal < 1) {
        internalErrors.value[`min_${index}`] = "El valor mínimo debe ser 1 o mayor";
        isValid = false;
    } 
    else{
          delete internalErrors.value[`min_${index}`];
        }

    const maxVal = parseInt(range.max);

    if (isNaN(maxVal) || maxVal < 1) {
        internalErrors.value[`max_${index}`] = "El valor máximo debe ser 1 o mayor";
        isValid = false;
    } else if (maxVal < minVal) {
        internalErrors.value[`max_${index}`] = "El máximo debe ser mayor o igual al mínimo";
        isValid = false;
    } else{
          delete internalErrors.value[`max_${index}`];
        }

  if (!validateRangeDiscountPercentage(range, index)) {
    isValid = false;
  }

    if (typeof range.scale_type === 'object') {
        if (!range.scale_type || !range.scale_type.id) {
          internalErrors.value[`scale_type_id_${index}`] = "El tipo de escala es requerido";
          isValid = false;
        }else{
          delete internalErrors.value[`scale_type_id_${index}`];
        }
    } else {
        delete internalErrors.value[`scale_type_id_${index}`];
    }

    if (!range.laboratory_name && typeof range.laboratory === 'object') {
      if (!range.laboratory || !range.laboratory.id) {
        internalErrors.value[`laboratory_id_${index}`] = "El laboratorio es requerido";
        isValid = false;
      }else{
          delete internalErrors.value[`laboratory_id_${index}`];
      }
    }else {
        delete internalErrors.value[`laboratory_id_${index}`];
    }

  return isValid;
};

const canSave = computed(() => {

  if (editableRange.value.length === 0) {
    return true;
  }
  let allRulesAreValid = true;
  editableRange.value.forEach((range, index) => {
    if (!range._markedForDeletion) {
        if (!validateRange(range, index)) {
            allRulesAreValid = false;
        }
    }
  });
  if (Object.keys(internalErrors.value).length > 0) {
      return false;
  }
 
 const hasChanges = editableRange.value.some(range => 
    range._markedNew
     );
  return allRulesAreValid && hasChanges;
 // const hasActiveRange = editableRange.value.some(range => !range._markedForDeletion);
  //return allRulesAreValid && (hasActiveRange || editableRange.value.every(range => range._markedForDeletion));
});

const onSave = () => {
  let allFormFieldsValid = true;
  internalErrors.value = {};

  editableRange.value.forEach((range, index) => {
    if (!range._markedForDeletion) {
      if (!validateRange(range, index)) {
        console.log(validateRange(range, index));
        allFormFieldsValid = false;
      }
    }
  });

  if (allFormFieldsValid) {
  console.log('dentro de if');

  const rangesToProcess = editableRange.value.filter(range => 
      range._markedNew
    );

    const rangeToSave = rangesToProcess.map((range) => ({
      id: range.id && range.id > 0 ? range.id : undefined,
      laboratory: range.laboratory,
      scale_type: range.scale_type,
      min: range.min,
      max: range.max,
      discount_percentage: parseFloat(range.discount_percentage),
    }));
    emit("save", rangeToSave);
  }
};

const syncLaboratoryName = (newLaboratoryId, item) => {
    if (newLaboratoryId === null || newLaboratoryId === undefined) {
        Object.assign(item.laboratory, { id: null, name: null });
        return;
    }
    const selectedLaboratory = props.laboratories.find(
        (lab) => lab.id === newLaboratoryId
    );
    if (!selectedLaboratory) {
        Object.assign(item.laboratory, { id: newLaboratoryId, name: 'Laboratorio no encontrado' }); 
        return;
    }
    Object.assign(item.laboratory, { 
        id: selectedLaboratory.id, 
        name: selectedLaboratory.name 
    });
}

const syncTypeName = (newscale_type_id, item) => {
    if (newscale_type_id === null || newscale_type_id === undefined) {
        Object.assign(item.scale_type, { id: null, name: null });
        return;
    }
    const selectedType = scale_type.value.find(
        (type) => type.id === newscale_type_id
    );
    if (!selectedType) {
        Object.assign(item.scale_type, { id: newscale_type_id, name: 'Tipo no encontrado' }); 
        return;
    }
    Object.assign(item.scale_type, { 
        id: selectedType.id, 
        name: selectedType.name 
    });
}

</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="900px"
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
      <VDivider/>

   <VCardText class="flex-grow-1" style="overflow-y: auto;">
        <div class="d-flex align-center mb-4">
          <VSpacer />
          <VBtn
            prepend-icon="tabler-plus"
            color="primary"
            variant="flat"
            @click="addNewRange"
            >Asociar rango
          </VBtn>
        </div>
        <VDataTable
          :headers="[
            { title: 'Laboratorio', key: 'laboratory_name', sortable: false },
            { title: 'Tipo', key: 'scale_type', sortable: false },
            { title: 'Desde', key: 'min', sortable: false },
            { title: 'Hasta', key: 'max', sortable: false },
            { title: 'Descuento %', key: 'discount_percentage', sortable: false },
            { title: 'Accion', key: 'actions', sortable: false },
          ]"
          :items="editableRange"
          density="compact"
          class="rounded-lg"
          no-data-text="No hay reglas registradas para este proveedor."
        >
          <template #item="{ item, index }">
            <tr 
              :key="item.id || item.tempId"
              :class="{ 'bg-grey-100 opacity-60': !item._markedNew }">
              <td>
                <VAutocomplete
                  v-if="item._markedNew"
                  v-model="item.laboratory.id"
                  :items="props.laboratories"
                  label="Laboratorio"
                  placeholder="Escribe para buscar un laboratorio"
                  item-title="name"
                  item-value="id"
                  clearable
                  variant="outlined"
                  :error-messages="getFieldError('laboratory_id', index)"
                  hide-details="auto"
                  density="compact"
                  @update:model-value="syncLaboratoryName($event, item)"
                />
                <span v-else>{{
                  item.laboratory_name && item.laboratory_name
                    ? item.laboratory_name
                    : "Sin Laboratorio"
                }}</span>
              </td>
              <td>
                <VAutocomplete
                  v-if="item._markedNew"
                  v-model="item.scale_type.id"
                  :items="scale_type"
                  label="Tipo"
                  placeholder="Escribe para buscar un Tipo"
                  item-title="name"
                  item-value="id"
                  clearable
                  variant="outlined"
                  :error-messages="getFieldError('scale_type_id', index)"
                  hide-details="auto"
                  density="compact"
                  @update:model-value="syncTypeName($event, item)"
                />
                <span v-else>{{item.scale_type}}</span>
              </td>
              <td>
                <VTextField
                  v-model="item.min"
                  type="number"
                  variant="plane"
                  :error-messages="getFieldError('min', index)"
                  hide-details="auto"
                  density="compact"
                  :disabled="!item._markedNew"
                  @update:model-value="validateRange(item, index)"
                />
              </td>
              <td>
                <VTextField
                  v-model="item.max"
                  type="number"
                  variant="plane"
                  :error-messages="getFieldError('max', index)"
                  hide-details="auto"
                  density="compact"
                  :disabled="!item._markedNew"
                  @update:model-value="validateRange(item, index)"
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
                  @update:model-value="validateRange(item, index)"
                />
              </td>
              <td>
                <div class="d-flex gap-1">
                  <VBtn
                    icon="tabler-trash"
                    variant="text"
                    color="error"
                    size="small"
                    @click="removeRange(item)"
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
