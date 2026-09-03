<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { computed } from "vue";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emits = defineEmits([
  "update:options",
  "verify-product",
]);

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => false);

const headers = [
  { title: "ID", key: "product_id", align: "center", width: "80px" },
  { title: "Producto", key: "product.name", width: "350px" },
  { title: "Stock Sistema", key: "system_quantity", align: "center" },
  { title: "Contado", key: "counted_quantity", align: "center" },
  { title: "Diferencia", key: "discrepancy", sortable: false, align: "center" },
  { title: "Usuario", key: "user.username" },
  { title: "Acción", key: "actions", sortable: false, align: "center" },
];

const handleVerifyProduct = (product) => {
  emits("verify-product", product);
};

const getDiscrepancyColor = (val) => {
  if (val === 0 || val === null) return 'secondary';
  return val > 0 ? 'success' : 'error';
};

const handleMobilePageChange = (newPage) => {
  emits('update:options', {
    page: newPage,
    itemsPerPage: props.itemsPerPage,
    sortBy: [],
  });
};
const formatOperatorName = (user) => {
  if (!user) return '—';
  
  const firstName = user.employee_name?.trim().split(' ')[0] || '';
  const lastName = user.employee_last_name?.trim().split(' ')[0] || '';
  
  if (firstName || lastName) {
    return `${firstName} ${lastName}`.trim();
  }
  
  return user.username || '—';
};

