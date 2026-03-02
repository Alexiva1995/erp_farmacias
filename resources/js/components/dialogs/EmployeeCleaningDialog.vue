<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  employee: { type: Object, default: () => ({}) },
  employees: { type: Array, default: () => [] },
  cleaningActivities: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clear-errors"]);

const isEditMode = computed(() => !!props.employee?.employee_id);
const dialogTitle = computed(() =>
  isEditMode.value ? "Editar Actividades Asignadas" : "Asignar Actividades",
);

const formData = ref({
  employee_id: null,
  activities: [],
  new_activity_id: null,
  new_activity_status: "Pendiente",
});

const editingActivity = ref(null);
const tempActivityId = ref(null);
const tempActivityStatus = ref("Pendiente");

// Eliminamos statusOptions ya que la asignación es automática

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      if (isEditMode.value) {
        formData.value = {
          employee_id: props.employee.employee_id,
          activities: props.employee.cleaning_activities
            ? JSON.parse(JSON.stringify(props.employee.cleaning_activities))
            : [],
          new_activity_id: null,
          new_activity_status: "Pendiente",
        };
      } else {
        formData.value = {
          employee_id: null,
          activities: [],
          new_activity_id: null,
          new_activity_status: "Pendiente",
        };
      }
      editingActivity.value = null;
      tempActivityId.value = null;
      tempActivityStatus.value = "Pendiente";
    }
  },
);

const closeDialog = () => {
  emit("update:modelValue", false);
  emit("clear-errors");
  editingActivity.value = null;
  tempActivityId.value = null;
  tempActivityStatus.value = "Pendiente";
};

const handleAddActivity = () => {
  if (!formData.value.new_activity_id) return;

  const activity = props.cleaningActivities.find(
    (act) => act.value === formData.value.new_activity_id,
  );

  if (activity) {
    const exists = formData.value.activities.some(
      (act) => act.id === activity.value,
    );

    if (!exists) {
      formData.value.activities.push({
        id: activity.value,
        name: activity.title,
        status: "Pendiente",
      });
      formData.value.new_activity_id = null;
    }
  }
};

const handleRemoveActivity = (activityId) => {
  formData.value.activities = formData.value.activities.filter(
    (act) => act.id !== activityId,
  );
};

const handleEditActivity = (activity) => {
  editingActivity.value = activity.id;
  tempActivityId.value = activity.id;
  tempActivityStatus.value = activity.status;
};

const handleSaveEdit = (oldActivityId) => {
  if (!tempActivityId.value) return;

  const newAct = props.cleaningActivities.find(
    (act) => act.value === tempActivityId.value,
  );

  if (newAct) {
    const index = formData.value.activities.findIndex(
      (act) => act.id === oldActivityId,
    );

    if (index !== -1) {
      const updatedActivities = [...formData.value.activities];
      updatedActivities[index] = {
        id: newAct.value,
        name: newAct.title,
        status: "Pendiente",
      };
      formData.value.activities = updatedActivities;
    }
  }

  editingActivity.value = null;
  tempActivityId.value = null;
};

const handleCancelEdit = () => {
  editingActivity.value = null;
  tempActivityId.value = null;
  tempActivityStatus.value = "Pendiente";
};

const handleSubmit = () => {
  if (!formData.value.employee_id) return;

  const dataToSend = {
    employee_id: formData.value.employee_id,
    activities: formData.value.activities.map((act) => ({
      activity_id: act.id,
      status: act.status,
    })),
  };

  emit("save", dataToSend);
};

const availableActivities = computed(() => {
  return props.cleaningActivities.filter(
    (act) => !formData.value.activities.some((a) => a.id === act.value),
  );
});

