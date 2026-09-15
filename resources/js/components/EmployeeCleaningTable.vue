<script setup>
// Tabla de tareas/actividades por empleado — homologada al estándar canónico
import AppEmptyState from "@/components/AppEmptyState.vue";
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { useAbility } from "@casl/vue";

const { can } = useAbility();

const props = defineProps({
  employeeCleanings: { type: Array,   required: true },
  loading:           { type: Boolean, default: false },
  totalRecords:      { type: Number,  required: true },
  itemsPerPage:      { type: Number,  required: true },
  page:              { type: Number,  required: true },
});

const emit = defineEmits([
  "update:options",
  "view-activities",
  "edit-assignment",
  "delete-assignment",
]);

const headers = [
  { title: "ID",          key: "employee_id",      sortable: true,  width: "70px" },
  { title: "Empleado",    key: "employee_name",    sortable: true },
  { title: "Total",       key: "activities_count", sortable: true,  align: "center", width: "120px" },
  { title: "Acciones",    key: "actions",          sortable: false, align: "end" },
];

const getInitials = (name) => {
  if (!name) return "N/A";
  return name
    .trim()
    .split(/\s+/)
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .substring(0, 2);
};

const getAvatarColor = (id) => {
  const colors = ["primary", "secondary", "success", "info", "warning", "error", "purple", "amber"];
  return colors[id % colors.length];
};

const formatCapitalize = (str) => {
  if (!str) return "";
  return str.toLowerCase().replace(/(?:^|\s|-)\S/g, (char) => char.toUpperCase());
};
</script>

