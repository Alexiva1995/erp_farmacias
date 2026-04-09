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
  { title: "ID", key: "id", sortable: true },
  { title: "Nombre de Ubicación", key: "name", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: 'center' },
];
</script>

<template>
  <div class="location-table-container">
    <VCard class="rounded-xl border-0 shadow-premium overflow-hidden">
      <!-- Decoración de Cabecera -->
      <div class="header-gradient pa-4 d-flex align-center justify-space-between text-white">
        <div class="d-flex align-center gap-3">
          <VAvatar color="white" variant="tonal" rounded="lg">
            <VIcon icon="tabler-map-pin" color="white" />
          </VAvatar>
          <div>
            <div class="text-h6 font-weight-black line-height-tight">Ubicaciones</div>
            <div class="text-caption opacity-80">
              Total: {{ props.locations.length }} registros
            </div>
          </div>
        </div>
      </div>

      <!-- Vista de Escritorio (Tabla) -->
      <VDataTable
        v-if="!mobile"
        :headers="headers"
        :items="props.locations"
        :loading="props.loading"
        class="premium-data-table"
        hover
        no-data-text="No se encontraron ubicaciones registradas"
      >
        <template #item.id="{ item }">
          <span class="text-subtitle-2 font-weight-black text-primary">#{{ item.id }}</span>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-3">
            <div class="header-indicator success rounded-pill"></div>
            <span class="text-body-1 font-weight-bold text-high-emphasis">{{ item.name }}</span>
          </div>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-2">
            <VTooltip text="Editar" location="top">
              <template #activator="{ props: tooltipProps }">
                <VBtn
                  v-bind="tooltipProps"
                  icon="tabler-pencil"
                  variant="tonal"
                  color="primary"
                  size="32"
                  class="rounded-lg transition-all"
                  @click="emit('edit-location', item)"
                />
              </template>
            </VTooltip>

            <VTooltip text="Eliminar" location="top">
              <template #activator="{ props: tooltipProps }">
                <VBtn
                  v-bind="tooltipProps"
                  icon="tabler-trash"
                  variant="tonal"
                  color="error"
                  size="32"
                  class="rounded-lg transition-all"
                  @click="emit('delete-location', item.id)"
                />
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

      <!-- Vista de Móvil (Tarjetas) -->
      <div v-else class="pa-4 d-flex flex-column gap-3">
        <div v-show="props.loading" class="text-center py-4">
          <VProgressCircular indeterminate color="primary" />
        </div>

        <div v-if="props.locations.length === 0 && !props.loading" class="text-center py-10 text-disabled">
          No se encontraron ubicaciones registradas
        </div>

        <VCard
          v-for="location in props.locations"
          :key="location.id"
          variant="outlined"
          class="rounded-xl border-dashed-thin transition-all"
        >
          <VCardText class="pa-4">
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="d-flex align-center gap-2">
                <div class="header-indicator success rounded-pill"></div>
                <div class="d-flex flex-column">
                  <span class="text-caption text-disabled font-weight-bold">#{{ location.id }}</span>
                  <span class="text-h6 font-weight-black">{{ location.name }}</span>
                </div>
              </div>
              <div class="d-flex gap-2">
                <VBtn
                  icon="tabler-pencil"
                  variant="tonal"
                  color="primary"
                  size="35"
                  class="rounded-lg"
                  @click="emit('edit-location', location)"
                />
                <VBtn
                  icon="tabler-trash"
                  variant="tonal"
                  color="error"
                  size="35"
                  class="rounded-lg"
                  @click="emit('delete-location', location.id)"
                />
              </div>
            </div>
          </VCardText>
        </VCard>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.shadow-premium {
  box-shadow: 0 10px 30px 0 rgba(0, 0, 0, 8%) !important;
}

.header-gradient {
  background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
}

.header-indicator {
  block-size: 20px;
  inline-size: 4px;
}

.header-indicator.success {
  background: linear-gradient(to bottom, #10b981, #059669);
}

.premium-data-table :deep(th) {
  background-color: #f8fafc !important;
  block-size: 48px !important;
  color: #64748b !important;
  font-size: 0.75rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.premium-data-table :deep(td) {
  border-block-end: 1px dashed #e2e8f0 !important;
  padding-block: 16px !important;
}

.transition-all {
  transition: all 0.2s ease-in-out;
}

.transition-all:hover {
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.2);
  transform: translateY(-2px);
}

.line-height-tight {
  line-height: 1.2;
}
</style>
