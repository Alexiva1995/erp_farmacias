<script setup>
import { useAuthStore } from "@/stores/auth";
import { formatDate, formatPrice } from "@/utils/formatters";
import ProductMergeDialog from "@/components/dialogs/ProductMergeDialog.vue";
import { computed, ref } from "vue";

const authStore = useAuthStore();

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  mode: { type: String, default: "products" },
  title: { type: String, default: "" },
});

const emit = defineEmits([
  "update:options",
  "edit-product",
  "delete-product",
  "count-product",
  "add-product-to-invoice",
  "product-merged",
]);

const headers = ref([
  { 
    title: "id", 
    key: "id", 
    sortable: true, 
    visible: true,
    cellClass: 'd-none d-sm-table-cell',
    headerClass: 'd-none d-sm-table-cell'
  },
  {
    title: "Producto",
    key: "name",
    sortable: true,
    width: "40%",
    visible: true,
  },
  {
    title: "Laboratorio",
    key: "laboratory.name",
    sortable: true,
    visible: true,
    cellClass: 'd-none d-md-table-cell',
    headerClass: 'd-none d-md-table-cell'
  },
  { title: "Exp.", key: "next_expiration", sortable: true, visible: true },
  {
    title: "STOCK",
    key: "stock_calculado",
    sortable: true,
    align: "end",
    visible: props.mode !== "inventory",
  },
  {
    title: "Costo",
    key: "unit_cost",
    sortable: true,
    visible: props.mode !== "inventory" && authStore.isAdmin,
    cellClass: 'd-none d-lg-table-cell',
    headerClass: 'd-none d-lg-table-cell'
  },
  {
    title: "Precio Venta",
    key: "sale_price",
    sortable: true,
    visible: props.mode !== "inventory" && authStore.isAdmin,
  },
  {
    title: "Acciones",
    key: "actions",
    sortable: false,
    align: "center",
    visible: true,
  },
]);

const visibleHeaders = computed(() =>
  headers.value.filter((header) => header.visible)
);

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
  if (validLots.length === 0) return product.ultima_fecha_vencimiento || "EXPIRADO";
  validLots.sort(
    (a, b) => new Date(a.expiration_date) - new Date(b.expiration_date)
  );
  const closestDate = new Date(validLots[0].expiration_date);
  return formatDate(closestDate);
};

const calculateSalePriceWithIva = (product) => {
  const basePrice = Number(product.sale_price || 0);
  if (product.iva == 1) {
    return basePrice * 1.16;
  }
  return basePrice;
};

// Estado para la fusión
const isMergeDialogVisible = ref(false);
const selectedProductForMerge = ref(null);

const openMergeModal = (product) => {
  selectedProductForMerge.value = product;
  isMergeDialogVisible.value = true;
};

const handleMobilePageChange = (newPage) => {
  emit('update:options', {
    page: newPage,
    itemsPerPage: props.itemsPerPage,
    sortBy: [],
  });
};
</script>

