<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import AppEmptyState from "@/components/AppEmptyState.vue";
import { formatDateSimple } from "@/utils/formatters";

const props = defineProps({
  lots: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalLots: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: String, default: undefined },
  orderBy: { type: String, default: "asc" },
  lotWithError: { type: [Number, null], default: null },
  locations: { type: Array, default: () => [] },
  loadingLocations: { type: Boolean, default: false },
  editingLotId: { type: [Number, null], default: null },
  editingLocation: { type: String, default: "" },
  isSaving: { type: Boolean, default: false },
  searchInput: { type: String, default: "" },
});

const emit = defineEmits([
  "update:editingLocation",
  "update:options",
  "start-edit",
  "cancel-edit",
  "save-inline-edit",
  "search-location",
]);

const formatStock = (quantity) => {
  const stock = Number(quantity ?? 0);
  return stock % 1 === 0 ? stock.toString() : stock.toFixed(2).replace(".", ",");
};
</script>

<template>
  <div class="d-block d-sm-none pa-2">
    <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
    
    <div v-if="props.lots.length === 0 && !props.loading" class="text-center py-8 text-disabled">
      <AppEmptyState
        title="¡Todo Ubicado!"
        message="No se encontraron lotes pendientes de asignación de ubicación en almacén."
        icon="tabler-map-pin-check"
      />
    </div>

    <div v-else class="d-flex flex-column gap-2">
      <VCard
        v-for="item in props.lots"
        :key="item.id"
        class="product-mobile-card border rounded-lg bg-surface pa-3 shadow-none position-relative"
      >
        <div class="d-flex align-center justify-space-between mb-2">
          <div class="d-flex align-center gap-1 min-width-0">
            <a
              :href="'/inventory/traceability?q=' + item.product?.id"
              target="_blank"
              class="text-decoration-none font-weight-black text-primary text-xs"
            >
              #{{ item.id }}
            </a>
            <span class="text-disabled">|</span>
            <span class="text-xs font-weight-black text-primary text-uppercase truncate mobile-lab-truncate">
              {{ item.product?.laboratory?.name || 'S/L' }}
            </span>
          </div>
          <VChip
            :color="(item.quantity ?? 0) > 0 ? 'success' : 'error'"
            label
            size="x-small"
            variant="tonal"
            class="font-weight-black"
          >
            {{ formatStock(item.quantity) }} UNDS
          </VChip>
        </div>

        <h4 class="text-xs font-weight-black text-high-emphasis text-uppercase leading-tight mb-1 text-truncate">
          {{ item.product?.name }}
          <span v-if="item.product?.iva == 1 || item.product?.iva === true" class="text-super-xs text-disabled"> (G)</span>
          <span v-if="item.product?.is_colombian_origin == 1 || item.product?.is_colombian_origin === true" class="text-super-xs text-disabled"> (COL)</span>
        </h4>

        <div class="d-flex align-center justify-space-between text-super-xs text-disabled mb-2">
          <span>Lote: <strong class="text-high-emphasis font-weight-black">{{ item.lot_number || 'S/L' }}</strong></span>
          <span>Vence: <strong class="text-high-emphasis font-weight-bold">{{ formatDateSimple(item.expiration_date) }}</strong></span>
        </div>

        <!-- Formulario de Edición Móvil In-line -->
        <div v-if="props.editingLotId === item.id" class="mt-3 pt-2 border-t d-flex flex-column gap-2">
          <VAutocomplete
            :model-value="props.editingLocation"
            :items="props.locations"
            item-title="name"
            item-value="name"
            label="Asignar Ubicación"
            density="compact"
            variant="outlined"
            hide-details
            placeholder="Buscar ubicación..."
            :loading="props.loadingLocations || props.isSaving"
            :disabled="props.isSaving"
            :error="props.lotWithError === item.id"
            @update:model-value="(val) => emit('update:editingLocation', val)"
            @update:search="(val) => emit('search-location', val)"
            @keydown.enter.prevent="emit('save-inline-edit', item)"
          />
          <div class="d-flex gap-2 justify-center mt-1">
            <VBtn
              size="small"
              variant="tonal"
              color="secondary"
              class="flex-grow-1 font-weight-bold"
              :disabled="props.isSaving"
              @click="emit('cancel-edit')"
            >
              Cancelar
            </VBtn>
            <VBtn
              size="small"
              color="primary"
              class="flex-grow-1 font-weight-bold"
              :loading="props.isSaving"
              @click="emit('save-inline-edit', item)"
            >
              Guardar
            </VBtn>
          </div>
        </div>

        <div v-else class="d-flex align-center justify-space-between text-super-xs text-medium-emphasis mt-2 pt-2 border-t">
          <span class="text-disabled">Ubicación: <strong class="text-error font-weight-bold">Sin asignar</strong></span>
          <IconBtn
            color="primary"
            size="small"
            @click="emit('start-edit', item)"
          >
            <VIcon icon="tabler-map-pin" size="18" />
            <VTooltip activator="parent">Asignar Ubicación</VTooltip>
          </IconBtn>
        </div>
      </VCard>
    </div>

    <!-- Paginación Móvil -->
    <div class="mt-4">
      <AppMobilePagination
        :page="props.page"
        :items-per-page="props.itemsPerPage"
        :total-items="props.totalLots"
        :loading="props.loading"
        :sort-by="typeof props.sortBy === 'string' ? props.sortBy : (props.sortBy?.[0]?.key || undefined)"
        :order-by="props.orderBy"
        @change="(options) => emit('update:options', options)"
      />
    </div>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs {
  font-size: 0.75rem !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

.mobile-lab-truncate {
  max-inline-size: 160px;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
