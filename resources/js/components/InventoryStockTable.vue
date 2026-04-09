<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { formatPrice, formatDateSimple } from "@/utils/formatters";
import { computed } from "vue";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: [String, Array], default: () => [] },
  orderBy: { type: String, default: "asc" },
  viewType: { type: String, default: "individual" },
});

const isGroup = computed(() => props.viewType === "group");

const sortByModel = computed(() => {
  if (!props.sortBy) return [];
  const key = Array.isArray(props.sortBy) ? props.sortBy[0] : props.sortBy;
  return key ? [{ key, order: props.orderBy || "asc" }] : [];
});

const emit = defineEmits(["update:options"]);

const headers = [
  { title: "ID", key: "id", sortable: true, cellClass: "font-weight-black text-primary" },
  { title: "Producto / Grupo", key: "name", sortable: true, width: "400px" },
  { title: "Costo", key: "unit_cost", sortable: true, align: 'end' },
  { title: "Ventas", key: "total_sold_completed", sortable: true, align: 'center' },
  { title: "Stock", key: "lote_quantity", sortable: true, align: 'center' },
  { title: "Pref.", key: "preferencia_product", sortable: true, align: 'center' },
  { title: "Prom.", key: "promedio_calculado", sortable: true, align: 'center' },
  { title: "AO", key: "totalQuantityInAutoOrder", sortable: true, align: 'center' },
  { title: "Diferencia", key: "diferencia_product", sortable: true, align: 'center' },
];

const getDiffColor = (val) => {
  const num = parseFloat(val);
  if (isNaN(num) || num === 0) return 'secondary';
  return num > 0 ? 'success' : 'error';
};
</script>