<template>
  <VCard>
    <VCardTitle v-if="props.title" class="d-flex align-center pa-4">
      <span class="text-h6 font-weight-bold">{{ props.title }}</span>
      <VSpacer />
    </VCardTitle>

    <VDivider />

    <!-- Vista de Escritorio (Tabla) -->
    <div class="d-none d-md-block">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="visibleHeaders"
        :items="props.products"
        :items-length="props.totalProduct"
        :loading="props.loading"
        class="text-no-wrap"
        density="compact"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.id="{ item }">
          <span class="text-xs font-weight-medium text-disabled">{{ item.id }}</span>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-3 py-2">
            <VAvatar
              v-if="item.photo_url"
              size="38"
              variant="tonal"
              rounded
              :image="item.photo_url"
            />
            <div class="d-flex flex-column min-width-0">
              <span
                class="text-sm font-weight-black text-high-emphasis text-truncate"
                :class="{ 
                  'text-warning': item.psychotropic == 1 || item.psychotropic === true
                }"
                style="max-inline-size: 320px;"
                :title="item.name"
              >
                {{ item.name.toUpperCase() }}
                <span v-if="item.iva == 1 || item.iva === true" class="text-xs text-disabled"> (G)</span>
                <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true" class="text-xs text-disabled"> (COL)</span>
              </span>
              <span class="text-xs text-disabled text-truncate" style="max-inline-size: 320px;">{{
                item.active_ingredient
              }}</span>
            </div>
          </div>
        </template>

        <template #item.stock_calculado="{ item }">
          <div class="text-end">
            <VChip
              :color="item.stock_calculado > 0 ? 'success' : 'error'"
              label
              size="x-small"
              variant="tonal"
              class="font-weight-black"
            >
              {{ item.stock_calculado ?? 0 }}
            </VChip>
          </div>
        </template>

        <template #item.next_expiration="{ item }">
          <span class="text-xs font-weight-medium">{{ nextExpirationDate(item) }}</span>
        </template>

        <template #item.unit_cost="{ item }">
          <span class="text-sm font-weight-medium text-high-emphasis">{{ formatPrice(item.unit_cost) }}</span>
        </template>

        <template #item.sale_price="{ item }">
          <div class="d-flex flex-column text-end">
            <span class="text-sm font-weight-black text-primary">{{
              formatPrice(calculateSalePriceWithIva(item))
            }}</span>
            <span v-if="item.iva == 1" class="text-super-xs text-success"
              >IVA INC.</span
            >
          </div>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-1">
            <template v-if="mode === 'products'">
              <IconBtn @click="emit('edit-product', item)" color="warning" size="small">
                <VIcon icon="tabler-edit" size="18" />
                <VTooltip activator="parent">Editar</VTooltip>
              </IconBtn>
              <IconBtn
                v-if="authStore.isAdmin"
                color="info"
                size="small"
                @click="openMergeModal(item)"
              >
                <VIcon icon="tabler-package" size="18" />
                <VTooltip activator="parent">Fusionar</VTooltip>
              </IconBtn>
              <IconBtn
                @click="emit('delete-product', item.id)"
                v-if="authStore.isAdmin"
                color="error"
                size="small"
              >
                <VIcon icon="tabler-trash" size="18" />
                <VTooltip activator="parent">Eliminar</VTooltip>
              </IconBtn>
            </template>

            <template v-else-if="mode === 'inventory'">
              <IconBtn 
                @click="emit('count-product', item)" 
                color="success"
                variant="tonal"
                size="small"
              >
                <VIcon icon="tabler-scan" size="18" />
                <VTooltip activator="parent" location="top">Contar producto</VTooltip>
              </IconBtn>
            </template>

            <template v-else-if="mode === 'add-to-invoice'">
              <IconBtn
                variant="tonal"
                color="success"
                size="small"
                @click="emit('add-product-to-invoice', item)"
              >
                <VIcon icon="tabler-plus" size="18" />
                <VTooltip activator="parent" location="top">Añadir a la factura</VTooltip>
              </IconBtn>
            </template>
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil (Tarjetas Compactas) -->
    <div class="d-block d-md-none pa-2">
      <VLinearProgress v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
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
              <VAvatar
                v-if="item.photo_url"
                size="44"
                variant="tonal"
                rounded
                :image="item.photo_url"
                class="flex-shrink-0 mt-1"
              />
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-center gap-1 mb-1">
                  <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate-2-lines">
                    <span class="text-primary text-xs">{{ item.id }}</span>
                    <span class="mx-1 text-disabled">|</span>
                    {{ item.name }}
                  </h3>
                  <VChip v-if="item.psychotropic" color="warning" size="x-small" label variant="flat" class="text-super-xs flex-shrink-0">PSI</VChip>
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
                <span class="text-super-xs text-disabled text-uppercase font-weight-black text-xs">Stock</span>
                <span :class="(item.stock_calculado ?? 0) > 0 ? 'text-success' : 'text-error'" class="text-base font-weight-black">
                  {{ item.stock_calculado ?? 0 }} <small class="text-super-xs">UNDS</small>
                </span>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black text-xs">Precio Venta ({{ item.iva == 1 ? 'IVA' : 'EX' }})</span>
                <span class="text-base font-weight-black text-primary">
                  {{ formatPrice(calculateSalePriceWithIva(item)) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Acciones Rectangulares al 100% -->
          <div class="d-flex border-t border-opacity-10">
            <template v-if="mode === 'products'">
              <VBtn 
                color="warning" 
                variant="text" 
                class="flex-grow-1 rounded-0" 
                height="40"
                icon="tabler-edit" 
                @click="emit('edit-product', item)"
              />
              <VDivider vertical class="border-opacity-10" />
              <VBtn 
                v-if="authStore.isAdmin" 
                color="info" 
                variant="text" 
                class="flex-grow-1 rounded-0" 
                height="40"
                icon="tabler-package" 
                @click="openMergeModal(item)"
              />
              <VDivider v-if="authStore.isAdmin" vertical class="border-opacity-10" />
              <VBtn 
                v-if="authStore.isAdmin" 
                color="error" 
                variant="text" 
                class="flex-grow-1 rounded-0" 
                height="40"
                icon="tabler-trash" 
                @click="emit('delete-product', item.id)"
              />
            </template>

            <template v-else-if="mode === 'inventory'">
              <VBtn 
                block 
                color="success" 
                variant="flat" 
                class="rounded-0"
                height="44"
                icon="tabler-scan" 
                @click="emit('count-product', item)"
              />
            </template>

            <template v-else-if="mode === 'add-to-invoice'">
              <VBtn 
                block 
                color="success" 
                variant="flat" 
                class="rounded-0"
                height="44"
                icon="tabler-plus" 
                @click="emit('add-product-to-invoice', item)"
              />
            </template>
          </div>
        </VCard>
      </div>

      <div class="d-flex justify-center mt-4">
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

    <!-- Diálogo de Fusión Refactorizado -->
    <ProductMergeDialog
      v-model="isMergeDialogVisible"
      :selected-product="selectedProductForMerge"
      @merged="emit('product-merged')"
    />
  </VCard>
</template>

<style scoped>
.product-mobile-card {
  overflow: hidden;
  border-radius: 8px !important;
  background: rgb(var(--v-theme-surface));
}

.truncate-2-lines {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
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

.text-xs {
  font-size: 0.75rem !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>
