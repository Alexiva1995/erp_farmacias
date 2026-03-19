<script setup>
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  employee: { type: Object, default: () => ({}) },
  employees: { type: Array, default: () => [] },
  laboratories: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clear-errors"]);

const { mobile } = useDisplay();

const isEditMode = computed(() => !!props.employee?.employee_id);
const dialogTitle = computed(() =>
  isEditMode.value ? "Editar Laboratorios Asignados" : "Asignar Laboratorios",
);

const formData = ref({
  employee_id: null,
  laboratories: [],
  new_laboratory_id: null,
});

const editingLaboratory = ref(null);
const tempLaboratoryId = ref(null);

watch(
  [() => props.modelValue, () => props.employee],
  ([newVisible], [oldVisible]) => {
    if (!newVisible) return;
    if (newVisible && (oldVisible === undefined || oldVisible === false)) {
      if (isEditMode.value && props.employee?.employee_id) {
        formData.value = {
          employee_id: props.employee.employee_id,
          laboratories: props.employee.laboratories
            ? JSON.parse(JSON.stringify(props.employee.laboratories))
            : [],
          new_laboratory_id: null,
        };
      } else {
        formData.value = {
          employee_id: null,
          laboratories: [],
          new_laboratory_id: null,
        };
      }
      editingLaboratory.value = null;
      tempLaboratoryId.value = null;
    }
  },
  { deep: true },
);

const displayEmployees = computed(() => {
  if (isEditMode.value && props.employee?.employee_id) {
    const exists = props.employees.some(e => Number(e.value) === Number(props.employee.employee_id));
    if (!exists) {
      return [
        ...props.employees,
        {
          title: props.employee.employee_name,
          value: props.employee.employee_id
        }
      ];
    }
  }
  return props.employees;
});

const closeDialog = () => {
  emit("update:modelValue", false);
  emit("clear-errors");
  editingLaboratory.value = null;
  tempLaboratoryId.value = null;
};

const handleAddLaboratory = () => {
  if (!formData.value.new_laboratory_id) return;

  const laboratory = props.laboratories.find(
    (lab) => Number(lab.value) === Number(formData.value.new_laboratory_id),
  );

  if (laboratory) {
    const exists = formData.value.laboratories.some(
      (lab) => Number(lab.id) === Number(laboratory.value),
    );

    if (!exists) {
      formData.value.laboratories.push({
        id: laboratory.value,
        name: laboratory.title,
      });
      formData.value.new_laboratory_id = null;
    }
  }
};

const handleRemoveLaboratory = (laboratoryId) => {
  formData.value.laboratories = formData.value.laboratories.filter(
    (lab) => lab.id !== laboratoryId,
  );
};

const handleEditLaboratory = (laboratory) => {
  editingLaboratory.value = laboratory.id;
  tempLaboratoryId.value = laboratory.id;
};

const handleSaveEdit = (oldLaboratoryId) => {
  if (!tempLaboratoryId.value) return;

  const newLab = props.laboratories.find(
    (lab) => Number(lab.value) === Number(tempLaboratoryId.value),
  );

  if (newLab) {
    const index = formData.value.laboratories.findIndex(
      (lab) => Number(lab.id) === Number(oldLaboratoryId),
    );

    if (index !== -1) {
      const updatedLabs = [...formData.value.laboratories];
      updatedLabs[index] = {
        id: newLab.value,
        name: newLab.title,
      };
      formData.value.laboratories = updatedLabs;
    }
  }

  editingLaboratory.value = null;
  tempLaboratoryId.value = null;
};

const handleCancelEdit = () => {
  editingLaboratory.value = null;
  tempLaboratoryId.value = null;
};

const handleSubmit = () => {
  const payload = {
    employee_id: formData.value.employee_id,
    laboratory_ids: formData.value.laboratories.map((lab) => lab.id),
  };
  emit("save", payload);
};

const availableLaboratories = computed(() => {
  return props.laboratories.filter(
    (lab) => !formData.value.laboratories.some((l) => Number(l.id) === Number(lab.value)),
  );
});

