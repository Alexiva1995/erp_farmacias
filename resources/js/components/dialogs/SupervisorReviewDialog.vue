<script setup>
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  execution: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits([
  "update:modelValue",
  "approve",
  "reject",
  "cancel",
  "clear-errors",
]);

const { mobile } = useDisplay();
const activeTab = ref("review");
const formData = ref({
  notes: "",
  rejection_reason: "",
  cancellation_reason: "",
});

watch(
  () => props.modelValue,
  (newValue) => {
    if (newValue) {
      formData.value = {
        notes: props.execution.notes || "",
        rejection_reason: "",
        cancellation_reason: "",
      };
      activeTab.value = "review";
    }
  }
);

const closeDialog = () => {
  emit("update:modelValue", false);
  emit("clear-errors");
};

const handleApprove = () => {
  emit("approve", {
    notes: formData.value.notes,
  });
};

const handleReject = () => {
  if (!formData.value.rejection_reason.trim()) {
    return;
  }
  emit("reject", {
    rejection_reason: formData.value.rejection_reason,
  });
};

const handleCancel = () => {
  if (!formData.value.cancellation_reason.trim()) {
    return;
  }
  emit("cancel", {
    cancellation_reason: formData.value.cancellation_reason,
  });
};

const getStatusColor = (status) => {
  const statusColors = {
    Pendiente: "warning",
    Procesada: "info",
    Completada: "success",
    Vencida: "error",
    Cancelada: "secondary",
  };
  return statusColors[status] || "default";
};

const getStatusIcon = (status) => {
  const statusIcons = {
    Pendiente: "tabler-clock",
    Procesada: "tabler-hourglass",
    Completada: "tabler-check",
    Vencida: "tabler-alert-triangle",
    Cancelada: "tabler-x",
  };
  return statusIcons[status] || "tabler-circle";
};

const getFrequencyColor = (frequency) => {
  const colors = {
    Diaria: "success",
    Semanal: "info",
    Quincenal: "warning",
    Bimestral: "warning",
    Mensual: "purple",
    Trimestral: "primary",
    Semestral: "secondary",
    Anual: "default",
  };
  return colors[frequency] || "secondary";
};

const getPhotoUrl = (photoPath) => {
  if (!photoPath) return null;
  const cleanPath = photoPath.startsWith("/")
    ? photoPath.substring(1)
    : photoPath;
  return `/storage/${cleanPath}`;
};

