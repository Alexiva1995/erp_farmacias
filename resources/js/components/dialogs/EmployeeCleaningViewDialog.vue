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

const formatCapitalize = (str) => {
  if (!str) return "";
  return str.toLowerCase().replace(/(?:^|\s|-)\S/g, (char) => char.toUpperCase());
};

const getFrequencyColor = (freq) => {
  switch (freq) {
    case "Diaria": return "success";
    case "Semanal": return "info";
    case "Quincenal": return "warning";
    case "Mensual": return "purple";
    default: return "secondary";
  }
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
    :max-width="mobile ? undefined : '750px'"
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    @update:model-value="closeDialog"
  >
    <VCard v-if="props.employee" class="rounded-xl border-0 shadow-xl overflow-hidden d-flex flex-column">
      <!-- Header Premium Estándar -->
      <div class="header-gradient pa-4 d-flex align-center shadow-sm">
        <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
          <VIcon icon="tabler-list-check" size="24" color="primary" />
        </VAvatar>
        <div class="d-flex flex-column">
          <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
            Control de Tareas y Actividades
          </h2>
          <div class="d-flex align-center gap-2 mt-1">
            <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold">
              {{ props.employee.employee_name || 'Empleado' }}
            </span>
          </div>
        </div>
        <VSpacer />
        <VChip color="white" variant="tonal" size="small" class="font-weight-bold me-2 rounded text-white">
          {{ props.employee.activities_count || 0 }} {{ (props.employee.activities_count || 0) === 1 ? 'actividad' : 'actividades' }}
        </VChip>
        <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="closeDialog" />
      </div>

      <VCardText class="pa-4 pa-sm-6 bg-light flex-grow-1 overflow-y-auto" style="max-height: 70vh;">
        <!-- Información del Empleado -->
        <div class="d-flex align-center gap-3 mb-4 pa-4 rounded-lg bg-white border elevation-1">
          <VAvatar color="primary" variant="tonal" size="40" class="rounded-lg font-weight-bold">
            {{ props.employee.employee_name?.split(" ").map((n) => n[0]).join("").substring(0, 2) || "N/A" }}
          </VAvatar>
          <div class="flex-grow-1">
            <span class="text-sm font-weight-bold text-high-emphasis d-block">{{ props.employee.employee_name }}</span>
            <span class="text-super-xs text-medium-emphasis font-weight-medium">ID #{{ props.employee.employee_id }}</span>
          </div>
          <VChip
            :color="props.employee.is_active ? 'success' : 'default'"
            size="x-small"
            variant="tonal"
            class="font-weight-bold rounded"
          >
            {{ props.employee.is_active ? 'ACTIVO' : 'INACTIVO' }}
          </VChip>
        </div>

        <!-- Tabla de Control de Actividades -->
        <div v-if="hasActivities">
          <VCard variant="flat" border class="rounded-lg overflow-hidden bg-white">
            <VTable density="comfortable">
              <thead>
                <tr>
                  <th class="text-left font-weight-bold">Actividad</th>
                  <th class="text-center font-weight-bold" style="width: 110px;">Frecuencia</th>
                  <th class="text-center font-weight-bold" style="width: 100px;">Asignadas (Mes)</th>
                  <th class="text-center font-weight-bold" style="width: 90px;">Cumplidas</th>
                  <th class="text-center font-weight-bold" style="width: 90px;">No Cumplidas</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="activity in props.employee.cleaning_activities"
                  :key="activity.id"
                >
                  <td>
                    <div class="d-flex flex-column py-1">
                      <span class="text-sm font-weight-medium text-capitalize text-high-emphasis">
                        {{ formatCapitalize(activity.name) }}
                      </span>
                      <span v-if="activity.day_of_week" class="text-super-xs text-medium-emphasis">
                        Día: {{ activity.day_of_week }}
                      </span>
                    </div>
                  </td>
                  <td class="text-center">
                    <VChip
                      :color="getFrequencyColor(activity.frequency)"
                      size="x-small"
                      variant="tonal"
                      class="font-weight-bold rounded uppercase"
                    >
                      {{ activity.frequency || 'N/A' }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    <VChip
                      color="primary"
                      size="x-small"
                      variant="tonal"
                      class="font-weight-bold rounded tabular-nums"
                    >
                      {{ activity.month_assigned || 0 }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    <VChip
                      :color="(activity.month_completed || 0) > 0 ? 'success' : 'default'"
                      size="x-small"
                      variant="tonal"
                      class="font-weight-bold rounded tabular-nums"
                    >
                      {{ activity.month_completed || 0 }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    <VChip
                      :color="(activity.month_failed || 0) > 0 ? 'error' : 'default'"
                      size="x-small"
                      variant="tonal"
                      class="font-weight-bold rounded tabular-nums"
                    >
                      {{ activity.month_failed || 0 }}
                    </VChip>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCard>
        </div>

        <!-- Estado vacío -->
        <div v-else class="text-center py-8">
          <VIcon icon="tabler-clipboard-off" size="56" color="disabled" class="mb-3 opacity-20" />
          <div class="text-xs font-weight-black text-disabled uppercase">Sin actividades asignadas</div>
        </div>
      </VCardText>

      <VCardActions class="pa-4 bg-light border-t">
        <VBtn
          color="primary"
          variant="flat"
          block
          height="38"
          class="rounded-lg font-weight-bold shadow-primary text-none"
          @click="closeDialog"
        >
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end)) 100%
  );
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.leading-tight {
  line-height: 1.25 !important;
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
