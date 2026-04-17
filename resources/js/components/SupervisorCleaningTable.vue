<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  executions: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "review"]);

const { mobile } = useDisplay();

const headers = [
  { title: "EMPLEADO", key: "employee_name", sortable: true },
  { title: "ACTIVIDAD", key: "activity_name", sortable: true },
  { title: "FRECUENCIA", key: "frequency", sortable: true, width: "120px" },
  { title: "ESTADO", key: "status", sortable: true, width: "120px", align: "center" },
  { title: "LÍMITE", key: "due_date", sortable: true, width: "140px" },
  { title: "COMPLETADA", key: "completed_date", sortable: true, width: "160px" },
  { title: "FOTO", key: "photo", sortable: false, align: "center", width: "80px" },
  { title: "ACCIONES", key: "actions", sortable: false, align: "center", width: "100px" },
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
  <div class="supervisor-cleaning-table-container">
    <!-- Vista de Escritorio -->
    <VCard class="border shadow-sm overflow-hidden">
      <VDataTableServer
        v-if="!mobile"
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
              <VImg v-if="item.employee_photo" :src="item.employee_photo" cover />
              <span v-else class="text-super-xs font-weight-black">{{ getEmployeeInitials(item.employee_name) }}</span>
            </VAvatar>
            <div class="d-flex flex-column truncate">
              <span class="text-sm font-weight-black text-high-emphasis leading-tight text-uppercase">
                {{ item.employee_name }}
              </span>
              <span class="text-super-xs font-weight-black uppercase mt-1 text-primary">
                ID: #{{ item.employee_id }}
              </span>
            </div>
          </div>
        </template>

        <!-- Actividad -->
        <template #item.activity_name="{ item }">
          <div class="d-flex flex-column py-1 truncate">
            <span class="text-sm font-weight-black text-high-emphasis leading-tight text-uppercase">
              {{ item.activity_name }}
            </span>
            <span v-if="item.rejection_reason" class="text-super-xs text-error mt-1 font-weight-black uppercase">
              MOTIVO: {{ item.rejection_reason }}
            </span>
            <span v-else class="text-super-xs text-disabled font-weight-black uppercase truncate mt-1" style="max-width: 200px">
              {{ item.description || 'SIN DESCRIPCIÓN' }}
            </span>
          </div>
        </template>

        <!-- Frecuencia -->
        <template #item.frequency="{ item }">
          <VChip :color="getFrequencyColor(item.frequency)" size="x-small" variant="tonal" class="rounded font-weight-black text-uppercase px-2">
            {{ item.frequency }}
          </VChip>
        </template>

        <!-- Estado -->
        <template #item.status="{ item }">
          <VChip :color="getStatusColor(item.status)" size="x-small" variant="tonal" class="rounded font-weight-black text-uppercase px-2">
            <VIcon :icon="getStatusIcon(item.status)" size="12" class="me-1" />
            {{ item.status }}
          </VChip>
        </template>

        <template #item.due_date="{ item }">
          <span class="text-xs font-weight-black text-medium-emphasis tabular-nums uppercase">
            {{ formatDate(item.due_date) }}
          </span>
        </template>

        <template #item.completed_date="{ item }">
          <div class="d-flex flex-column py-1">
            <span class="text-xs font-weight-black text-disabled tabular-nums uppercase">
              {{ formatDateTime(item.completed_date) }}
            </span>
            <span v-if="item.approved_by && item.status === 'Completada'" class="text-super-xs text-success mt-1 font-weight-black uppercase">
              <VIcon icon="tabler-user-check" size="10" class="me-1" />
              POR: {{ item.approved_by }}
            </span>
          </div>
        </template>

        <!-- Foto -->
        <template #item.photo="{ item }">
          <div v-if="item.photo" class="d-flex justify-center">
            <VMenu open-on-hover transition="scale-transition">
              <template #activator="{ props: menuProps }">
                <IconBtn v-bind="menuProps" size="small" :color="item.status === 'Procesada' ? 'primary' : 'secondary'" variant="tonal" class="rounded">
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
          <div class="d-flex justify-center">
            <IconBtn size="small" :color="canReview(item) ? 'primary' : 'info'" variant="tonal" class="rounded" @click="emit('review', item)">
              <VIcon :icon="canReview(item) ? 'tabler-shield-check' : 'tabler-eye'" size="18" />
            </IconBtn>
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

      <!-- Vista Móvil: Cards Premium -->
      <div v-else class="pa-4 bg-light">
        <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-4 rounded" />
        
        <div v-if="props.executions.length === 0 && !props.loading" class="text-center pa-12">
          <VIcon icon="tabler-search-off" size="64" class="text-disabled mb-4 opacity-20" />
          <p class="text-sm uppercase font-weight-black text-disabled">No hay actividades para supervisar</p>
        </div>

        <VRow>
          <VCol v-for="item in props.executions" :key="item.execution_id" cols="12">
            <VCard class="rounded-lg border shadow-sm mb-4 overflow-hidden">
              <div class="pa-4">
                <div class="d-flex justify-space-between align-start mb-4">
                  <div class="d-flex align-center gap-3 min-width-0">
                    <VAvatar :color="getEmployeeColor(item.employee_id)" size="40" variant="tonal" class="rounded">
                      <VImg v-if="item.employee_photo" :src="item.employee_photo" cover />
                      <span v-else class="text-sm font-weight-black text-uppercase">{{ getEmployeeInitials(item.employee_name) }}</span>
                    </VAvatar>
                    <div class="d-flex flex-column min-width-0">
                      <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate">
                        {{ item.employee_name }}
                      </h3>
                      <span class="text-super-xs text-primary mt-1 font-weight-black uppercase">
                        ID: #{{ item.employee_id }} • {{ item.activity_name }}
                      </span>
                    </div>
                  </div>
                  <div class="d-flex gap-1">
                    <IconBtn
                      size="small"
                      :color="canReview(item) ? 'primary' : 'info'"
                      variant="tonal"
                      class="rounded"
                      @click="emit('review', item)"
                    >
                      <VIcon :icon="canReview(item) ? 'tabler-shield-check' : 'tabler-eye'" size="18" />
                    </IconBtn>
                  </div>
                </div>

                <div v-if="item.rejection_reason" class="pa-3 bg-error-lighten-5 rounded-lg border-error border-dashed mb-4">
                   <div class="d-flex align-center gap-2">
                    <VIcon icon="tabler-alert-circle" size="16" color="error" />
                    <span class="text-super-xs text-error font-weight-black uppercase">Motivo Rechazo: {{ item.rejection_reason }}</span>
                  </div>
                </div>

                <VDivider class="my-4 border-opacity-10" />

                <div class="d-flex justify-space-between align-center mb-4">
                  <div class="d-flex flex-column">
                    <span class="text-super-xs text-disabled font-weight-black uppercase ms-1">Fecha Límite</span>
                    <span class="text-xs font-weight-black mt-1 text-medium-emphasis uppercase tabular-nums">
                      {{ formatDate(item.due_date) }}
                    </span>
                  </div>
                  <div class="d-flex flex-column align-end">
                    <span class="text-super-xs text-disabled font-weight-black uppercase me-1">Estado</span>
                    <VChip :color="getStatusColor(item.status)" size="x-small" variant="tonal" class="font-weight-black text-uppercase mt-1 rounded px-2">
                      {{ item.status }}
                    </VChip>
                  </div>
                </div>

                <div v-if="item.photo" class="mb-4">
                  <span class="text-super-xs font-weight-black text-disabled uppercase ms-1 d-block mb-2">Evidencia Fotográfica</span>
                  <VImg :src="getPhotoUrl(item.photo)" cover height="160" class="rounded-lg border bg-light shadow-sm" />
                </div>
                
                <div v-if="item.approved_by && item.status === 'Completada'" class="d-flex align-center justify-end gap-1 mt-2">
                  <VIcon icon="tabler-user-check" size="14" color="success" />
                  <span class="text-super-xs text-success font-weight-black uppercase">
                    Aprobado por: {{ item.approved_by }}
                  </span>
                </div>
              </div>
            </VCard>
          </VCol>
        </VRow>

        <!-- Paginación Móvil -->
        <div v-if="props.totalRecords > props.itemsPerPage" class="d-flex justify-center mt-6">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
            size="small"
            @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
          />
        </div>
      </div>
    </VCard>
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

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
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

:deep(.v-data-table-footer) {
  display: none !important;
}
</style>
