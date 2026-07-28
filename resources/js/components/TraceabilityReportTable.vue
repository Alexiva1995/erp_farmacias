<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import TraceabilityMovementDetailsDialog from "@/components/dialogs/TraceabilityMovementDetailsDialog.vue";
import { formatDateSimple } from "@/utils/formatters";
import { ref } from "vue";

const props = defineProps({
  sales: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalSales: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "filter-product"]);

const showDetailsDialog = ref(false);
const selectedMovementId = ref(null);

const handleReferenceClick = (item) => {
  selectedMovementId.value = item.id;
  showDetailsDialog.value = true;
};

const formatStockValue = (val) => {
  if (val === undefined || val === null) return "0";
  const numVal = parseFloat(val);
  return Number.isInteger(numVal) ? numVal.toString() : numVal.toFixed(2);
};

const formatQuantityValue = (val) => {
  if (val === undefined || val === null) return "0";
  const numVal = parseFloat(val);
  const sign = numVal > 0 ? "+" : "";
  const strVal = Number.isInteger(numVal) ? numVal.toString() : numVal.toFixed(2);
  return `${sign}${strVal}`;
};

const getUserDisplayName = (user) => {
  if (!user) return "N/A";
  if (user.employee?.name || user.employee?.last_name) {
    const name = user.employee.name ? user.employee.name.trim().split(" ")[0] : "";
    const lastName = user.employee.last_name ? user.employee.last_name.trim().split(" ")[0] : "";
    return `${name} ${lastName}`.trim() || "N/A";
  }
  const fallback = user.username || user.email || "N/A";
  return fallback.split("@")[0];
};

const headers = [
  { title: "ID Prod", key: "id", sortable: true, cellClass: "font-weight-black text-primary", width: "90px" },
  { title: "Producto", key: "product.name", sortable: true, width: "320px" },
  { title: "S. Ant", key: "stock_before", sortable: true, align: "center" },
  { title: "Cant.", key: "quantity", sortable: false, align: "center" },
  { title: "S. Fin", key: "stock_after", sortable: true, align: "center" },
  { title: "Fecha Mov.", key: "movement_date", sortable: true },
  { title: "Tipo", key: "movement_type", sortable: true },
  { title: "Operador", key: "user.email", sortable: true },
  { title: "Acción", key: "reference", sortable: false, align: "center" },
];
</script>

