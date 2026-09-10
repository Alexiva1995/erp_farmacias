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
  { title: "id", key: "id", sortable: true, cellClass: "font-weight-black text-primary", width: "80px" },
  { title: "Producto", key: "product.name", sortable: true, width: "350px" },
  { title: "Transición Stock", key: "stock_transition", sortable: false, align: "center", width: "160px" },
  { title: "Cant.", key: "quantity", sortable: false, align: "center", width: "90px" },
  { title: "Fecha Mov.", key: "movement_date", sortable: true, width: "130px" },
  { title: "Tipo", key: "movement_type", sortable: true, align: "center", width: "110px" },
  { title: "Operador", key: "user.email", sortable: true, width: "130px" },
  { title: "Acción", key: "reference", sortable: false, align: "center", width: "80px" },
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
        density="compact"
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
          <a
            href="#"
            class="text-decoration-none font-weight-black text-primary"
            @click.prevent="emit('filter-product', item.product_id)"
          >
            {{ item.product_id }}
          </a>
        </template>

        <template #item.product.name="{ item }">
          <div class="d-flex align-center gap-x-2 py-1">
            <div class="d-flex flex-column truncate" style="max-inline-size: 330px;">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate"
                :class="{ 'text-warning': item.product?.psychotropic }"
                :title="item.product?.name"
              >
                {{ item.product?.name?.toUpperCase() || 'N/A' }}
                <span v-if="item.dish" class="text-primary font-weight-black text-none"> - {{ item.dish.name }}</span>
                <span v-if="item.product?.iva" class="text-xs text-disabled"> (G)</span>
                <span v-if="item.product?.is_colombian_origin" class="text-xs text-disabled"> (COL)</span>
              </span>
              <div class="d-flex align-center flex-wrap gap-1 text-super-xs">
                <span class="text-disabled truncate" style="max-inline-size: 150px;">
                  {{ item.product?.active_ingredient || "Sin principio" }}
                </span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 130px;">
                  {{ item.product?.laboratory?.name || 'S/L' }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <template #item.stock_transition="{ item }">
          <div class="d-flex align-center justify-center gap-1 font-weight-bold">
            <span class="text-medium-emphasis text-body-2">
              {{ formatStockValue(item.global_stock_before) }}
            </span>
            <VIcon icon="tabler-arrow-narrow-right" size="18" class="text-disabled mx-1" />
            <span class="text-body-2 font-weight-black" :class="item.global_stock_after > 0 ? 'text-primary' : 'text-error'">
              {{ formatStockValue(item.global_stock_after) }}
            </span>
          </div>
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

        <template #item.movement_date="{ item }">
          <span class="text-no-wrap text-body-2">{{ formatDateSimple(item.movement_date) }}</span>
        </template>

        <template #item.movement_type="{ item }">
          <VChip size="x-small" variant="tonal" color="primary" class="text-uppercase font-weight-black">
            {{ item.movement_type }}
          </VChip>
        </template>

        <template #item.user.email="{ item }">
          <span class="text-body-2 font-weight-medium truncate" style="max-inline-size: 120px;" :title="getUserDisplayName(item.user)">
            {{ getUserDisplayName(item.user) }}
          </span>
        </template>

        <template #item.reference="{ item }">
          <VBtn
            icon
            variant="text"
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
            <div class="d-flex gap-2 align-start mb-2">
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-center gap-1">
                  <a
                    href="#"
                    class="text-caption font-weight-black text-primary text-decoration-none"
                    @click.prevent="emit('filter-product', item.product_id)"
                  >
                    {{ item.product_id }}
                  </a>
                  <span class="text-disabled">|</span>
                  <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate mb-0">
                    {{ item.product?.name || 'S/N' }}
                  </h3>
                </div>
                <div class="d-flex align-center flex-wrap gap-1 text-super-xs mt-1">
                  <span class="text-disabled truncate" style="max-inline-size: 140px;">
                    {{ item.product?.active_ingredient || 'Sin principio' }}
                  </span>
                  <span class="text-disabled mx-1">|</span>
                  <span class="text-primary font-weight-black text-uppercase text-truncate" style="max-inline-size: 120px;">
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

.text-super-xs {
  font-size: 0.72rem !important;
  line-height: 1.1;
}
</style>
