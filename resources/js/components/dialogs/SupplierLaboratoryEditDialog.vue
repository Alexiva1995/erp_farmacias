<script setup>
import { ref, watch, computed } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
  laboratories: { type: Array, default: () => [] },
  laboratoryLinks: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

const editableRulesLaboratory = ref([]);
const tempIdCounter = ref(-1);
const internalErrors = ref({});
const isEditing = ref(false);
const rulesOriginalValues = ref({});

/*if (props.laboratoryLinks && props.laboratoryLinks.length > 0) {
console.log(props.laboratoryLinks);
    editableRulesLaboratory.value = props.laboratoryLinks.map((rule) => {
        return {
            ...rule,
            laboratory: rule.laboratory ? { ...rule.laboratory } : { id: null, name: null }, 
            _markedForDeletion: false,
            _markedForEdit: false,
        };
    });
}*/

watch(
  () => props.errors,
  (newErrors) => {
    internalErrors.value = { ...internalErrors.value, ...newErrors };
  },
  { deep: true }
);

watch(
  () => props.laboratoryLinks,
  (newRulesLaboratory) => {
    if (!newRulesLaboratory || newRulesLaboratory.length === 0) {
      editableRulesLaboratory.value = [];
      return;
    }
    
    editableRulesLaboratory.value = newRulesLaboratory.map((rule) => {
      return {
        ...rule,
        laboratory: rule.laboratory ? { ...rule.laboratory } : { id: null, name: null }, 
        _markedForDeletion: false,
        _markedForEdit: false, 
      };
    });
    console.log("Datos cargados en editableRulesLaboratory:", editableRulesLaboratory.value);
  },
  { deep: true, immediate: true }
);

console.log(editableRulesLaboratory);

const closeDialog = () => {
  internalErrors.value = {};
  emit("update:modelValue", false);
};

const addNewRuleLaboratoryRow = () => {
  editableRulesLaboratory.value.push({
    id: tempIdCounter.value,
    phone: null,
    laboratory: {
       id: null,
      name: null,
    },
    _markedForEdit: true,
    _markedForDeletion: false,
  });
  tempIdCounter.value--;
};

const removeRule = (ruleLaboratory) => {
  const index = editableRulesLaboratory.value.findIndex((r) => r === ruleLaboratory);
  if (index === -1) return;
  editableRulesLaboratory.value.splice(index, 1);
  Object.keys(internalErrors.value).forEach((key) => {
    if (key.endsWith(`_${index}`)) {
      delete internalErrors.value[key];
    }
  });
};

const restoreRule = (ruleLaboratory) => {
 if (ruleLaboratory.id > 0 && !ruleLaboratory._markedForEdit) {
        rulesOriginalValues.value[ruleLaboratory.id] = {
            phone: ruleLaboratory.phone,
            laboratory: { ...ruleLaboratory.laboratory },
            _markedForDeletion: ruleLaboratory._markedForDeletion,
        };
    }
  ruleLaboratory._markedForDeletion = false;
  ruleLaboratory._markedForEdit = true;
};


const syncLaboratoryName = (newLaboratoryId, item) => {
    if (newLaboratoryId === null || newLaboratoryId === undefined) {
        Object.assign(item.laboratory, { id: null, name: null });
        item._markedForEdit = true; 
        return;
    }
    
    const selectedLaboratory = props.laboratories.find(
        (lab) => lab.id === newLaboratoryId
    );

    if (!selectedLaboratory) {
        Object.assign(item.laboratory, { id: newLaboratoryId, name: 'Laboratorio no encontrado' }); 
        item._markedForEdit = true;
        return;
    }
    
    Object.assign(item.laboratory, { 
        id: selectedLaboratory.id, 
        name: selectedLaboratory.name 
    });
    item._markedForEdit = true; 
}

const getFieldError = (field, index) => {
  return internalErrors.value[`${field}_${index}`];
};

const canSave = computed(() => {
  if (editableRulesLaboratory.value.length === 0) {
    return true;
  }
  let allRulesAreValid = true;
  editableRulesLaboratory.value.forEach((ruleLaboratory, index) => {
    if (!ruleLaboratory._markedForDeletion) {
        if (!validateRuleLaboratory(ruleLaboratory, index)) {
            allRulesAreValid = false;
        }
    }
  });
  if (Object.keys(internalErrors.value).length > 0) {
      return false;
  }
  const hasActiveRulesLaboratory = editableRulesLaboratory.value.some(ruleLaboratory => !ruleLaboratory._markedForDeletion);
  return allRulesAreValid && (hasActiveRulesLaboratory || editableRulesLaboratory.value.every(ruleLaboratory => ruleLaboratory._markedForDeletion));
});