const formatDate = (date) => {
  if (!date) return "N/A";
  if (date.includes("T") || date.includes(" ")) {
    return new Date(date).toLocaleDateString("es-ES", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  }
  const [year, month, day] = date.split("-");
  const dateObj = new Date(year, month - 1, day);
  return dateObj.toLocaleDateString("es-ES", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const formatDateTime = (datetime) => {
  if (!datetime) return "N/A";
  return new Date(datetime).toLocaleString("es-ES", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const canApprove = computed(() => props.execution.status === "Procesada");
const canReject = computed(() => props.execution.status === "Procesada");
const canCancel = computed(() => ["Pendiente", "Procesada"].includes(props.execution.status));
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    :max-width="mobile ? undefined : '800px'"
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'scale-transition'"
    persistent
    scrollable
    @update:model-value="closeDialog"
  >
    <VCard class="rounded-xl border-0 shadow-xl overflow-hidden d-flex flex-column">
      <!-- Header Premium Estándar -->
      <div class="header-gradient pa-4 d-flex align-center shadow-sm">
        <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
          <VIcon icon="tabler-shield-check" size="24" color="primary" />
        </VAvatar>
        <div class="d-flex flex-column">
          <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
            Revisión de Actividad
          </h2>
          <div class="d-flex align-center gap-2 mt-1">
            <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold">
              {{ props.execution.employee_name || 'Empleado' }}
            </span>
          </div>
        </div>
        <VSpacer />
        <VChip color="white" variant="tonal" size="small" class="font-weight-bold me-2 rounded text-white">
          {{ props.execution.status || 'Estado' }}
        </VChip>
        <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="closeDialog" />
      </div>

      <VCardText class="pa-4 pa-sm-6 bg-light flex-grow-1 overflow-y-auto" style="max-height: 75vh;">
        <!-- Información de la Ejecución -->
        <VCard variant="flat" border class="mb-4 rounded-lg bg-white elevation-1">
          <VCardText class="pa-4">
            <VRow>
              <!-- Columna Izquierda: Info del Empleado y Actividad -->
              <VCol cols="12" md="6">
                <div class="d-flex flex-column gap-3">
                  <!-- Empleado -->
                  <div class="d-flex align-center gap-3">
                    <VAvatar color="primary" size="40" variant="tonal" class="rounded-lg">
                      <VIcon icon="tabler-user" size="20" />
                    </VAvatar>
                    <div>
                      <div class="text-super-xs text-disabled font-weight-bold uppercase">Empleado</div>
                      <div class="text-sm font-weight-bold text-high-emphasis">
                        {{ props.execution.employee_name }}
                      </div>
                    </div>
                  </div>

                  <!-- Actividad -->
                  <div class="d-flex align-center gap-3">
                    <VAvatar color="success" size="40" variant="tonal" class="rounded-lg">
                      <VIcon icon="tabler-checklist" size="20" />
                    </VAvatar>
                    <div class="flex-grow-1">
                      <div class="text-super-xs text-disabled font-weight-bold uppercase">Actividad</div>
                      <div class="text-sm font-weight-bold text-high-emphasis">
                        {{ props.execution.activity_name }}
                      </div>
                      <div class="text-super-xs text-medium-emphasis">
                        {{ props.execution.description || "Sin descripción adicional" }}
                      </div>
                    </div>
                  </div>
                </div>
              </VCol>

              <!-- Columna Derecha: Detalles -->
              <VCol cols="12" md="6">
                <div class="d-flex flex-column gap-3">
                  <!-- Estado y Frecuencia -->
                  <div class="d-flex gap-2">
                    <VChip
                      :color="getStatusColor(props.execution.status)"
                      variant="tonal"
                    >
                      <VIcon
                        :icon="getStatusIcon(props.execution.status)"
                        size="14"
                        class="me-1"
                      />
                      {{ props.execution.status }}
                    </VChip>
                    <VChip
                      :color="getFrequencyColor(props.execution.frequency)"
                      variant="tonal"
                    >
                      {{ props.execution.frequency }}
                    </VChip>
                  </div>

                  <!-- Fechas -->
                  <div class="d-flex flex-column gap-2">
                    <div class="d-flex align-center gap-2">
                      <VIcon icon="tabler-calendar" size="18" color="primary" />
                      <div>
                        <span class="text-xs text-disabled"
                          >Fecha Inicio:
                        </span>
                        <span class="text-sm font-weight-medium">
                          {{ formatDate(props.execution.scheduled_date) }}
                        </span>
                      </div>
                    </div>

                    <div class="d-flex align-center gap-2">
                      <VIcon
                        icon="tabler-calendar-due"
                        size="18"
                        color="error"
                      />
                      <div>
                        <span class="text-xs text-disabled"
                          >Fecha Límite:
                        </span>
                        <span class="text-sm font-weight-medium">
                          {{ formatDate(props.execution.due_date) }}
                        </span>
                      </div>
                    </div>

                    <div class="d-flex align-center gap-2">
                      <VIcon
                        icon="tabler-calendar-check"
                        size="18"
                        color="success"
                      />
                      <div>
                        <span class="text-xs text-disabled"
                          >Fecha Completada:
                        </span>
                        <span class="text-sm font-weight-medium">
                          {{ formatDateTime(props.execution.completed_date) }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <!-- Notas del empleado -->
                  <div
                    v-if="props.execution.notes"
                    class="d-flex align-start gap-2"
                  >
                    <VIcon icon="tabler-note" size="18" color="info" />
                    <div class="flex-grow-1">
                      <div class="text-xs text-disabled mb-1">
                        Notas del empleado:
                      </div>
                      <div class="text-sm">{{ props.execution.notes }}</div>
                    </div>
                  </div>

                  <!-- Razón de rechazo anterior -->
                  <VAlert
                    v-if="props.execution.rejection_reason"
                    type="error"
                    variant="tonal"
                    density="compact"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-alert-circle" />
                    </template>
                    <div class="text-xs">
                      <strong>Rechazada anteriormente:</strong><br />
                      {{ props.execution.rejection_reason }}
                    </div>
                  </VAlert>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Evidencia Fotográfica -->
        <VCard variant="outlined" class="mb-5">
          <VCardTitle class="pa-4 d-flex align-center gap-2">
            <VIcon icon="tabler-photo" size="20" color="primary" />
            <span class="text-body-1">Evidencia Fotográfica</span>
            <VSpacer />
            <VBtn
              v-if="props.execution.photo"
              size="small"
              variant="tonal"
              :href="getPhotoUrl(props.execution.photo)"
              target="_blank"
              prepend-icon="tabler-external-link"
            >
              Abrir en nueva pestaña
            </VBtn>
          </VCardTitle>
          <VDivider />
          <VCardText class="pa-0">
            <VImg
              v-if="props.execution.photo"
              :src="getPhotoUrl(props.execution.photo)"
              cover
              max-height="500"
              class="bg-grey-lighten-2"
            >
              <template #error>
                <div
                  class="d-flex flex-column align-center justify-center fill-height text-error pa-8"
                >
                  <VIcon icon="tabler-photo-off" size="64" class="mb-3" />
                  <span class="text-body-2">No se pudo cargar la imagen</span>
                </div>
              </template>
            </VImg>
            <div v-else class="pa-8 text-center">
              <VIcon icon="tabler-photo-off" size="64" color="grey-lighten-1" />
              <div class="text-body-2 text-disabled mt-3">
                No hay evidencia fotográfica
              </div>
            </div>
          </VCardText>
        </VCard>

        <!-- Tabs de Acciones -->
        <VTabs v-model="activeTab" color="primary" class="mb-4">
          <VTab value="review" prepend-icon="tabler-check">Aprobar</VTab>
          <VTab value="reject" prepend-icon="tabler-x">Rechazar</VTab>
          <VTab value="cancel" prepend-icon="tabler-ban">Cancelar</VTab>
        </VTabs>

        <VWindow v-model="activeTab">
          <!-- Tab: Aprobar -->
          <VWindowItem value="review">
            <VCard variant="outlined">
              <VCardText class="pa-4">
                <VAlert type="success" variant="tonal" class="mb-4">
                  <template #prepend>
                    <VIcon icon="tabler-info-circle" />
                  </template>
                  <div class="text-sm">
                    Al aprobar, la actividad se marcará como
                    <strong>Completada</strong> y se registrará tu aprobación.
                  </div>
                </VAlert>

                <VTextarea
                  v-model="formData.notes"
                  label="Notas adicionales (opcional)"
                  placeholder="Agrega comentarios sobre la revisión..."
                  prepend-inner-icon="tabler-note"
                  rows="3"
                  counter="500"
                  :error-messages="props.errors.notes"
                  @update:model-value="emit('clear-errors')"
                />

                <div class="d-flex justify-end gap-2 mt-4">
                  <VBtn
                    color="secondary"
                    variant="tonal"
                    height="38"
                    class="rounded-lg font-weight-bold text-none"
                    @click="closeDialog"
                  >
                    Cancelar
                  </VBtn>
                  <VBtn
                    color="success"
                    variant="flat"
                    height="38"
                    class="rounded-lg font-weight-bold shadow-primary text-none"
                    prepend-icon="tabler-check"
                    :disabled="!canApprove"
                    @click="handleApprove"
                  >
                    Aprobar Actividad
                  </VBtn>
                </div>
              </VCardText>
            </VCard>
          </VWindowItem>

          <!-- Tab: Rechazar -->
          <VWindowItem value="reject">
            <VCard variant="flat" border class="rounded-lg">
              <VCardText class="pa-4">
                <VAlert type="error" variant="tonal" class="mb-4">
                  <template #prepend>
                    <VIcon icon="tabler-alert-circle" />
                  </template>
                  <div class="text-sm">
                    Al rechazar, la actividad volverá a estado
                    <strong>Pendiente</strong> y el empleado deberá realizarla
                    nuevamente. La foto actual será eliminada.
                  </div>
                </VAlert>

                <VTextarea
                  v-model="formData.rejection_reason"
                  label="Razón del rechazo *"
                  placeholder="Explica por qué se rechaza la actividad..."
                  prepend-inner-icon="tabler-message-circle"
                  rows="4"
                  counter="500"
                  :error-messages="props.errors.rejection_reason"
                  @update:model-value="emit('clear-errors')"
                />

                <div class="d-flex justify-end gap-2 mt-4">
                  <VBtn
                    color="secondary"
                    variant="tonal"
                    height="38"
                    class="rounded-lg font-weight-bold text-none"
                    @click="closeDialog"
                  >
                    Cancelar
                  </VBtn>
                  <VBtn
                    color="error"
                    variant="flat"
                    height="38"
                    class="rounded-lg font-weight-bold text-none"
                    prepend-icon="tabler-x"
                    :disabled="!canReject || !formData.rejection_reason.trim()"
                    @click="handleReject"
                  >
                    Rechazar y Devolver
                  </VBtn>
                </div>
              </VCardText>
            </VCard>
          </VWindowItem>

          <!-- Tab: Cancelar -->
          <VWindowItem value="cancel">
            <VCard variant="flat" border class="rounded-lg">
              <VCardText class="pa-4">
                <VAlert type="warning" variant="tonal" class="mb-4">
                  <template #prepend>
                    <VIcon icon="tabler-alert-triangle" />
                  </template>
                  <div class="text-sm">
                    Al cancelar, la actividad se marcará como
                    <strong>Cancelada</strong> permanentemente. Esta acción es
                    definitiva.
                  </div>
                </VAlert>

                <VTextarea
                  v-model="formData.cancellation_reason"
                  label="Razón de la cancelación *"
                  placeholder="Explica por qué se cancela la actividad..."
                  prepend-inner-icon="tabler-message-circle"
                  rows="4"
                  counter="500"
                  :error-messages="props.errors.cancellation_reason"
                  @update:model-value="emit('clear-errors')"
                />

                <div class="d-flex justify-end gap-2 mt-4">
                  <VBtn
                    color="secondary"
                    variant="tonal"
                    height="38"
                    class="rounded-lg font-weight-bold text-none"
                    @click="closeDialog"
                  >
                    Cancelar
                  </VBtn>
                  <VBtn
                    color="warning"
                    variant="flat"
                    height="38"
                    class="rounded-lg font-weight-bold text-none"
                    prepend-icon="tabler-ban"
                    :disabled="
                      !canCancel || !formData.cancellation_reason.trim()
                    "
                    @click="handleCancel"
                  >
                    Cancelar Actividad
                  </VBtn>
                </div>
              </VCardText>
            </VCard>
          </VWindowItem>
        </VWindow>
      </VCardText>
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
</style>
