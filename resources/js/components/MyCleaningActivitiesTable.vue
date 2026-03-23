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
  <VCard class="border-0 shadow-sm overflow-hidden">
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.myActivities"
      :items-length="props.totalRecords"
      :loading="props.loading"
      class="premium-table text-no-wrap"
      density="compact"
      @update:options="(options) => emit('update:options', options)"
    >
      <!-- Actividad -->
      <template #item.activity_name="{ item }">
        <div class="d-flex align-center gap-3 py-2">
          <VAvatar color="primary" size="34" variant="tonal" class="rounded">
            <VIcon icon="tabler-checkbox" size="18" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-xs font-weight-black text-high-emphasis leading-tight">
              {{ item.activity_name }}
            </span>
            <!-- Mostrar razón de rechazo si existe -->
            <span v-if="item.rejection_reason" class="text-super-xs text-error mt-1 font-weight-bold">
              <VIcon icon="tabler-alert-circle" size="10" class="me-1" />
              Rechazada: {{ item.rejection_reason }}
            </span>
          </div>
        </div>
      </template>

      <!-- Descripción -->
      <template #item.description="{ item }">
        <span class="text-xs text-medium-emphasis">
          {{ item.description || "Sin descripción" }}
        </span>
      </template>

      <!-- Frecuencia -->
      <template #item.frequency="{ item }">
        <VChip
          :color="getFrequencyColor(item.frequency)"
          size="x-small"
          variant="tonal"
          class="rounded font-weight-black px-2"
        >
          {{ item.frequency.toUpperCase() }}
        </VChip>
      </template>

      <!-- Estado -->
      <template #item.status="{ item }">
        <VChip
          :color="getStatusColor(item.status)"
          size="x-small"
          variant="tonal"
          class="rounded font-weight-black px-2"
        >
          <VIcon :icon="getStatusIcon(item.status)" size="12" class="me-1" />
          {{ item.status.toUpperCase() }}
        </VChip>
      </template>

      <!-- Fecha Inicio -->
      <template #item.scheduled_date="{ item }">
        <span class="text-xs font-weight-black tabular-nums">
          {{ formatDate(item.scheduled_date) }}
        </span>
      </template>

      <!-- Fecha Límite -->
      <template #item.due_date="{ item }">
        <div class="d-flex flex-column py-1">
          <span class="text-xs font-weight-black tabular-nums">
            {{ formatDate(item.due_date) }}
          </span>

          <!-- Vencida -->
          <span
            v-if="isOverdue(item.due_date, item.status)"
            class="text-super-xs text-error d-flex align-center gap-1 font-weight-black uppercase"
          >
            <VIcon icon="tabler-alert-triangle" size="10" />
            ¡Vencida!
          </span>

          <!-- Vence hoy -->
          <span
            v-else-if="
              getDaysRemaining(item.due_date) === 0 &&
              item.status === 'Pendiente'
            "
            class="text-super-xs text-warning d-flex align-center gap-1 font-weight-black uppercase"
          >
            <VIcon icon="tabler-clock-exclamation" size="10" />
            ¡Vence hoy!
          </span>

          <!-- Vence pronto -->
          <span
            v-else-if="isDueSoon(item.due_date, item.status)"
            class="text-super-xs text-warning d-flex align-center gap-1 font-weight-black uppercase"
          >
            <VIcon icon="tabler-clock-hour-4" size="10" />
            Queda {{ getDaysRemaining(item.due_date) }} día
          </span>
        </div>
      </template>

      <!-- Fecha Completada -->
      <template #item.completed_date="{ item }">
        <span class="text-xs font-weight-black tabular-nums text-disabled">
          {{ formatDateTime(item.completed_date) }}
        </span>
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
                color="primary"
                class="rounded-lg"
              >
                <VIcon icon="tabler-photo" size="18" />
                <VTooltip activator="parent" location="top">Ver evidencia</VTooltip>
              </VBtn>
            </template>
            <VCard class="rounded-lg shadow-xl overflow-hidden" max-width="300">
              <VImg
                :src="getPhotoUrl(item.photo)"
                cover
                aspect-ratio="1"
                class="bg-grey-lighten-2"
              >
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
          <!-- Botón para procesar/completar solo si está Pendiente -->
          <VTooltip v-if="canEdit(item)" text="Subir Evidencia" location="top">
            <template #activator="{ props: tp }">
              <VBtn v-bind="tp" icon="tabler-upload" variant="text" color="primary" size="32" class="rounded-lg" @click="emit('update-status', item)" />
            </template>
          </VTooltip>

          <!-- Mostrar info si ya está procesada o completada -->
          <VChip
            v-else-if="item.status === 'Procesada'"
            size="x-small"
            color="info"
            variant="flat"
            class="rounded font-weight-black"
          >
            REVISIÓN
          </VChip>

          <VChip
            v-else-if="item.status === 'Completada'"
            size="x-small"
            color="success"
            variant="flat"
            class="rounded font-weight-black px-1"
          >
            <VIcon icon="tabler-check" size="12" />
          </VChip>

          <VChip
            v-else-if="item.status === 'Vencida'"
            size="x-small"
            color="error"
            variant="flat"
            class="rounded font-weight-black px-1"
          >
             <VIcon icon="tabler-alert-triangle" size="12" />
          </VChip>

          <!-- Ver notas si existen -->
          <VTooltip v-if="item.notes" location="top" max-width="300">
            <template #activator="{ props: tp }">
              <VBtn v-bind="tp" icon="tabler-note" variant="text" color="secondary" size="32" class="rounded-lg" />
            </template>
            <div class="text-xs">
              <strong>Notas:</strong><br />
              {{ item.notes }}
            </div>
          </VTooltip>
        </div>
      </template>

      <!-- Paginación -->
      <template #bottom>
        <VDivider class="opacity-10" />
        <div class="d-flex justify-end pa-2">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
            size="small"
            @update:model-value="
              (newPage) => emit('update:options', { ...props, page: newPage })
            "
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
