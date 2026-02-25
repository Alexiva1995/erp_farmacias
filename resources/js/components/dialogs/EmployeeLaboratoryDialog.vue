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
  if (!formData.value.employee_id) return;

  const dataToSend = {
    employee_id: formData.value.employee_id,
    laboratory_ids: formData.value.laboratories.map((lab) => lab.id),
  };

  emit("save", dataToSend);
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
    max-width="700"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard v-if="formData" class="d-flex flex-column">
      <VCardTitle class="d-flex align-center pa-4 pb-3 bg-primary">
        <VIcon
          :icon="isEditMode ? 'tabler-edit' : 'tabler-plus'"
          size="24"
          color="white"
          class="me-2"
        />
        <span class="text-h5 font-weight-bold text-white">{{
          dialogTitle
        }}</span>

        <VSpacer />
        <VBtn
          icon
          variant="text"
          color="white"
          size="small"
          @click="closeDialog"
        >
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1 pa-4" style="overflow-y: auto">
        <VForm @submit.prevent="handleSubmit">
          <!-- Select de Empleado -->
          <VRow dense class="mb-2">
            <VCol cols="12">
              <VSelect
                v-model="formData.employee_id"
                :items="props.employees"
                :disabled="isEditMode"
                label="Empleado *"
                variant="outlined"
                density="compact"
                placeholder="Selecciona un empleado"
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
          <VRow dense class="mb-2">
            <VCol cols="12">
              <div class="d-flex gap-2">
                <VSelect
                  v-model="formData.new_laboratory_id"
                  :items="availableLaboratories"
                  label="Agregar Laboratorio"
                  variant="outlined"
                  density="compact"
                  placeholder="Selecciona un laboratorio"
                  :disabled="!formData.employee_id"
                  clearable
                  class="flex-grow-1"
                />
                <VBtn
                  color="success"
                  variant="flat"
                  size="default"
                  :disabled="
                    !formData.new_laboratory_id || !formData.employee_id
                  "
                  @click="handleAddLaboratory"
                  style="height: 40px"
                >
                  <VIcon icon="tabler-plus" />
                </VBtn>
              </div>
            </VCol>
          </VRow>

          <!-- Lista de Laboratorios Asignados -->
          <VRow dense class="mt-2">
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
                  label
                >
                  {{ formData.laboratories.length }}
                </VChip>
              </div>

              <!-- Mensaje cuando no hay laboratorios -->
              <VAlert
                v-if="formData.laboratories.length === 0"
                type="info"
                variant="tonal"
                rounded="lg"
                class="mb-0"
              >
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-info-circle" />
                  <span>No hay laboratorios asignados</span>
                </div>
              </VAlert>

              <!-- Tabla de Laboratorios -->
              <VCard v-else variant="outlined" class="rounded-lg border">
                <VList class="pa-0">
                  <template
                    v-for="(laboratory, index) in formData.laboratories"
                    :key="`lab-${laboratory.id}-${index}`"
                  >
                    <VListItem class="px-4 py-2">
                      <template #prepend>
                        <VAvatar
                          :color="getLaboratoryColor(index)"
                          variant="tonal"
                          size="32"
                        >
                          <VIcon icon="tabler-flask" size="18" />
                        </VAvatar>
                      </template>

                      <VListItemTitle>
                        <!-- Modo normal: mostrar nombre -->
                        <div
                          v-if="editingLaboratory !== laboratory.id"
                          class="d-flex align-center"
                        >
                          <span class="text-body-2 font-weight-medium">
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
                          class="my-1"
                        />
                      </VListItemTitle>

                      <template #append>
                        <div class="d-flex gap-1">
                          <!-- Botones en modo normal -->
                          <template v-if="editingLaboratory !== laboratory.id">
                            <VBtn
                              icon
                              variant="text"
                              size="x-small"
                              color="warning"
                              @click="handleEditLaboratory(laboratory)"
                            >
                              <VIcon icon="tabler-edit" size="18" />
                              <VTooltip activator="parent" location="top">
                                Cambiar
                              </VTooltip>
                            </VBtn>
                            <VBtn
                              icon
                              variant="text"
                              size="x-small"
                              color="error"
                              @click="handleRemoveLaboratory(laboratory.id)"
                            >
                              <VIcon icon="tabler-trash" size="18" />
                              <VTooltip activator="parent" location="top">
                                Eliminar
                              </VTooltip>
                            </VBtn>
                          </template>

                          <!-- Botones en modo edición -->
                          <template v-else>
                            <VBtn
                              icon
                              variant="text"
                              size="x-small"
                              color="success"
                              @click="handleSaveEdit(laboratory.id)"
                            >
                              <VIcon icon="tabler-check" size="18" />
                              <VTooltip activator="parent" location="top">
                                Guardar
                              </VTooltip>
                            </VBtn>
                            <VBtn
                              icon
                              variant="text"
                              size="x-small"
                              color="error"
                              @click="handleCancelEdit"
                            >
                              <VIcon icon="tabler-x" size="18" />
                              <VTooltip activator="parent" location="top">
                                Cancelar
                              </VTooltip>
                            </VBtn>
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

      <VCardActions class="pa-4 d-flex gap-2">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1"
          style="flex: 1 1 50%; max-width: 50%"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          :disabled="
            !formData.employee_id || formData.laboratories.length === 0
          "
          @click="handleSubmit"
          class="flex-grow-1"
          style="flex: 1 1 50%; max-width: 50%"
        >
          {{ isEditMode ? "Actualizar" : "Guardar" }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
