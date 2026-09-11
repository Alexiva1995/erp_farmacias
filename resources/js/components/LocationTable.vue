<script setup>
import { computed } from "vue";
import { useDisplay } from "vuetify";
import AppEmptyState from "@/components/AppEmptyState.vue";

const props = defineProps({
  locations: { type: Array, required: true },
  loading: { type: Boolean, default: false },
});

const { mobile } = useDisplay();

const emit = defineEmits([
  "edit-location",
  "delete-location",
]);

const headers = [
  {
    title: "ID",
    key: "id",
    sortable: true,
    cellClass: "font-weight-black text-primary d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell",
    width: "80px",
  },
  {
    title: "Ubicación",
    key: "name",
    sortable: true,
    width: "40%",
  },
  {
    title: "Productos",
    key: "products_count",
    sortable: true,
    align: "center",
  },
  {
    title: "Unidades",
    key: "units_count",
    sortable: true,
    align: "end",
  },
  {
    title: "Acciones",
    key: "actions",
    sortable: false,
    align: "center",
    width: "120px",
  },
];

const formatUnits = (units) => {
  const num = Number(units || 0);
  return num % 1 === 0 ? num.toString() : num.toFixed(2).replace(".", ",");
};
</script>

<template>
  <div class="location-table-container">
    <VCard class="rounded-lg border shadow-sm overflow-hidden">
      <!-- Cabecera Estándar (como en Productos) -->
      <VCardTitle class="d-flex align-center pa-4">
        <span class="text-h6 font-weight-bold">Listado de Ubicaciones</span>
        <VSpacer />
        <VChip size="small" color="primary" variant="tonal" class="font-weight-black">
          {{ props.locations.length }} UBICACIONES
        </VChip>
      </VCardTitle>

      <VDivider />

      <!-- Vista de Escritorio (Tabla) -->
      <div class="d-none d-md-block">
        <VDataTable
          :headers="headers"
          :items="props.locations"
          :loading="props.loading"
          :sort-by="[{ key: 'units_count', order: 'desc' }]"
          class="text-no-wrap"
          density="compact"
          hover
        >
          <template #no-data>
            <AppEmptyState
              title="No se encontraron ubicaciones"
              message="No hay ubicaciones registradas o no coinciden con los filtros aplicados."
              icon="tabler-map-pin-off"
            />
          </template>

          <template #item.id="{ item }">
            <span class="font-weight-black text-primary">
              {{ item.id }}
            </span>
          </template>

          <template #item.name="{ item }">
            <div class="d-flex align-center gap-2 py-2">
              <div class="header-indicator success rounded-pill"></div>
              <span class="text-sm font-weight-black text-high-emphasis text-uppercase">{{ item.name }}</span>
            </div>
          </template>

          <template #item.products_count="{ item }">
            <div class="text-center">
              <VChip
                :color="item.products_count > 0 ? 'primary' : 'secondary'"
                size="x-small"
                variant="tonal"
                label
                class="font-weight-black"
              >
                {{ item.products_count }} {{ item.products_count === 1 ? 'REF' : 'REFS' }}
              </VChip>
            </div>
          </template>

          <template #item.units_count="{ item }">
            <div class="text-end">
              <VChip
                :color="item.units_count > 0 ? 'success' : 'secondary'"
                size="x-small"
                variant="tonal"
                label
                class="font-weight-black"
              >
                {{ formatUnits(item.units_count) }} UNDS
              </VChip>
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
                    :disabled="props.loading"
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
                    :disabled="props.loading"
                    @click="emit('delete-location', item.id)"
                  >
                    <VIcon icon="tabler-trash" size="18" />
                  </IconBtn>
                </template>
              </VTooltip>
            </div>
          </template>

          <template #loading>
            <VSkeletonLoader type="table-row@5" />
          </template>
        </VDataTable>
      </div>

      <!-- Vista de Móvil (Tarjetas de estilo consistente con productos) -->
      <div class="d-block d-md-none pa-2">
        <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />

        <div v-if="props.locations.length === 0 && !props.loading" class="text-center py-8 text-disabled">
          No se encontraron ubicaciones registradas.
        </div>

        <div class="d-flex flex-column gap-2">
          <VCard
            v-for="location in props.locations"
            :key="location.id"
            variant="flat"
            class="location-mobile-card border mb-1"
          >
            <div class="pa-2 pa-sm-3">
              <div class="d-flex justify-space-between align-center mb-1">
                <div class="d-flex align-center gap-2">
                  <span class="text-xs font-weight-black text-primary">{{ location.id }}</span>
                  <span class="mx-1 text-disabled font-weight-regular">|</span>
                  <span class="text-sm font-weight-black text-high-emphasis text-uppercase">{{ location.name }}</span>
                </div>
              </div>

              <!-- Caja compacta de Referencias y Unidades -->
              <div class="d-flex align-center justify-space-between bg-var-theme-background px-2 py-1 mt-2 rounded border-dashed-thin">
                <div class="d-flex align-center gap-2">
                  <span class="text-super-xs text-disabled text-uppercase font-weight-bold letter-spacing-1">Productos:</span>
                  <VChip
                    :color="location.products_count > 0 ? 'primary' : 'secondary'"
                    size="x-small"
                    variant="tonal"
                    label
                    class="font-weight-black"
                  >
                    {{ location.products_count }} {{ location.products_count === 1 ? 'REF' : 'REFS' }}
                  </VChip>
                </div>
                <div class="d-flex align-center gap-2">
                  <span class="text-super-xs text-disabled text-uppercase font-weight-bold letter-spacing-1">Unidades:</span>
                  <VChip
                    :color="location.units_count > 0 ? 'success' : 'secondary'"
                    size="x-small"
                    variant="tonal"
                    label
                    class="font-weight-black"
                  >
                    {{ formatUnits(location.units_count) }} UNDS
                  </VChip>
                </div>
              </div>

              <VDivider class="my-2 border-opacity-10" />

              <div class="d-flex gap-2">
                <VBtn
                  color="warning"
                  variant="text"
                  class="flex-grow-1 rounded-0"
                  height="36"
                  prepend-icon="tabler-edit"
                  @click="emit('edit-location', location)"
                >
                  Editar
                </VBtn>
                <VDivider vertical class="border-opacity-10" />
                <VBtn
                  color="error"
                  variant="text"
                  class="flex-grow-1 rounded-0"
                  height="36"
                  prepend-icon="tabler-trash"
                  @click="emit('delete-location', location.id)"
                >
                  Eliminar
                </VBtn>
              </div>
            </div>
          </VCard>
        </div>
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

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.15);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs {
  font-size: 0.75rem !important;
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
