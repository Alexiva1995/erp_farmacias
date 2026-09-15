<script setup>
// Tabla de supervisión de tareas de limpieza — homologada al estándar canónico
import AppEmptyState from "@/components/AppEmptyState.vue";
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { useAuthStore } from "@/stores/auth";

const props = defineProps({
  executions: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "review", "resolve-vencida"]);

const authStore = useAuthStore();

const isOwnTask = (item) => {
  if (item.employee?.user_id) {
    return item.employee.user_id === authStore.user?.id;
  }
  return false;
};

const headers = [
  { title: "EMPLEADO", key: "employee_name", sortable: true },
  { title: "ACTIVIDAD", key: "activity_name", sortable: true },
  { title: "FRECUENCIA", key: "frequency", sortable: true, width: "120px", align: "center" },
  { title: "ESTADO", key: "status", sortable: true, width: "120px", align: "center" },
  { title: "LÍMITE", key: "due_date", sortable: true, width: "120px" },
  { title: "COMPLETADA", key: "completed_date", sortable: true, width: "150px" },
  { title: "FOTO", key: "photo", sortable: false, align: "center", width: "80px" },
  { title: "ACCIONES", key: "actions", sortable: false, align: "end", width: "110px" },
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
  return item.status === "Procesada";
};

const getPhotoUrl = (photoPath) => {
  if (!photoPath) return null;
  const cleanPath = photoPath.startsWith("/")
    ? photoPath.substring(1)
    : photoPath;
  return `/storage/${cleanPath}`;
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
  const colors = ["primary", "secondary", "success", "info", "warning", "error", "purple", "amber"];
  return colors[employeeId % colors.length];
};

const formatCapitalize = (str) => {
  if (!str) return "";
  return str.toLowerCase().replace(/(?:^|\s|-)\S/g, (char) => char.toUpperCase());
};
</script>