<template>
  <VCard variant="flat" class="border rounded-lg overflow-hidden">
    <!-- Vista de Escritorio (Tabla Vuetify 3) -->
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
            <p class="text-xs text-medium-emphasis mb-0">No se encontraron movimientos registrados con los filtros seleccionados.</p>
          </div>
        </template>

        <template #item.id="{ item }">
          <VBtn
            variant="text"
            color="primary"
            size="small"
            class="px-1 font-weight-black text-decoration-none"
            @click.prevent="emit('filter-product', item.product_id)"
          >
            #{{ item.product_id }}
          </VBtn>
        </template>

        <template #item.product.name="{ item }">
          <div class="d-flex align-center gap-x-3 py-2">
            <VAvatar
              v-if="item.product?.photo_url"
              size="40"
              variant="tonal"
              rounded
              :image="item.product.photo_url"
              class="border elevation-1 flex-shrink-0"
            />
            <div class="d-flex flex-column truncate" style="max-inline-size: 280px;">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase truncate"
                :class="{ 'text-warning': item.product?.psychotropic }"
              >
                {{ item.product?.name || 'N/A' }}
                <span v-if="item.dish" class="text-primary font-weight-black text-none"> - {{ item.dish.name }}</span>
                <span v-if="item.product?.iva" class="text-caption text-medium-emphasis"> (G)</span>
                <span v-if="item.product?.is_colombian_origin" class="text-caption text-info"> (COL)</span>
              </span>
              <div class="d-flex align-center gap-1 text-caption text-medium-emphasis">
                <span class="truncate" style="max-inline-size: 140px;">
                  {{ item.product?.active_ingredient || "Sin principio" }}
                </span>
                <span>|</span>
                <span class="text-primary font-weight-bold text-uppercase truncate" style="max-inline-size: 100px;">
                  {{ item.product?.laboratory?.name || 'S/L' }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <template #item.stock_before="{ item }">
          <span class="text-body-2 font-weight-bold">
            {{ formatStockValue(item.global_stock_before) }}
          </span>
        </template>

        <template #item.stock_after="{ item }">
          <span class="text-body-2 font-weight-bold" :class="item.global_stock_after > 0 ? 'text-primary' : 'text-error'">
            {{ formatStockValue(item.global_stock_after) }}
          </span>
        </template>

        <template #item.movement_date="{ item }">
          <span class="text-no-wrap text-body-2">{{ formatDateSimple(item.movement_date) }}</span>
        </template>

        <template #item.user.email="{ item }">
          <span class="text-body-2 font-weight-medium">{{ getUserDisplayName(item.user) }}</span>
        </template>

        <template #item.quantity="{ item }">
          <VChip
            :color="item.quantity > 0 ? 'success' : 'error'"
            size="small"
            label
            variant="tonal"
            class="font-weight-black"
          >
            {{ formatQuantityValue(item.quantity) }}
          </VChip>
        </template>

        <template #item.movement_type="{ item }">
          <VChip size="x-small" variant="outlined" color="primary" class="text-uppercase font-weight-bold">
            {{ item.movement_type }}
          </VChip>
        </template>

        <template #item.reference="{ item }">
          <VBtn
            icon
            variant="tonal"
            color="primary"
            size="small"
            @click="handleReferenceClick(item)"
          >
            <VIcon icon="tabler-eye" size="18" />
            <VTooltip activator="parent" location="top">Ver Detalles de Acción</VTooltip>
          </VBtn>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil (Tarjetas Responsivas con Skeletons) -->
    <div class="d-block d-md-none pa-3">
      <!-- Loading Skeleton para Móvil -->
      <div v-if="props.loading" class="d-flex flex-column gap-3">
        <VSkeletonLoader
          v-for="n in 3"
          :key="n"
          type="list-item-avatar-three-line, actions"
          class="border rounded-lg"
        />
      </div>

      <!-- Estado Vacío -->
      <div v-else-if="props.sales.length === 0" class="text-center py-10">
        <VAvatar size="56" color="primary" variant="tonal" class="mb-3">
          <VIcon icon="tabler-database-x" size="28" class="text-primary" />
        </VAvatar>
        <h4 class="text-sm font-weight-black text-high-emphasis">Sin movimientos de trazabilidad</h4>
        <p class="text-xs text-medium-emphasis px-4 mt-1 mb-0">No se encontraron registros con los criterios aplicados.</p>
      </div>

      <!-- Listado de Tarjetas en Móvil -->
      <div v-else class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.sales"
          :key="item.id"
          variant="flat"
          class="border rounded-lg overflow-hidden"
        >
          <div class="pa-3">
            <div class="d-flex gap-3 align-start mb-2">
              <VAvatar
                v-if="item.product?.photo_url"
                size="44"
                variant="tonal"
                rounded
                :image="item.product.photo_url"
                class="flex-shrink-0 border"
              />
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-center gap-1">
                  <VBtn
                    variant="text"
                    color="primary"
                    size="x-small"
                    class="px-0 font-weight-black min-width-0"
                    @click.prevent="emit('filter-product', item.product_id)"
                  >
                    #{{ item.product_id }}
                  </VBtn>
                  <span class="text-disabled">|</span>
                  <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate mb-0">
                    {{ item.product?.name || 'S/N' }}
                  </h3>
                </div>
                <div class="d-flex align-center flex-wrap gap-x-2 text-caption text-medium-emphasis mt-1">
                  <span class="text-truncate" style="max-inline-size: 140px;">
                    {{ item.product?.active_ingredient || 'Sin principio' }}
                  </span>
                  <span>|</span>
                  <span class="text-primary font-weight-bold text-uppercase text-truncate" style="max-inline-size: 110px;">
                    {{ item.product?.laboratory?.name || 'S/L' }}
                  </span>
                </div>
              </div>
            </div>

            <VDivider class="my-2" />

            <div class="d-flex justify-space-between align-center bg-var-theme-background-light px-3 py-2 rounded border-dashed-thin mb-2">
              <div class="d-flex flex-column text-center">
                <span class="text-caption text-disabled text-uppercase font-weight-black">Stock Ant</span>
                <span class="text-sm font-weight-black text-medium-emphasis">
                  {{ formatStockValue(item.global_stock_before) }}
                </span>
              </div>
              <div class="d-flex flex-column align-center">
                <VChip
                  :color="item.quantity > 0 ? 'success' : 'error'"
                  size="x-small"
                  label
                  variant="tonal"
                  class="font-weight-black"
                >
                  {{ formatQuantityValue(item.quantity) }}
                </VChip>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-caption text-disabled text-uppercase font-weight-black">Stock Fin</span>
                <span class="text-sm font-weight-black" :class="item.global_stock_after > 0 ? 'text-primary' : 'text-error'">
                  {{ formatStockValue(item.global_stock_after) }}
                </span>
              </div>
            </div>

            <div class="pa-2 rounded bg-var-theme-background border-s-4 border-primary">
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-caption text-disabled text-uppercase font-weight-bold">Operador:</span>
                <span class="text-caption font-weight-bold text-high-emphasis">{{ getUserDisplayName(item.user) }}</span>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-caption text-disabled text-uppercase font-weight-bold">Fecha:</span>
                <span class="text-caption font-weight-black text-high-emphasis">{{ formatDateSimple(item.movement_date) }}</span>
              </div>
            </div>
          </div>

          <VBtn 
            block 
            color="primary" 
            variant="tonal" 
            class="rounded-0 font-weight-black"
            height="40"
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
.bg-var-theme-background {
  background-color: rgba(var(--v-theme-primary), 0.04);
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
</style>
