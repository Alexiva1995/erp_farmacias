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
  { title: "ID", key: "employee_id", sortable: true },
  { title: "Empleado", key: "employee_name", sortable: true, width: "35%" },
  { title: "Identificación", key: "identification", sortable: false, width: "20%" },
  { title: "Actividades", key: "activities_count", sortable: true, align: "center" },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const getInitials = (name) => {
  if (!name) return "N/A";
  return name.split(" ").map((n) => n[0]).join("").toUpperCase().substring(0, 2);
};

const getAvatarColor = (index) => {
  const colors = ["primary", "secondary", "success", "info", "warning", "error"];
  return colors[index % colors.length];
};
</script>

<template>
  <VCard class="border-0 shadow-sm overflow-hidden">
    <!-- Vista de Escritorio -->
    <VDataTableServer
      v-if="!mobile"
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
      <template #item.employee_id="{ item }">
        <span class="text-xs font-weight-black text-disabled">#{{ item.employee_id }}</span>
      </template>

      <template #item.employee_name="{ item }">
        <div class="d-flex align-center gap-3 py-2">
          <VAvatar :color="getAvatarColor(item.employee_id)" size="34" variant="tonal" class="rounded">
            <span class="text-super-xs font-weight-black">{{ getInitials(item.employee_name) }}</span>
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-xs font-weight-black text-high-emphasis leading-tight">
              {{ item.employee_name }}
            </span>
            <span class="text-super-xs font-weight-bold mt-1 uppercase" :class="item.is_active ? 'text-success' : 'text-error'">
              {{ item.is_active ? "Activo" : "Inactivo" }}
            </span>
          </div>
        </div>
      </template>

      <template #item.identification="{ item }">
        <span class="text-xs text-medium-emphasis font-weight-black tabular-nums">
          {{ item.identification || "N/A" }}
        </span>
      </template>

      <template #item.activities_count="{ item }">
        <VChip
          :color="item.activities_count > 0 ? 'success' : 'surface-variant'"
          size="x-small"
          variant="flat"
          class="font-weight-black rounded px-3"
          style="color: white !important; min-inline-size: 36px;"
        >
          {{ item.activities_count }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-1 justify-center">
          <VTooltip text="Ver Actividades" location="top">
            <template #activator="{ props: tp }">
              <VBtn v-bind="tp" icon="tabler-eye" variant="text" color="info" size="32" class="rounded-lg" @click="emit('view-activities', item)" />
            </template>
          </VTooltip>

          <VTooltip text="Editar Actividades" location="top">
            <template #activator="{ props: tp }">
              <VBtn v-bind="tp" icon="tabler-edit" variant="text" color="warning" size="32" class="rounded-lg" @click="emit('edit-assignment', item)" />
            </template>
          </VTooltip>

          <VMenu v-if="item.cleaning_activities.length > 0">
            <template #activator="{ props: menuProps }">
              <VTooltip text="Eliminar Actividad" location="top">
                <template #activator="{ props: tp }">
                  <VBtn v-bind="{ ...menuProps, ...tp }" icon="tabler-trash" variant="text" color="error" size="32" class="rounded-lg" />
                </template>
              </VTooltip>
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
                <VListItemTitle class="text-xs font-weight-black">{{ act.name }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </div>
      </template>

      <template #bottom>
        <VDivider class="opacity-10" />
        <div class="d-flex justify-end pa-2">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
            size="small"
            @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
          />
        </div>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil -->
    <div v-else class="pa-4 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-4 rounded" />

      <div v-if="props.employeeCleanings.length === 0 && !props.loading" class="text-center pa-10">
        <VIcon icon="tabler-clipboard-off" size="48" class="text-disabled mb-2 opacity-20" />
        <p class="text-disabled text-sm uppercase font-weight-black">No se encontraron empleados</p>
      </div>

      <VRow>
        <VCol v-for="item in props.employeeCleanings" :key="item.employee_id" cols="12">
          <VCard class="border-0 shadow-sm overflow-hidden">
            <div class="pa-4">
              <div class="d-flex align-center gap-3 mb-4">
                <VAvatar :color="getAvatarColor(item.employee_id)" size="44" variant="tonal" class="rounded">
                  <span class="text-body-1 font-weight-black">{{ getInitials(item.employee_name) }}</span>
                </VAvatar>
                <div class="d-flex flex-column flex-grow-1">
                  <span class="text-sm font-weight-black text-high-emphasis leading-tight">
                    {{ item.employee_name }}
                  </span>
                  <span class="text-super-xs text-disabled mt-1 font-weight-bold">
                    ID: #{{ item.employee_id }} • {{ item.identification || 'Sin DNI' }}
                  </span>
                </div>
              </div>

              <VDivider class="mb-4 opacity-10" />

              <div class="d-flex align-center justify-space-between mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase text-no-wrap">Actividades Asignadas</span>
                <VChip size="x-small" color="success" variant="flat" class="rounded px-2 font-weight-black">
                  {{ item.activities_count }}
                </VChip>
              </div>

              <div class="d-flex gap-2">
                <VBtn block size="small" variant="tonal" color="info" class="rounded-lg font-weight-black" @click="emit('view-activities', item)">
                   <VIcon start icon="tabler-eye" size="16" /> Ver
                </VBtn>
                <VBtn block size="small" variant="tonal" color="warning" class="rounded-lg font-weight-black" @click="emit('edit-assignment', item)">
                   <VIcon start icon="tabler-edit" size="16" /> Editar
                </VBtn>
                <VBtn v-if="item.cleaning_activities.length > 0" icon="tabler-trash" variant="tonal" color="error" size="32" class="rounded-lg">
                  <VIcon icon="tabler-trash" size="18" />
                  <VMenu activator="parent" transition="scale-transition">
                    <VList density="compact" class="rounded-lg py-1 border shadow-lg">
                      <VListItem v-for="act in item.cleaning_activities" :key="act.id" @click="emit('delete-assignment', item.employee_id, act.id)">
                        <template #prepend><VIcon icon="tabler-trash" size="16" color="error" class="me-2" /></template>
                        <VListItemTitle class="text-xs font-weight-black">Eliminar {{ act.name }}</VListItemTitle>
                      </VListItem>
                    </VList>
                  </VMenu>
                </VBtn>
              </div>
            </div>
          </VCard>
        </VCol>
      </VRow>

      <div v-if="props.totalRecords > props.itemsPerPage" class="mt-6 d-flex justify-center">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
          size="small"
          @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
        />
      </div>
    </div>
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

.bg-light {
  background-color: rgba(var(--v-theme-on-surface), 0.015);
}

.leading-tight {
  line-height: 1.2;
}
</style>
