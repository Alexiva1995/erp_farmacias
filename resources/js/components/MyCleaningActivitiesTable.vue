<script setup>
const props = defineProps({
  myActivities: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "update-status"]);

const headers = [
  { title: "Actividad", key: "activity_name", sortable: true, width: "20%" },
  { title: "Descripción", key: "description", sortable: false, width: "18%" },
  { title: "Frecuencia", key: "frequency", sortable: true },
  { title: "Estado", key: "status", sortable: true },
  { title: "Fecha Inicio", key: "scheduled_date", sortable: true },
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

// FUNCIÓN CORREGIDA PARA FORMATEAR FECHAS
const formatDate = (date) => {
  if (!date) return "N/A";

  // Si ya viene en formato ISO completo (con hora)
  if (date.includes("T") || date.includes(" ")) {
    return new Date(date).toLocaleDateString("es-ES", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  }

  // Si es solo fecha YYYY-MM-DD, parsear manualmente para evitar problemas de timezone
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

// FUNCIÓN PARA VERIFICAR SI ESTÁ ATRASADA
const isOverdue = (dueDate, status) => {
  if (status !== "Pendiente") return false;
  if (!dueDate) return false;

  // Fecha actual sin hora
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  // Fecha límite sin hora
  let due;
  if (dueDate.includes("T") || dueDate.includes(" ")) {
    due = new Date(dueDate);
  } else {
    const [year, month, day] = dueDate.split("-");
    due = new Date(year, month - 1, day);
  }
  due.setHours(0, 0, 0, 0);

  return due < today;
};

// FUNCIÓN PARA VERIFICAR SI VENCE PRONTO (2 días)
const isDueSoon = (dueDate, status) => {
  if (status !== "Pendiente") return false;
  if (!dueDate) return false;

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const twoDaysFromNow = new Date();
  twoDaysFromNow.setDate(twoDaysFromNow.getDate() + 2);
  twoDaysFromNow.setHours(23, 59, 59, 999);

  let due;
  if (dueDate.includes("T") || dueDate.includes(" ")) {
    due = new Date(dueDate);
  } else {
    const [year, month, day] = dueDate.split("-");
    due = new Date(year, month - 1, day);
  }

  return due >= today && due <= twoDaysFromNow;
};

// FUNCIÓN PARA OBTENER DÍAS RESTANTES
const getDaysRemaining = (dueDate) => {
  if (!dueDate) return null;

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  let due;
  if (dueDate.includes("T") || dueDate.includes(" ")) {
    due = new Date(dueDate);
  } else {
    const [year, month, day] = dueDate.split("-");
    due = new Date(year, month - 1, day);
  }
  due.setHours(0, 0, 0, 0);

  const diffTime = due - today;
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  return diffDays;
};

const canEdit = (item) => {
  return item.status === "Pendiente";
};

const getPhotoUrl = (photoPath) => {
  if (!photoPath) return null;
  return `${import.meta.env.VITE_API_URL}/storage/${photoPath}`;
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.myActivities"
      :items-length="props.totalRecords"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <!-- Actividad -->
      <template #item.activity_name="{ item }">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" size="38" variant="tonal">
            <VIcon icon="tabler-checkbox" size="20" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">
              {{ item.activity_name }}
            </span>
            <!-- Mostrar razón de rechazo si existe -->
            <span v-if="item.rejection_reason" class="text-xs text-error mt-1">
              <VIcon icon="tabler-alert-circle" size="12" class="me-1" />
              Rechazada: {{ item.rejection_reason }}
            </span>
          </div>
        </div>
      </template>

      <!-- Descripción -->
      <template #item.description="{ item }">
        <span class="text-sm text-medium-emphasis">
          {{ item.description || "Sin descripción" }}
        </span>
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

      <!-- Fecha Inicio -->
      <template #item.scheduled_date="{ item }">
        <span class="text-sm">
          {{ formatDate(item.scheduled_date) }}
        </span>
      </template>

      <!-- Fecha Límite -->
      <template #item.due_date="{ item }">
        <div class="d-flex flex-column">
          <span class="text-sm font-weight-medium">
            {{ formatDate(item.due_date) }}
          </span>

          <!-- Vencida -->
          <span
            v-if="isOverdue(item.due_date, item.status)"
            class="text-xs text-error d-flex align-center gap-1 mt-1"
          >
            <VIcon icon="tabler-alert-triangle" size="12" />
            ¡Vencida!
          </span>

          <!-- Vence hoy -->
          <span
            v-else-if="
              getDaysRemaining(item.due_date) === 0 &&
              item.status === 'Pendiente'
            "
            class="text-xs text-warning d-flex align-center gap-1 mt-1"
          >
            <VIcon icon="tabler-clock-exclamation" size="12" />
            ¡Vence hoy!
          </span>

          <!-- Vence pronto -->
          <span
            v-else-if="isDueSoon(item.due_date, item.status)"
            class="text-xs text-warning d-flex align-center gap-1 mt-1"
          >
            <VIcon icon="tabler-clock-hour-4" size="12" />
            Vence en {{ getDaysRemaining(item.due_date) }} día(s)
          </span>
        </div>
      </template>

      <!-- Fecha Completada -->
      <template #item.completed_date="{ item }">
        <span class="text-sm">
          {{ formatDateTime(item.completed_date) }}
        </span>
      </template>

      <!-- Evidencia (Foto) -->
      <template #item.photo="{ item }">
        <div v-if="item.photo" class="d-flex justify-center">
          <VMenu>
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon
                variant="text"
                size="small"
                color="primary"
              >
                <VIcon icon="tabler-photo" size="20" />
                <VTooltip activator="parent" location="top">
                  Ver foto de evidencia
                </VTooltip>
              </VBtn>
            </template>
            <VCard max-width="400">
              <VImg
                :src="getPhotoUrl(item.photo)"
                cover
                aspect-ratio="1"
                class="bg-grey-lighten-2"
              >
                <template #error>
                  <div
                    class="d-flex align-center justify-center fill-height text-error"
                  >
                    <VIcon icon="tabler-photo-off" size="48" />
                  </div>
                </template>
              </VImg>
              <VCardActions class="justify-end">
                <VBtn
                  size="small"
                  color="primary"
                  :href="getPhotoUrl(item.photo)"
                  target="_blank"
                  prepend-icon="tabler-external-link"
                >
                  Abrir en nueva pestaña
                </VBtn>
              </VCardActions>
            </VCard>
          </VMenu>
        </div>
        <div v-else class="d-flex justify-center">
          <VIcon icon="tabler-photo-off" size="20" color="grey" />
        </div>
      </template>

      <!-- Acciones -->
      <template #item.actions="{ item }">
        <div class="d-flex gap-1 justify-center">
          <!-- Botón para procesar/completar solo si está Pendiente -->
          <IconBtn v-if="canEdit(item)" @click="emit('update-status', item)">
            <VIcon icon="tabler-upload" />
            <VTooltip activator="parent" location="top">
              Procesar Actividad
            </VTooltip>
          </IconBtn>

          <!-- Mostrar info si ya está procesada o completada -->
          <VBtn
            v-else-if="item.status === 'Procesada'"
            size="small"
            color="info"
            variant="tonal"
            disabled
          >
            <VIcon icon="tabler-hourglass" size="16" class="me-1" />
            En revisión
          </VBtn>

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

          <!-- Ver notas si existen -->
          <IconBtn v-if="item.notes">
            <VIcon icon="tabler-note" />
            <VTooltip activator="parent" location="top" max-width="300">
              <div class="text-sm">
                <strong>Notas:</strong><br />
                {{ item.notes }}
              </div>
            </VTooltip>
          </IconBtn>
        </div>
      </template>

      <!-- Paginación -->
      <template #bottom>
        <VDivider />
        <div class="d-flex justify-end pa-2">
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