<template>
  <div class="employee-cleaning-table-container">
    <!-- Vista Desktop -->
    <div class="d-none d-md-block">
      <VCard border variant="flat">
        <VDataTableServer
          :headers="headers.filter(h => h.key !== 'actions' || can('manage', 'admin'))"
          :items-per-page="props.itemsPerPage"
          :items="props.employeeCleanings"
          :items-length="props.totalRecords"
          :loading="props.loading"
          :page="props.page"
          density="comfortable"
          @update:options="(options) => emit('update:options', options)"
        >
          <!-- Estado vacío -->
          <template #no-data>
            <AppEmptyState
              title="Sin actividades asignadas"
              message="No se encontraron empleados con tareas o actividades vinculadas."
              icon="tabler-clipboard-off"
            />
          </template>

          <!-- ID -->
          <template #item.employee_id="{ item }">
            <span class="font-weight-bold text-primary">{{ item.employee_id }}</span>
          </template>

          <!-- Empleado con avatar -->
          <template #item.employee_name="{ item }">
            <div class="d-flex align-center gap-3 py-1">
              <VAvatar
                :color="!item.photo_url ? getAvatarColor(item.employee_id) : undefined"
                size="34"
                variant="tonal"
                class="rounded-lg"
              >
                <VImg v-if="item.photo_url" :src="item.photo_url" cover />
                <span v-else class="text-xs font-weight-bold">{{ getInitials(item.employee_name) }}</span>
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-sm font-weight-medium text-high-emphasis leading-tight">
                  {{ item.employee_name }}
                </span>
                <span class="text-super-xs text-medium-emphasis font-weight-medium">
                  {{ item.is_active ? "Activo" : "Inactivo" }}
                </span>
              </div>
            </div>
          </template>

          <!-- Total Actividades -->
          <template #item.activities_count="{ item }">
            <VChip
              :color="item.activities_count > 0 ? 'success' : 'surface-variant'"
              size="x-small"
              variant="tonal"
              class="font-weight-bold rounded px-3"
            >
              {{ item.activities_count }}
            </VChip>
          </template>

          <!-- Acciones -->
          <template #item.actions="{ item }">
            <div class="d-flex justify-end gap-1">
              <IconBtn color="info" size="small" @click="emit('view-activities', item)">
                <VIcon icon="tabler-eye" size="18" />
                <VTooltip activator="parent">Ver control de actividades</VTooltip>
              </IconBtn>

              <template v-if="can('manage', 'admin')">
                <IconBtn color="primary" size="small" @click="emit('edit-assignment', item)">
                  <VIcon icon="tabler-edit" size="18" />
                  <VTooltip activator="parent">Editar asignación</VTooltip>
                </IconBtn>

                <VMenu v-if="item.cleaning_activities && item.cleaning_activities.length > 0" location="bottom end">
                  <template #activator="{ props: menuProps }">
                    <IconBtn v-bind="menuProps" color="error" size="small">
                      <VIcon icon="tabler-trash" size="18" />
                      <VTooltip activator="parent">Eliminar actividades</VTooltip>
                    </IconBtn>
                  </template>
                  <VList density="compact" class="rounded-lg py-1 border shadow-lg">
                    <VListItem
                      v-for="act in item.cleaning_activities"
                      :key="act.id"
                      @click="emit('delete-assignment', item.employee_id, act.id)"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-circle-x" size="16" color="error" class="me-2" />
                      </template>
                      <VListItemTitle class="text-xs font-weight-bold text-error">{{ formatCapitalize(act.name) }}</VListItemTitle>
                    </VListItem>
                  </VList>
                </VMenu>
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
        v-if="props.employeeCleanings.length === 0 && !props.loading"
        title="Sin asignaciones"
        message="No se encontraron empleados con tareas o actividades vinculadas."
        icon="tabler-clipboard-off"
      />

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.employeeCleanings"
          :key="item.employee_id"
          variant="flat"
          border
          class="mb-1 overflow-hidden premium-card bg-white"
        >
          <div class="pa-4">
            <!-- Cabecera -->
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="d-flex align-center gap-3 min-width-0">
                <VAvatar
                  :color="!item.photo_url ? getAvatarColor(item.employee_id) : undefined"
                  size="42"
                  variant="tonal"
                  class="rounded-lg"
                >
                  <VImg v-if="item.photo_url" :src="item.photo_url" cover />
                  <span v-else class="text-sm font-weight-bold">{{ getInitials(item.employee_name) }}</span>
                </VAvatar>
                <div class="d-flex flex-column min-width-0">
                  <span class="text-primary font-weight-black text-xs uppercase mb-0.5">
                    ID #{{ item.employee_id }}
                  </span>
                  <h3 class="text-sm font-weight-semibold text-high-emphasis leading-tight truncate">
                    {{ item.employee_name }}
                  </h3>
                  <span class="text-super-xs text-medium-emphasis font-weight-medium">
                    {{ item.identification || 'Sin DNI' }}
                  </span>
                </div>
              </div>

              <!-- Acciones móvil -->
              <div class="d-flex gap-1 ms-2 flex-shrink-0">
                <IconBtn size="x-small" color="info" @click="emit('view-activities', item)">
                  <VIcon icon="tabler-eye" size="16" />
                  <VTooltip activator="parent">Ver</VTooltip>
                </IconBtn>

                <template v-if="can('manage', 'admin')">
                  <IconBtn size="x-small" color="primary" @click="emit('edit-assignment', item)">
                    <VIcon icon="tabler-edit" size="16" />
                    <VTooltip activator="parent">Editar</VTooltip>
                  </IconBtn>
                  <VMenu v-if="item.cleaning_activities && item.cleaning_activities.length > 0" location="bottom end">
                    <template #activator="{ props: menuProps }">
                      <IconBtn v-bind="menuProps" size="x-small" color="error">
                        <VIcon icon="tabler-trash" size="16" />
                      </IconBtn>
                    </template>
                    <VList density="compact" class="rounded-lg py-1 border shadow-lg">
                      <VListItem
                        v-for="act in item.cleaning_activities"
                        :key="act.id"
                        @click="emit('delete-assignment', item.employee_id, act.id)"
                      >
                        <template #prepend>
                          <VIcon icon="tabler-circle-x" size="16" color="error" class="me-2" />
                        </template>
                        <VListItemTitle class="text-xs font-weight-bold text-error">{{ formatCapitalize(act.name) }}</VListItemTitle>
                      </VListItem>
                    </VList>
                  </VMenu>
                </template>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <!-- Resumen Total -->
            <div class="d-flex align-center justify-space-between">
              <span class="text-xs font-weight-bold text-medium-emphasis uppercase">Total Actividades Asignadas</span>
              <VChip size="x-small" color="success" variant="tonal" class="font-weight-bold rounded px-2">
                {{ item.activities_count }}
              </VChip>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Mobile Pagination -->
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

.gap-1 { gap: 4px !important; }
.gap-3 { gap: 12px !important; }
</style>
