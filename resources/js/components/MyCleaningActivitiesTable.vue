<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  myActivities: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "update-status"]);

const { mobile } = useDisplay();

const headers = [
  { title: "Actividad", key: "activity_name", sortable: true },
  { title: "Frecuencia", key: "frequency", sortable: true, width: "120px" },
  { title: "Estado", key: "status", sortable: true, width: "120px", align: "center" },
  { title: "Fecha Inicio", key: "scheduled_date", sortable: true, width: "140px" },
  { title: "Fecha Límite", key: "due_date", sortable: true, width: "140px" },
  { title: "Completada", key: "completed_date", sortable: true, width: "160px" },
  { title: "Foto", key: "photo", sortable: false, align: "center", width: "80px" },
  { title: "Acciones", key: "actions", sortable: false, align: "center", width: "100px" },
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

const isOverdue = (dueDate, status) => {
  if (status !== "Pendiente") return false;
  if (!dueDate) return false;
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
  return due < today;
};

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
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
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
  <div class="my-activities-table-container">
    <!-- Vista de Escritorio -->
    <VCard v-if="!mobile" class="border shadow-sm overflow-hidden bg-surface">
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
              <VIcon icon="tabler-clipboard-list" size="18" />
            </VAvatar>
            <div class="d-flex flex-column truncate">
              <span class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight mb-1">
                {{ item.activity_name }}
              </span>
              <span v-if="item.rejection_reason" class="text-super-xs text-error font-weight-black uppercase">
                MOTIVO: {{ item.rejection_reason }}
              </span>
              <span v-else class="text-super-xs text-disabled font-weight-black uppercase truncate" style="max-width: 250px;">
                {{ item.description || 'SIN DESCRIPCIÓN' }}
              </span>
            </div>
          </div>
        </template>

        <!-- Frecuencia -->
        <template #item.frequency="{ item }">
          <VChip :color="getFrequencyColor(item.frequency)" size="x-small" variant="tonal" class="font-weight-black text-uppercase rounded px-2">
            {{ item.frequency }}
          </VChip>
        </template>

        <!-- Estado -->
        <template #item.status="{ item }">
          <VChip :color="getStatusColor(item.status)" size="x-small" variant="tonal" class="font-weight-black text-uppercase rounded px-2">
            <VIcon :icon="getStatusIcon(item.status)" size="12" class="me-1" />
            {{ item.status }}
          </VChip>
        </template>

        <!-- Fechas -->
        <template #item.scheduled_date="{ item }">
          <span class="text-xs font-weight-black text-medium-emphasis tabular-nums uppercase">{{ formatDate(item.scheduled_date) }}</span>
        </template>

        <template #item.due_date="{ item }">
          <div class="d-flex flex-column">
            <span class="text-xs font-weight-black tabular-nums uppercase" :class="isOverdue(item.due_date, item.status) ? 'text-error' : 'text-medium-emphasis'">
              {{ formatDate(item.due_date) }}
            </span>
            <span v-if="isOverdue(item.due_date, item.status)" class="text-super-xs text-error font-weight-black uppercase">VENCIDA</span>
          </div>
        </template>

        <template #item.completed_date="{ item }">
          <span class="text-xs font-weight-black text-disabled tabular-nums uppercase">{{ formatDateTime(item.completed_date) }}</span>
        </template>

        <!-- Foto -->
        <template #item.photo="{ item }">
          <div v-if="item.photo" class="d-flex justify-center">
            <VMenu open-on-hover transition="scale-transition">
              <template #activator="{ props: menuProps }">
                <IconBtn v-bind="menuProps" size="small" color="primary" variant="tonal" class="rounded">
                  <VIcon icon="tabler-photo" size="18" />
                </IconBtn>
              </template>
              <VCard class="rounded-lg shadow-xl overflow-hidden" max-width="300">
                <VImg :src="getPhotoUrl(item.photo)" cover aspect-ratio="1" class="bg-grey-lighten-2" />
              </VCard>
            </VMenu>
          </div>
          <VIcon v-else icon="tabler-photo-off" size="18" class="text-disabled" />
        </template>

        <!-- Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-1">
            <IconBtn v-if="canEdit(item)" size="small" color="primary" variant="tonal" class="rounded" @click="emit('update-status', item)">
              <VIcon icon="tabler-upload" size="18" />
            </IconBtn>
            <VIcon v-else-if="item.status === 'Completada'" icon="tabler-circle-check" color="success" size="18" />
            <VIcon v-else icon="tabler-clock-pause" color="disabled" size="18" />
          </div>
        </template>

        <template #bottom>
          <VDivider class="opacity-10" />
          <div class="d-flex align-center justify-space-between pa-2">
            <span class="text-super-xs text-disabled font-weight-black uppercase ms-2">
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

    <!-- Vista Móvil Premium -->
    <div v-else class="pa-4 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-4 rounded" />

      <VRow>
        <VCol v-for="item in props.myActivities" :key="item.id" cols="12">
          <VCard class="rounded-lg border shadow-sm mb-4 overflow-hidden">
            <div class="pa-4">
              <div class="d-flex justify-space-between align-start mb-4">
                <div class="d-flex align-center gap-3 min-width-0">
                  <VAvatar color="primary" size="40" variant="tonal" class="rounded">
                    <VIcon icon="tabler-clipboard-list" size="20" />
                  </VAvatar>
                  <div class="d-flex flex-column min-width-0">
                    <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate">
                      {{ item.activity_name }}
                    </h3>
                    <div class="d-flex align-center gap-1 mt-1">
                      <VChip :color="getFrequencyColor(item.frequency)" size="x-super-small" variant="tonal" class="text-super-xs font-weight-black uppercase rounded-sm px-1">
                        {{ item.frequency }}
                      </VChip>
                      <span class="text-super-xs text-primary font-weight-black uppercase">ID: #{{ item.id }}</span>
                    </div>
                  </div>
                </div>
                <div class="d-flex gap-1">
                  <IconBtn v-if="canEdit(item)" size="small" color="primary" variant="tonal" class="rounded" @click="emit('update-status', item)">
                    <VIcon icon="tabler-upload" size="18" />
                  </IconBtn>
                  <VChip v-else :color="getStatusColor(item.status)" size="x-small" variant="tonal" class="font-weight-black text-uppercase rounded px-2">
                    <VIcon :icon="getStatusIcon(item.status)" size="12" class="me-1" />
                    {{ item.status }}
                  </VChip>
                </div>
              </div>

              <div v-if="item.rejection_reason" class="pa-3 bg-error-lighten-5 rounded border-error border-dashed border-opacity-25 mb-4">
                <p class="text-xs text-error font-weight-black uppercase mb-0">
                  <VIcon icon="tabler-alert-triangle" size="14" class="me-1" />
                  RECHAZADA: {{ item.rejection_reason }}
                </p>
              </div>

              <VDivider class="my-4 border-opacity-10" />

              <div class="d-flex justify-space-between align-center">
                <div class="d-flex flex-column">
                  <span class="text-super-xs text-disabled uppercase font-weight-black">Fecha Límite</span>
                  <span class="text-xs font-weight-black mt-1 uppercase" :class="isOverdue(item.due_date, item.status) ? 'text-error' : 'text-medium-emphasis'">
                    {{ formatDate(item.due_date) }}
                  </span>
                </div>
                <div class="d-flex flex-column align-end">
                  <span class="text-super-xs text-disabled uppercase font-weight-black">Estado</span>
                  <VChip :color="getStatusColor(item.status)" size="x-small" variant="tonal" class="font-weight-black text-uppercase mt-1 rounded px-2">
                    {{ item.status }}
                  </VChip>
                </div>
              </div>

              <div v-if="item.photo" class="mt-4">
                <VImg :src="getPhotoUrl(item.photo)" cover height="160" class="rounded-lg border bg-light shadow-sm" @click="() => {}" />
              </div>
            </div>
          </VCard>
        </VCol>
      </VRow>

      <div v-if="props.myActivities.length === 0 && !props.loading" class="text-center pa-8">
        <VIcon icon="tabler-search-off" size="48" color="disabled" class="mb-2" />
        <p class="text-disabled font-weight-medium">No se encontraron actividades</p>
      </div>

      <!-- Paginación Móvil -->
      <div v-if="props.totalRecords > props.itemsPerPage" class="d-flex justify-center mt-4">
        <VPagination
          v-model="props.page"
          :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
          :total-visible="5"
          size="small"
          @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
:deep(.premium-table) {
  background: transparent !important;

  thead th {
    background: white !important;
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
    font-size: 0.75rem !important;
    font-weight: 900 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05rem !important;
    border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
  }

  tbody tr {
    transition: background-color 0.2s ease;
    &:hover {
      background-color: rgba(var(--v-theme-primary), 0.02) !important;
    }
    td {
      padding-block: 12px !important;
      border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
    }
  }
}

.bg-light {
  background-color: rgba(var(--v-theme-on-surface), 0.015);
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.x-super-small {
  height: 16px !important;
  font-size: 0.6rem !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.leading-none {
  line-height: 1;
}

.leading-tight {
  line-height: 1.25;
}

.uppercase {
  text-transform: uppercase;
}

.bg-error-lighten-5 {
  background-color: rgba(var(--v-theme-error), 0.03);
}

.border-dashed {
  border-style: dashed !important;
}
</style>
