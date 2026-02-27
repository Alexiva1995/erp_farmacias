<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  employee: { type: Object, default: () => ({}) },
  employees: { type: Array, default: () => [] },
  laboratories: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clear-errors"]);

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
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      if (isEditMode.value) {
        // Modo edición: cargar datos del empleado
        formData.value = {
          employee_id: props.employee.employee_id,
          laboratories: props.employee.laboratories
            ? JSON.parse(JSON.stringify(props.employee.laboratories))
            : [],
          new_laboratory_id: null,
        };
      } else {
        // Modo creación: limpiar formulario
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
);

const closeDialog = () => {
  emit("update:modelValue", false);
  emit("clear-errors");
  editingLaboratory.value = null;
  tempLaboratoryId.value = null;
};

const handleAddLaboratory = () => {
  if (!formData.value.new_laboratory_id) return;

  const laboratory = props.laboratories.find(
    (lab) => lab.value === formData.value.new_laboratory_id,
  );

  if (laboratory) {
    const exists = formData.value.laboratories.some(
      (lab) => lab.id === laboratory.value,
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
    (lab) => lab.value === tempLaboratoryId.value,
  );

  if (newLab) {
    const index = formData.value.laboratories.findIndex(
      (lab) => lab.id === oldLaboratoryId,
    );

    if (index !== -1) {
      // Crear nuevo array con el laboratorio reemplazado
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
    (lab) => !formData.value.laboratories.some((l) => l.id === lab.value),
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
    max-width="600"
    persistent
    @update:model-value="closeDialog"
  >
    <VCard v-if="formData" class="rounded-lg overflow-hidden border shadow-lg bg-surface">
      <!-- Header Estándar del Sistema -->
      <VCardTitle class="pa-4 d-flex align-center bg-primary">
        <VIcon
          :icon="isEditMode ? 'tabler-edit' : 'tabler-plus'"
          size="24"
          color="white"
          class="me-3"
        />
        <span class="text-h5 font-weight-bold text-white">{{ dialogTitle }}</span>

        <VSpacer />

        <VBtn
          icon="tabler-x"
          variant="text"
          color="white"
          size="small"
          @click="closeDialog"
        />
      </VCardTitle>

      <VCardText class="pa-6">
        <VForm @submit.prevent="handleSubmit">
          <!-- Sección de Empleado -->
          <div class="mb-4">
            <VSelect
              v-model="formData.employee_id"
              :items="props.employees"
              :disabled="isEditMode"
              label="Empleado *"
              variant="outlined"
              density="compact"
              placeholder="Selecciona un empleado"
              :error-messages="props.errors.employee_id"
              class="rounded-lg"
              @update:model-value="emit('clear-errors')"
            >
              <template #selection="{ item }">
                <div class="d-flex align-center gap-2">
                  <VAvatar size="24" color="primary" variant="tonal">
                    <span class="text-xs font-weight-bold">
                      {{ item.title.split(" ").map((n) => n[0]).join("").substring(0, 2).toUpperCase() }}
                    </span>
                  </VAvatar>
                  <span class="text-body-2">{{ item.title }}</span>
                </div>
              </template>
            </VSelect>
          </div>

          <VDivider class="mb-5" />

          <!-- Sección de Asignación -->
          <div class="text-subtitle-1 font-weight-bold mb-3">
            Asignación de Laboratorios
          </div>

          <div class="d-flex gap-2 mb-4">
            <VSelect
              v-model="formData.new_laboratory_id"
              :items="availableLaboratories"
              label="Agregar Laboratorio"
              variant="outlined"
              density="compact"
              placeholder="Selecciona un laboratorio"
              :disabled="!formData.employee_id"
              class="flex-grow-1"
              clearable
            />
            <VBtn
              color="success"
              variant="flat"
              height="40"
              :disabled="!formData.new_laboratory_id || !formData.employee_id"
              @click="handleAddLaboratory"
            >
              <VIcon icon="tabler-plus" />
            </VBtn>
          </div>

          <!-- Lista de Laboratorios Asignados -->
          <VCard variant="outlined" class="rounded-lg border">
            <div v-if="formData.laboratories.length === 0" class="pa-6 d-flex flex-column align-center justify-center text-center">
              <VIcon icon="tabler-flask-off" size="40" class="text-disabled mb-2" />
              <div class="text-body-2 text-disabled">No hay laboratorios asignados</div>
            </div>

            <VList v-else class="pa-0" density="compact">
              <template v-for="(laboratory, index) in formData.laboratories" :key="laboratory.id">
                <VListItem class="py-2">
                  <template #prepend>
                    <VAvatar
                      :color="getLaboratoryColor(index)"
                      variant="tonal"
                      size="32"
                      class="me-3"
                    >
                      <VIcon icon="tabler-flask" size="18" />
                    </VAvatar>
                  </template>

                  <VListItemTitle>
                    <div v-if="editingLaboratory !== laboratory.id" class="text-body-2 font-weight-medium">
                      {{ laboratory.name }}
                    </div>
                    <VSelect
                      v-else
                      v-model="tempLaboratoryId"
                      :items="props.laboratories"
                      density="compact"
                      variant="outlined"
                      hide-details
                      autofocus
                    />
                  </VListItemTitle>

                  <template #append>
                    <div class="d-flex gap-1">
                      <template v-if="editingLaboratory !== laboratory.id">
                        <VBtn
                          icon="tabler-edit"
                          variant="text"
                          size="x-small"
                          color="primary"
                          @click="handleEditLaboratory(laboratory)"
                        />
                        <VBtn
                          icon="tabler-trash"
                          variant="text"
                          size="x-small"
                          color="error"
                          @click="handleRemoveLaboratory(laboratory.id)"
                        />
                      </template>
                      <template v-else>
                        <VBtn
                          icon="tabler-check"
                          variant="text"
                          size="x-small"
                          color="success"
                          @click="handleSaveEdit(laboratory.id)"
                        />
                        <VBtn
                          icon="tabler-x"
                          variant="text"
                          size="x-small"
                          color="error"
                          @click="handleCancelEdit"
                        />
                      </template>
                    </div>
                  </template>
                </VListItem>
                <VDivider v-if="index < formData.laboratories.length - 1" />
              </template>
            </VList>
          </VCard>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 d-flex gap-2">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1"
          style="inline-size: 50%;"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          :disabled="!formData.employee_id || formData.laboratories.length === 0"
          @click="handleSubmit"
          class="flex-grow-1"
          style="inline-size: 50%;"
        >
          {{ isEditMode ? 'Actualizar' : 'Guardar' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.border {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12) !important;
}
</style>
