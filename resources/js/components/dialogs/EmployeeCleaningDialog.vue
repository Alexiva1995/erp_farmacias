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
  isEditMode.value ? "Editar Asignación" : "Asignar Actividades",
);

const formData = ref({
  employee_id: null,
  activities: [],
  new_activity_id: null,
  new_activity_status: "Pendiente",
  new_activity_day: null,
});

const daysOfWeek = [
  { title: "Lunes", value: "Lunes" },
  { title: "Martes", value: "Martes" },
  { title: "Miércoles", value: "Miércoles" },
  { title: "Jueves", value: "Jueves" },
  { title: "Viernes", value: "Viernes" },
  { title: "Sábado", value: "Sábado" },
  { title: "Domingo", value: "Domingo" },
];

const editingActivity = ref(null);
const tempActivityId = ref(null);
const tempActivityDay = ref(null);

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
          new_activity_day: null,
        };
      } else {
        formData.value = {
          employee_id: null,
          activities: [],
          new_activity_id: null,
          new_activity_status: "Pendiente",
          new_activity_day: null,
        };
      }
      editingActivity.value = null;
      tempActivityId.value = null;
      tempActivityDay.value = null;
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
  tempActivityDay.value = null;
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
        day_of_week: formData.value.new_activity_day,
        frequency: activity.frequency,
      });
      formData.value.new_activity_id = null;
      formData.value.new_activity_day = null;
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
  tempActivityDay.value = activity.day_of_week || null;
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
        day_of_week: tempActivityDay.value,
        frequency: newAct.frequency,
      };
      formData.value.activities = updatedActivities;
    }
  }

  editingActivity.value = null;
  tempActivityId.value = null;
  tempActivityDay.value = null;
};

const handleCancelEdit = () => {
  editingActivity.value = null;
  tempActivityId.value = null;
  tempActivityDay.value = null;
};

