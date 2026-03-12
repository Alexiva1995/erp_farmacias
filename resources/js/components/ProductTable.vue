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

</script>

<template>
  <VCard>
    <VCardTitle v-if="props.title">{{ props.title }}</VCardTitle>
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
              <!-- En móvil añadimos ID y Laboratorio al nombre -->
              <span class="d-inline d-sm-none text-primary font-weight-bold">[{{ item.id }}] </span>
              {{ item.name.toUpperCase() }}
              <span v-if="item.iva == 1 || item.iva === true"> (G)</span>
              <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true"> (COL)</span>
              <div v-if="item.laboratory" class="d-block d-md-none text-xs text-secondary italic">
                {{ item.laboratory.name }}
              </div>
            </span>
            <span class="text-sm text-disabled">{{
              item.active_ingredient
            }}</span>
          </div>
        </div>
      </template>

      <template #item.stock_calculado="{ item }">
        <div class="text-end">
          <span class="font-weight-medium">{{ item.stock_calculado ?? 0 }}</span>
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

    <!-- Diálogo de Fusión Refactorizado -->
    <ProductMergeDialog
      v-model="isMergeDialogVisible"
      :selected-product="selectedProductForMerge"
      @merged="emit('product-merged')"
    />
  </VCard>
</template>
