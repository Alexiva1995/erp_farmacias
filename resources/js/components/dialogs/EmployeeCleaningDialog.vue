<script setup>
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  employee: { type: Object, default: () => ({}) },
  employees: { type: Array, default: () => [] },
  cleaningActivities: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save", "clear-errors"]);

const isEditMode = computed(() => !!props.employee?.employee_id);
const { mobile } = useDisplay();
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

watch(
  [() => props.modelValue, () => props.employee],
  ([newVisible], [oldVisible]) => {
    if (!newVisible) return;
    if (newVisible && (oldVisible === undefined || oldVisible === false)) {
      if (isEditMode.value && props.employee?.employee_id) {
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
  editingActivity.value = null;
  tempActivityId.value = null;
};

const handleAddActivity = () => {
  if (!formData.value.new_activity_id) return;

  const activity = props.cleaningActivities.find(
    (act) => Number(act.value) === Number(formData.value.new_activity_id),
  );

  if (activity) {
    const exists = formData.value.activities.some(
      (act) => Number(act.id) === Number(activity.value),
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
};

const handleSaveEdit = (oldActivityId) => {
  if (!tempActivityId.value) return;

  const newAct = props.cleaningActivities.find(
    (act) => Number(act.value) === Number(tempActivityId.value),
  );

  if (newAct) {
    const index = formData.value.activities.findIndex(
      (act) => Number(act.id) === Number(oldActivityId),
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
    (act) => !formData.value.activities.some((a) => Number(a.id) === Number(act.value)),
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
    :max-width="mobile ? undefined : '700px'"
    :fullscreen="mobile"
    persistent
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    @update:model-value="closeDialog"
  >
    <VCard v-if="formData" class="rounded-xl border-0 shadow-xl overflow-hidden d-flex flex-column">
      <!-- Header Premium -->
      <div class="premium-header pa-5 d-flex align-center">
        <div class="d-flex align-center gap-3">
          <VAvatar color="white" variant="tonal" size="40" class="rounded-lg">
            <VIcon :icon="isEditMode ? 'tabler-edit' : 'tabler-checkbox'" size="22" color="white" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-h6 font-weight-black text-white leading-none mb-1">{{ dialogTitle }}</span>
            <span class="text-xs text-white opacity-70 font-weight-medium text-capitalize">
              {{ isEditMode ? `Empleado: ${props.employee?.employee_name || ''}` : 'Selecciona empleado y actividades' }}
            </span>
          </div>
        </div>
        <VSpacer />
        <VBtn icon="tabler-x" variant="text" color="white" size="small" class="rounded-lg bg-white-opacity-10" @click="closeDialog" />
      </div>

      <VDivider class="opacity-10" />

      <VCardText class="flex-grow-1 pa-6" style="max-block-size: 70vh; overflow-y: auto;">
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

          <!-- Agregar Nueva Actividad -->
          <div class="mb-2">
            <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Agregar Actividad</span>
            <div class="d-flex gap-2">
              <VSelect
                v-model="formData.new_activity_id"
                :items="availableActivities"
                placeholder="Selecciona una actividad..."
                :disabled="!formData.employee_id"
                clearable
                class="flex-grow-1 premium-input"
                density="compact"
                variant="outlined"
                color="primary"
                hide-details
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-list-check" size="18" color="disabled" class="me-2" />
                </template>
              </VSelect>
              <VBtn
                color="success"
                variant="flat"
                class="rounded-lg"
                :disabled="!formData.new_activity_id || !formData.employee_id"
                @click="handleAddActivity"
                style="block-size: 38px; min-inline-size: 40px;"
              >
                <VIcon icon="tabler-plus" size="20" />
              </VBtn>
            </div>
          </div>

          <!-- Lista de Actividades Asignadas -->
          <div class="mt-6">
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-super-xs font-weight-black text-disabled uppercase">Actividades Asignadas</span>
              <VChip
                :color="formData.activities.length > 0 ? 'success' : 'surface-variant'"
                size="x-small"
                variant="flat"
                class="font-weight-black rounded"
                style="color: white !important;"
              >
                {{ formData.activities.length }}
              </VChip>
            </div>

            <!-- Tabla de Actividades -->
            <VCard variant="outlined" class="rounded-lg border">
              <div v-if="formData.activities.length === 0" class="pa-6 d-flex flex-column align-center justify-center text-center">
                <VIcon icon="tabler-checkbox" size="40" class="text-disabled mb-2 opacity-20" />
                <div class="text-xs font-weight-bold text-disabled uppercase">No hay actividades asignadas</div>
              </div>

              <VList v-else class="pa-0">
                <template v-for="(activity, index) in formData.activities" :key="activity.id">
                  <VListItem class="px-4 py-2">
                    <template #prepend>
                      <VAvatar :color="getActivityColor(index)" variant="tonal" size="32">
                        <VIcon icon="tabler-checkbox" size="18" />
                      </VAvatar>
                    </template>

                    <VListItemTitle>
                      <div v-if="editingActivity !== activity.id" class="d-flex align-center justify-space-between">
                        <span class="text-body-2 font-weight-medium text-capitalize">{{ activity.name }}</span>
                        <VChip :color="getStatusColor(activity.status)" size="x-small" variant="tonal" label>
                          <VIcon :icon="getStatusIcon(activity.status)" size="12" class="me-1" />
                          {{ activity.status }}
                        </VChip>
                      </div>
                      <VSelect
                        v-else
                        v-model="tempActivityId"
                        :items="props.cleaningActivities"
                        density="compact"
                        variant="outlined"
                        hide-details
                        class="my-1"
                      />
                    </VListItemTitle>

                    <template #append>
                      <div class="d-flex gap-1">
                        <template v-if="editingActivity !== activity.id">
                          <VBtn icon variant="text" size="x-small" color="warning" @click="handleEditActivity(activity)">
                            <VIcon icon="tabler-edit" size="18" />
                          </VBtn>
                          <VBtn icon variant="text" size="x-small" color="error" @click="handleRemoveActivity(activity.id)">
                            <VIcon icon="tabler-trash" size="18" />
                          </VBtn>
                        </template>
                        <template v-else>
                          <VBtn icon variant="text" size="x-small" color="success" @click="handleSaveEdit(activity.id)">
                            <VIcon icon="tabler-check" size="18" />
                          </VBtn>
                          <VBtn icon variant="text" size="x-small" color="error" @click="handleCancelEdit">
                            <VIcon icon="tabler-x" size="18" />
                          </VBtn>
                        </template>
                      </div>
                    </template>
                  </VListItem>
                  <VDivider v-if="index < formData.activities.length - 1" class="opacity-10" />
                </template>
              </VList>
            </VCard>
          </div>
        </VForm>
      </VCardText>

      <VDivider class="opacity-10" />

      <VCardActions class="pa-6 d-flex gap-3 mt-auto">
        <VBtn color="secondary" variant="tonal" class="rounded-lg font-weight-black flex-grow-1 h-44" @click="closeDialog">
          CANCELAR
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          :disabled="!formData.employee_id || formData.activities.length === 0"
          class="rounded-lg font-weight-black flex-grow-1 h-44 shadow-sm"
          @click="handleSubmit"
        >
          <VIcon start :icon="isEditMode ? 'tabler-refresh' : 'tabler-device-floppy'" size="18" />
          {{ isEditMode ? "ACTUALIZAR" : "GUARDAR" }}
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
