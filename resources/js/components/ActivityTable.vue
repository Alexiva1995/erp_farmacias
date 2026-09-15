<script setup>
// Tabla de actividades de limpieza — patrón idéntico a SocialBenefitsTable
import AppEmptyState from "@/components/AppEmptyState.vue";
import AppMobilePagination from "@/components/AppMobilePagination.vue";

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
  <div class="activity-table-container">
    <!-- Vista Desktop -->
    <div class="d-none d-md-block">
      <VCard border variant="flat">
        <VDataTableServer
          :headers="headers"
          :items-per-page="props.itemsPerPage"
          :items="props.activities"
          :items-length="props.totalActivities"
          :loading="props.loading"
          :page="props.page"
          density="comfortable"
          @update:options="(options) => emit('update:options', options)"
        >
          <!-- Estado vacío -->
          <template #no-data>
            <AppEmptyState
              title="Sin actividades"
              message="No se encontraron actividades de limpieza con los filtros actuales."
              icon="tabler-sparkles"
            />
          </template>

          <!-- ID -->
          <template #item.id="{ item }">
            <span class="font-weight-bold text-primary">{{ item.id }}</span>
          </template>

          <!-- Actividad -->
          <template #item.activity="{ item }">
            <span class="text-sm font-weight-medium text-high-emphasis">{{ item.activity }}</span>
          </template>

          <!-- Descripción -->
          <template #item.description="{ item }">
            <span class="text-sm text-medium-emphasis">{{ item.description || '—' }}</span>
          </template>

          <!-- Frecuencia -->
          <template #item.frequency="{ item }">
            <VChip
              :color="getFrequencyColor(item.frequency)"
              size="x-small"
              variant="tonal"
              class="font-weight-bold"
            >
              {{ item.frequency }}
            </VChip>
          </template>

          <!-- Acciones -->
          <template #item.actions="{ item }">
            <div class="d-flex justify-end gap-1">
              <IconBtn color="warning" size="small" @click="emit('edit-activity', item)">
                <VIcon icon="tabler-edit" size="18" />
                <VTooltip activator="parent">Editar actividad</VTooltip>
              </IconBtn>
              <IconBtn color="error" size="small" @click="emit('delete-activity', item.id)">
                <VIcon icon="tabler-trash" size="18" />
                <VTooltip activator="parent">Eliminar actividad</VTooltip>
              </IconBtn>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- Vista Móvil (Cards) -->
    <div class="d-block d-md-none pa-2 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />

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
          class="mb-1 overflow-hidden premium-card bg-white"
        >
          <div class="pa-4">
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="d-flex flex-column min-width-0">
                <span class="text-primary font-weight-black text-xs uppercase mb-0.5">ID #{{ item.id }}</span>
                <h3 class="text-sm font-weight-semibold text-high-emphasis leading-tight truncate">
                  {{ item.activity }}
                </h3>
              </div>
              <div class="d-flex gap-1 ms-2 flex-shrink-0">
                <IconBtn size="x-small" color="warning" @click="emit('edit-activity', item)">
                  <VIcon icon="tabler-edit" size="16" />
                  <VTooltip activator="parent">Editar</VTooltip>
                </IconBtn>
                <IconBtn size="x-small" color="error" @click="emit('delete-activity', item.id)">
                  <VIcon icon="tabler-trash" size="16" />
                  <VTooltip activator="parent">Eliminar</VTooltip>
                </IconBtn>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="d-flex align-center justify-space-between">
              <VChip
                :color="getFrequencyColor(item.frequency)"
                size="x-small"
                variant="tonal"
                class="font-weight-bold"
              >
                {{ item.frequency }}
              </VChip>
              <span v-if="item.description" class="text-xs text-medium-emphasis text-truncate ms-3">
                {{ item.description }}
              </span>
              <span v-else class="text-xs text-disabled italic ms-3">Sin descripción</span>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Mobile Pagination -->
      <div class="d-flex justify-center mt-4 pb-2">
        <AppMobilePagination
          :page="props.page"
          :items-per-page="props.itemsPerPage"
          :total-items="props.totalActivities"
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

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>

