<script setup>
// Tabla de actividades de limpieza — homologada al estándar del módulo de empleados
import AppEmptyState from "@/components/AppEmptyState.vue";
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  activities:      { type: Array,   required: true },
  loading:         { type: Boolean, default: false },
  totalActivities: { type: Number,  required: true },
  itemsPerPage:    { type: Number,  required: true },
  page:            { type: Number,  required: true },
});

const emit = defineEmits([
  "update:options",
  "edit-activity",
  "delete-activity",
]);

const { mobile } = useDisplay();

const headers = [
  { title: "ID",          key: "id",          sortable: true,  align: "start" },
  { title: "Actividad",   key: "activity",    sortable: true,  width: "32%" },
  { title: "Descripción", key: "description", sortable: false, width: "32%" },
  { title: "Frecuencia",  key: "frequency",   sortable: true,  align: "center" },
  { title: "Acciones",    key: "actions",     sortable: false, align: "center" },
];

// Colores semánticos por frecuencia
const getFrequencyColor = (frequency) => {
  const colors = {
    Diaria:     "error",
    Semanal:    "warning",
    Bimestral:  "info",
    Mensual:    "primary",
    Trimestral: "secondary",
    Semestral:  "success",
    Anual:      "default",
  };
  return colors[frequency] || "default";
};
</script>

<template>
  <!-- Vista Escritorio -->
  <VCard variant="flat" border class="overflow-hidden">
    <VDataTableServer
      v-if="!mobile"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.activities"
      :items-length="props.totalActivities"
      :loading="props.loading"
      class="text-no-wrap"
      density="comfortable"
      @update:options="(options) => emit('update:options', options)"
    >
      <!-- ID -->
      <template #item.id="{ item }">
        <span class="font-weight-black text-primary tabular-nums text-xs">#{{ item.id }}</span>
      </template>

      <!-- Actividad con avatar -->
      <template #item.activity="{ item }">
        <div class="d-flex align-center gap-3 py-1">
          <VAvatar color="primary" variant="tonal" size="32" class="rounded-lg flex-shrink-0">
            <VIcon icon="tabler-sparkles" size="16" />
          </VAvatar>
          <span class="text-sm font-weight-bold text-high-emphasis">{{ item.activity }}</span>
        </div>
      </template>

      <!-- Descripción truncada -->
      <template #item.description="{ item }">
        <span class="text-xs text-medium-emphasis text-truncate d-block" style="max-inline-size: 280px;">
          {{ item.description || '—' }}
        </span>
      </template>

      <!-- Frecuencia como chip tonal -->
      <template #item.frequency="{ item }">
        <VChip
          :color="getFrequencyColor(item.frequency)"
          size="x-small"
          variant="tonal"
          class="font-weight-bold px-2 rounded"
        >
          {{ item.frequency }}
        </VChip>
      </template>

      <!-- Acciones con tooltips -->
      <template #item.actions="{ item }">
        <div class="d-flex justify-center gap-1">
          <VTooltip text="Editar actividad" location="top">
            <template #activator="{ props: tip }">
              <IconBtn v-bind="tip" size="small" color="warning" variant="tonal" class="rounded"
                @click="emit('edit-activity', item)">
                <VIcon icon="tabler-edit" size="18" />
              </IconBtn>
            </template>
          </VTooltip>
          <VTooltip text="Eliminar actividad" location="top">
            <template #activator="{ props: tip }">
              <IconBtn v-bind="tip" size="small" color="error" variant="tonal" class="rounded"
                @click="emit('delete-activity', item.id)">
                <VIcon icon="tabler-trash" size="18" />
              </IconBtn>
            </template>
          </VTooltip>
        </div>
      </template>

      <!-- Footer paginación estándar -->
      <template #bottom>
        <VDivider />
        <div class="d-flex align-center justify-space-between px-4 py-2">
          <span class="text-xs text-disabled font-weight-bold">
            Total: {{ props.totalActivities }} registros
          </span>
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalActivities / props.itemsPerPage)"
            :total-visible="5"
            size="small"
            density="compact"
            active-color="primary"
            variant="flat"
            @update:model-value="(p) => emit('update:options', { page: p, itemsPerPage: props.itemsPerPage, sortBy: [] })"
          />
        </div>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil -->
    <div v-else class="pa-3 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-3 rounded" />

      <AppEmptyState
        v-if="props.activities.length === 0 && !props.loading"
        title="Sin actividades"
        message="No se encontraron actividades de limpieza con los filtros actuales."
        icon="tabler-sparkles"
      />

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.activities"
          :key="item.id"
          variant="flat"
          border
          class="rounded-lg overflow-hidden"
        >
          <div class="pa-4">
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="d-flex align-center gap-3 min-width-0">
                <VAvatar color="primary" variant="tonal" size="40" class="rounded-lg flex-shrink-0">
                  <VIcon icon="tabler-sparkles" size="20" />
                </VAvatar>
                <div class="min-width-0">
                  <p class="text-sm font-weight-bold text-high-emphasis mb-0 text-truncate">{{ item.activity }}</p>
                  <span class="text-xs text-disabled font-weight-medium">#{{ item.id }}</span>
                </div>
              </div>
              <div class="d-flex gap-1 ms-2 flex-shrink-0">
                <IconBtn size="small" color="warning" variant="tonal" class="rounded" @click="emit('edit-activity', item)">
                  <VIcon icon="tabler-edit" size="16" />
                </IconBtn>
                <IconBtn size="small" color="error" variant="tonal" class="rounded" @click="emit('delete-activity', item.id)">
                  <VIcon icon="tabler-trash" size="16" />
                </IconBtn>
              </div>
            </div>

            <VDivider class="mb-3 opacity-10" />

            <div class="d-flex justify-space-between align-center mb-2">
              <span class="text-xs font-weight-bold text-medium-emphasis uppercase">Frecuencia</span>
              <VChip :color="getFrequencyColor(item.frequency)" size="x-small" variant="tonal"
                class="font-weight-bold px-2 rounded">
                {{ item.frequency }}
              </VChip>
            </div>
            <p v-if="item.description" class="text-xs text-medium-emphasis mb-0 leading-relaxed">{{ item.description }}</p>
            <span v-else class="text-xs text-disabled italic">Sin descripción</span>
          </div>
        </VCard>
      </div>

      <AppMobilePagination
        v-if="props.totalActivities > 0"
        :page="props.page"
        :items-per-page="props.itemsPerPage"
        :total-items="props.totalActivities"
        class="mt-4"
        @change="(opts) => emit('update:options', opts)"
        @update:page="(p) => emit('update:options', { page: p, itemsPerPage: props.itemsPerPage, sortBy: [] })"
        @update:items-per-page="(n) => emit('update:options', { page: 1, itemsPerPage: n, sortBy: [] })"
      />
    </div>
  </VCard>
</template>

<style scoped>
}

.bg-light {
  background-color: rgba(var(--v-theme-on-surface), 0.015);
}

.leading-tight {
  line-height: 1.2;
}

.leading-relaxed {
  line-height: 1.5;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.truncate-2-lines {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

:deep(.v-data-table-footer) {
  display: none !important;
}
</style>

