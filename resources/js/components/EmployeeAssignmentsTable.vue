<script setup>
import { computed } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  assignments: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  hideDaily: { type: Boolean, default: false },
});

const emit = defineEmits(["update:options", "delete-assignment", "update:hideDaily"]);

const { mobile } = useDisplay();

const headers = [
  { title: "Empleado", key: "employee_name", sortable: true },
  { title: "Actividad", key: "activity_name", sortable: true },
  { title: "Frecuencia", key: "frequency", sortable: true },
  { title: "Día Sugerido", key: "day_of_week", sortable: true },
  { title: "Estado", key: "status", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center", width: "100px" },
];

const getFrequencyColor = (freq) => {
  switch (freq) {
    case "Diaria": return "success";
    case "Semanal": return "info";
    case "Quincenal": return "warning";
    case "Mensual": return "error";
    default: return "secondary";
  }
};
</script>

<template>
  <div class="assignments-table-container mt-8">
    <VCard class="border shadow-sm overflow-hidden bg-surface">
      <VCardTitle class="px-4 py-4 d-flex align-center justify-space-between flex-wrap gap-4">
        <div class="d-flex align-center gap-2">
          <VAvatar color="primary" variant="tonal" size="32" class="rounded">
            <VIcon icon="tabler-list-details" size="20" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-sm font-weight-black uppercase leading-none">Resumen de Asignaciones</span>
            <span class="text-super-xs text-disabled font-weight-black uppercase mt-1">Lista plana de todas las tareas vinculadas</span>
          </div>
        </div>

        <div class="d-flex align-center gap-4">
          <VSwitch
            :model-value="props.hideDaily"
            label="OCULTAR DIARIAS"
            density="compact"
            hide-details
            color="primary"
            class="font-weight-black text-xs"
            @update:model-value="emit('update:hideDaily', $event)"
          />
        </div>
      </VCardTitle>

      <VDivider class="opacity-10" />

      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.assignments"
        :items-length="props.totalRecords"
        :loading="props.loading"
        class="premium-table text-no-wrap"
        density="compact"
        @update:options="(options) => emit('update:options', options)"
      >
        <!-- Empleado -->
        <template #item.employee_name="{ item }">
          <div class="d-flex flex-column py-1">
            <span class="text-xs font-weight-black text-high-emphasis text-uppercase">
              {{ item.employee_name }} {{ item.employee_last_name }}
            </span>
          </div>
        </template>

        <!-- Actividad -->
        <template #item.activity_name="{ item }">
          <span class="text-xs font-weight-black text-medium-emphasis text-uppercase">
            {{ item.activity_name }}
          </span>
        </template>

        <!-- Frecuencia -->
        <template #item.frequency="{ item }">
          <VChip :color="getFrequencyColor(item.frequency)" size="x-small" variant="tonal" label class="rounded font-weight-black text-uppercase">
            <VIcon icon="tabler-repeat" size="10" class="me-1" />
            {{ item.frequency }}
          </VChip>
        </template>

        <!-- Día -->
        <template #item.day_of_week="{ item }">
          <VChip v-if="item.day_of_week" color="info" size="x-small" variant="flat" label class="rounded font-weight-black text-uppercase">
            <VIcon icon="tabler-calendar" size="10" class="me-1" />
            {{ item.day_of_week }}
          </VChip>
          <span v-else class="text-super-xs text-disabled font-weight-black uppercase">CUALQUIER DÍA</span>
        </template>

        <!-- Estado -->
        <template #item.status="{ item }">
          <VChip size="x-small" variant="tonal" :color="item.status === 'Pendiente' ? 'warning' : 'success'" class="rounded font-weight-black text-uppercase">
            {{ item.status }}
          </VChip>
        </template>

        <!-- Acciones -->
        <template #item.actions="{ item }">
          <IconBtn size="small" color="error" variant="tonal" class="rounded" @click="emit('delete-assignment', item.employee_id, item.cleaning_activity_id)">
            <VIcon icon="tabler-trash" size="18" />
          </IconBtn>
        </template>

        <template #bottom>
          <VDivider class="opacity-10" />
          <div class="d-flex align-center justify-space-between pa-2">
            <span class="text-super-xs text-disabled font-weight-black uppercase ms-2 tabular-nums">
              Mostrando {{ props.assignments.length }} de {{ props.totalRecords }} registros
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
  </div>
</template>

<style scoped>
:deep(.premium-table) {
  background: transparent !important;
  thead th {
    background: white !important;
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
    font-size: 0.7rem !important;
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
      padding-block: 10px !important;
      border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
    }
  }
}
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}
.uppercase {
  text-transform: uppercase;
}
</style>
