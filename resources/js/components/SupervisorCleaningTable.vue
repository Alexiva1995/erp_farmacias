<script setup>
const props = defineProps({
  executions: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "review"]);

const headers = [
  { title: "Empleado", key: "employee_name", sortable: true, width: "15%" },
  { title: "Actividad", key: "activity_name", sortable: true, width: "18%" },
  { title: "Descripción", key: "description", sortable: false, width: "15%" },
  { title: "Frecuencia", key: "frequency", sortable: true },
  { title: "Estado", key: "status", sortable: true },
  { title: "Fecha Límite", key: "due_date", sortable: true },
  { title: "Fecha Completada", key: "completed_date", sortable: true },
  { title: "Evidencia", key: "photo", sortable: false, align: "center" },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

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
    Diaria: "error",
    Semanal: "warning",
    Bimestral: "info",
    Mensual: "success",
    Trimestral: "primary",
    Semestral: "secondary",
    Anual: "default",
  };
  return colors[frequency] || "default";
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

const canReview = (item) => {
  // Solo puede revisar si está Procesada
  return item.status === "Procesada";
};

const getPhotoUrl = (photoPath) => {
  if (!photoPath) return null;
  const baseUrl = import.meta.env.VITE_API_URL;
  return `${baseUrl}/storage/${photoPath}`;
};

const getEmployeeInitials = (name) => {
  if (!name) return "?";
  const names = name.split(" ");
  if (names.length >= 2) {
    return `${names[0][0]}${names[1][0]}`.toUpperCase();
  }
  return name.substring(0, 2).toUpperCase();
};

const getEmployeeColor = (employeeId) => {
  const colors = ["primary", "secondary", "success", "info", "warning", "error"];
  return colors[employeeId % colors.length];
};
</script>

