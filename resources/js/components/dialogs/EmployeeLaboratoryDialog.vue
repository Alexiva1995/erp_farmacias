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
  isEditMode.value ? "Editar Asignación" : "Asignar Laboratorios",
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
  ([newVisible, newEmployee], [oldVisible, oldEmployee]) => {
    if (!newVisible) return;
    if (newVisible && !oldVisible) {
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
  // Mejora UX: Si hay un laboratorio seleccionado en el combo pero no se pulsó el botón +, añadirlo automáticamente
  if (formData.value.new_laboratory_id) {
    handleAddLaboratory();
  }

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
  const colors = ["primary", "secondary", "success", "info", "warning", "error"];
  return colors[index % colors.length];
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    :max-width="mobile ? undefined : '700px'"
    :fullscreen="mobile"
    persistent
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    @update:model-value="closeDialog"
  >
    <VCard v-if="formData" class="rounded-xl border-0 shadow-xl overflow-hidden d-flex flex-column">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon :icon="isEditMode ? 'tabler-user-cog' : 'tabler-flask'" size="24" color="primary" />
          </VAvatar>
          <div class="d-flex flex-column">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ dialogTitle }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold">
                {{ isEditMode ? `Gestión de ID: #${props.employee.employee_id}` : 'Asignación de laboratorios registrados' }}
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="closeDialog" />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light flex-grow-1 overflow-y-auto" style="max-height: 70vh;">
        <VForm @submit.prevent="handleSubmit" class="d-flex flex-column gap-6">
          
          <!-- Sección Empleado -->
          <section>
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary shadow-sm"></div>
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Selección de Empleado</span>
            </div>

            <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border">
              <VRow dense>
                <VCol cols="12">
                  <AppSelect
                    v-model="formData.employee_id"
                    :items="displayEmployees"
                    :disabled="isEditMode"
                    label="Empleado responsable"
                    placeholder="Seleccionar empleado..."
                    variant="outlined"
                    density="comfortable"
                    hide-details="auto"
                    class="shadow-sm"
                    :error-messages="props.errors.employee_id"
                    prepend-inner-icon="tabler-user"
                  >
                    <template #selection="{ item }">
                      <div class="d-flex align-center gap-2">
                        <VAvatar size="24" color="primary" variant="tonal" class="rounded">
                          <span class="text-super-xs font-weight-black">
                            {{ item.title.split(" ").map((n) => n[0]).join("").substring(0, 2).toUpperCase() }}
                          </span>
                        </VAvatar>
                        <span class="text-xs font-weight-bold">{{ item.title }}</span>
                      </div>
                    </template>
                  </AppSelect>
                </VCol>
              </VRow>
            </VCard>
          </section>

          <!-- Sección Gestión -->
          <section>
            <div class="d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center gap-2">
                <div class="header-indicator primary shadow-sm"></div>
                <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Gestión de Laboratorios</span>
              </div>
              <VChip v-if="formData.laboratories.length > 0" color="primary" size="x-small" variant="flat" class="font-weight-black rounded">
                {{ formData.laboratories.length }} VINCULADOS
              </VChip>
            </div>

            <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border mb-4">
              <VRow dense>
                <VCol cols="12">
                  <div class="d-flex align-end gap-3">
                    <AppSelect
                      v-model="formData.new_laboratory_id"
                      :items="availableLaboratories"
                      label="Vincular nuevo laboratorio"
                      placeholder="Seleccionar laboratorio..."
                      :disabled="!formData.employee_id"
                      variant="outlined"
                      density="comfortable"
                      hide-details
                      class="flex-grow-1 shadow-sm"
                      prepend-inner-icon="tabler-flask"
                    />
                    <VBtn
                      color="primary"
                      variant="flat"
                      class="rounded-lg shadow-primary"
                      height="48"
                      min-width="50"
                      :disabled="!formData.new_laboratory_id || !formData.employee_id"
                      @click="handleAddLaboratory"
                    >
                      <VIcon icon="tabler-plus" size="24" />
                    </VBtn>
                  </div>
                </VCol>
              </VRow>
            </VCard>

            <!-- Lista de Laboratorios -->
            <VCard variant="flat" class="border rounded-lg bg-white elevation-1 overflow-hidden">
              <div v-if="formData.laboratories.length === 0" class="pa-8 d-flex flex-column align-center justify-center text-center">
                <VIcon icon="tabler-flask-off" size="40" class="text-disabled opacity-20 mb-3" />
                <div class="text-xs font-weight-black text-disabled uppercase">No hay laboratorios asignados aún</div>
              </div>

              <VList v-else class="pa-0">
                <template v-for="(lab, index) in formData.laboratories" :key="lab.id">
                  <VListItem class="px-4 py-3">
                    <template #prepend>
                      <VAvatar :color="getLaboratoryColor(index)" variant="tonal" size="36" class="rounded-lg">
                        <VIcon icon="tabler-flask" size="20" />
                      </VAvatar>
                    </template>

                    <VListItemTitle>
                      <div v-if="editingLaboratory !== lab.id" class="text-sm font-weight-black uppercase text-high-emphasis">
                        {{ lab.name }}
                      </div>
                      <AppSelect
                        v-else
                        v-model="tempLaboratoryId"
                        :items="props.laboratories"
                        density="compact"
                        variant="outlined"
                        hide-details
                        class="shadow-sm"
                      />
                    </VListItemTitle>

                    <template #append>
                      <div class="d-flex gap-1">
                        <template v-if="editingLaboratory !== lab.id">
                          <VBtn icon variant="tonal" size="x-small" color="warning" class="rounded" @click="handleEditLaboratory(lab)">
                            <VIcon icon="tabler-edit" size="18" />
                          </VBtn>
                          <VBtn icon variant="tonal" size="x-small" color="error" class="rounded" @click="handleRemoveLaboratory(lab.id)">
                            <VIcon icon="tabler-trash" size="18" />
                          </VBtn>
                        </template>
                        <template v-else>
                          <VBtn icon variant="flat" size="x-small" color="success" class="rounded" @click="handleSaveEdit(lab.id)">
                            <VIcon icon="tabler-check" size="18" />
                          </VBtn>
                          <VBtn icon variant="flat" size="x-small" color="error" class="rounded" @click="handleCancelEdit">
                            <VIcon icon="tabler-x" size="18" />
                          </VBtn>
                        </template>
                      </div>
                    </template>
                  </VListItem>
                  <VDivider v-if="index < formData.laboratories.length - 1" class="border-opacity-10" />
                </template>
              </VList>
              <div v-if="props.errors.laboratory_ids" class="pa-3 text-center">
                <span class="text-xs text-error font-weight-black uppercase">{{ Array.isArray(props.errors.laboratory_ids) ? props.errors.laboratory_ids[0] : props.errors.laboratory_ids }}</span>
              </div>
            </VCard>
          </section>

        </VForm>
      </VCardText>

      <VCardActions class="pa-4 bg-light border-t">
        <VRow no-gutters class="w-100">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="closeDialog"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :disabled="!formData.employee_id || (formData.laboratories.length === 0 && !formData.new_laboratory_id)"
              @click="handleSubmit"
            >
              <VIcon start icon="tabler-device-floppy" size="18" class="me-2" />
              Guardar Cambios
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.leading-tight {
  line-height: 1.25 !important;
}
</style>
