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
  if (validLots.length === 0) return product.ultima_fecha_vencimiento;
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
    <VCardTitle v-if="props.title">{{ props.title }}</VCardTitle>


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
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.id="{ item }">
          <span class="font-weight-medium">{{ item.id }}</span>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              v-if="item.photo_url"
              size="38"
              variant="tonal"
              rounded
              :image="item.photo_url"
            />
            <div class="d-flex flex-column">
              <span
                class="text-body-1 font-weight-medium text-high-emphasis"
                :class="{ 
                  'text-warning font-weight-bold': item.psychotropic == 1 || item.psychotropic === true
                }"
              >
                {{ item.name.toUpperCase() }}
                <span v-if="item.iva == 1 || item.iva === true"> (G)</span>
                <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true"> (COL)</span>
              </span>
              <span class="text-sm text-disabled">{{
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
              size="small"
              variant="tonal"
            >
              {{ item.stock_calculado ?? 0 }}
            </VChip>
          </div>
        </template>

        <template #item.next_expiration="{ item }">
          <span>{{ nextExpirationDate(item) }}</span>
        </template>

        <template #item.unit_cost="{ item }">
          <span class="font-weight-medium">{{ item.unit_cost }}</span>
        </template>

        <template #item.sale_price="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-medium">{{
              formatPrice(calculateSalePriceWithIva(item))
            }}</span>
            <span v-if="item.iva == 1" class="text-xs text-success"
              >(IVA incluido)</span
            >
          </div>
        </template>

        <template #item.actions="{ item }">
          <template v-if="mode === 'products'">
            <IconBtn @click="emit('edit-product', item)" color="warning">
              <VIcon icon="tabler-edit" />
              <VTooltip activator="parent">Editar</VTooltip>
            </IconBtn>
            <IconBtn
              v-if="authStore.isAdmin"
              color="info"
              @click="openMergeModal(item)"
            >
              <VIcon icon="tabler-package" />
              <VTooltip activator="parent">Fusionar</VTooltip>
            </IconBtn>
            <IconBtn
              @click="emit('delete-product', item.id)"
              v-if="authStore.isAdmin"
              color="error"
            >
              <VIcon icon="tabler-trash" />
              <VTooltip activator="parent">Eliminar</VTooltip>
            </IconBtn>
          </template>

          <template v-else-if="mode === 'inventory'">
            <div class="d-flex justify-center">
              <IconBtn 
                @click="emit('count-product', item)" 
                color="purple"
              >
                <VIcon icon="tabler-scan" />
                <VTooltip activator="parent" location="top"
                  >Contar producto</VTooltip
                >
              </IconBtn>
            </div>
          </template>

          <template v-else-if="mode === 'add-to-invoice'">
            <VBtn
              icon
              variant="tonal"
              color="success"
              size="small"
              @click="emit('add-product-to-invoice', item)"
            >
              <VIcon icon="tabler-plus" />
              <VTooltip activator="parent" location="top"
                >Añadir a la factura</VTooltip
              >
            </VBtn>
          </template>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil (Tarjetas) -->
    <div class="d-block d-md-none pa-4">
      <VLinearProgress v-if="props.loading" indeterminate color="primary" class="mb-4" />
      
      <div v-if="props.products.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No se encontraron productos.
      </div>

      <div class="d-flex flex-column gap-4">
        <VCard
          v-for="item in props.products"
          :key="item.id"
          variant="outlined"
          class="product-mobile-card"
        >
          <div class="pa-4">
            <div class="d-flex gap-4 align-start mb-3">
              <VAvatar
                size="48"
                variant="tonal"
                rounded
                :image="item.photo_url"
                v-if="item.photo_url"
              />
              <div class="d-flex flex-column flex-grow-1">
                <div class="d-flex justify-space-between align-start">
                  <span class="text-xs text-primary font-weight-bold">#{{ item.id }}</span>
                  <VChip
                    v-if="item.psychotropic"
                    color="warning"
                    size="x-small"
                    label
                    variant="flat"
                  >
                    PSICOTRÓPICO
                  </VChip>
                </div>
                <h3 class="text-body-1 font-weight-bold text-high-emphasis leading-tight">
                  {{ item.name.toUpperCase() }}
                </h3>
                <span class="text-xs text-disabled">{{ item.active_ingredient }}</span>
              </div>
            </div>

            <VDivider class="mb-3 border-opacity-25" />

            <VRow dense class="mb-2">
              <VCol cols="6">
                <div class="text-xs text-disabled mb-1 text-uppercase font-weight-bold">Laboratorio</div>
                <div class="text-sm font-weight-medium truncate">{{ item.laboratory?.name || 'N/A' }}</div>
              </VCol>
              <VCol cols="6">
                <div class="text-xs text-disabled mb-1 text-uppercase font-weight-bold">Próx. Venc.</div>
                <div class="text-sm font-weight-medium">{{ nextExpirationDate(item) }}</div>
              </VCol>
            </VRow>

            <VRow dense class="mb-4">
              <VCol cols="6">
                <div class="text-xs text-disabled mb-1 text-uppercase font-weight-bold">Stock Actual</div>
                <VChip
                  :color="item.stock_calculado > 0 ? 'success' : 'error'"
                  label
                  size="small"
                  class="font-weight-bold"
                >
                  {{ item.stock_calculado ?? 0 }} UNDS
                </VChip>
              </VCol>
              <VCol cols="6">
                <div class="text-xs text-disabled mb-1 text-uppercase font-weight-bold">P. Venta</div>
                <div class="text-lg font-weight-black text-primary">
                  {{ formatPrice(calculateSalePriceWithIva(item)) }}
                </div>
                <div v-if="item.iva == 1" class="text-super-xs text-success font-weight-bold mt-n1">CON IVA</div>
              </VCol>
            </VRow>

            <div class="d-flex justify-end gap-2 mt-2">
              <template v-if="mode === 'products'">
                <VBtn
                  color="warning"
                  variant="tonal"
                  size="small"
                  prepend-icon="tabler-edit"
                  @click="emit('edit-product', item)"
                >
                  Editar
                </VBtn>
                <VBtn
                  v-if="authStore.isAdmin"
                  color="info"
                  variant="tonal"
                  size="small"
                  icon="tabler-package"
                  @click="openMergeModal(item)"
                />
                <VBtn
                  v-if="authStore.isAdmin"
                  color="error"
                  variant="tonal"
                  size="small"
                  icon="tabler-trash"
                  @click="emit('delete-product', item.id)"
                />
              </template>

              <template v-else-if="mode === 'inventory'">
                <VBtn
                  block
                  color="purple"
                  variant="flat"
                  prepend-icon="tabler-scan"
                  @click="emit('count-product', item)"
                >
                  Contar Producto
                </VBtn>
              </template>

              <template v-else-if="mode === 'add-to-invoice'">
                <VBtn
                  block
                  color="success"
                  variant="flat"
                  prepend-icon="tabler-plus"
                  @click="emit('add-product-to-invoice', item)"
                >
                  Añadir Factura
                </VBtn>
              </template>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="d-flex justify-center mt-6">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalProduct / props.itemsPerPage)"
          :total-visible="3"
          density="comfortable"
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
  border-radius: 12px !important;
  background: rgb(var(--v-theme-surface));
  transition: transform 0.2s ease;
}

.product-mobile-card:active {
  transform: scale(0.98);
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.leading-tight {
  line-height: 1.25;
}

.text-super-xs {
  font-size: 0.65rem;
  letter-spacing: 0.5px;
}

.gap-4 {
  gap: 1rem !important;
}
</style>
