<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import TraceabilityMovementDetailsDialog from "@/components/dialogs/TraceabilityMovementDetailsDialog.vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { formatDateSimple } from "@/utils/formatters";
import { computed, ref } from "vue";

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => (brandingStore.settings.business_type === 'restaurant' || brandingStore.settings.business_type === 'minimarket'));

const props = defineProps({
  sales: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalSales: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const formatStockValue = (val, product) => {
  if (val === undefined || val === null) return "0";
  const numVal = parseFloat(val);
  if (isRestaurant.value && product && (product.unit_of_measure === 'g' || product.unit_of_measure === 'ml') && product.presentation > 0) {
    const totalQty = Math.round(numVal * product.presentation);
    return `${totalQty} ${product.unit_of_measure}`;
  }
  return numVal.toString();
};

const formatQuantityValue = (val, product) => {
  if (val === undefined || val === null) return "0";
  const numVal = parseFloat(val);
  const sign = numVal > 0 ? "+" : "";
  if (isRestaurant.value && product && (product.unit_of_measure === 'g' || product.unit_of_measure === 'ml') && product.presentation > 0) {
    const totalQty = Math.round(numVal * product.presentation);
    return `${sign}${totalQty} ${product.unit_of_measure}`;
  }
  return `${sign}${numVal}`;
};

const emit = defineEmits(["update:options"]);

const showDetailsDialog = ref(false);
const selectedMovementId = ref(null);

const handleReferenceClick = (item) => {
  selectedMovementId.value = item.id;
  showDetailsDialog.value = true;
};

const getUserDisplayName = (user) => {
  if (!user) return "N/A";
  
  let name = "";
  let lastName = "";
  
  if (user.employee?.name) {
    name = user.employee.name.trim().split(" ")[0];
  }
  
  if (user.employee?.last_name) {
    lastName = user.employee.last_name.trim().split(" ")[0];
  }

  if (name || lastName) {
    return `${name} ${lastName}`.trim();
  }
  
  const fallback = user.username || user.email || "N/A";
  return fallback.split("@")[0];
};

const headers = [
  { title: "ID", key: "id", sortable: true, cellClass: "font-weight-black text-primary" },
  { title: "Producto", key: "product.name", sortable: true, width: "350px" },
  { title: "S. Ant", key: "stock_before", sortable: true, align: "center" },
  { title: "Cant.", key: "quantity", sortable: false, align: "center" },
  { title: "S. Fin", key: "stock_after", sortable: true, align: "center" },
  { title: "Fecha", key: "movement_date", sortable: true },
  { title: "Tipo", key: "movement_type", sortable: true },
  { title: "Operador", key: "user.email", sortable: true },
  { title: "Acción", key: "reference", sortable: true, align: "center" },
];
</script>

<template>
  <VCard variant="flat">
    <!-- Vista de Escritorio (Tabla) -->
    <div class="d-none d-md-block">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.sales"
        :items-length="props.totalSales"
        :loading="props.loading"
        class="text-no-wrap"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #no-data>
          <div class="py-12 d-flex flex-column align-center justify-center gap-y-2">
            <VAvatar size="64" color="primary" variant="tonal" class="mb-2">
              <VIcon icon="tabler-database-x" size="32" class="text-primary" />
            </VAvatar>
            <h4 class="text-base font-weight-black text-high-emphasis">Sin movimientos de trazabilidad</h4>
            <p class="text-xs text-medium-emphasis">No se registraron movimientos que coincidan con los filtros aplicados.</p>
          </div>
        </template>

        <template #item.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.product_id"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.product_id }}
          </a>
        </template>

        <template #item.product.name="{ item }">
          <div class="d-flex align-center gap-x-4 py-2">
            <VAvatar
              v-if="item.product?.photo_url"
              size="44"
              variant="tonal"
              rounded
              :image="item.product.photo_url"
              class="border elevation-1 flex-shrink-0"
            />
            <div class="d-flex flex-column truncate" style="max-inline-size: 300px;">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase truncate"
                :class="{ 'text-warning': item.product?.psychotropic }"
              >
                {{ item.product?.name || 'N/A' }}
                <span v-if="item.dish" class="text-primary font-weight-black text-none"> - {{ item.dish.name }}</span>
                <span v-if="item.product?.iva"> (G)</span>
                <span v-if="item.product?.is_colombian_origin"> (COL)</span>
              </span>
              <div class="d-flex align-center gap-1 text-super-xs">
                <span class="text-disabled truncate" style="max-inline-size: 150px;">
                  {{ isRestaurant ? `${item.product?.presentation || ''} ${item.product?.unit_of_measure || ''}` : (item.product?.active_ingredient || "") }}
                </span>
                <span class="text-disabled">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 100px;">
                  {{ item.product?.laboratory?.name || 'S/L' }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <template #item.stock_before="{ item }">
          <span class="text-body-2 font-weight-bold">
            {{ formatStockValue(item.global_stock_before !== undefined ? item.global_stock_before : item.stock_before, item.product) }}
          </span>
        </template>

        <template #item.stock_after="{ item }">
          <span class="text-body-2 font-weight-bold">
            {{ formatStockValue(item.global_stock_after !== undefined ? item.global_stock_after : item.stock_after, item.product) }}
          </span>
        </template>

        <template #item.movement_date="{ item }">
          <span class="text-no-wrap text-body-2">{{ formatDateSimple(item.movement_date) }}</span>
        </template>

        <template #item.user.email="{ item }">
          <span class="text-body-2">{{ getUserDisplayName(item.user) }}</span>
        </template>

        <template #item.quantity="{ item }">
          <VChip
            :color="item.quantity > 0 ? 'success' : 'error'"
            size="small"
            label
            variant="tonal"
            class="font-weight-bold"
          >
            {{ formatQuantityValue(item.quantity, item.product) }}
          </VChip>
        </template>

        <template #item.movement_type="{ item }">
          <span class="text-caption text-uppercase font-weight-medium">{{ item.movement_type }}</span>
        </template>

        <template #item.reference="{ item }">
          <VBtn
            icon
            variant="text"
            color="primary"
            size="small"
            @click="handleReferenceClick(item)"
          >
            <VIcon icon="tabler-eye" />
            <VTooltip activator="parent" location="top">Ver Detalles de Acción</VTooltip>
          </VBtn>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil (Tarjetas) -->
    <div class="d-block d-md-none pa-2">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.sales.length === 0 && !props.loading" class="text-center py-10">
        <VAvatar size="56" color="primary" variant="tonal" class="mb-3">
          <VIcon icon="tabler-database-x" size="28" class="text-primary" />
        </VAvatar>
        <h4 class="text-sm font-weight-black text-high-emphasis">Sin movimientos de trazabilidad</h4>
        <p class="text-xs text-medium-emphasis px-4 mt-1">No se encontraron registros de inventario con los criterios de búsqueda actuales.</p>
      </div>

      <div v-else class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.sales"
          :key="item.id"
          variant="flat"
          class="border mb-2 overflow-hidden"
          style="border-radius: 8px !important;"
        >
          <div class="pa-3">
            <div class="d-flex gap-3 align-start mb-2">
              <VAvatar
                v-if="item.product?.photo_url"
                size="44"
                variant="tonal"
                rounded
                :image="item.product.photo_url"
                class="flex-shrink-0 mt-1 border"
              />
              <div class="flex-grow-1 min-width-0">
                <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight text-truncate">
                  <a
                    :href="'/inventory/traceability?q=' + item.product_id"
                    class="text-decoration-none text-primary"
                  >
                    #{{ item.product_id }}
                  </a>
                  <span class="mx-1 text-disabled">|</span>
                  {{ item.product?.name || 'S/N' }}
                  <span v-if="item.dish" class="text-primary font-weight-black text-none"> - {{ item.dish.name }}</span>
                </h3>
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs mt-1">
                  <span class="text-medium-emphasis font-weight-medium text-truncate" style="max-inline-size: 150px;">
                    {{ isRestaurant ? `${item.product?.presentation || ''} ${item.product?.unit_of_measure || ''}` : (item.product?.active_ingredient || '') }}
                  </span>
                  <span class="text-disabled">|</span>
                  <span class="text-primary font-weight-black text-uppercase text-truncate" style="max-inline-size: 120px;">{{ item.product?.laboratory?.name || 'S/L' }}</span>
                </div>
              </div>
            </div>
 
            <VDivider class="my-2 border-opacity-10" />
            <div class="d-flex justify-space-between align-center bg-var-theme-background-light px-3 py-2 rounded border-dashed-thin mb-2">
              <div class="d-flex flex-column text-center">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Stock Ant</span>
                <span class="text-base font-weight-black text-medium-emphasis">
                  {{ formatStockValue(item.global_stock_before !== undefined ? item.global_stock_before : item.stock_before, item.product) }}
                </span>
              </div>
              <div class="d-flex flex-column align-center">
                <VIcon 
                  :icon="item.quantity > 0 ? 'tabler-circle-plus' : 'tabler-circle-minus'" 
                  :color="item.quantity > 0 ? 'success' : 'error'"
                  size="20"
                />
                <span :class="item.quantity > 0 ? 'text-success' : 'text-error'" class="text-sm font-weight-black">
                   {{ formatQuantityValue(item.quantity, item.product) }}
                </span>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Stock Fin</span>
                <span class="text-base font-weight-black" :class="(item.global_stock_after !== undefined ? item.global_stock_after : item.stock_after) > 0 ? 'text-primary' : 'text-error'">
                  {{ formatStockValue(item.global_stock_after !== undefined ? item.global_stock_after : item.stock_after, item.product) }}
                </span>
              </div>
            </div>

            <div class="bg-var-theme-background pa-2 rounded-sm border-s-4 border-primary">
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Operador</span>
                <span class="text-xs font-weight-bold text-medium-emphasis uppercase">{{ getUserDisplayName(item.user) }}</span>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Fecha Mov.</span>
                <span class="text-xs font-weight-black text-high-emphasis">{{ formatDateSimple(item.movement_date) }}</span>
              </div>
            </div>
          </div>
 
          <div class="d-flex border-t border-opacity-10">
            <VBtn 
              block 
              color="primary" 
              variant="flat" 
              class="rounded-0 font-weight-black"
              height="44"
              prepend-icon="tabler-eye" 
              @click="handleReferenceClick(item)"
            >
              VER ACCIÓN ({{
                item.order_id != null
                  ? `ORD-${item.order_id}`
                  : item.invoice_id != null
                  ? (item.invoice?.invoice_number ?? `INV-${item.invoice_id}`)
                  : `ID-${item.id}`
              }})
            </VBtn>
          </div>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div v-if="props.sales.length > 0" class="d-flex justify-center mt-4">
         <AppMobilePagination
            :page="props.page"
            :items-per-page="props.itemsPerPage"
            :total-items="props.totalSales"
            :loading="props.loading"
            @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
          />
      </div>
    </div>
  </VCard>

  <TraceabilityMovementDetailsDialog
    v-model="showDetailsDialog"
    :movement-id="selectedMovementId"
  />
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-theme-primary), 0.05);
}

.bg-var-theme-background-light {
  background-color: rgba(var(--v-border-color), 0.05);
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }

.leading-tight { line-height: 1.25 !important; }

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>