const getLaboratoryColor = (index) => {
  const colors = [
    "primary",
    "secondary",
    "success",
    "info",
    "warning",
    "error",
  ];
  return colors[index % colors.length];
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    :max-width="mobile ? undefined : '600px'"
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    persistent
    @update:model-value="closeDialog"
  >
    <VCard v-if="formData" class="rounded-xl border-0 shadow-xl overflow-hidden d-flex flex-column">
      <!-- Header Premium -->
      <div class="premium-header pa-5 d-flex align-center">
        <div class="d-flex align-center gap-3">
          <VAvatar color="white" variant="tonal" size="40" class="rounded-lg">
            <VIcon :icon="isEditMode ? 'tabler-edit' : 'tabler-flask-2'" size="22" color="white" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-h6 font-weight-black text-white leading-none mb-1">{{ dialogTitle }}</span>
            <span class="text-xs text-white opacity-70 font-weight-medium">
              {{ isEditMode ? `Empleado: ${props.employee?.employee_name || ''}` : 'Selecciona empleado y laboratorios' }}
            </span>
          </div>
        </div>
        <VSpacer />
        <VBtn icon="tabler-x" variant="text" color="white" size="small" class="rounded-lg bg-white-opacity-10" @click="closeDialog" />
      </div>

      <VDivider class="opacity-10" />

      <VCardText class="flex-grow-1 pa-6">
        <VForm @submit.prevent="handleSubmit">
          <!-- Selector de Empleado -->
          <div class="mb-6">
            <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Empleado *</span>
            <VSelect
              v-model="formData.employee_id"
              :items="displayEmployees"
              :disabled="isEditMode"
              placeholder="Selecciona un empleado"
              density="compact"
              color="primary"
              variant="outlined"
              :error-messages="props.errors.employee_id"
              clearable
              hide-details="auto"
              class="premium-input"
              @update:model-value="emit('clear-errors')"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-user" size="18" color="disabled" class="me-2" />
              </template>
              <template #selection="{ item }">
                <div class="d-flex align-center gap-2">
                  <VAvatar size="22" color="primary" variant="tonal" class="rounded">
                    <span class="text-super-xs font-weight-black">
                      {{ item.title.split(" ").map((n) => n[0]).join("").substring(0, 2).toUpperCase() }}
                    </span>
                  </VAvatar>
                  <span class="text-xs font-weight-bold text-capitalize">{{ item.title }}</span>
                </div>
              </template>
            </VSelect>
          </div>

          <!-- Agregar Nuevo Laboratorio -->
          <div class="mb-2">
            <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Agregar Laboratorio</span>
            <div class="d-flex gap-2">
              <VSelect
                v-model="formData.new_laboratory_id"
                :items="availableLaboratories"
                placeholder="Selecciona un laboratorio"
                :disabled="!formData.employee_id"
                clearable
                class="flex-grow-1 premium-input"
                density="compact"
                variant="outlined"
                color="primary"
                hide-details
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-flask" size="18" color="disabled" class="me-2" />
                </template>
              </VSelect>
              <VBtn
                color="success"
                variant="flat"
                class="rounded-lg"
                :disabled="!formData.new_laboratory_id || !formData.employee_id"
                @click="handleAddLaboratory"
                style="block-size: 38px; min-inline-size: 40px;"
              >
                <VIcon icon="tabler-plus" size="20" />
              </VBtn>
            </div>
          </div>

          <!-- Lista de Laboratorios Asignados -->
          <div class="mt-6">
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-super-xs font-weight-black text-disabled uppercase">Laboratorios Asignados</span>
              <VChip
                :color="formData.laboratories.length > 0 ? 'success' : 'surface-variant'"
                size="x-small"
                variant="flat"
                class="font-weight-black rounded"
                style="color: white !important;"
              >
                {{ formData.laboratories.length }}
              </VChip>
            </div>

            <!-- Tabla de Laboratorios -->
            <VCard variant="outlined" class="rounded-lg border">
              <div v-if="formData.laboratories.length === 0" class="pa-6 d-flex flex-column align-center justify-center text-center">
                <VIcon icon="tabler-flask-off" size="40" class="text-disabled mb-2 opacity-20" />
                <div class="text-xs font-weight-bold text-disabled uppercase">No hay laboratorios asignados</div>
              </div>

              <VList v-else class="pa-0">
                <template v-for="(lab, index) in formData.laboratories" :key="lab.id">
                  <VListItem class="px-4 py-2">
                    <template #prepend>
                      <VAvatar :color="getLaboratoryColor(index)" variant="tonal" size="32">
                        <VIcon icon="tabler-flask" size="18" />
                      </VAvatar>
                    </template>

                    <VListItemTitle>
                      <div v-if="editingLaboratory !== lab.id" class="text-body-2 font-weight-medium">
                        {{ lab.name }}
                      </div>
                      <VSelect
                        v-else
                        v-model="tempLaboratoryId"
                        :items="props.laboratories"
                        density="compact"
                        variant="outlined"
                        hide-details
                        class="my-1"
                      />
                    </VListItemTitle>

                    <template #append>
                      <div class="d-flex gap-1">
                        <template v-if="editingLaboratory !== lab.id">
                          <VBtn icon variant="text" size="x-small" color="warning" @click="handleEditLaboratory(lab)">
                            <VIcon icon="tabler-edit" size="18" />
                          </Btn>
                          <VBtn icon variant="text" size="x-small" color="error" @click="handleRemoveLaboratory(lab.id)">
                            <VIcon icon="tabler-trash" size="18" />
                          </Btn>
                        </template>
                        <template v-else>
                          <VBtn icon variant="text" size="x-small" color="success" @click="handleSaveEdit(lab.id)">
                            <VIcon icon="tabler-check" size="18" />
                          </Btn>
                          <VBtn icon variant="text" size="x-small" color="error" @click="handleCancelEdit">
                            <VIcon icon="tabler-x" size="18" />
                          </Btn>
                        </template>
                      </div>
                    </template>
                  </VListItem>
                  <VDivider v-if="index < formData.laboratories.length - 1" class="opacity-10" />
                </template>
              </VList>
            </VCard>
          </div>
        </VForm>
      </VCardText>

      <VDivider class="opacity-10" />

      <VCardActions class="pa-6 d-flex gap-3">
        <VBtn color="secondary" variant="tonal" class="rounded-lg font-weight-black flex-grow-1 h-44" @click="closeDialog">
          CANCELAR
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          :disabled="!formData.employee_id || formData.laboratories.length === 0"
          class="rounded-lg font-weight-black flex-grow-1 h-44 shadow-sm"
          @click="handleSubmit"
        >
          {{ isEditMode ? 'ACTUALIZAR' : 'GUARDAR' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.premium-header {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #2b3341 100%) !important;
}

.bg-white-opacity-10 {
  background-color: rgba(255, 255, 255, 10%) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.leading-none {
  line-height: 1;
}

.h-44 {
  block-size: 44px !important;
}

:deep(.premium-input) {
  .v-field__outline {
    --v-field-border-opacity: 0.15;
  }
}

.border {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12) !important;
}
</style>
