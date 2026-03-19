<script setup>
import { formatPrice } from "@/utils/formatters";
import { computed } from "vue";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: [String, Array], default: () => [] },
  orderBy: { type: String, default: "asc" },
});

const sortByModel = computed(() => {
  if (!props.sortBy) return [];
  const key = Array.isArray(props.sortBy) ? props.sortBy[0] : props.sortBy;
  return key ? [{ key, order: props.orderBy || "asc" }] : [];
});

const emit = defineEmits(["update:options"]);

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Producto", key: "name", sortable: true, width: "350px" },
  { title: "Laboratorio", key: "laboratory.name", sortable: false },
  { title: "Costo", key: "unit_cost", sortable: true, align: 'end' },
  { title: "Ventas", key: "total_sold_completed", sortable: true, align: 'center' },
  { title: "Stock", key: "lote_quantity", sortable: true, align: 'center' },
  { title: "Pref.", key: "preferencia_product", sortable: true, align: 'center' },
  { title: "Prom.", key: "promedio_calculado", sortable: true, align: 'center' },
  { title: "AO", key: "totalQuantityInAutoOrder", sortable: true, align: 'center' },
  { title: "Diferencia", key: "diferencia_product", sortable: true, align: 'center' },
];

const handleMobilePageChange = (newPage) => {
  emit('update:options', {
    page: newPage,
    itemsPerPage: props.itemsPerPage,
    sortBy: [],
  });
};

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
          <span class="font-weight-black text-primary">{{ item.id }}</span>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-4 py-2">
            <VAvatar
              v-if="item.photo_url"
              size="44"
              variant="tonal"
              rounded
              :image="item.photo_url"
              class="border elevation-1"
            />
            <div class="d-flex flex-column text-normal-white">
              <span class="text-subtitle-2 font-weight-black text-high-emphasis leading-tight uppercase">
                {{ item.name }}
                <span v-if="item.is_colombian_origin == 1" class="text-info"> (COL)</span>
              </span>
              <span class="text-super-xs font-weight-bold text-disabled">{{ item.active_ingredient }}</span>
            </div>
          </div>
        </template>

        <template #item.laboratory.name="{ item }">
          <span class="text-super-xs font-weight-black text-primary text-uppercase">
            {{ item.laboratory?.name || 'S/L' }}
          </span>
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
            {{ item.lote_quantity }} UNDS
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
      <VLinearProgress v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
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
              <VAvatar
                v-if="item.photo_url"
                size="48"
                variant="tonal"
                rounded
                :image="item.photo_url"
                class="flex-shrink-0 border"
              />
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-center justify-space-between mb-1">
                  <span class="text-primary font-weight-black text-xs">{{ item.id }}</span>
                  <VChip
                    :color="getDiffColor(item.diferencia_product)"
                    size="x-small"
                    variant="flat"
                    class="font-weight-black"
                  >
                    DIF: {{ Math.ceil(parseFloat(item.diferencia_product || 0)) }}
                  </VChip>
                </div>
                <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight mb-1 truncate">
                  {{ item.name }}
                </h3>
                <div class="text-super-xs text-disabled font-weight-bold uppercase truncate">
                  {{ item.active_ingredient }}
                </div>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <!-- Grid de Información -->
            <div class="d-grid mobile-stock-grid gap-3">
              <div class="stat-box">
                <span class="label">Laboratorio</span>
                <span class="value text-primary">{{ item.laboratory?.name || 'S/L' }}</span>
              </div>
              <div class="stat-box text-center">
                <span class="label">Costo</span>
                <span class="value">{{ formatPrice(item.unit_cost) }}</span>
              </div>
              <div class="stat-box text-right">
                <span class="label">Stock Actual</span>
                <VChip :color="item.lote_quantity > 0 ? 'success' : 'error'" size="x-small" label class="font-weight-black mt-1">
                  {{ item.lote_quantity }} UNDS
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
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalProduct / props.itemsPerPage)"
          :total-visible="3"
          density="compact"
          size="small"
          @update:model-value="handleMobilePageChange"
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

.mobile-stock-grid {
  display: grid;
  align-items: center;
  grid-template-columns: 1fr 1fr 1fr;
}

.stat-box .label {
  display: block;
  font-size: 0.6rem;
  font-weight: 900;
  color: rgba(var(--v-theme-on-surface), 0.45);
  text-transform: uppercase;
  margin-bottom: 2px;
}

.stat-box .value {
  font-size: 0.75rem;
  font-weight: 800;
  text-transform: uppercase;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.text-normal-white {
  overflow-wrap: break-word;
  white-space: normal;
}

.leading-tight {
  line-height: 1.2 !important;
}

.gap-3 {
  gap: 12px !important;
}

.flex-1 {
  flex: 1;
}
</style>
