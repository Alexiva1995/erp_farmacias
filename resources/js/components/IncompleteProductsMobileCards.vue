<script setup>
import { computed } from "vue";
import BarcodeScannerDialog from "@/components/dialogs/BarcodeScannerDialog.vue";
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import AppEmptyState from "@/components/AppEmptyState.vue";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: String, default: undefined },
  orderBy: { type: String, default: "asc" },
  productWithError: { type: [Number, null], default: null },
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  isRestaurant: { type: Boolean, default: false },
  editingProductId: { type: [Number, null], default: null },
  editingBarcode: { type: String, default: "" },
  editingLaboratoryId: { type: [Number, null], default: null },
  editingOriginId: { type: [Number, null], default: null },
  isSaving: { type: Boolean, default: false },
  isScannerVisible: { type: Boolean, default: false },
  searchInput: { type: String, default: "" },
});

const emit = defineEmits([
  "update:editingBarcode",
  "update:editingLaboratoryId",
  "update:editingOriginId",
  "update:isScannerVisible",
  "update:options",
  "start-edit",
  "cancel-edit",
  "save-inline-edit",
  "open-scanner",
  "handle-scan",
  "search-laboratory",
  "search-origin",
  "create-laboratory",
  "create-origin",
]);

const isMissing = (product, field) => {
  if (!product) return false;
  if (field === "barcode") return !product.barcode;
  if (field === "laboratory") return !product.laboratory_id;
  if (field === "origin") return !props.isRestaurant && !product.origin_id;
  return false;
};
</script>

<template>
  <div class="d-block d-sm-none pa-2">
    <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
    
    <div v-if="props.products.length === 0 && !props.loading" class="text-center py-8 text-disabled">
      <AppEmptyState
        title="¡Todo Completo!"
        message="No se encontraron productos con datos incompletos."
        icon="tabler-circle-check"
      />
    </div>

    <div v-else class="d-flex flex-column gap-2">
      <VCard
        v-for="item in props.products"
        :key="item.id"
        class="product-mobile-card border rounded-lg bg-surface pa-3 shadow-none position-relative"
      >
        <div class="d-flex align-center justify-space-between mb-2">
          <div class="d-flex align-center gap-1 min-width-0">
            <span class="text-xs font-weight-black text-primary">#{{ item.id }}</span>
            <span class="text-disabled">|</span>
            <span class="text-xs font-weight-black text-primary uppercase truncate mobile-lab-truncate">
              {{ item.laboratory?.name || 'S/L' }}
            </span>
          </div>
        </div>

        <h4 class="text-xs font-weight-black text-high-emphasis uppercase leading-tight mb-1 text-truncate">
          {{ item.name }}
        </h4>

        <!-- Formulario de Edición Móvil In-line -->
        <div v-if="props.editingProductId === item.id" class="mt-3 pt-2 border-t d-flex flex-column gap-2">
          <VTextField
            v-if="isMissing(item, 'barcode')"
            :model-value="props.editingBarcode"
            density="compact"
            variant="outlined"
            label="Barcode"
            placeholder="Escribir barcode..."
            hide-details
            append-inner-icon="tabler-camera"
            :error="props.productWithError === item.id"
            @update:model-value="(val) => emit('update:editingBarcode', val)"
            @click:append-inner="emit('open-scanner', item)"
          />
          <VAutocomplete
            v-if="isMissing(item, 'laboratory')"
            :model-value="props.editingLaboratoryId"
            :items="props.laboratories"
            item-title="name"
            item-value="id"
            label="Laboratorio / Marca"
            density="compact"
            variant="outlined"
            hide-details
            placeholder="Buscar o crear..."
            @update:model-value="(val) => emit('update:editingLaboratoryId', val)"
            @update:search="(val) => emit('search-laboratory', val)"
            @keydown.enter.prevent="emit('create-laboratory')"
          />
          <VAutocomplete
            v-if="isMissing(item, 'origin') && !props.isRestaurant"
            :model-value="props.editingOriginId"
            :items="props.origins"
            item-title="name"
            item-value="id"
            label="Origen"
            density="compact"
            variant="outlined"
            hide-details
            placeholder="Buscar o crear..."
            @update:model-value="(val) => emit('update:editingOriginId', val)"
            @update:search="(val) => emit('search-origin', val)"
            @keydown.enter.prevent="emit('create-origin')"
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

        <div v-else class="d-flex align-center justify-end text-super-xs text-medium-emphasis mt-2 pt-2 border-t">
          <IconBtn
            color="primary"
            size="small"
            @click="emit('start-edit', item)"
          >
            <VIcon icon="tabler-edit" size="18" />
            <VTooltip activator="parent">Completar Datos</VTooltip>
          </IconBtn>
        </div>
      </VCard>
    </div>

    <!-- Paginación Móvil -->
    <div class="mt-4">
      <AppMobilePagination
        :page="props.page"
        :items-per-page="props.itemsPerPage"
        :total-items="props.totalProduct"
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
  max-inline-size: 200px;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
