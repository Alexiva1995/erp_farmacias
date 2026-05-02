<script setup>
import { computed } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  employee: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);
const { mobile } = useDisplay();

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

const initials = computed(() => {
  if (!props.employee.employee_name) return "N/A";
  return props.employee.employee_name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .substring(0, 2)
    .toUpperCase();
});
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    :max-width="mobile ? undefined : '600px'"
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    @update:model-value="closeDialog"
  >
    <VCard v-if="props.employee" class="rounded-xl border-0 shadow-xl overflow-hidden d-flex flex-column">
      <!-- Header Premium -->
      <div class="premium-header pa-5 d-flex align-center">
        <div class="d-flex align-center gap-3">
          <VAvatar color="white" variant="tonal" size="40" class="rounded-lg">
            <VIcon icon="tabler-eye" size="22" color="white" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-h6 font-weight-black text-white leading-none mb-1">Detalle de Actividades</span>
            <span class="text-xs text-white opacity-70 font-weight-medium text-capitalize">
              {{ props.employee.employee_name }}
            </span>
          </div>
        </div>
        <VSpacer />
        <VBtn icon="tabler-x" variant="text" color="white" size="small" class="rounded-lg bg-white-opacity-10" @click="closeDialog" />
      </div>

      <VDivider class="opacity-10" />

      <VCardText class="pa-6" style="max-block-size: 70vh; overflow-y: auto;">
        <!-- Información del Empleado -->
        <div class="d-flex align-center gap-4 mb-6 pa-5 rounded-xl bg-surface-variant-opacity-2 border-dashed">
          <VAvatar
            :color="props.employee.employee_id ? 'primary' : 'disabled'"
            variant="tonal"
            size="56"
            class="rounded-xl shadow-sm"
          >
            <span class="text-lg font-weight-black">
              {{ initials }}
            </span>
          </VAvatar>
          <div class="d-flex flex-column flex-grow-1">
            <span class="text-h6 font-weight-black text-capitalize leading-none mb-1">
              {{ props.employee.employee_name }}
            </span>
            <div class="d-flex align-center gap-2">
              <span class="text-super-xs font-weight-black text-disabled uppercase">ID: #{{ props.employee.employee_id }}</span>
              <VDivider vertical class="mx-1" />
              <span class="text-super-xs font-weight-black text-disabled uppercase">{{ props.employee.identification || 'Sin ID' }}</span>
            </div>
          </div>
          <div class="d-none d-sm-flex flex-column align-end">
             <VChip
              :color="props.employee.activities_count > 0 ? 'success' : 'surface-variant'"
              size="small"
              variant="flat"
              class="font-weight-black rounded px-3"
              style="color: white !important;"
            >
              {{ props.employee.activities_count }} Actividades
            </VChip>
          </div>
        </div>

        <!-- Título de Sección -->
        <div class="d-flex align-center justify-space-between mb-4 px-1">
          <span class="text-super-xs font-weight-black text-disabled uppercase">Actividades Asignadas</span>
          <span v-if="mobile" class="text-super-xs font-weight-black text-primary uppercase">{{ props.employee.activities_count }} TOTAL</span>
        </div>

        <!-- Lista de Actividades -->
        <div v-if="hasActivities">
          <VCard variant="outlined" class="rounded-xl border-0 bg-surface shadow-sm overflow-hidden border">
            <VList class="pa-0">
              <template
                v-for="(activity, index) in props.employee.cleaning_activities"
                :key="activity.id"
              >
                <VListItem class="px-5 py-3">
                  <template #prepend>
                    <VAvatar
                      :color="getActivityColor(index)"
                      variant="tonal"
                      size="38"
                      class="rounded-lg"
                    >
                      <VIcon icon="tabler-checkbox" size="20" />
                    </VAvatar>
                  </template>

                  <VListItemTitle>
                    <div class="d-flex align-center justify-space-between">
                      <span class="text-sm font-weight-bold text-capitalize">
                        {{ activity.name }}
                      </span>
                      <div class="d-flex align-center gap-1">
                        <VChip color="secondary" size="x-small" variant="flat" label class="rounded font-weight-black">
                          <VIcon icon="tabler-repeat" size="12" class="me-1" />
                          {{ activity.frequency ? activity.frequency.toUpperCase() : 'N/A' }}
                        </VChip>
                        <VChip v-if="activity.day_of_week" color="info" size="x-small" variant="flat" label class="rounded font-weight-black">
                          <VIcon icon="tabler-calendar" size="12" class="me-1" />
                          {{ activity.day_of_week.toUpperCase() }}
                        </VChip>
                        <VChip
                          :color="getStatusColor(activity.status)"
                          size="x-small"
                          variant="tonal"
                          class="font-weight-black rounded"
                        >
                          <VIcon
                            :icon="getStatusIcon(activity.status)"
                            size="12"
                            class="me-1"
                          />
                          {{ activity.status }}
                        </VChip>
                      </div>
                    </div>
                  </VListItemTitle>
                </VListItem>
                <VDivider
                  v-if="index < props.employee.cleaning_activities.length - 1"
                  class="opacity-10"
                />
              </template>
            </VList>
          </VCard>
        </div>

        <!-- Mensaje cuando no hay actividades -->
        <VAlert v-else type="info" variant="tonal" class="rounded-xl mt-2">
          <div class="d-flex align-center gap-3 pa-2">
            <VIcon icon="tabler-info-circle" size="24" />
            <span class="text-sm font-weight-bold">Este empleado no tiene actividades asignadas.</span>
          </div>
        </VAlert>
      </VCardText>

      <VDivider class="opacity-10 mt-auto" />

      <VCardActions class="pa-6">
        <VBtn
          block
          color="primary"
          variant="tonal"
          size="large"
          class="rounded-xl font-weight-black"
          @click="closeDialog"
        >
          CERRAR DETALLE
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.premium-header {
  background: linear-gradient(135deg, rgb(var(--v-theme-info)) 0%, #2b3341 100%) !important;
}

.bg-white-opacity-10 {
  background-color: rgba(255, 255, 255, 10%) !important;
}

.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.03) !important;
}

.border-dashed {
  border: 1px dashed rgba(var(--v-theme-on-surface), 0.1) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.leading-none {
  line-height: 1;
}

.border {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08) !important;
}
</style>
