<script setup>
import { computed } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  employeeCleanings: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits([
  "update:options",
  "view-activities",
  "edit-assignment",
  "delete-assignment",
]);

const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "employee_id", sortable: true, width: "80px" },
  { title: "Empleado", key: "employee_name", sortable: true },
  { title: "Identificación", key: "identification", sortable: false, width: "200px" },
  { title: "Actividades", key: "activities_count", sortable: true, align: "center", width: "150px" },
  { title: "Acciones", key: "actions", sortable: false, align: "center", width: "150px" },
];

const getInitials = (name) => {
  if (!name) return "N/A";
  return name.trim().split(/\s+/).map((n) => n[0]).join("").toUpperCase().substring(0, 2);
};

const getAvatarColor = (id) => {
  const colors = ["primary", "secondary", "success", "info", "warning", "error", "purple", "amber"];
  return colors[id % colors.length];
};
</script>

<template>
  <div class="employee-cleaning-table-container">
    <!-- Vista de Escritorio -->
    <VCard v-if="!mobile" class="border shadow-sm overflow-hidden bg-surface">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.employeeCleanings"
        :items-length="props.totalRecords"
        :loading="props.loading"
        class="premium-table text-no-wrap"
        density="compact"
        @update:options="(options) => emit('update:options', options)"
      >
        <!-- ID -->
        <template #item.employee_id="{ item }">
          <span class="font-weight-black text-primary tabular-nums text-xs uppercase">
            {{ item.employee_id }}
          </span>
        </template>

        <!-- Empleado -->
        <template #item.employee_name="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar :color="getAvatarColor(item.employee_id)" size="34" variant="tonal" class="rounded">
              <VImg v-if="item.photo_url" :src="item.photo_url" cover />
              <span v-else class="text-super-xs font-weight-black">{{ getInitials(item.employee_name) }}</span>
            </VAvatar>
            <div class="d-flex flex-column truncate">
              <span class="text-sm font-weight-black text-high-emphasis leading-tight text-uppercase">
                {{ item.employee_name }}
              </span>
              <span class="text-super-xs font-weight-black uppercase mt-1" :class="item.is_active ? 'text-success' : 'text-error'">
                {{ item.is_active ? 'ESTADO: ACTIVO' : 'ESTADO: INACTIVO' }}
              </span>
            </div>
          </div>
        </template>

        <!-- Identificación -->
        <template #item.identification="{ item }">
          <span class="text-xs font-weight-black text-medium-emphasis tabular-nums uppercase">
            {{ item.identification || 'N/A' }}
          </span>
        </template>

        <!-- Actividades -->
        <template #item.activities_count="{ item }">
          <VChip
            size="x-small"
            :color="item.activities_count > 0 ? 'success' : 'surface-variant'"
            class="font-weight-black text-uppercase px-2 rounded"
            variant="tonal"
          >
            {{ item.activities_count }} ASIGNADAS
          </VChip>
        </template>

        <!-- Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-1">
            <IconBtn size="small" color="info" variant="tonal" class="rounded" @click="emit('view-activities', item)">
              <VIcon icon="tabler-eye" size="18" />
            </IconBtn>
            <IconBtn size="small" color="warning" variant="tonal" class="rounded" @click="emit('edit-assignment', item)">
              <VIcon icon="tabler-edit" size="18" />
            </IconBtn>
            <VMenu v-if="item.cleaning_activities.length > 0" location="bottom end">
              <template #activator="{ props: menuProps }">
                <IconBtn v-bind="menuProps" size="small" color="error" variant="tonal" class="rounded">
                  <VIcon icon="tabler-trash" size="18" />
                </IconBtn>
              </template>
              <VList density="compact" class="rounded-lg py-1 border shadow-lg">
                <VListItem
                  v-for="act in item.cleaning_activities"
                  :key="act.id"
                  @click="emit('delete-assignment', item.employee_id, act.id)"
                >
                  <template #prepend>
                    <VIcon icon="tabler-trash" size="16" color="error" class="me-2" />
                  </template>
                  <VListItemTitle class="text-xs font-weight-black text-uppercase">{{ act.name }}</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
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
        <VCol v-for="item in props.employeeCleanings" :key="item.employee_id" cols="12">
          <VCard class="rounded-lg border shadow-sm mb-4 overflow-hidden">
            <div class="pa-4">
              <div class="d-flex justify-space-between align-start mb-4">
                <div class="d-flex align-center gap-3 min-width-0">
                  <VAvatar :color="getAvatarColor(item.employee_id)" size="40" variant="tonal" class="rounded">
                    <VImg v-if="item.photo_url" :src="item.photo_url" cover />
                    <span v-else class="text-sm font-weight-black">{{ getInitials(item.employee_name) }}</span>
                  </VAvatar>
                  <div class="d-flex flex-column min-width-0">
                    <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate">
                      {{ item.employee_name }}
                    </h3>
                    <span class="text-super-xs text-primary mt-1 font-weight-black uppercase">
                      ID: #{{ item.employee_id }} • {{ item.identification || 'SIN DNI' }}
                    </span>
                  </div>
                </div>
                <div class="d-flex gap-1">
                  <IconBtn size="small" color="info" variant="tonal" class="rounded" @click="emit('view-activities', item)">
                    <VIcon icon="tabler-eye" size="18" />
                  </IconBtn>
                  <IconBtn size="small" color="warning" variant="tonal" class="rounded" @click="emit('edit-assignment', item)">
                    <VIcon icon="tabler-edit" size="18" />
                  </IconBtn>
                  <VMenu v-if="item.cleaning_activities && item.cleaning_activities.length > 0" location="bottom end">
                    <template #activator="{ props: menuProps }">
                      <IconBtn v-bind="menuProps" size="small" color="error" variant="tonal" class="rounded">
                        <VIcon icon="tabler-trash" size="18" />
                      </IconBtn>
                    </template>
                    <VList density="compact" class="rounded-lg py-1 border shadow-lg">
                      <VListItem
                        v-for="act in item.cleaning_activities"
                        :key="act.id"
                        @click="emit('delete-assignment', item.employee_id, act.id)"
                      >
                        <template #prepend>
                          <VIcon icon="tabler-trash" size="16" color="error" class="me-2" />
                        </template>
                        <VListItemTitle class="text-xs font-weight-black text-uppercase">{{ act.name }}</VListItemTitle>
                      </VListItem>
                    </VList>
                  </VMenu>
                </div>
              </div>

              <VDivider class="my-4 border-opacity-10" />

              <div class="d-flex justify-space-between align-center">
                <div class="d-flex flex-column">
                  <span class="text-super-xs text-disabled uppercase font-weight-black">Estado</span>
                  <span class="text-xs font-weight-black mt-1 uppercase" :class="item.is_active ? 'text-success' : 'text-error'">
                    {{ item.is_active ? 'ACTIVO' : 'INACTIVO' }}
                  </span>
                </div>
                <div class="d-flex flex-column align-end">
                  <span class="text-super-xs text-disabled uppercase font-weight-black">Actividades</span>
                  <VChip
                    size="x-small"
                    :color="item.activities_count > 0 ? 'success' : 'surface-variant'"
                    class="font-weight-black text-uppercase mt-1 rounded"
                    variant="tonal"
                  >
                    {{ item.activities_count }} ASIGNADAS
                  </VChip>
                </div>
              </div>
            </div>
          </VCard>
        </VCol>
      </VRow>

      <div v-if="props.employeeCleanings.length === 0 && !props.loading" class="text-center pa-12">
        <VIcon icon="tabler-package-off" size="64" class="text-disabled mb-4 opacity-20" />
        <p class="text-sm uppercase font-weight-black text-disabled">No se encontraron empleados</p>
      </div>

      <!-- Paginación Móvil -->
      <div v-if="props.totalRecords > props.itemsPerPage" class="mt-6 d-flex justify-center">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
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

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.leading-tight {
  line-height: 1.25;
}

.leading-none {
  line-height: 1;
}

.uppercase {
  text-transform: uppercase;
}
</style>