// Muestra entero salvo que el producto tenga unidades de medida (g/ml)
const formatQuantity = (value, product) => {
  if (value === null || value === undefined) return '—';
  const unit = product?.unit_of_measure;
  if (unit === 'g' || unit === 'ml') {
    return Number(value).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
  }
  return Math.trunc(Number(value)).toLocaleString('es-VE');
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
    <VCardTitle class="d-flex align-center py-3 px-4">
      <VIcon icon="tabler-cash-register" class="me-2 text-primary" />
      <span class="text-h6 font-weight-black uppercase">Conteos de Punto de Venta</span>
      <VSpacer />
      <VChip size="small" color="primary" variant="flat" class="font-weight-black">
        {{ props.totalProduct }} TOTAL
      </VChip>
    </VCardTitle>

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
        @update:options="$emit('update:options', $event)"
        item-value="id"
        hover
      >
        <template #item.product_id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + (item.productId ?? item.product_id)"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.productId ?? item.product_id ?? "—" }}
          </a>
        </template>

        <template #item.product.name="{ item }">
          <div class="d-flex align-center gap-x-4 py-2">
            <div class="d-flex flex-column text-normal-white">
              <span
                class="text-subtitle-2 font-weight-black text-high-emphasis leading-tight uppercase"
                :class="{ 'text-warning': !isRestaurant && item.product.psychotropic }"
              >
                {{ item.product.name.toUpperCase() }}
                <span v-if="item.product.is_colombian_origin == 1" class="text-info"> (COL)</span>
              </span>
              <span class="text-super-xs font-weight-bold text-disabled uppercase d-flex align-center flex-wrap gap-1">
                <span class="text-primary">{{ item.product.laboratory?.name || 'S/L' }}</span>
                <span v-if="!isRestaurant" class="mx-1">|</span>
                <span v-if="!isRestaurant">{{ item.product.active_ingredient }}</span>
                <template v-if="getProductLocations(item.product).length > 0">
                  <span class="mx-1">|</span>
                  <span class="text-success font-weight-black">
                    📍 {{ getProductLocations(item.product).join(', ') }}
                  </span>
                </template>
              </span>
            </div>
          </div>
        </template>

        <template #item.system_quantity="{ item }">
          <span class="font-weight-bold">{{ formatQuantity(item.system_quantity, item.product) }}</span>
        </template>

        <template #item.counted_quantity="{ item }">
          <span class="font-weight-bold text-primary">{{ formatQuantity(item.counted_quantity, item.product) }}</span>
        </template>

        <template #item.discrepancy="{ item }">
          <VChip
            :color="getDiscrepancyColor(item.discrepancy)"
            size="x-small"
            variant="flat"
            class="font-weight-black px-2 shadow-sm"
          >
            {{ item.discrepancy > 0 ? "+" : "" }}{{ item.discrepancy }}
          </VChip>
        </template>

        <template #item.user.username="{ item }">
          <div class="d-flex align-center gap-2">
            <VAvatar size="24" color="primary" variant="tonal">
              <span class="text-super-xs font-weight-black">{{ (item.user?.username || 'U').charAt(0).toUpperCase() }}</span>
            </VAvatar>
            <span class="text-caption font-weight-bold text-medium-emphasis">{{ formatOperatorName(item.user) }}</span>
          </div>
        </template>

        <template #item.actions="{ item }">
          <IconBtn @click="handleVerifyProduct(item)" size="small" color="primary" variant="tonal" class="rounded">
            <VIcon icon="tabler-clipboard-check" />
            <VTooltip activator="parent" location="top">Verificar conteo</VTooltip>
          </IconBtn>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil -->
    <div class="d-block d-md-none pa-2 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.products.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No se encontraron conteos de punto de venta.
      </div>

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.products"
          :key="item.id"
          variant="flat"
          class="border mb-1 overflow-hidden premium-card"
        >
          <div class="pa-4">
            <div class="d-flex gap-3 align-start mb-3">
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-center justify-space-between mb-1">
                  <a
                    :href="'/inventory/traceability?q=' + (item.productId ?? item.product_id)"
                    target="_blank"
                    class="text-decoration-none text-primary font-weight-black text-xs"
                  >
                    {{ item.productId ?? item.product_id }}
                  </a>
                  <VChip
                    :color="getDiscrepancyColor(item.discrepancy)"
                    size="x-small"
                    variant="flat"
                    class="font-weight-black"
                  >
                    DIF: {{ item.discrepancy > 0 ? "+" : "" }}{{ item.discrepancy }}
                  </VChip>
                </div>
                <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate mb-1">
                  {{ item.product?.name }}
                </h3>
                <div class="text-super-xs text-disabled font-weight-bold uppercase d-flex align-center flex-wrap gap-1">
                  <span class="text-primary">{{ item.product.laboratory?.name || 'S/L' }}</span>
                  <span v-if="!isRestaurant" class="mx-1">|</span>
                  <span v-if="!isRestaurant">{{ item.product?.active_ingredient }}</span>
                  <template v-if="getProductLocations(item.product).length > 0">
                    <span class="mx-1">|</span>
                    <span class="text-success font-weight-black">
                      📍 {{ getProductLocations(item.product).join(', ') }}
                    </span>
                  </template>
                </div>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="d-grid mobile-stock-grid gap-3 mb-3">
              <div class="stat-box">
                <span class="label">Sistema</span>
                <span class="value text-medium-emphasis">
                  {{ formatQuantity(item.system_quantity, item.product) }}
                  <small>{{ item.product?.unit_of_measure === 'g' || item.product?.unit_of_measure === 'ml' ? item.product.unit_of_measure.toUpperCase() : 'UNDS' }}</small>
                </span>
              </div>
              <div class="stat-box text-center">
                <span class="label">Contado</span>
                <span class="value text-primary font-weight-black">
                  {{ formatQuantity(item.counted_quantity, item.product) }}
                  <small>{{ item.product?.unit_of_measure === 'g' || item.product?.unit_of_measure === 'ml' ? item.product.unit_of_measure.toUpperCase() : 'UNDS' }}</small>
                </span>
              </div>
              <div class="stat-box text-right">
                <span class="label">Operador</span>
                <span class="value text-caption truncate">{{ formatOperatorName(item.user) }}</span>
              </div>
            </div>

            <VBtn
              block
              color="primary"
              variant="tonal"
              size="small"
              height="36"
              class="rounded-lg shadow-sm"
              @click="handleVerifyProduct(item)"
            >
              <VIcon icon="tabler-clipboard-check" />
              <VTooltip activator="parent" location="top">VERIFICAR CONTEO</VTooltip>
            </VBtn>
          </div>
        </VCard>
      </div>

      <div class="d-flex justify-center mt-4 pb-2">
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
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.bg-light {
  background-color: #f8fafc !important;
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
  color: rgba(var(--v-theme-on-surface), 0.45);
  font-size: 0.6rem;
  font-weight: 900;
  margin-block-end: 2px;
  text-transform: uppercase;
}

.stat-box .value {
  font-size: 0.75rem;
  font-weight: 800;
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
  line-height: 1.25 !important;
}

.gap-3 {
  gap: 12px !important;
}
</style>
