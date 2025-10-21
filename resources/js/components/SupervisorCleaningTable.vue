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

  const cleanPath = photoPath.startsWith("/")
    ? photoPath.substring(1)
    : photoPath;
  const baseUrl = import.meta.env.VITE_API_URL || "http://localhost:8000";

  return `${baseUrl}/storage/${cleanPath}`;
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
  const colors = [
    "primary",
    "secondary",
    "success",
    "info",
    "warning",
    "error",
  ];
  return colors[employeeId % colors.length];
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.executions"
      :items-length="props.totalRecords"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <!-- Empleado -->
      <template #item.employee_name="{ item }">
        <div class="d-flex align-center gap-3">
          <VAvatar
            :color="getEmployeeColor(item.employee_id)"
            size="38"
            variant="tonal"
          >
            <span class="text-sm font-weight-medium">
              {{ getEmployeeInitials(item.employee_name) }}
            </span>
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">
              {{ item.employee_name }}
            </span>
          </div>
        </div>
      </template>

      <!-- Actividad -->
      <template #item.activity_name="{ item }">
        <div class="d-flex flex-column">
          <span class="text-body-1 font-weight-medium">
            {{ item.activity_name }}
          </span>
          <!-- Mostrar razón de rechazo si existe -->
          <span v-if="item.rejection_reason" class="text-xs text-error mt-1">
            <VIcon icon="tabler-alert-circle" size="12" class="me-1" />
            Motivo: {{ item.rejection_reason }}
          </span>
        </div>
      </template>

      <!-- Descripción -->
      <template #item.description="{ item }">
        <VTooltip location="top" max-width="300">
          <template #activator="{ props: tooltipProps }">
            <span v-bind="tooltipProps" class="text-sm text-medium-emphasis">
              {{
                item.description
                  ? item.description.length > 50
                    ? item.description.substring(0, 50) + "..."
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
        <VChip
          :color="getFrequencyColor(item.frequency)"
          size="small"
          variant="tonal"
        >
          {{ item.frequency }}
        </VChip>
      </template>

      <!-- Estado -->
      <template #item.status="{ item }">
        <VChip
          :color="getStatusColor(item.status)"
          size="small"
          variant="tonal"
        >
          <VIcon :icon="getStatusIcon(item.status)" size="14" class="me-1" />
          {{ item.status }}
        </VChip>
      </template>

      <!-- Fecha Límite -->
      <template #item.due_date="{ item }">
        <span class="text-sm">
          {{ formatDate(item.due_date) }}
        </span>
      </template>

      <!-- Fecha Completada -->
      <template #item.completed_date="{ item }">
        <div class="d-flex flex-column">
          <span class="text-sm font-weight-medium">
            {{ formatDateTime(item.completed_date) }}
          </span>
          <!-- Mostrar aprobador si existe -->
          <span
            v-if="item.approved_by && item.status === 'Completada'"
            class="text-xs text-success mt-1"
          >
            <VIcon icon="tabler-user-check" size="12" class="me-1" />
            Por: {{ item.approved_by }}
          </span>
        </div>
      </template>

      <!-- Evidencia (Foto) -->
      <template #item.photo="{ item }">
        <div v-if="item.photo" class="d-flex justify-center">
          <VMenu location="start">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon
                variant="text"
                size="small"
                :color="item.status === 'Procesada' ? 'primary' : 'default'"
              >
                <VIcon icon="tabler-photo" size="20" />
                <VTooltip activator="parent" location="top">
                  Ver foto de evidencia
                </VTooltip>
              </VBtn>
            </template>
            <VCard max-width="500">
              <VCardTitle
                class="d-flex align-center justify-space-between pa-3"
              >
                <span class="text-body-1">Evidencia Fotográfica</span>
                <VBtn
                  icon
                  variant="text"
                  size="small"
                  :href="getPhotoUrl(item.photo)"
                  target="_blank"
                >
                  <VIcon icon="tabler-external-link" />
                </VBtn>
              </VCardTitle>
              <VDivider />
              <VImg
                :src="getPhotoUrl(item.photo)"
                cover
                max-height="400"
                class="bg-grey-lighten-2"
              >
                <template #error>
                  <div
                    class="d-flex flex-column align-center justify-center fill-height text-error pa-4"
                  >
                    <VIcon icon="tabler-photo-off" size="48" class="mb-2" />
                    <span class="text-xs text-center"
                      >No se pudo cargar la imagen</span
                    >
                  </div>
                </template>
              </VImg>
              <VDivider />
              <VCardText v-if="item.notes" class="pa-3">
                <div class="text-xs text-disabled mb-1">
                  Notas del empleado:
                </div>
                <div class="text-sm">{{ item.notes }}</div>
              </VCardText>
            </VCard>
          </VMenu>
        </div>
        <div v-else class="d-flex justify-center">
          <VTooltip location="top">
            <template #activator="{ props: tooltipProps }">
              <VIcon
                v-bind="tooltipProps"
                icon="tabler-photo-off"
                size="20"
                color="grey"
              />
            </template>
            Sin evidencia
          </VTooltip>
        </div>
      </template>

      <!-- Acciones -->
      <template #item.actions="{ item }">
        <div class="d-flex gap-1 justify-center">
          <!-- Botón Revisar (solo si está Procesada) -->
          <VBtn
            v-if="canReview(item)"
            size="small"
            color="primary"
            variant="elevated"
            prepend-icon="tabler-eye-check"
            @click="emit('review', item)"
          >
            Revisar
          </VBtn>

          <!-- Estado si ya fue revisada -->
          <VBtn
            v-else-if="item.status === 'Completada'"
            size="small"
            color="success"
            variant="tonal"
            disabled
          >
            <VIcon icon="tabler-check" size="16" class="me-1" />
            Aprobada
          </VBtn>

          <VBtn
            v-else-if="item.status === 'Vencida'"
            size="small"
            color="error"
            variant="tonal"
            disabled
          >
            <VIcon icon="tabler-alert-triangle" size="16" class="me-1" />
            Vencida
          </VBtn>

          <VBtn
            v-else-if="item.status === 'Cancelada'"
            size="small"
            color="secondary"
            variant="tonal"
            disabled
          >
            <VIcon icon="tabler-x" size="16" class="me-1" />
            Cancelada
          </VBtn>

          <!-- Ver detalles (siempre disponible) -->
          <IconBtn @click="emit('review', item)">
            <VIcon icon="tabler-info-circle" />
            <VTooltip activator="parent" location="top">
              Ver Detalles
            </VTooltip>
          </IconBtn>
        </div>
      </template>

      <!-- Mensaje cuando no hay datos -->
      <template #no-data>
        <div class="text-center py-8">
          <VIcon icon="tabler-clipboard-off" size="64" color="grey-lighten-1" />
          <div class="text-body-1 text-disabled mt-3">
            No hay actividades para revisar
          </div>
          <div class="text-sm text-disabled">
            Ajusta los filtros para ver más resultados
          </div>
        </div>
      </template>

      <!-- Paginación -->
      <template #bottom>
        <VDivider />
        <div class="d-flex justify-space-between align-center pa-4">
          <div class="text-sm text-disabled">
            Mostrando {{ props.executions.length }} de
            {{ props.totalRecords }} registros
          </div>
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
            @update:model-value="
              (newPage) => emit('update:options', { ...props, page: newPage })
            "
          />
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