const getActivityColor = (index) => {
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

const getStatusColor = (status) => {
  const statusColors = {
    Pendiente: "warning",
    Completada: "success",
    Cancelada: "error",
  };
  return statusColors[status] || "default";
};

const getStatusIcon = (status) => {
  const statusIcons = {
    Pendiente: "tabler-clock",
    Completada: "tabler-check",
    Cancelada: "tabler-x",
  };
  return statusIcons[status] || "tabler-circle";
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800"
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

      <VCardText class="flex-grow-1 pa-4" style="overflow-y: auto;">
        <VForm @submit.prevent="handleSubmit">
          <!-- Select de Empleado -->
          <VRow dense class="mb-2">
            <VCol cols="12">
              <VSelect
                v-model="formData.employee_id"
                :items="props.employees"
                :disabled="isEditMode"
                label="Empleado *"
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

          <!-- Agregar Nueva Actividad -->
          <VRow dense class="mb-2">
            <VCol cols="12">
              <div class="d-flex gap-2">
                <VSelect
                  v-model="formData.new_activity_id"
                  :items="availableActivities"
                  label="Agregar Actividad"
                  placeholder="Selecciona una actividad para asignar"
                  :disabled="!formData.employee_id"
                  clearable
                  class="flex-grow-1"
                />
                <VBtn
                  color="success"
                  variant="flat"
                  size="default"
                  :disabled="!formData.new_activity_id || !formData.employee_id"
                  @click="handleAddActivity"
                  style="block-size: 40px;"
                >
                  <VIcon icon="tabler-plus" />
                </VBtn>
              </div>
            </VCol>
          </VRow>

          <!-- Lista de Actividades Asignadas -->
          <VRow dense class="mt-2">
            <VCol cols="12">
              <div class="d-flex align-center justify-space-between mb-3">
                <span class="text-subtitle-1 font-weight-medium">
                  Actividades Asignadas
                </span>
                <VChip
                  :color="
                    formData.activities.length > 0 ? 'primary' : 'default'
                  "
                  size="small"
                  variant="tonal"
                  label
                >
                  {{ formData.activities.length }}
                </VChip>
              </div>

              <!-- Mensaje cuando no hay actividades -->
              <VAlert
                v-if="formData.activities.length === 0"
                type="info"
                variant="tonal"
                rounded="lg"
                class="mb-0"
              >
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-info-circle" />
                  <span>No hay actividades asignadas</span>
                </div>
              </VAlert>

              <!-- Tabla de Actividades -->
              <VCard v-else variant="outlined" class="rounded-lg border">
                <VList class="pa-0">
                  <template
                    v-for="(activity, index) in formData.activities"
                    :key="`activity-${activity.id}-${index}`"
                  >
                    <VListItem class="px-4 py-2">
                      <template #prepend>
                        <VAvatar
                          :color="getActivityColor(index)"
                          variant="tonal"
                          size="32"
                        >
                          <VIcon icon="tabler-checkbox" size="18" />
                        </VAvatar>
                      </template>

                      <VListItemTitle>
                        <!-- Modo normal: mostrar nombre y estado -->
                        <div
                          v-if="editingActivity !== activity.id"
                          class="d-flex align-center justify-space-between"
                        >
                          <span class="text-body-2 font-weight-medium">
                            {{ activity.name }}
                          </span>
                          <VChip
                            :color="getStatusColor(activity.status)"
                            size="x-small"
                            variant="tonal"
                            label
                          >
                            <VIcon
                              :icon="getStatusIcon(activity.status)"
                              size="12"
                              class="me-1"
                            />
                            {{ activity.status }}
                          </VChip>
                        </div>

                        <!-- Modo edición: mostrar selects -->
                        <div v-else class="d-flex gap-2 my-1">
                          <VSelect
                            v-model="tempActivityId"
                            :items="props.cleaningActivities"
                            hide-details
                            class="flex-grow-1"
                          />
                        </div>
                      </VListItemTitle>

                      <template #append>
                        <div class="d-flex gap-1">
                          <!-- Botones en modo normal -->
                          <template v-if="editingActivity !== activity.id">
                            <VBtn
                              icon
                              variant="text"
                              size="x-small"
                              color="warning"
                              @click="handleEditActivity(activity)"
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
                              @click="handleRemoveActivity(activity.id)"
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
                              @click="handleSaveEdit(activity.id)"
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
                    <VDivider v-if="index < formData.activities.length - 1" />
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
          style="flex: 1 1 50%; max-inline-size: 50%;"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          :disabled="!formData.employee_id || formData.activities.length === 0"
          @click="handleSubmit"
          class="flex-grow-1"
          style="flex: 1 1 50%; max-inline-size: 50%;"
        >
          {{ isEditMode ? "Actualizar" : "Guardar" }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
