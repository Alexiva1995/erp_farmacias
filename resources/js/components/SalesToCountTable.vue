<script setup>
import { computed } from "vue";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  mode: { type: String, default: "inventory" },
  title: { type: String, default: "" },
});

const emit = defineEmits(["update:options", "count-product"]);

const headers = computed(() => {
  const baseHeaders = [
    { title: "ID", key: "id", sortable: true, width: "80px" },
    { title: "Producto", key: "name", sortable: true, width: "30%" },
    { title: "Laboratorio", key: "laboratory.name", sortable: true, width: "15%" },
    { title: "Expiración", key: "next_expiration", sortable: true, width: "120px" },
    { title: "Acciones", key: "actions", sortable: false, align: "center", width: "100px" },
  ];
  
  // Solo agregar costo y precio de venta si no está en modo inventory
  if (props.mode !== "inventory") {
    baseHeaders.splice(4, 0, 
      { title: "Costo", key: "unit_cost", sortable: true, align: "end", width: "120px" },
      { title: "P. Venta", key: "sale_price", sortable: true, align: "end", width: "120px" }
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
  if (validLots.length === 0) return "Expirado";
  validLots.sort(
    (a, b) => new Date(a.expiration_date) - new Date(b.expiration_date)
  );
  const closestDate = new Date(validLots[0].expiration_date);
  return closestDate.toISOString().split("T")[0];
};

const calculateSalePriceWithIva = (product) => {
  const basePrice = Number(product.sale_price || 0);
  return product.iva == 1
    ? (basePrice * 1.16).toFixed(2)
    : basePrice.toFixed(2);
};

const formatPrice = (price) => {
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    minimumFractionDigits: 0,
  }).format(price || 0);
};
</script>

<template>
  <VCard>
    <VCardTitle v-if="props.title">{{ props.title }}</VCardTitle>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.products"
      :items-length="props.totalProduct"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
      item-value="id"
      hover
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
        {{ item.stock }}
      </template>

      <template #item.next_expiration="{ item }">
        <span>{{ nextExpirationDate(item) }}</span>
      </template>

      <template #item.unit_cost="{ item }">
        <span class="font-weight-medium">{{
          formatPrice(item.unit_cost)
        }}</span>
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
        <template v-if="mode === 'inventory'">
          <div class="d-flex justify-center">
            <IconBtn 
              @click="emit('count-product', item)" 
              color="purple"
            >
              <VIcon icon="tabler-scan" />
              <VTooltip activator="parent" location="top"
                >Contar producto de venta</VTooltip
              >
            </IconBtn>
          </div>
        </template>
      </template>
    </VDataTableServer>
  </VCard>
</template>