const onSave = () => {
  let allFormFieldsValid = true;
  internalErrors.value = {};

  editableRulesLaboratory.value.forEach((ruleLaboratory, index) => {
    if (!ruleLaboratory._markedForDeletion) {
      if (!validateRuleLaboratory(ruleLaboratory, index)) {
        allFormFieldsValid = false;
      }
    }
  });

  if (allFormFieldsValid) {
    const rulesToSave = editableRulesLaboratory.value.map((ruleLaboratory) => ({
      id: ruleLaboratory.id && ruleLaboratory.id > 0 ? ruleLaboratory.id : undefined,
      phone: ruleLaboratory.phone,
      laboratory: ruleLaboratory.laboratory,
    }));
    emit("save", rulesToSave);
  }
};

const validateRuleLaboratory = (ruleLaboratory, index) => {
if (ruleLaboratory._markedForDeletion) {
        delete internalErrors.value[`phone_${index}`];
        if (parseInt(ruleLaboratory.laboratory) === 0) { 
            return true;
        }
        return true;
    }
    
    let isValid = true;
    
  
    if (!ruleLaboratory.phone || isNaN(ruleLaboratory.phone) || String(ruleLaboratory.phone).trim() === '') {
        internalErrors.value[`phone_${index}`] = "El telefono es requerido";
        isValid = false;
    } else {
        delete internalErrors.value[`phone_${index}`]; 
    }

    if (!ruleLaboratory.laboratory || !ruleLaboratory.laboratory.id) {
        internalErrors.value[`laboratory_id_${index}`] = "El laboratorio es requerido";
        isValid = false;
    } else {
        delete internalErrors.value[`laboratory_id_${index}`];
    }

    return isValid;

  /*if (ruleLaboratory._markedForDeletion || parseInt(ruleLaboratory.laboratory) === 0) {
    delete internalErrors.value[`phone_${index}`];
    return true;
  }
  let isValid = true;

  if (isNaN(ruleLaboratory.phone)) {
    internalErrors.value[`phone_${index}`] = "El telefono es requerido";
    isValid = false;
  } else {
    delete internalErrors.value[`rule_phone_${index}`];
  }

  return isValid;*/
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
        <span class="text-h5 font-weight-bold">Laboratorios</span>
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
            @click="addNewRuleLaboratoryRow"
            >Asociar Laboratorio
          </VBtn>
        </div>
        <VDataTable
          :headers="[
            { title: 'Número Representante', key: 'phone', sortable: false },
            { title: 'Laboratorio', key: 'laboratory.name', sortable: false },
            { title: 'Acciones', key: 'actions', sortable: false },
          ]"
          :items="editableRulesLaboratory"
          density="compact"
          class="rounded-lg"
          no-data-text="No hay reglas registradas para este proveedor."
        >
          <template #item="{ item, index }">
            <tr 
              :key="item.id || item.tempId"
              :class="{ 'bg-grey-100 opacity-60': !item._markedForEdit }">
              <td>
                <VTextField
                  v-if="item._markedForEdit"
                  v-model="item.phone"
                  type="tel"
                  prefix="+"
                  variant="outlined"
                  :error-messages="getFieldError('phone', index)"
                  hide-details="auto"
                  density="compact"
                />

                <div v-else class="d-flex align-center">
                  <span>{{ item.phone ? `+${item.phone}` : "N/A" }}</span>
                  <VTooltip
                    text="Contactar por WhatsApp"
                    location="top"
                    :z-index="5000"
                  >
                    <template #activator="{ props }">
                      <VBtn
                        icon
                        :href="
                          item.phone
                            ? `https://wa.me/${item.phone.replace(/\D/g, '')}`
                            : undefined
                        "
                        target="_blank"
                        variant="text"
                        size="small"
                        v-bind="props"
                        :disabled="!item.phone"
                      >
                        <VIcon
                          :icon="
                            item.phone
                              ? 'tabler-brand-whatsapp'
                              : 'tabler-phone-off'
                          "
                          :color="item.phone ? 'success' : 'grey'"
                        />
                      </VBtn>
                    </template>
                  </VTooltip>
                </div>
              </td>

              <td>
                <VAutocomplete
                  v-if="item._markedForEdit"
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
                  item.laboratory && item.laboratory.name
                    ? item.laboratory.name
                    : "Sin Laboratorio"
                }}</span>
              </td>

              <td>
                <div class="d-flex gap-1">
                  <VBtn
                    icon="tabler-trash"
                    variant="text"
                    color="error"
                    size="small"
                    @click="removeRule(item)"
                  />
                  <VBtn
                    icon="tabler-edit"
                    variant="text"
                    color="success"
                    size="small"
                    @click="restoreRule(item)"
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
          Guardar Cambios
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
