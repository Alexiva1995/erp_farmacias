<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { computed } from "vue";
import { formatPrice, formatDateSimple } from "@/utils/formatters";

const props = defineProps({
  products:     { type: Array,   required: true },
  loading:      { type: Boolean, default: false },
  totalProduct: { type: Number,  required: true },
  itemsPerPage: { type: Number,  required: true },
  page:         { type: Number,  required: true },
  mode:         { type: String,  default: "inventory" },
  title:        { type: String,  default: "" },
});

const emit = defineEmits(["update:options", "count-product"]);

const headers = computed(() => {
  const baseHeaders = [
    { title: "ID",           key: "id",               sortable: true,  width: "80px"  },
    { title: "Producto",     key: "name",              sortable: true,  width: "40%"   },
    { title: "Laboratorio",  key: "laboratory.name",   sortable: true,  width: "15%"   },
    { title: "Expiración",   key: "next_expiration",   sortable: true,  width: "120px" },
    { title: "Acciones",     key: "actions",           sortable: false, align: "center", width: "100px" },
  ];

  if (props.mode !== "inventory") {
    baseHeaders.splice(4, 0,
      { title: "Costo",    key: "unit_cost",   sortable: true, align: "end", width: "120px" },
      { title: "P. Venta", key: "sale_price",  sortable: true, align: "end", width: "120px" }
    );
  }

  return baseHeaders;
});

const nextExpirationDate = (product) => {
  if (
    !product.lots ||
    !Array.isArray(product.lots) ||
    product.lots.length === 0
  )
    return "N/A";
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const validLots = product.lots.filter((lot) => {
    if (!lot.expiration_date) return false;
    const expirationDate = new Date(lot.expiration_date);
    return !isNaN(expirationDate.getTime()) && expirationDate >= today;
  });
  if (validLots.length === 0) return "EXPIRADO";
  validLots.sort(
    (a, b) => new Date(a.expiration_date) - new Date(b.expiration_date)
  );
  const closestDate = new Date(validLots[0].expiration_date);
  return closestDate.toISOString().split("T")[0];
};

const calculateSalePriceWithIva = (product) => {
  const basePrice = Number(product.sale_price || 0);
  return product.iva == 1 ? basePrice * 1.16 : basePrice;
};

const getProductLocations = (product) => {
  if (!product) return [];
  if (product.lot_locations && Array.isArray(product.lot_locations) && product.lot_locations.length > 0) {
    return product.lot_locations.filter(Boolean);
  }
  if (product.lots && Array.isArray(product.lots)) {
    const locs = product.lots.map(l => l.location).filter(l => l && String(l).trim() !== '');
    return [...new Set(locs)];
  }
  if (product.location) {
    return [product.location];
  }
  return [];
};
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden">
    <VCardTitle v-if="props.title" class="pa-4">
      <span class="text-h6 font-weight-bold">{{ props.title }}</span>
    </VCardTitle>

    <VDivider />

    <!-- Vista de Escritorio -->
    <div class="d-none d-md-block">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.products"
        :items-length="props.totalProduct"
        :loading="props.loading"
        class="text-no-wrap"
        density="compact"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.id"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.id }}
          </a>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-3 py-2">
            <div class="d-flex flex-column min-width-0">
              <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" style="max-inline-size: 320px;">
                {{ item.name.toUpperCase() }}
                <span v-if="item.iva == 1" class="text-xs text-disabled"> (G)</span>
                <span v-if="item.is_colombian_origin == 1" class="text-xs text-disabled"> (COL)</span>
              </span>
              <div class="d-flex align-center flex-wrap gap-1 text-super-xs mt-1">
                <span class="text-disabled truncate" style="max-inline-size: 200px;">{{ item.active_ingredient }}</span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 150px;">
                  {{ item.laboratory?.name || 'S/L' }}
                </span>
                <template v-if="getProductLocations(item).length > 0">
                  <span class="text-disabled mx-1">|</span>
                  <span class="text-success font-weight-black text-uppercase">
                    📍 {{ getProductLocations(item).join(', ') }}
                  </span>
                </template>
              </div>
            </div>
          </div>
        </template>

        <template #item.next_expiration="{ item }">
          <span class="text-xs font-weight-medium">{{ nextExpirationDate(item) }}</span>
        </template>

        <template #item.unit_cost="{ item }">
          <span class="text-sm font-weight-medium text-high-emphasis">{{
            formatPrice(item.unit_cost)
          }}</span>
        </template>

        <template #item.sale_price="{ item }">
          <div class="d-flex flex-column text-end">
            <span class="text-sm font-weight-black text-primary">{{
              formatPrice(calculateSalePriceWithIva(item))
            }}</span>
            <span v-if="item.iva == 1" class="text-super-xs text-success">IVA INC.</span>
          </div>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center">
            <template v-if="mode === 'inventory'">
              <IconBtn 
                @click="emit('count-product', item)" 
                color="success"
                variant="tonal"
                size="small"
              >
                <VIcon icon="tabler-scan" size="18" />
                <VTooltip activator="parent" location="top">Contar producto de factura</VTooltip>
              </IconBtn>
            </template>
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil -->
    <div class="d-block d-md-none pa-2">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.products.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No se encontraron productos.
      </div>

      <div class="d-flex flex-column gap-2">
        <VCard
          v-for="item in props.products"
          :key="item.id"
          variant="flat"
          class="product-mobile-card border mb-1"
        >
          <div class="pa-3">
            <div class="d-flex gap-3 align-start">
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-center gap-1 mb-1">
                  <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight text-truncate">
                    <a
                      :href="'/inventory/traceability?q=' + item.id"
                      target="_blank"
                      class="text-decoration-none text-primary text-xs"
                    >
                      {{ item.id }}
                    </a>
                    <span class="mx-1 text-disabled">|</span>
                    {{ item.name.toUpperCase() }}
                  </h3>
                </div>
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs">
                  <span class="text-medium-emphasis font-weight-medium text-truncate" style="max-inline-size: 150px;">{{ item.active_ingredient }}</span>
                  <span class="text-disabled">|</span>
                  <span class="text-primary font-weight-bold text-truncate" style="max-inline-size: 120px;">{{ item.laboratory?.name || 'S/L' }}</span>
                </div>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="d-flex align-center justify-space-between bg-var-theme-background px-3 py-2 rounded border-dashed-thin">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Expiración</span>
                <span class="text-xs font-weight-black">{{ nextExpirationDate(item) }}</span>
              </div>
              <div v-if="mode !== 'inventory'" class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Precio Venta ({{ item.iva == 1 ? 'IVA' : 'EX' }})</span>
                <span class="text-sm font-weight-black text-primary">
                  {{ formatPrice(calculateSalePriceWithIva(item)) }}
                </span>
              </div>
            </div>
          </div>

          <div v-if="mode === 'inventory'" class="border-t border-opacity-10">
            <VBtn 
              block 
              color="success" 
              variant="flat" 
              class="rounded-0"
              height="44"
              icon="tabler-scan" 
              @click="emit('count-product', item)"
            />
          </div>
        </VCard>
      </div>

      <div class="d-flex justify-center mt-4">
        <AppMobilePagination
          :page="props.page"
          :items-per-page="props.itemsPerPage"
          :total-items="props.totalProduct"
          :loading="props.loading"
          @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
        />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.product-mobile-card {
  overflow: hidden;
  border-radius: 8px !important;
  background: rgb(var(--v-theme-surface));
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.gap-3 { gap: 12px !important; }

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>
