<script setup>
import { computed } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  employee: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);

const closeDialog = () => {
  emit("update:modelValue", false);
};

const getActivityColor = (index) => {
  const colors = [
    "success",
    "info",
    "warning",
    "secondary",
    "primary",
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

const hasActivities = computed(() => {
  return (
    props.employee.cleaning_activities &&
    props.employee.cleaning_activities.length > 0
  );
});
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="600"
    @update:model-value="closeDialog"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-5">
        <VIcon icon="tabler-checkbox" size="24" class="text-success" />
        <span class="text-h6">Actividades de Limpieza Asignadas</span>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-5">
        <!-- Información del Empleado -->
        <div class="d-flex align-center gap-3 mb-4 pa-4 rounded bg-surface">
          <VAvatar color="primary" variant="tonal" size="48">
            <span class="text-base">
              {{
                props.employee.employee_name
                  ?.split(" ")
                  .map((n) => n[0])
                  .join("")
                  .substring(0, 2) || "N/A"
              }}
            </span>
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-h6 font-weight-medium">
              {{ props.employee.employee_name }}
            </span>
            <span class="text-sm text-disabled">
              {{ props.employee.identification }}
            </span>
          </div>
          <VSpacer />
          <VChip
            :color="props.employee.activities_count > 0 ? 'success' : 'default'"
            variant="tonal"
          >
            {{ props.employee.activities_count }}
            {{
              props.employee.activities_count === 1
                ? "actividad"
                : "actividades"
            }}
          </VChip>
        </div>

        <!-- Lista de Actividades -->
        <div v-if="hasActivities">
          <VCard variant="outlined" class="overflow-hidden">
            <VList class="pa-0">
              <template
                v-for="(activity, index) in props.employee.cleaning_activities"
                :key="activity.id"
              >
                <VListItem class="px-4 py-3">
                  <template #prepend>
                    <VAvatar
                      :color="getActivityColor(index)"
                      variant="tonal"
                      size="38"
                    >
                      <VIcon icon="tabler-checkbox" size="20" />
                    </VAvatar>
                  </template>

                  <VListItemTitle>
                    <div class="d-flex align-center justify-space-between">
                      <span class="text-body-1 font-weight-medium">
                        {{ activity.name }}
                      </span>
                      <VChip
                        :color="getStatusColor(activity.status)"
                        size="small"
                        variant="tonal"
                      >
                        <VIcon
                          :icon="getStatusIcon(activity.status)"
                          size="14"
                          class="me-1"
                        />
                        {{ activity.status }}
                      </VChip>
                    </div>
                  </VListItemTitle>
                </VListItem>
                <VDivider
                  v-if="index < props.employee.cleaning_activities.length - 1"
                />
              </template>
            </VList>
          </VCard>
        </div>

        <!-- Mensaje cuando no hay actividades -->
        <VAlert v-else type="info" variant="tonal">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-info-circle" />
            <span>Este empleado no tiene actividades asignadas</span>
          </div>
        </VAlert>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-5">
        <VSpacer />
        <VBtn color="primary" @click="closeDialog"> Cerrar </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
