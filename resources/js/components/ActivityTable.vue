<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  activities: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalActivities: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits([
  "update:options",
  "edit-activity",
  "delete-activity",
]);

const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "id", sortable: true, class: "font-weight-black text-super-xs" },
  { title: "ACTIVIDAD", key: "activity", sortable: true, width: "35%", class: "font-weight-black text-super-xs" },
  { title: "DESCRIPCIÓN", key: "description", sortable: false, width: "35%", class: "font-weight-black text-super-xs" },
  { title: "FRECUENCIA", key: "frequency", sortable: true, class: "font-weight-black text-super-xs text-center", align: 'center' },
  { title: "ACCIONES", key: "actions", sortable: false, align: "end", class: "font-weight-black text-super-xs text-right" },
];

const getFrequencyColor = (frequency) => {
  const colors = {
    Diaria: "error",
    Semanal: "warning",
    Bimestral: "info",
    Mensual: "primary",
    Trimestral: "secondary",
    Semestral: "success",
    Anual: "default",
  };
  return colors[frequency] || "default";
};

const getFrequencyIcon = (frequency) => {
  const icons = {
    Diaria: "tabler-refresh",
    Semanal: "tabler-calendar-event",
    Bimestral: "tabler-calendar-stats",
    Mensual: "tabler-calendar",
    Trimestral: "tabler-calendar-repeat",
    Semestral: "tabler-calendar-due",
    Anual: "tabler-calendar-star",
  };
  return icons[frequency] || "tabler-activity";
};
</script>

<template>
  <VCard class="overflow-hidden">
    <!-- Vista de Escritorio: Tabla Premium -->
    <VDataTableServer
      v-if="!mobile"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.activities"
      :items-length="props.totalActivities"
      :loading="props.loading"
      class="premium-table text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <span class="text-xs font-weight-black text-disabled tabular-nums">#{{ item.id }}</span>
      </template>

      <template #item.activity="{ item }">
        <div class="d-flex align-center gap-3 py-2">
          <VAvatar color="primary" variant="tonal" size="32" class="rounded font-weight-black text-super-xs">
            {{ item.activity.charAt(0).toUpperCase() }}
          </VAvatar>
          <span class="text-xs font-weight-black text-high-emphasis leading-tight">{{ item.activity }}</span>
        </div>
      </template>

      <template #item.description="{ item }">
        <div class="text-xs text-medium-emphasis text-truncate" style="max-inline-size: 300px;">
          {{ item.description || 'Sin descripción' }}
        </div>
      </template>

      <template #item.frequency="{ item }">
        <VChip
          :color="getFrequencyColor(item.frequency)"
          size="x-small"
          class="font-weight-black px-2 rounded"
          variant="flat"
        >
          {{ item.frequency.toUpperCase() }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex justify-end gap-1">
          <VTooltip text="Editar" location="top">
            <template #activator="{ props: tooltipProps }">
              <VBtn v-bind="tooltipProps" icon="tabler-edit" variant="text" color="warning" size="32" @click="emit('edit-activity', item)" />
            </template>
          </VTooltip>
          <VTooltip text="Eliminar" location="top">
            <template #activator="{ props: tooltipProps }">
              <VBtn v-bind="tooltipProps" icon="tabler-trash" variant="text" color="error" size="32" @click="emit('delete-activity', item.id)" />
            </template>
          </VTooltip>
        </div>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil: Cards Premium -->
    <div v-else class="pa-4 bg-light">
      <VRow>
        <VCol v-for="item in props.activities" :key="item.id" cols="12">
          <VCard class="rounded-lg border shadow-sm mb-4 overflow-hidden">
            <div class="pa-4 border-b d-flex justify-space-between align-center bg-surface">
              <div class="d-flex align-center gap-3">
                <VAvatar color="primary" variant="tonal" size="40" class="rounded font-weight-black">
                  {{ item.activity.charAt(0).toUpperCase() }}
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="text-sm font-weight-black text-high-emphasis leading-tight">{{ item.activity }}</span>
                  <span class="text-super-xs text-disabled uppercase font-weight-bold">ID: #{{ item.id }}</span>
                </div>
              </div>
              <VChip
                :color="getFrequencyColor(item.frequency)"
                size="x-small"
                class="font-weight-black px-2 rounded"
                variant="flat"
              >
                {{ item.frequency.toUpperCase() }}
              </VChip>
            </div>
            
            <VCardText class="pa-4">
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Descripción</span>
                <p class="text-xs text-medium-emphasis leading-relaxed mb-0 italic">
                  {{ item.description || 'No hay descripción detallada para esta actividad.' }}
                </p>
              </div>
              
              <VDivider class="border-dashed mb-4" />
              
              <div class="d-flex gap-2">
                <VBtn 
                  block 
                  color="warning" 
                  variant="tonal" 
                  size="small" 
                  class="rounded-lg flex-grow-1 font-weight-black" 
                  @click="emit('edit-activity', item)"
                >
                  <VIcon start icon="tabler-edit" size="16" />
                  EDITAR
                </VBtn>
                <VBtn 
                  block 
                  color="error" 
                  variant="tonal" 
                  size="small" 
                  class="rounded-lg flex-grow-1 font-weight-black" 
                  @click="emit('delete-activity', item.id)"
                >
                  <VIcon start icon="tabler-trash" size="16" />
                  BORRAR
                </VBtn>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
      
      <div v-if="props.activities.length === 0 && !props.loading" class="text-center py-12">
        <VIcon icon="tabler-mood-empty" size="64" color="disabled" class="mb-4 opacity-20" />
        <div class="text-sm font-weight-black text-disabled uppercase tabular-nums">Sin actividades registradas</div>
      </div>
      
      <div v-if="props.loading" class="text-center py-12">
        <VProgressCircular indeterminate color="primary" size="32" class="mb-4" />
        <div class="text-super-xs font-weight-black text-disabled uppercase">Cargando actividades...</div>
      </div>
    </div>
  </VCard>
</template>

<style scoped>
/* Estilos para Tabla Premium */
:deep(.premium-table) {
  background: transparent !important;

  thead {
    background: rgba(var(--v-theme-on-surface), 0.02);

    th {
      background: transparent !important;
      block-size: 48px !important;
      border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
      color: rgb(var(--v-theme-disabled)) !important;
    }
  }

  tbody tr {
    transition: background-color 0.2s ease;

    &:hover {
      background-color: rgba(var(--v-theme-primary), 0.02) !important;
    }

    td {
      block-size: 56px !important;
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

.leading-relaxed {
  line-height: 1.6;
}

.border-dashed {
  border-style: dashed !important;
}

.italic {
  font-style: italic;
}

:deep(.v-data-table-footer) {
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}
</style>
