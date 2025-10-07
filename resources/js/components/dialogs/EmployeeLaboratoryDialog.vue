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
  isEditMode.value ? "Editar Laboratorios Asignados" : "Asignar Laboratorios"
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
  }
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
    (lab) => lab.value === formData.value.new_laboratory_id
  );

  if (laboratory) {
    const exists = formData.value.laboratories.some(
      (lab) => lab.id === laboratory.value
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
    (lab) => lab.id !== laboratoryId
  );
};

const handleEditLaboratory = (laboratory) => {
  editingLaboratory.value = laboratory.id;
  tempLaboratoryId.value = laboratory.id;
};

const handleSaveEdit = (oldLaboratoryId) => {
  if (!tempLaboratoryId.value) return;

  const newLab = props.laboratories.find(
    (lab) => lab.value === tempLaboratoryId.value
  );

  if (newLab) {
    const index = formData.value.laboratories.findIndex(
      (lab) => lab.id === oldLaboratoryId
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
  if (!formData.value.employee_id) return;

  const dataToSend = {
    employee_id: formData.value.employee_id,
    laboratory_ids: formData.value.laboratories.map((lab) => lab.id),
  };

  emit("save", dataToSend);
};

const availableLaboratories = computed(() => {
  return props.laboratories.filter(
    (lab) => !formData.value.laboratories.some((l) => l.id === lab.value)
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
    max-width="700"
    @update:model-value="closeDialog"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-5">
        <VIcon
          :icon="isEditMode ? 'tabler-edit' : 'tabler-plus'"
          size="24"
          class="text-primary"
        />
        <span class="text-h6">{{ dialogTitle }}</span>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-5">
        <VForm @submit.prevent="handleSubmit">
          <!-- Select de Empleado -->
          <VRow>
            <VCol cols="12">
              <VSelect
                v-model="formData.employee_id"
                :items="props.employees"
                :disabled="isEditMode"
                label="Empleado *"
                placeholder="Selecciona un empleado"
                prepend-inner-icon="tabler-user"
                :error-messages="props.errors.employee_id"
                clearable
                @update:model-value="emit('clear-errors')"
              >
                <template #selection="{ item }">
                  <div class="d-flex align-center gap-2">
                    <VAvatar size="24" color="primary" variant="tonal">
                      <span class="text-xs">
                        {{
                          item.title
                            .split(" ")
                            .map((n) => n[0])
                            .join("")
                            .substring(0, 2)
                        }}
                      </span>
                    </VAvatar>
                    <span>{{ item.title }}</span>
                  </div>
                </template>
              </VSelect>
            </VCol>
          </VRow>

          <!-- Agregar Nuevo Laboratorio -->
          <VRow class="mt-2">
            <VCol cols="12">
              <div class="d-flex gap-2">
                <VSelect
                  v-model="formData.new_laboratory_id"
                  :items="availableLaboratories"
                  label="Agregar Laboratorio"
                  placeholder="Selecciona un laboratorio"
                  prepend-inner-icon="tabler-flask"
                  :disabled="!formData.employee_id"
                  clearable
                  class="flex-grow-1"
                />
                <VBtn
                  color="primary"
                  :disabled="
                    !formData.new_laboratory_id || !formData.employee_id
                  "
                  @click="handleAddLaboratory"
                >
                  <VIcon icon="tabler-plus" />
                </VBtn>
              </div>
            </VCol>
          </VRow>

          <!-- Lista de Laboratorios Asignados -->
          <VRow class="mt-4">
            <VCol cols="12">
              <div class="d-flex align-center justify-space-between mb-3">
                <span class="text-subtitle-1 font-weight-medium">
                  Laboratorios Asignados
                </span>
                <VChip
                  :color="
                    formData.laboratories.length > 0 ? 'primary' : 'default'
                  "
                  size="small"
                  variant="tonal"
                >
                  {{ formData.laboratories.length }}
                </VChip>
              </div>

              <!-- Mensaje cuando no hay laboratorios -->
              <VAlert
                v-if="formData.laboratories.length === 0"
                type="info"
                variant="tonal"
                class="mb-0"
              >
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-info-circle" />
                  <span>No hay laboratorios asignados</span>
                </div>
              </VAlert>

              <!-- Tabla de Laboratorios -->
              <VCard v-else variant="outlined" class="overflow-hidden">
                <VList class="pa-0">
                  <template
                    v-for="(laboratory, index) in formData.laboratories"
                    :key="`lab-${laboratory.id}-${index}`"
                  >
                    <VListItem class="px-4 py-3">
                      <template #prepend>
                        <VAvatar
                          :color="getLaboratoryColor(index)"
                          variant="tonal"
                          size="38"
                        >
                          <VIcon icon="tabler-flask" size="20" />
                        </VAvatar>
                      </template>

                      <VListItemTitle>
                        <!-- Modo normal: mostrar nombre -->
                        <div
                          v-if="editingLaboratory !== laboratory.id"
                          class="d-flex align-center"
                        >
                          <span class="text-body-1 font-weight-medium">
                            {{ laboratory.name }}
                          </span>
                        </div>

                        <!-- Modo edición: mostrar select -->
                        <VSelect
                          v-else
                          v-model="tempLaboratoryId"
                          :items="props.laboratories"
                          density="compact"
                          variant="outlined"
                          hide-details
                          class="mt-1"
                        />
                      </VListItemTitle>

                      <template #append>
                        <div class="d-flex gap-1">
                          <!-- Botones en modo normal -->
                          <template v-if="editingLaboratory !== laboratory.id">
                            <IconBtn
                              size="small"
                              @click="handleEditLaboratory(laboratory)"
                            >
                              <VIcon icon="tabler-edit" size="20" />
                              <VTooltip activator="parent" location="top">
                                Cambiar
                              </VTooltip>
                            </IconBtn>
                            <IconBtn
                              size="small"
                              color="error"
                              @click="handleRemoveLaboratory(laboratory.id)"
                            >
                              <VIcon icon="tabler-trash" size="20" />
                              <VTooltip activator="parent" location="top">
                                Eliminar
                              </VTooltip>
                            </IconBtn>
                          </template>

                          <!-- Botones en modo edición -->
                          <template v-else>
                            <IconBtn
                              size="small"
                              color="success"
                              @click="handleSaveEdit(laboratory.id)"
                            >
                              <VIcon icon="tabler-check" size="20" />
                              <VTooltip activator="parent" location="top">
                                Guardar
                              </VTooltip>
                            </IconBtn>
                            <IconBtn
                              size="small"
                              color="error"
                              @click="handleCancelEdit"
                            >
                              <VIcon icon="tabler-x" size="20" />
                              <VTooltip activator="parent" location="top">
                                Cancelar
                              </VTooltip>
                            </IconBtn>
                          </template>
                        </div>
                      </template>
                    </VListItem>
                    <VDivider v-if="index < formData.laboratories.length - 1" />
                  </template>
                </VList>
              </VCard>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-5 d-flex gap-3">
        <VBtn
          color="secondary"
          variant="outlined"
          class="flex-grow-1"
          @click="closeDialog"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          class="flex-grow-1"
          :disabled="
            !formData.employee_id || formData.laboratories.length === 0
          "
          @click="handleSubmit"
        >
          {{ isEditMode ? "Actualizar" : "Guardar" }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
