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
  { title: "ID", key: "id", sortable: true },
  { title: "ACTIVIDAD", key: "activity", sortable: true, width: "35%" },
  { title: "DESCRIPCIÓN", key: "description", sortable: false, width: "35%" },
  { title: "FRECUENCIA", key: "frequency", sortable: true, align: 'center' },
  { title: "ACCIONES", key: "actions", sortable: false, align: "center" },
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
</script>

<template>
  <VCard class="border shadow-sm overflow-hidden">
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
      density="compact"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <span class="font-weight-black text-primary tabular-nums text-xs uppercase">{{ item.id }}</span>
      </template>

      <template #item.activity="{ item }">
        <div class="py-2">
          <span class="text-sm font-weight-black text-high-emphasis leading-tight text-uppercase">
            {{ item.activity }}
          </span>
        </div>
      </template>

      <template #item.description="{ item }">
        <div class="text-xs text-medium-emphasis text-truncate text-uppercase font-weight-black" style="max-inline-size: 300px;">
          {{ item.description || 'SIN DESCRIPCIÓN' }}
        </div>
      </template>

      <template #item.frequency="{ item }">
        <VChip
          :color="getFrequencyColor(item.frequency)"
          size="x-small"
          class="font-weight-black px-2 rounded text-uppercase"
          variant="tonal"
        >
          {{ item.frequency }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex justify-center gap-1">
          <IconBtn
            size="small"
            color="warning"
            variant="tonal"
            class="rounded"
            @click="emit('edit-activity', item)"
          >
            <VIcon icon="tabler-edit" size="18" />
          </IconBtn>
          <IconBtn
            size="small"
            color="error"
            variant="tonal"
            class="rounded"
            @click="emit('delete-activity', item.id)"
          >
            <VIcon icon="tabler-trash" size="18" />
          </IconBtn>
        </div>
      </template>

      <template #bottom>
        <VDivider class="opacity-10" />
        <div class="d-flex align-center justify-space-between pa-2">
          <span class="text-super-xs text-disabled font-weight-black uppercase ms-2">
            Total: {{ props.totalActivities }} registros
          </span>
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalActivities / props.itemsPerPage)"
            size="small"
            @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
          />
        </div>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil: Cards Premium -->
    <div v-else class="pa-4 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-4 rounded" />

      <div v-if="props.activities.length === 0 && !props.loading" class="text-center pa-12">
        <VIcon icon="tabler-package-off" size="64" class="text-disabled mb-4 opacity-20" />
        <p class="text-sm uppercase font-weight-black text-disabled">No se encontraron actividades</p>
      </div>

      <VRow>
        <VCol v-for="item in props.activities" :key="item.id" cols="12">
          <VCard class="rounded-lg border shadow-sm mb-4 overflow-hidden">
            <div class="pa-4">
              <div class="d-flex justify-space-between align-start mb-4">
                <div class="d-flex flex-column min-width-0">
                  <span class="text-primary font-weight-black text-super-xs uppercase mb-1">Actividad</span>
                  <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate">
                    {{ item.activity }}
                  </h3>
                  <span class="text-super-xs text-disabled uppercase font-weight-black mt-1">ID: #{{ item.id }}</span>
                </div>
                <div class="d-flex gap-1">
                  <IconBtn
                    size="small"
                    color="warning"
                    variant="tonal"
                    class="rounded"
                    @click="emit('edit-activity', item)"
                  >
                    <VIcon icon="tabler-edit" size="18" />
                  </IconBtn>
                  <IconBtn
                    size="small"
                    color="error"
                    variant="tonal"
                    class="rounded"
                    @click="emit('delete-activity', item.id)"
                  >
                    <VIcon icon="tabler-trash" size="18" />
                  </IconBtn>
                </div>
              </div>

              <VDivider class="my-4 border-opacity-10" />

              <div class="mb-4">
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-super-xs font-weight-black text-disabled uppercase">Frecuencia</span>
                  <VChip
                    :color="getFrequencyColor(item.frequency)"
                    size="x-small"
                    class="font-weight-black px-2 rounded text-uppercase"
                    variant="tonal"
                  >
                    {{ item.frequency }}
                  </VChip>
                </div>
                <div>
                   <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Descripción</span>
                   <p class="text-xs text-medium-emphasis leading-relaxed mb-0 font-weight-black text-uppercase truncate-2-lines">
                    {{ item.description || 'Sin descripción detallada' }}
                  </p>
                </div>
              </div>
            </div>
          </VCard>
        </VCol>
      </VRow>
      
      <div v-if="props.totalActivities > props.itemsPerPage" class="mt-6 d-flex justify-center">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalActivities / props.itemsPerPage)"
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

