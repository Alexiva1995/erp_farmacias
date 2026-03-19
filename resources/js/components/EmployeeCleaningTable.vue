<script setup>
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
  { title: "ID Empleado", key: "employee_id", sortable: true },
  { title: "Empleado", key: "employee_name", sortable: true, width: "35%" },
  {
    title: "Identificación",
    key: "identification",
    sortable: false,
    width: "20%",
  },
  { title: "Total Actividades", key: "activities_count", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const getInitials = (name) => {
  if (!name) return "N/A";
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .substring(0, 2);
};

const getAvatarColor = (index) => {
  const colors = [
    "primary",
    "secondary",
    "success",
    "info",
    "warning",
    "error",
  ];
  return colors[index % colors.length];
};

const getStatusColor = (status) => {
  const statusColors = {
    Pendiente: "warning",
    Completada: "success",
    Cancelada: "error",
  };
  return statusColors[status] || "default";
};

const getStatusIcon = (status) => {
  const statusIcons = {
    Pendiente: "tabler-clock",
    Completada: "tabler-check",
    Cancelada: "tabler-x",
  };
  return statusIcons[status] || "tabler-circle";
};
</script>

<template>
  <div>
    <!-- Vista de Escritorio (Desktop Table) -->
    <VCard v-if="!mobile" class="rounded-xl border-0 shadow-sm overflow-hidden">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.employeeCleanings"
        :items-length="props.totalRecords"
        :loading="props.loading"
        class="text-no-wrap premium-table"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.employee_id="{ item }">
          <span class="text-xs font-weight-black text-disabled">#{{ item.employee_id }}</span>
        </template>

        <template #item.employee_name="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              :color="getAvatarColor(item.employee_id)"
              size="38"
              variant="tonal"
              class="rounded-lg"
            >
              <span class="text-xs font-weight-black">{{ getInitials(item.employee_name) }}</span>
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-sm font-weight-bold text-high-emphasis text-capitalize">
                {{ item.employee_name }}
              </span>
              <span class="text-super-xs font-weight-medium" :class="item.is_active ? 'text-success' : 'text-error'">
                {{ item.is_active ? "Activo" : "Inactivo" }}
              </span>
            </div>
          </div>
        </template>

        <template #item.identification="{ item }">
          <span class="text-xs font-weight-medium text-medium-emphasis">
            {{ item.identification || "N/A" }}
          </span>
        </template>

        <template #item.activities_count="{ item }">
          <VChip
            :color="item.activities_count > 0 ? 'success' : 'surface-variant'"
            size="small"
            variant="flat"
            class="font-weight-black rounded px-3"
            style="min-inline-size: 40px; color: white !important;"
          >
            {{ item.activities_count }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-center">
            <VBtn
              icon
              variant="text"
              size="32"
              color="info"
              class="rounded-lg"
              @click="emit('view-activities', item)"
            >
              <VIcon icon="tabler-eye" size="18" />
              <VTooltip activator="parent" location="top">Ver Actividades</VTooltip>
            </VBtn>

            <VBtn
              icon
              variant="text"
              size="32"
              color="warning"
              class="rounded-lg"
              @click="emit('edit-assignment', item)"
            >
              <VIcon icon="tabler-edit" size="18" />
              <VTooltip activator="parent" location="top">Editar Actividades</VTooltip>
            </VBtn>

            <VMenu transition="scale-transition">
              <template #activator="{ props: menuProps }">
                <VBtn
                  v-bind="menuProps"
                  icon
                  variant="text"
                  size="32"
                  color="error"
                  class="rounded-lg"
                  :disabled="item.cleaning_activities.length === 0"
                >
                  <VIcon icon="tabler-trash" size="18" />
                  <VTooltip activator="parent" location="top">Eliminar Actividad</VTooltip>
                </VBtn>
              </template>
              <VList class="rounded-lg shadow-lg border-0 pa-2">
                <VListItem
                  v-for="activity in item.cleaning_activities"
                  :key="activity.id"
                  class="rounded-md mb-1"
                  color="error"
                  @click="emit('delete-assignment', item.employee_id, activity.id)"
                >
                  <template #prepend>
                    <VIcon icon="tabler-trash" size="18" class="me-3" />
                  </template>
                  <VListItemTitle class="text-xs font-weight-bold">Eliminar {{ activity.name }}</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </div>
        </template>

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
              class="premium-pagination"
              @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
            />
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista Móvil (Premium Cards) -->
    <div v-else class="d-flex flex-column gap-4">
      <div v-if="props.loading" class="d-flex justify-center pa-8">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <template v-else-if="props.employeeCleanings.length > 0">
        <VCard
          v-for="(item, index) in props.employeeCleanings"
          :key="item.employee_id"
          class="rounded-xl border-0 shadow-md premium-card overflow-hidden"
        >
          <div class="premium-card-decoration"></div>
          
          <VCardText class="pa-5">
            <!-- Cabecera de la Tarjeta -->
            <div class="d-flex align-center gap-3 mb-4">
              <VAvatar
                :color="getAvatarColor(item.employee_id)"
                size="44"
                variant="tonal"
                class="rounded-lg shadow-sm"
              >
                <span class="text-sm font-weight-black">{{ getInitials(item.employee_name) }}</span>
              </VAvatar>
              <div class="d-flex flex-column flex-grow-1">
                <div class="d-flex align-center justify-space-between">
                  <span class="text-sm font-weight-black text-capitalize leading-none truncate-text">
                    {{ item.employee_name }}
                  </span>
                  <span class="text-super-xs font-weight-black text-disabled">#{{ item.employee_id }}</span>
                </div>
                <div class="d-flex align-center gap-1 mt-1">
                  <VIcon :icon="item.is_active ? 'tabler-circle-check-filled' : 'tabler-circle-x-filled'" size="12" :color="item.is_active ? 'success' : 'error'" />
                  <span class="text-super-xs font-weight-bold uppercase" :class="item.is_active ? 'text-success' : 'text-error'">
                    {{ item.is_active ? "Activo" : "Inactivo" }}
                  </span>
                </div>
              </div>
            </div>

            <VDivider class="mb-4 opacity-10" />

            <!-- Información Detallada -->
            <div class="d-flex flex-column gap-3 mb-4">
              <div class="d-flex align-center justify-space-between">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-id" size="16" color="disabled" />
                  <span class="text-xs text-disabled font-weight-medium">Identificación</span>
                </div>
                <span class="text-xs font-weight-black">{{ item.identification || 'N/A' }}</span>
              </div>
              
              <div class="d-flex align-center justify-space-between">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-list-check" size="16" color="disabled" />
                  <span class="text-xs text-disabled font-weight-medium">Actividades</span>
                </div>
                <VChip
                  :color="item.activities_count > 0 ? 'success' : 'surface-variant'"
                  size="x-small"
                  variant="flat"
                  class="font-weight-black rounded px-2"
                  style="color: white !important;"
                >
                  {{ item.activities_count }}
                </VChip>
              </div>
            </div>

            <!-- Chips de Actividades (pequeña muestra) -->
            <div v-if="item.cleaning_activities.length > 0" class="d-flex flex-wrap gap-1 mb-4">
              <VChip
                v-for="act in item.cleaning_activities.slice(0, 3)"
                :key="act.id"
                size="super-xs"
                variant="tonal"
                color="secondary"
                class="rounded-sm font-weight-bold"
              >
                {{ act.name }}
              </VChip>
              <VChip v-if="item.cleaning_activities.length > 3" size="super-xs" variant="tonal" color="disabled" class="rounded-sm font-weight-bold">
                +{{ item.cleaning_activities.length - 3 }}
              </VChip>
            </div>

            <!-- Acciones -->
            <div class="d-flex gap-2">
              <VBtn
                variant="tonal"
                color="info"
                class="flex-grow-1 rounded-lg text-xs font-weight-black h-36"
                @click="emit('view-activities', item)"
              >
                <VIcon start icon="tabler-eye" size="16" />
                VER
              </VBtn>
              <VBtn
                variant="tonal"
                color="warning"
                class="flex-grow-1 rounded-lg text-xs font-weight-black h-36"
                @click="emit('edit-assignment', item)"
              >
                <VIcon start icon="tabler-edit" size="16" />
                EDITAR
              </VBtn>
              <VBtn
                icon
                variant="tonal"
                color="error"
                size="36"
                class="rounded-lg"
                :disabled="item.cleaning_activities.length === 0"
              >
                <VIcon icon="tabler-trash" size="18" />
                <VMenu activator="parent" transition="scale-transition">
                  <VList class="rounded-lg shadow-lg border-0 pa-2">
                    <VListItem
                      v-for="activity in item.cleaning_activities"
                      :key="activity.id"
                      class="rounded-md mb-1"
                      color="error"
                      @click="emit('delete-assignment', item.employee_id, activity.id)"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-trash" size="18" class="me-3" />
                      </template>
                      <VListItemTitle class="text-xs font-weight-bold">Eliminar {{ activity.name }}</VListItemTitle>
                    </VListItem>
                  </VList>
                </VMenu>
              </VBtn>
            </div>
          </VCardText>
        </VCard>

        <!-- Paginación Móvil -->
        <div class="d-flex justify-center mt-4">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
            size="small"
            rounded="circle"
            class="premium-pagination"
            @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
          />
        </div>
      </template>

      <VAlert v-else type="info" variant="tonal" class="rounded-xl">
        No se encontraron resultados
      </VAlert>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(.v-data-table-header) {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

.premium-table :deep(.v-data-table-header th) {
  height: 48px !important;
  font-size: 0.65rem !important;
  font-weight: 900 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05em !important;
  color: rgba(var(--v-theme-on-surface), 0.5) !important;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.leading-none {
  line-height: 1;
}

.premium-card {
  position: relative;
  transition: all 0.3s ease;
}

.premium-card:active {
  transform: scale(0.98);
}

.premium-card-decoration {
  position: absolute;
  top: 0;
  right: 0;
  width: 100px;
  height: 100px;
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.05) 0%, transparent 100%);
  border-radius: 0 0 0 100%;
}

.truncate-text {
  max-width: 150px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.h-36 {
  height: 36px !important;
}

.v-chip.v-chip--size-super-xs {
  --v-chip-size: 16px;
  font-size: 0.6rem;
  padding: 0 6px;
}

.premium-pagination :deep(.v-btn) {
  background-color: white !important;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
  box-shadow: 0 2px 4px rgba(0,0,0,0.02) !important;
}

.premium-pagination :deep(.v-pagination__item--active .v-btn) {
  background: rgb(var(--v-theme-primary)) !important;
  color: white !important;
  border: 0 !important;
}
</style>
