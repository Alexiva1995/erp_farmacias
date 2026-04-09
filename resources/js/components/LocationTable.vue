<script setup>
const props = defineProps({
  locations: { type: Array, required: true },
  loading: { type: Boolean, default: false },
});

import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

const emit = defineEmits([
  "edit-location",
  "delete-location",
]);

const headers = [
  { title: "id", key: "id", sortable: true, cellClass: 'font-weight-black text-primary' },
  { title: "Nombre de Ubicación", key: "name", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: 'center' },
];
</script>

<template>
  <div class="location-table-container">
    <VCard class="rounded-lg border shadow-sm overflow-hidden">
      <!-- Cabecera Estándar (como en Productos) -->
      <VCardTitle class="d-flex align-center pa-4">
        <span class="text-h6 font-weight-bold">Listado de Ubicaciones</span>
        <VSpacer />
        <VChip size="small" color="primary" variant="tonal" class="font-weight-black">
          {{ props.locations.length }} ITEMS
        </VChip>
      </VCardTitle>

      <VDivider />

      <!-- Vista de Escritorio (Tabla Compacta Estándar) -->
      <VDataTable
        v-if="!mobile"
        :headers="headers"
        :items="props.locations"
        :loading="props.loading"
        class="text-no-wrap"
        density="compact"
        hover
        no-data-text="No se encontraron ubicaciones registradas"
      >
        <template #item.id="{ item }">
          <span class="text-sm font-weight-black text-primary">#{{ item.id }}</span>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2 py-2">
            <div class="header-indicator success rounded-pill"></div>
            <span class="text-sm font-weight-black text-high-emphasis text-uppercase">{{ item.name }}</span>
          </div>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <VTooltip text="Editar" location="top">
              <template #activator="{ props: tooltipProps }">
                <IconBtn
                  v-bind="tooltipProps"
                  color="warning"
                  size="small"
                  @click="emit('edit-location', item)"
                >
                  <VIcon icon="tabler-edit" size="18" />
                </IconBtn>
              </template>
            </VTooltip>

            <VTooltip text="Eliminar" location="top">
              <template #activator="{ props: tooltipProps }">
                <IconBtn
                  v-bind="tooltipProps"
                  color="error"
                  size="small"
                  @click="emit('delete-location', item.id)"
                >
                  <VIcon icon="tabler-trash" size="18" />
                </IconBtn>
              </template>
            </VTooltip>
          </div>
        </template>

        <template #loading>
          <div class="py-10 text-center">
            <VProgressCircular indeterminate color="primary" />
            <div class="mt-2 text-caption text-primary font-weight-bold">Cargando ubicaciones...</div>
          </div>
        </template>
      </VDataTable>

      <!-- Vista de Móvil (Tarjetas Estándar) -->
      <div v-else class="pa-2 d-flex flex-column gap-2">
        <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />

        <div v-if="props.locations.length === 0 && !props.loading" class="text-center py-10 text-disabled">
          No se encontraron ubicaciones registradas
        </div>

        <VCard
          v-for="location in props.locations"
          :key="location.id"
          variant="flat"
          class="location-mobile-card border mb-1"
        >
          <div class="pa-3">
            <div class="d-flex justify-space-between align-center mb-1">
              <div class="d-flex align-center gap-2">
                <span class="text-xs font-weight-black text-primary">#{{ location.id }}</span>
                <span class="text-sm font-weight-black text-uppercase">{{ location.name }}</span>
              </div>
            </div>
            
            <VDivider class="my-2 border-opacity-10" />

            <div class="d-flex gap-2">
              <VBtn 
                color="warning" 
                variant="text" 
                class="flex-grow-1 rounded-0" 
                height="36"
                icon="tabler-edit" 
                @click="emit('edit-location', location)"
              />
              <VDivider vertical class="border-opacity-10" />
              <VBtn 
                color="error" 
                variant="text" 
                class="flex-grow-1 rounded-0" 
                height="36"
                icon="tabler-trash" 
                @click="emit('delete-location', location.id)"
              />
            </div>
          </div>
        </VCard>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.location-mobile-card {
  overflow: hidden;
  border-radius: 8px !important;
  background: rgb(var(--v-theme-surface));
}

.header-indicator {
  block-size: 16px;
  inline-size: 3px;
}

.header-indicator.success {
  background: linear-gradient(to bottom, #10b981, #059669);
}

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
</style>