<template>
  <VCard variant="flat" border>
    <!-- Vista de Escritorio (Tabla) -->
    <div class="d-none d-md-block">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.products"
        :items-length="props.totalProduct"
        :loading="props.loading"
        :sort-by="sortByModel"
        class="text-no-wrap"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.id="{ item }">
          <a
            v-if="!isGroup"
            :href="'/inventory/traceability?q=' + item.id"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.id }}
          </a>
          <span v-else class="text-disabled font-weight-black">G-{{ item.group_id || item.id }}</span>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-4 py-2">
            <VAvatar
              v-if="isGroup"
              size="32"
              variant="tonal"
              rounded
              color="primary"
              class="border elevation-1 flex-shrink-0"
            >
              <VIcon icon="tabler-users-group" size="20" />
            </VAvatar>

            <div class="d-flex flex-column truncate" style="max-inline-size: 350px;">
              <span class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate">
                {{ item.name || 'N/A' }}
                <span v-if="!isGroup && item.is_colombian_origin == 1" class="text-info font-weight-black"> (COL)</span>
              </span>
              <div v-if="!isGroup" class="d-flex align-center gap-1 text-super-xs mt-1">
                <span class="text-disabled truncate" style="max-inline-size: 180px;">{{ item.active_ingredient || "" }}</span>
                <span class="text-disabled">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 120px;">
                  {{ item.laboratory?.name || 'S/L' }}
                </span>
              </div>
              <div v-else class="text-super-xs mt-1 text-disabled"> Consolidado de grupo </div>
            </div>
          </div>
        </template>

        <template #item.unit_cost="{ item }">
          <span class="font-weight-black text-high-emphasis">{{ formatPrice(item.unit_cost) }}</span>
        </template>

        <template #item.lote_quantity="{ item }">
          <VChip
            :color="item.lote_quantity > 0 ? 'success' : 'error'"
            size="x-small"
            label
            variant="flat"
            class="font-weight-black"
          >
            {{ item.lote_quantity }}
          </VChip>
        </template>

        <template #item.preferencia_product="{ item }">
          <span class="font-weight-bold">{{ parseFloat(item.preferencia_product || 0).toFixed(2) }}</span>
        </template>

        <template #item.promedio_calculado="{ item }">
          <span class="font-weight-bold text-disabled">{{ parseFloat(item.promedio_calculado || 0).toFixed(2) }}</span>
        </template>

        <template #item.diferencia_product="{ item }">
          <VChip
            v-if="item.diferencia_product != null && item.diferencia_product != ''"
            :color="getDiffColor(item.diferencia_product)"
            size="x-small"
            variant="flat"
            class="font-weight-black px-2 shadow-sm"
          >
            {{ parseFloat(item.diferencia_product) > 0 ? "+" : "" }}{{ Math.ceil(parseFloat(item.diferencia_product)) }}
          </VChip>
          <span v-else class="text-disabled font-weight-bold">0</span>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil (Tarjetas) -->
    <div class="d-block d-md-none pa-2 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.products.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No se encontraron productos registrados.
      </div>

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.products"
          :key="item.id"
          variant="flat"
          class="border mb-1 overflow-hidden premium-card"
        >
          <div class="pa-4">
            <!-- Header de Tarjeta -->
            <div class="d-flex gap-3 align-start mb-3">
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-center justify-space-between mb-1 text-truncate">
                  <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate">
                    <a
                      v-if="!isGroup"
                      :href="'/inventory/traceability?q=' + item.id"
                      target="_blank"
                      class="text-decoration-none text-primary"
                    >
                      #{{ item.id }}
                    </a>
                    <span v-else class="text-primary font-weight-black">G-{{ item.group_id || item.id }}</span>
                    <span class="mx-1 text-disabled">|</span>
                    {{ item.name }}
                  </h3>
                </div>
                <div v-if="!isGroup" class="d-flex align-center flex-wrap gap-x-2 text-super-xs mt-1">
                  <span class="text-medium-emphasis font-weight-medium text-truncate" style="max-inline-size: 140px;">{{ item.active_ingredient || '' }}</span>
                  <span class="text-disabled">|</span>
                  <span class="text-primary font-weight-black text-uppercase text-truncate" style="max-inline-size: 100px;">{{ item.laboratory?.name || 'S/L' }}</span>
                </div>
                <div v-else class="text-super-xs mt-1 text-disabled"> Consolidado de grupo </div>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <!-- Grid de Información -->
            <div class="bg-var-theme-background-light px-3 py-2 rounded mb-3 border-dashed-thin">
              <div class="d-flex align-center justify-space-between mb-2">
                <div class="d-flex flex-column">
                  <span class="text-super-xs text-disabled text-uppercase font-weight-black">Stock Actual</span>
                  <VChip :color="item.lote_quantity > 0 ? 'success' : 'error'" size="x-small" label class="font-weight-black mt-1" variant="flat">
                    {{ item.lote_quantity }}
                  </VChip>
                </div>
                <div class="d-flex flex-column text-right">
                  <span class="text-super-xs text-disabled text-uppercase font-weight-black">Costo Unidad</span>
                  <span class="text-sm font-weight-black text-high-emphasis mt-1">{{ formatPrice(item.unit_cost) }}</span>
                </div>
              </div>

              <div class="d-flex align-center justify-space-between pt-1 border-t border-opacity-10 mt-1">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Diferencia</span>
                <VChip
                  :color="getDiffColor(item.diferencia_product)"
                  size="x-small"
                  variant="tonal"
                  class="font-weight-black"
                >
                  {{ parseFloat(item.diferencia_product) > 0 ? '+' : '' }}{{ Math.ceil(parseFloat(item.diferencia_product || 0)) }}
                </VChip>
              </div>
            </div>

            <!-- Footer con Proyecciones -->
            <div class="mt-3 bg-var-theme-background-light rounded pa-2 d-flex justify-space-between align-center border-s-4 border-warning">
              <div class="text-center flex-1">
                <span class="text-super-xs text-disabled d-block uppercase font-weight-black">Pref.</span>
                <span class="text-caption font-weight-black">{{ parseFloat(item.preferencia_product || 0).toFixed(1) }}</span>
              </div>
              <VDivider vertical class="mx-2" />
              <div class="text-center flex-1">
                <span class="text-super-xs text-disabled d-block uppercase font-weight-black">Prom.</span>
                <span class="text-caption font-weight-black">{{ parseFloat(item.promedio_calculado || 0).toFixed(1) }}</span>
              </div>
              <VDivider vertical class="mx-2" />
              <div class="text-center flex-1">
                <span class="text-super-xs text-disabled d-block uppercase font-weight-black">A.O.</span>
                <span class="text-caption font-weight-black text-primary">{{ item.totalQuantityInAutoOrder || 0 }}</span>
              </div>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="d-flex justify-center mt-4 pb-2">
         <AppMobilePagination
            :page="props.page"
            :items-per-page="props.itemsPerPage"
            :total-items="props.totalProduct"
            :loading="props.loading"
            :sort-by="typeof props.sortBy === 'string' ? props.sortBy : (props.sortBy[0]?.key || undefined)"
            :order-by="props.orderBy"
            @change="(options) => emit('update:options', options)"
          />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.bg-light {
  background-color: #f8fafc !important;
}

.bg-var-theme-background-light {
  background-color: rgba(var(--v-border-color), 0.05);
}

.premium-card {
  border-radius: 12px !important;
  transition: transform 0.2s ease;
}

.premium-card:active {
  transform: scale(0.98);
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }

.leading-tight { line-height: 1.2 !important; }

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}

.flex-1 { flex: 1; }
</style>
