<script setup>
import { computed } from "vue";
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import AppEmptyState from "@/components/AppEmptyState.vue";
import { formatDateSimple } from "@/utils/formatters";
import { useBrandingStore } from "@/stores/useBrandingStore";

const props = defineProps({
  products: { type: Array, required: true },
  totalProducts: { type: Number, required: true },
  loading: { type: Boolean, default: false },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "adjust-lots"]);
const brandingStore = useBrandingStore();

const isRestaurant = computed(() => brandingStore.settings?.business_type === "restaurant");
const isMiniMarket = computed(() => brandingStore.settings?.business_type === "minimarket");

const headers = computed(() => [
  { 
    title: "ID", 
    key: "id", 
    sortable: true,
    cellClass: "font-weight-black text-primary",
  },
  { 
    title: "PRODUCTO", 
    key: "name", 
    sortable: true,
    width: "45%",
  },
  { 
    title: "EXP.", 
    key: "next_expiration", 
    sortable: false,
  },
  { 
    title: "STOCK", 
    key: "stock_calculado", 
    sortable: true, 
    align: "end" 
  },
  { 
    title: "Acciones", 
    key: "actions", 
    sortable: false, 
    align: "center" 
  },
]);

const nextExpirationDate = (product) => {
  if (!product.lots || !Array.isArray(product.lots) || product.lots.length === 0) return "N/A";
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const validLots = product.lots.filter((lot) => {
    if (!lot.expiration_date) return false;
    const expirationDate = new Date(lot.expiration_date);
    return !isNaN(expirationDate.getTime()) && expirationDate >= today;
  });
  if (validLots.length === 0) return product.ultima_fecha_vencimiento || "EXPIRADO";
  validLots.sort((a, b) => new Date(a.expiration_date) - new Date(b.expiration_date));
  const closestDate = new Date(validLots[0].expiration_date);
  return formatDateSimple(closestDate);
};

