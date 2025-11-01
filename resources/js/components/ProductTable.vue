<script setup>
const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  mode: { type: String, default: "products" },
});

const emit = defineEmits([
  "update:options",
  "edit-product",
  "delete-product",
  "count-product",
  "add-product-to-invoice",
]);

const headers = [
  { title: "id", key: "id", sortable: true },
  { title: "Producto", key: "name", sortable: true, width: "40%" },
  { title: "Laboratorio", key: "laboratory.name", sortable: true },
  {
    title: "Stock",
    key: "valid_stock",
    sortable: true,
    value: (item) => {
      return item.stock_calculado;
    },
  },
  { title: "Exp.", key: "next_expiration", sortable: true },
  { title: "Costo", key: "unit_cost", sortable: true },
  { title: "Precio Venta", key: "sale_price", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];
// TODO: hay que modificar la funcion para que muestr la fecha de vencimiento apesar de que los lotes ya esten todos vencidos (puede que se tenga que modificar la consulta en el backend)
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
  // if (validLots.length === 0) return "Todos expiraron";
  if (validLots.length === 0) return product.ultima_fecha_vencimiento;
  validLots.sort(
    (a, b) => new Date(a.expiration_date) - new Date(b.expiration_date)
  );
  const closestDate = new Date(validLots[0].expiration_date);
  return closestDate.toISOString().split("T")[0];
};

const calculateSalePriceWithIva = (product) => {
  const basePrice = Number(product.sale_price || 0);
  if (product.iva == 1) {
    const priceWithIva = basePrice * 1.16;

    return priceWithIva.toFixed(2);
  }
  return basePrice;
};

const formatPrice = (price) => {
  if (typeof price !== "number") return "0.00";
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(price);
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
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
              :class="{ 'text-primary': item.psychotropic == 1 }"
            >
              {{ item.name }}
              <span v-if="item.iva == 1"> (G)</span>
              <span v-if="item.is_colombian_origin == 1"> (COL)</span>
            </span>
            <span class="text-sm text-disabled">{{
              item.active_ingredient
            }}</span>
          </div>
        </div>
      </template>

      <template #item.stock="{ item }">
        <span class="font-weight-medium">{{ item.stock }}</span>
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
          <IconBtn @click="emit('edit-product', item)">
            <VIcon icon="tabler-edit" />
          </IconBtn>
          <IconBtn @click="emit('delete-product', item.id)">
            <VIcon icon="tabler-trash" />
          </IconBtn>
        </template>

        <template v-else-if="mode === 'inventory'">
          <IconBtn @click="emit('count-product', item)">
            <VIcon icon="tabler-scan" />
            <VTooltip activator="parent" location="top"
              >Contar producto</VTooltip
            >
          </IconBtn>
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
  </VCard>
</template>