<template>
  <VCard class="border-0 shadow-sm overflow-hidden">
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.executions"
      :items-length="props.totalRecords"
      :loading="props.loading"
      class="premium-table text-no-wrap"
      density="compact"
      @update:options="(options) => emit('update:options', options)"
    >
      <!-- Empleado -->
      <template #item.employee_name="{ item }">
        <div class="d-flex align-center gap-3 py-2">
          <VAvatar :color="getEmployeeColor(item.employee_id)" size="34" variant="tonal" class="rounded">
            <span class="text-super-xs font-weight-black">
              {{ getEmployeeInitials(item.employee_name) }}
            </span>
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-xs font-weight-black text-high-emphasis leading-tight">
              {{ item.employee_name }}
            </span>
          </div>
        </div>
      </template>

      <!-- Actividad -->
      <template #item.activity_name="{ item }">
        <div class="d-flex flex-column py-1">
          <span class="text-xs font-weight-black leading-tight">
            {{ item.activity_name }}
          </span>
          <!-- Mostrar razón de rechazo si existe -->
          <span v-if="item.rejection_reason" class="text-super-xs text-error mt-1 font-weight-black">
            <VIcon icon="tabler-alert-circle" size="10" class="me-1" />
            Motivo: {{ item.rejection_reason }}
          </span>
        </div>
      </template>

      <!-- Descripción -->
      <template #item.description="{ item }">
        <VTooltip location="top" max-width="300">
          <template #activator="{ props: tp }">
            <span v-bind="tp" class="text-xs text-medium-emphasis">
              {{
                item.description
                  ? item.description.length > 40
                    ? item.description.substring(0, 40) + "..."
                    : item.description
                  : "Sin descripción"
              }}
            </span>
          </template>
          {{ item.description || "Sin descripción" }}
        </VTooltip>
      </template>

      <!-- Frecuencia -->
      <template #item.frequency="{ item }">
        <VChip :color="getFrequencyColor(item.frequency)" size="x-small" variant="tonal" class="rounded font-weight-black px-2">
          {{ item.frequency.toUpperCase() }}
        </VChip>
      </template>

      <!-- Estado -->
      <template #item.status="{ item }">
        <VChip :color="getStatusColor(item.status)" size="x-small" variant="tonal" class="rounded font-weight-black px-2">
          <VIcon :icon="getStatusIcon(item.status)" size="12" class="me-1" />
          {{ item.status.toUpperCase() }}
        </VChip>
      </template>

      <!-- Fecha Límite -->
      <template #item.due_date="{ item }">
        <span class="text-xs font-weight-black tabular-nums">
          {{ formatDate(item.due_date) }}
        </span>
      </template>

      <!-- Fecha Completada -->
      <template #item.completed_date="{ item }">
        <div class="d-flex flex-column py-1">
          <span class="text-xs font-weight-black tabular-nums">
            {{ formatDateTime(item.completed_date) }}
          </span>
          <!-- Mostrar aprobador si existe -->
          <span v-if="item.approved_by && item.status === 'Completada'" class="text-super-xs text-success mt-1 font-weight-black">
            <VIcon icon="tabler-user-check" size="10" class="me-1" />
            Por: {{ item.approved_by }}
          </span>
        </div>
      </template>

      <!-- Evidencia (Foto) -->
      <template #item.photo="{ item }">
        <div v-if="item.photo" class="d-flex justify-center">
          <VMenu open-on-hover transition="scale-transition">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon
                variant="text"
                size="32"
                :color="item.status === 'Procesada' ? 'primary' : 'secondary'"
                class="rounded-lg"
              >
                <VIcon icon="tabler-photo" size="18" />
                <VTooltip activator="parent" location="top">Ver evidencia</VTooltip>
              </VBtn>
            </template>
            <VCard class="rounded-lg shadow-xl overflow-hidden" max-width="300">
              <VImg :src="getPhotoUrl(item.photo)" cover aspect-ratio="1" class="bg-grey-lighten-2">
                <template #error>
                  <div class="d-flex align-center justify-center fill-height text-error">
                    <VIcon icon="tabler-photo-off" size="32" />
                  </div>
                </template>
              </VImg>
              <VCardActions class="pa-2 bg-surface">
                <VBtn
                  block
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  class="rounded-lg font-weight-black"
                  :href="getPhotoUrl(item.photo)"
                  target="_blank"
                >
                  <VIcon start icon="tabler-external-link" size="14" />
                  Ampliar
                </VBtn>
              </VCardActions>
            </VCard>
          </VMenu>
        </div>
        <div v-else class="d-flex justify-center opacity-30">
          <VIcon icon="tabler-photo-off" size="18" color="grey" />
        </div>
      </template>

      <!-- Acciones -->
      <template #item.actions="{ item }">
        <div class="d-flex gap-1 justify-center">
          <VTooltip text="Revisar Actividad" location="top">
            <template #activator="{ props: tp }">
              <VBtn
                v-bind="tp"
                icon="tabler-eye-check"
                variant="text"
                :color="canReview(item) ? 'primary' : 'info'"
                size="32"
                class="rounded-lg"
                @click="emit('review', item)"
              />
            </template>
          </VTooltip>
        </div>
      </template>

      <!-- Paginación -->
      <template #bottom>
        <VDivider class="opacity-10" />
        <div class="d-flex align-center justify-space-between pa-4">
          <span class="text-super-xs text-disabled font-weight-bold uppercase">
            Total: {{ props.totalRecords }} registros
          </span>
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
            size="small"
            @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
          />
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>

<style scoped>
:deep(.premium-table) {
  background: transparent !important;

  thead th {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
    font-size: 0.75rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
  }

  tbody tr {
    transition: background-color 0.2s ease;
    &:hover {
      background-color: rgba(var(--v-theme-primary), 0.02) !important;
    }
    td {
      border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
    }
  }
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.leading-tight {
  line-height: 1.2;
}

.opacity-30 {
  opacity: 0.3;
}
</style>
