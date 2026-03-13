<script setup>
const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emits = defineEmits([
  "update:options",
  "product-click",
  "verify-product",
]);

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
</script>

<template>
  <VCard variant="flat" border>
    <VCardTitle class="d-flex align-center py-3 px-4">
      <VIcon icon="tabler-package-search" class="me-2 text-primary" />
      <span class="text-h6 font-weight-black uppercase">Productos en Conteo</span>
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
          <span class="font-weight-black text-primary">
            {{ item.productId ?? item.product_id ?? "—" }}
          </span>
        </template>

        <template #item.product.name="{ item }">
          <div class="d-flex align-center gap-x-4 py-2">
            <VAvatar
              v-if="item.product?.photo_url"
              size="44"
              variant="tonal"
              rounded
              :image="item.product.photo_url"
              class="border elevation-1"
            />
            <div class="d-flex flex-column text-normal-white">
              <span
                class="text-subtitle-2 font-weight-black text-high-emphasis leading-tight uppercase"
                :class="{ 'text-warning': item.product.psychotropic }"
              >
                {{ item.product.name }}
                <span v-if="item.product.is_colombian_origin == 1" class="text-info"> (COL)</span>
              </span>
              <span class="text-super-xs font-weight-bold text-disabled uppercase">{{ item.product.active_ingredient }}</span>
            </div>
          </div>
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
              <span class="text-super-xs font-weight-black">{{ item.user?.username?.charAt(0).toUpperCase() }}</span>
            </VAvatar>
            <span class="text-caption font-weight-bold text-medium-emphasis">{{ item.user?.username || '—' }}</span>
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
      <VLinearProgress v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.products.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No se encontraron productos en conteo.
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
              <VAvatar
                v-if="item.product?.photo_url"
                size="48"
                variant="tonal"
                rounded
                :image="item.product.photo_url"
                class="flex-shrink-0 border"
              />
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-center justify-space-between mb-1">
                  <span class="text-primary font-weight-black text-xs">
                    {{ item.productId ?? item.product_id }}
                  </span>
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
                <div class="text-super-xs text-disabled font-weight-bold uppercase truncate">
                  {{ item.product?.active_ingredient }}
                </div>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="d-grid mobile-stock-grid gap-3 mb-3">
              <div class="stat-box">
                <span class="label">Sistema</span>
                <span class="value text-medium-emphasis">{{ item.system_quantity }} <small>UNDS</small></span>
              </div>
              <div class="stat-box text-center">
                <span class="label">Contado</span>
                <span class="value text-primary font-weight-black">{{ item.counted_quantity }} <small>UNDS</small></span>
              </div>
              <div class="stat-box text-right">
                <span class="label">Operador</span>
                <span class="value text-caption truncate">{{ item.user?.username || '—' }}</span>
              </div>
            </div>

            <VBtn 
              block 
              color="primary" 
              variant="flat" 
              size="small"
              height="40"
              prepend-icon="tabler-clipboard-check" 
              class="font-weight-black shadow-sm"
              @click="handleVerifyProduct(item)"
            >
              VERIFICAR CONTEO
            </VBtn>
          </div>
        </VCard>
      </div>

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