const handleSubmit = () => {
  if (!formData.value.employee_id) return;

  const dataToSend = {
    employee_id: formData.value.employee_id,
    activities: formData.value.activities.map((act) => ({
      activity_id: act.id,
      status: act.status,
      day_of_week: act.day_of_week,
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
  const colors = ["primary", "secondary", "success", "info", "warning", "error"];
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
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon :icon="isEditMode ? 'tabler-user-cog' : 'tabler-checkbox'" size="24" color="primary" />
          </VAvatar>
          <div class="d-flex flex-column">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ dialogTitle }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold">
                {{ isEditMode ? `Gestión de ID: #${props.employee.employee_id}` : 'Asignación de actividades de limpieza' }}
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
                <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Gestión de Tareas</span>
              </div>
              <VChip v-if="formData.activities.length > 0" color="primary" size="x-small" variant="flat" class="font-weight-black rounded">
                {{ formData.activities.length }} ASIGNADAS
              </VChip>
            </div>

            <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border mb-4">
              <VRow dense>
                <VCol cols="12">
                  <div class="d-flex align-end gap-3">
                    <AppSelect
                      v-model="formData.new_activity_id"
                      :items="availableActivities"
                      label="Vincular nueva actividad"
                      placeholder="Seleccionar tarea..."
                      :disabled="!formData.employee_id"
                      variant="outlined"
                      density="comfortable"
                      hide-details
                      class="flex-grow-1 shadow-sm"
                      prepend-inner-icon="tabler-list-check"
                    />
                    <AppSelect
                      v-model="formData.new_activity_day"
                      :items="daysOfWeek"
                      label="Día sugerido"
                      placeholder="Cualquier día"
                      :disabled="!formData.employee_id"
                      variant="outlined"
                      density="comfortable"
                      hide-details
                      class="flex-grow-1 shadow-sm"
                      prepend-inner-icon="tabler-calendar-event"
                    />
                    <VBtn
                      color="primary"
                      variant="flat"
                      class="rounded-lg shadow-primary"
                      height="48"
                      min-width="50"
                      :disabled="!formData.new_activity_id || !formData.employee_id"
                      @click="handleAddActivity"
                    >
                      <VIcon icon="tabler-plus" size="24" />
                    </VBtn>
                  </div>
                </VCol>
              </VRow>
            </VCard>

            <!-- Lista de Actividades -->
            <VCard variant="flat" class="border rounded-lg bg-white elevation-1 overflow-hidden">
              <div v-if="formData.activities.length === 0" class="pa-8 d-flex flex-column align-center justify-center text-center">
                <VIcon icon="tabler-checkbox" size="40" class="text-disabled opacity-20 mb-3" />
                <div class="text-xs font-weight-black text-disabled uppercase">No hay actividades asignadas aún</div>
              </div>

              <VList v-else class="pa-0">
                <template v-for="(activity, index) in formData.activities" :key="activity.id">
                  <VListItem class="px-4 py-3">
                    <template #prepend>
                      <VAvatar :color="getActivityColor(index)" variant="tonal" size="36" class="rounded-lg">
                        <VIcon icon="tabler-checkbox" size="20" />
                      </VAvatar>
                    </template>

                    <VListItemTitle>
                      <div v-if="editingActivity !== activity.id" class="d-flex align-center justify-space-between gap-2">
                        <span class="text-sm font-weight-black uppercase text-high-emphasis">{{ activity.name }}</span>
                        <div class="d-flex align-center gap-1">
                          <VChip color="secondary" size="x-small" variant="flat" label class="rounded font-weight-black">
                            <VIcon icon="tabler-repeat" size="12" class="me-1" />
                            {{ activity.frequency ? activity.frequency.toUpperCase() : 'N/A' }}
                          </VChip>
                          <VChip v-if="activity.day_of_week" color="info" size="x-small" variant="flat" label class="rounded font-weight-black">
                            <VIcon icon="tabler-calendar" size="12" class="me-1" />
                            {{ activity.day_of_week.toUpperCase() }}
                          </VChip>
                          <VChip :color="getStatusColor(activity.status)" size="x-small" variant="tonal" label class="rounded font-weight-black">
                            <VIcon :icon="getStatusIcon(activity.status)" size="12" class="me-1" />
                            {{ activity.status.toUpperCase() }}
                          </VChip>
                        </div>
                      </div>
                      <div v-else class="d-flex flex-column gap-2">
                        <AppSelect
                          v-model="tempActivityId"
                          :items="props.cleaningActivities"
                          density="compact"
                          variant="outlined"
                          hide-details
                          class="shadow-sm mb-1"
                        />
                        <AppSelect
                          v-model="tempActivityDay"
                          :items="daysOfWeek"
                          label="Día de la semana"
                          density="compact"
                          variant="outlined"
                          hide-details
                          class="shadow-sm"
                        />
                      </div>
                    </VListItemTitle>

                    <template #append>
                      <div class="d-flex gap-1">
                        <template v-if="editingActivity !== activity.id">
                          <VBtn icon variant="tonal" size="x-small" color="warning" class="rounded" @click="handleEditActivity(activity)">
                            <VIcon icon="tabler-edit" size="18" />
                          </VBtn>
                          <VBtn icon variant="tonal" size="x-small" color="error" class="rounded" @click="handleRemoveActivity(activity.id)">
                            <VIcon icon="tabler-trash" size="18" />
                          </VBtn>
                        </template>
                        <template v-else>
                          <VBtn icon variant="flat" size="x-small" color="success" class="rounded" @click="handleSaveEdit(activity.id)">
                            <VIcon icon="tabler-check" size="18" />
                          </VBtn>
                          <VBtn icon variant="flat" size="x-small" color="error" class="rounded" @click="handleCancelEdit">
                            <VIcon icon="tabler-x" size="18" />
                          </VBtn>
                        </template>
                      </div>
                    </template>
                  </VListItem>
                  <VDivider v-if="index < formData.activities.length - 1" class="border-opacity-10" />
                </template>
              </VList>
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
              :disabled="!formData.employee_id || formData.activities.length === 0"
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
  background: var(--brand-gradient) !important;
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
