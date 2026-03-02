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
  "verify-product",
]);

const headers = [
  { title: "#", key: "product_id", align: "center", width: "78px" },
  { title: "Producto", key: "product.name", width: "300px" },
  { title: "Stock Sistema", key: "system_quantity", align: "center" },
  { title: "Cant. Contada", key: "counted_quantity", align: "center" },
  { title: "Diferencia", key: "discrepancy", sortable: false, align: "center" },
  { title: "Usuario", key: "user.email" },
  { title: "Acción", key: "actions", sortable: false, align: "center" },
];

const handleVerifyProduct = (product) => {
  emits("verify-product", product);
};
</script>

<template>
  <VCard>
    <VCardTitle>Conteos de Punto de Venta Pendientes</VCardTitle>
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
        <span class="font-weight-medium text-high-emphasis">
          {{ item.productId ?? item.product_id ?? "—" }}
        </span>
      </template>

      <template #item.product.name="{ item }">
        <div class="d-flex align-start gap-x-4" style=" inline-size: 100%;max-inline-size: 300px;">
          <VAvatar
            v-if="item.product?.photo_url"
            size="38"
            variant="tonal"
            rounded
            :image="item.product.photo_url"
            style="flex-shrink: 0;"
          />
          <div class="d-flex flex-column" style=" flex: 1;min-inline-size: 0; overflow-wrap: break-word; word-wrap: break-word;">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis"
              :class="{
                'text-primary': item.product.psychotropic == 1,
                'text-warning font-weight-bold': item.product.psychotropic == 1 || item.product.psychotropic === true
              }"
              style=" line-height: 1.4; overflow-wrap: break-word; white-space: normal;word-wrap: break-word;"
            >
              {{ item.product.name?.toUpperCase() || "N/A" }}
              <span v-if="item.product.iva == 1"> (G)</span>
              <span v-if="item.product.is_colombian_origin == 1"> (COL)</span>
            </span>
            <span
              v-if="item.product.laboratory?.name"
              class="text-sm text-disabled d-flex align-center gap-1"
              style=" line-height: 1.3; overflow-wrap: break-word; white-space: normal;word-wrap: break-word;"
            >
              <VIcon icon="tabler-building" size="14" />
              {{ item.product.laboratory.name }}
            </span>
          </div>
        </div>
      </template>

      <template #item.discrepancy="{ item }">
        <span
          :class="{
            'text-success': item.discrepancy > 0,
            'text-error': item.discrepancy < 0,
          }"
          class="font-weight-medium"
        >
          {{ item.discrepancy > 0 ? "+" : "" }}{{ item.discrepancy }}
        </span>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex justify-center">
          <IconBtn @click="handleVerifyProduct(item)" size="small" color="primary">
            <VIcon icon="tabler-clipboard-check" />
            <VTooltip activator="parent" location="top">Verificar conteo</VTooltip>
          </IconBtn>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>

<style scoped>
::deep(.v-data-table td:nth-child(2)) {
  overflow: hidden !important;
  inline-size: 300px !important;
  max-inline-size: 300px !important;
  overflow-wrap: break-word !important;
  padding-block: 12px !important;
  vertical-align: top !important;
  white-space: normal !important;
  word-wrap: break-word !important;
}

::deep(.v-data-table th:nth-child(2)) {
  inline-size: 300px !important;
  max-inline-size: 300px !important;
  white-space: normal !important;
}

::deep(.v-data-table__wrapper) {
  overflow-x: auto;
}
</style>