<template>
  <div class="supervisor-cleaning-table-container">
    <!-- Vista de Escritorio -->
    <div class="d-none d-md-block">
      <VCard border variant="flat">
        <VDataTableServer
          :items-per-page="props.itemsPerPage"
          :page="props.page"
          :headers="headers"
          :items="props.executions"
          :items-length="props.totalRecords"
          :loading="props.loading"
          density="comfortable"
          @update:options="(options) => emit('update:options', options)"
        >
          <!-- Estado Vacío -->
          <template #no-data>
            <AppEmptyState
              title="Sin actividades para supervisar"
              message="No hay ejecuciones registradas con los filtros seleccionados."
              icon="tabler-clipboard-off"
            />
          </template>

          <!-- Empleado -->
          <template #item.employee_name="{ item }">
            <div class="d-flex align-center gap-3 py-1">
              <VAvatar
                :color="!item.employee_photo ? getEmployeeColor(item.employee_id) : undefined"
                size="34"
                variant="tonal"
                class="rounded-lg font-weight-bold"
              >
                <VImg v-if="item.employee_photo" :src="item.employee_photo" cover />
                <span v-else class="text-xs">{{ getEmployeeInitials(item.employee_name) }}</span>
              </VAvatar>
              <div class="d-flex flex-column truncate">
                <span class="text-sm font-weight-medium text-high-emphasis leading-tight text-capitalize">
                  {{ formatCapitalize(item.employee_name) }}
                </span>
                <span class="text-super-xs text-medium-emphasis font-weight-medium">
                  ID #{{ item.employee_id }}
                </span>
              </div>
            </div>
          </template>

          <!-- Actividad -->
          <template #item.activity_name="{ item }">
            <div class="d-flex flex-column py-1 truncate">
              <span class="text-sm font-weight-medium text-high-emphasis leading-tight text-capitalize">
                {{ formatCapitalize(item.activity_name) }}
              </span>
              <span v-if="item.rejection_reason" class="text-super-xs text-error mt-0.5 font-weight-bold uppercase">
                Motivo: {{ item.rejection_reason }}
              </span>
              <span v-else-if="item.description" class="text-super-xs text-medium-emphasis truncate mt-0.5" style="max-width: 240px">
                {{ item.description }}
              </span>
            </div>
          </template>

          <!-- Frecuencia -->
          <template #item.frequency="{ item }">
            <VChip
              :color="getFrequencyColor(item.frequency)"
              size="x-small"
              variant="tonal"
              class="rounded font-weight-bold uppercase px-2"
            >
              {{ item.frequency || 'N/A' }}
            </VChip>
          </template>

          <!-- Estado -->
          <template #item.status="{ item }">
            <VChip
              :color="getStatusColor(item.status)"
              size="x-small"
              variant="tonal"
              class="rounded font-weight-bold uppercase px-2"
            >
              <VIcon :icon="getStatusIcon(item.status)" size="12" class="me-1" />
              {{ item.status }}
            </VChip>
          </template>

          <!-- Límite -->
          <template #item.due_date="{ item }">
            <span class="text-xs font-weight-medium text-medium-emphasis tabular-nums">
              {{ formatDate(item.due_date) }}
            </span>
          </template>

          <!-- Completada -->
          <template #item.completed_date="{ item }">
            <div class="d-flex flex-column py-1">
              <span class="text-xs font-weight-medium text-medium-emphasis tabular-nums">
                {{ formatDateTime(item.completed_date) }}
              </span>
              <span v-if="item.approved_by && item.status === 'Completada'" class="text-super-xs text-success font-weight-bold">
                <VIcon icon="tabler-user-check" size="11" class="me-1" />
                Por: {{ item.approved_by }}
              </span>
            </div>
          </template>

          <!-- Foto -->
          <template #item.photo="{ item }">
            <div v-if="item.photo" class="d-flex justify-center">
              <VMenu open-on-hover transition="scale-transition">
                <template #activator="{ props: menuProps }">
                  <IconBtn v-bind="menuProps" size="small" :color="item.status === 'Procesada' ? 'primary' : 'secondary'" variant="tonal">
                    <VIcon icon="tabler-photo" size="18" />
                  </IconBtn>
                </template>
                <VCard class="rounded-lg shadow-xl overflow-hidden" max-width="300">
                  <VImg :src="getPhotoUrl(item.photo)" cover aspect-ratio="1" class="bg-grey-lighten-2" />
                </VCard>
              </VMenu>
            </div>
            <VIcon v-else icon="tabler-photo-off" size="18" class="text-disabled opacity-40" />
          </template>

          <!-- Acciones -->
          <template #item.actions="{ item }">
            <div class="d-flex justify-end gap-1">
              <template v-if="item.status === 'Procesada' && !isOwnTask(item)">
                <IconBtn size="small" color="primary" @click="emit('review', item)">
                  <VIcon icon="tabler-shield-check" size="18" />
                  <VTooltip activator="parent">Revisar actividad</VTooltip>
                </IconBtn>
              </template>
              <template v-else-if="item.status === 'Vencida' && !isOwnTask(item)">
                <!-- Check simple: Solo quitar de la lista (marcar como revisada/archivada) -->
                <IconBtn size="small" color="secondary" @click="emit('resolve-vencida', { item, action: 'hide' })">
                  <VIcon icon="tabler-check" size="18" />
                  <VTooltip activator="parent">Archivar vencida</VTooltip>
                </IconBtn>
                <!-- Doble check: Marcar como completada -->
                <IconBtn size="small" color="success" @click="emit('resolve-vencida', { item, action: 'complete' })">
                  <VIcon icon="tabler-checks" size="18" />
                  <VTooltip activator="parent">Aprobar vencida</VTooltip>
                </IconBtn>
              </template>
              <template v-else-if="!isOwnTask(item)">
                <IconBtn size="small" color="info" @click="emit('review', item)">
                  <VIcon icon="tabler-eye" size="18" />
                  <VTooltip activator="parent">Ver detalles</VTooltip>
                </IconBtn>
              </template>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- Vista Móvil (Cards) -->
    <div class="d-block d-md-none pa-2 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <AppEmptyState
        v-if="props.executions.length === 0 && !props.loading"
        title="Sin actividades"
        message="No hay ejecuciones registradas con los filtros seleccionados."
        icon="tabler-clipboard-off"
      />

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.executions"
          :key="item.execution_id"
          variant="flat"
          border
          class="mb-1 overflow-hidden premium-card bg-white"
        >
          <div class="pa-4">
            <!-- Cabecera Card -->
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="d-flex align-center gap-3 min-width-0">
                <VAvatar
                  :color="!item.employee_photo ? getEmployeeColor(item.employee_id) : undefined"
                  size="42"
                  variant="tonal"
                  class="rounded-lg font-weight-bold"
                >
                  <VImg v-if="item.employee_photo" :src="item.employee_photo" cover />
                  <span v-else class="text-sm font-weight-bold">{{ getEmployeeInitials(item.employee_name) }}</span>
                </VAvatar>
                <div class="d-flex flex-column min-width-0">
                  <span class="text-primary font-weight-bold text-xs uppercase mb-0.5">
                    ID #{{ item.employee_id }}
                  </span>
                  <h3 class="text-sm font-weight-semibold text-high-emphasis leading-tight truncate text-capitalize">
                    {{ formatCapitalize(item.employee_name) }}
                  </h3>
                  <span class="text-super-xs text-medium-emphasis font-weight-medium">
                    {{ formatCapitalize(item.activity_name) }}
                  </span>
                </div>
              </div>

              <!-- Acciones móvil -->
              <div class="d-flex gap-1 ms-2 flex-shrink-0">
                <IconBtn
                  size="x-small"
                  :color="canReview(item) ? 'primary' : 'info'"
                  @click="emit('review', item)"
                >
                  <VIcon :icon="canReview(item) ? 'tabler-shield-check' : 'tabler-eye'" size="16" />
                </IconBtn>
              </div>
            </div>

            <!-- Motivo Rechazo -->
            <div v-if="item.rejection_reason" class="pa-2.5 bg-error-lighten-5 rounded-lg border-error border-dashed mb-3">
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-alert-circle" size="14" color="error" />
                <span class="text-super-xs text-error font-weight-bold uppercase">Motivo: {{ item.rejection_reason }}</span>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <!-- Datos secundarios -->
            <div class="d-flex justify-space-between align-center mb-3">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled font-weight-bold uppercase">Fecha Límite</span>
                <span class="text-xs font-weight-medium mt-0.5 text-medium-emphasis tabular-nums">
                  {{ formatDate(item.due_date) }}
                </span>
              </div>
              <div class="d-flex flex-column align-end">
                <span class="text-super-xs text-disabled font-weight-bold uppercase me-1">Estado</span>
                <VChip :color="getStatusColor(item.status)" size="x-small" variant="tonal" class="font-weight-bold text-uppercase mt-0.5 rounded px-2">
                  {{ item.status }}
                </VChip>
              </div>
            </div>

            <div v-if="item.photo" class="mb-2">
              <span class="text-super-xs font-weight-bold text-disabled uppercase d-block mb-1">Evidencia Fotográfica</span>
              <VImg :src="getPhotoUrl(item.photo)" cover height="140" class="rounded-lg border bg-light shadow-sm" />
            </div>
            
            <div v-if="item.approved_by && item.status === 'Completada'" class="d-flex align-center justify-end gap-1 mt-2">
              <VIcon icon="tabler-user-check" size="13" color="success" />
              <span class="text-super-xs text-success font-weight-bold">
                Aprobado por: {{ item.approved_by }}
              </span>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="d-flex justify-center mt-4 pb-2">
        <AppMobilePagination
          :page="props.page"
          :items-per-page="props.itemsPerPage"
          :total-items="props.totalRecords"
          :loading="props.loading"
          @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.bg-light {
  background-color: #f8fafc !important;
}

.premium-card {
  border-radius: 12px !important;
  transition: transform 0.2s ease;
}

.premium-card:active {
  transform: scale(0.98);
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.leading-tight {
  line-height: 1.25 !important;
}

.min-width-0 {
  min-width: 0;
}

.bg-error-lighten-5 {
  background-color: rgba(var(--v-theme-error), 0.04);
}

.border-dashed {
  border-style: dashed !important;
}
</style>