const formatStock = (item) => {
  const stock = Number(item.stock_calculado ?? 0);
  if (isMiniMarket.value) {
    return Math.round(stock).toString();
  }
  if (!isRestaurant.value) {
    return stock % 1 === 0 ? stock.toString() : stock.toFixed(2).replace(".", ",");
  }
  return stock.toString();
};
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
    <!-- Desktop Table -->
    <div class="d-none d-sm-block">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.products"
        :items-length="props.totalProducts"
        :loading="props.loading"
        class="text-no-wrap"
        density="compact"
        item-value="id"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #no-data>
          <AppEmptyState
            title="¡Todo Lotificado!"
            message="No hay productos pendientes de asignación de lotes en el catálogo."
            icon="tabler-package-off"
          />
        </template>

        <!-- ID sin símbolo # -->
        <template #item.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.id"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.id }}
          </a>
        </template>

        <!-- Producto con activo/laboratorio idéntico a ProductTable -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-3 py-2">
            <div class="d-flex flex-column min-width-0">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate"
                :class="{ 
                  'text-warning': item.psychotropic == 1 || item.psychotropic === true
                }"
                style="max-inline-size: 320px;"
                :title="item.name"
              >
                {{ item.name?.toUpperCase() || "—" }}
                <span v-if="item.iva == 1 || item.iva === true" class="text-xs text-disabled"> (G)</span>
                <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true" class="text-xs text-disabled"> (COL)</span>
              </span>
              <div class="d-flex align-center gap-1 text-super-xs">
                <span class="text-disabled truncate" style="max-inline-size: 200px;">
                  {{ item.active_ingredient || item.presentation || 'Sin Especificación' }}
                </span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 150px;">
                  {{ item.laboratory?.name || 'S/L' }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <!-- EXP. -->
        <template #item.next_expiration="{ item }">
          <span class="text-xs font-weight-medium">{{ nextExpirationDate(item) }}</span>
        </template>

        <!-- STOCK con chip y desglose flotante al pasar el cursor -->
        <template #item.stock_calculado="{ item }">
          <div class="text-end">
            <VMenu
              v-if="item.lots && item.lots.length > 0 && brandingStore.settings?.enable_lots !== false"
              open-on-hover
              location="bottom end"
              offset="8px"
            >
              <template #activator="{ props: menuProps }">
                <VChip
                  v-bind="menuProps"
                  :color="item.stock_calculado > 0 ? 'success' : 'error'"
                  label
                  size="x-small"
                  variant="tonal"
                  class="font-weight-black cursor-pointer hover-chip"
                >
                  {{ formatStock(item) }}
                  <VIcon icon="tabler-info-circle" size="12" class="ms-1" />
                </VChip>
              </template>
              <VCard min-width="280" class="rounded-xl border shadow-lg pa-3">
                <div class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-flex align-center gap-1">
                  <VIcon icon="tabler-clipboard-list" size="14" />
                  Desglose de Lotes
                </div>
                <VDivider class="mb-2" />
                <div style="max-height: 180px; overflow-y: auto;">
                  <div 
                    v-for="lot in item.lots" 
                    :key="lot.id"
                    class="d-flex align-center justify-space-between py-1 border-bottom-light"
                  >
                    <div class="d-flex flex-column text-left">
                      <span class="text-xs font-weight-bold text-high-emphasis">Lote: {{ lot.lot_number }}</span>
                      <span class="text-super-xs text-disabled">Exp: {{ formatDateSimple(lot.expiration_date) }}</span>
                    </div>
                    <VChip size="x-small" label color="secondary" variant="flat" class="font-weight-black">
                      {{ lot.quantity }}
                    </VChip>
                  </div>
                </div>
              </VCard>
            </VMenu>
            <VChip
              v-else
              :color="item.stock_calculado > 0 ? 'success' : 'error'"
              label
              size="x-small"
              variant="tonal"
              class="font-weight-black"
            >
              {{ formatStock(item) }}
            </VChip>
          </div>
        </template>

        <!-- Acciones con IconBtn limpio sin fondo -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-1">
            <IconBtn
              color="primary"
              size="small"
              @click.stop="emit('adjust-lots', item)"
            >
              <VIcon icon="tabler-package" size="18" />
              <VTooltip activator="parent" location="top">Crear / Ajustar Lotes</VTooltip>
            </IconBtn>
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Mobile Cards -->
    <div class="d-block d-sm-none pa-2">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.products.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        <AppEmptyState
          title="¡Todo Lotificado!"
          message="No hay productos pendientes de asignación de lotes en el catálogo."
          icon="tabler-package-off"
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
              <span class="text-xs font-weight-black text-primary uppercase truncate" style="max-inline-size: 150px;">
                {{ item.laboratory?.name || 'S/L' }}
              </span>
            </div>
            <VChip
              :color="item.stock_calculado > 0 ? 'success' : 'error'"
              label
              size="x-small"
              variant="tonal"
              class="font-weight-black"
            >
              {{ formatStock(item) }} UNDS
            </VChip>
          </div>

          <h4 class="text-xs font-weight-black text-high-emphasis uppercase leading-tight mb-1 text-truncate">
            {{ item.name }}
          </h4>

          <div class="d-flex align-center justify-space-between text-super-xs text-medium-emphasis mt-2 pt-2 border-t">
            <span>Exp: {{ nextExpirationDate(item) }}</span>
            <IconBtn
              color="primary"
              size="small"
              @click.stop="emit('adjust-lots', item)"
            >
              <VIcon icon="tabler-package" size="18" />
              <VTooltip activator="parent">Asignar Lotes</VTooltip>
            </IconBtn>
          </div>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="mt-4">
        <AppMobilePagination
          :page="props.page"
          :items-per-page="props.itemsPerPage"
          :total-items="props.totalProducts"
          :loading="props.loading"
          @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
        />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.hover-chip:hover {
  filter: brightness(0.95);
  box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}

.border-bottom-light {
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08);
}
.border-bottom-light:last-child {
  border-bottom: none;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs {
  font-size: 0.75rem !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
