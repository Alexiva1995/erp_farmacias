<script setup>
import Swal from "sweetalert2";

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
  "approve-product",
  "reject-product",
]);

const headers = [
  { title: "Producto", key: "product.name", width: "300px" },
  { title: "Stock Actual", key: "system_quantity", align: "center" },
  { title: "Cant. Contada", key: "counted_quantity", align: "center" },
  {
    title: "Diferencia",
    key: "discrepancy",
    sortable: false,
    align: "center",
  },
  { title: "Usuario", key: "user.email" },
  { title: "Accion", key: "actions", sortable: false, align: "center" },
];

const emitProductClick = (product) => {
  console.log(product);
  emits("product-click", product);
};

const handleApproveProduct = async (product) => {
  const userName = product.user?.email || "un usuario";

  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: `Vas a aprobar el conteo del producto "${product.product.name}" realizado por ${userName}.`,
    icon: "question",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Aprobar",
    confirmButtonColor: "#28a745",
    reverseButtons: true,
    didOpen: () => {
      const actions = Swal.getActions();
      const confirmButton = Swal.getConfirmButton();
      const cancelButton = Swal.getCancelButton();

      actions.style.display = "flex";
      actions.style.gap = "10px";
      actions.style.width = "100%";
      actions.style.padding = "0 20px";

      confirmButton.style.flex = "1";
      confirmButton.style.width = "50%";

      cancelButton.style.flex = "1";
      cancelButton.style.width = "50%";
    },
  });

  if (result.isConfirmed) {
    emits("approve-product", product);
  }
};

const handleRejectProduct = async (product) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: `Vas a rechazar el conteo del producto "${product.product.name}" y abrir el modal de corrección.`,
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Abrir Corrección",
    confirmButtonColor: "#dc3545",
    reverseButtons: true,
    didOpen: () => {
      const actions = Swal.getActions();
      const confirmButton = Swal.getConfirmButton();
      const cancelButton = Swal.getCancelButton();

      actions.style.display = "flex";
      actions.style.gap = "10px";
      actions.style.width = "100%";
      actions.style.padding = "0 20px";

      confirmButton.style.flex = "1";
      confirmButton.style.width = "50%";

      cancelButton.style.flex = "1";
      cancelButton.style.width = "50%";
    },
  });

  if (result.isConfirmed) {
    emits("reject-product", product);
  }
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
      @update:options="$emit('update:options', $event)"
      item-value="id"
      hover
    >
      <template #item.id="{ item }">
        <span class="font-weight-medium">{{ item.id }}</span>
      </template>

      <template #item.product.name="{ item }">
        <div class="d-flex align-start gap-x-4" style="max-width: 300px; width: 100%;">
          <VAvatar
            v-if="item.product?.photo_url"
            size="38"
            variant="tonal"
            rounded
            :image="item.product.photo_url"
            style="flex-shrink: 0;"
          />
          <div class="d-flex flex-column" style="min-width: 0; flex: 1; word-wrap: break-word; overflow-wrap: break-word;">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis"
              :class="{ 
                'text-primary': item.product.psychotropic == 1,
                'text-warning font-weight-bold': item.product.psychotropic == 1 || item.product.psychotropic === true
              }"
              style="word-wrap: break-word; overflow-wrap: break-word; line-height: 1.4; white-space: normal;"
            >
              {{ item.product.name?.toUpperCase() || 'N/A' }}
              <span v-if="item.product.iva == 1"> (G)</span>
              <span v-if="item.product.is_colombian_origin == 1"> (COL)</span>
            </span>
            <span class="text-sm text-disabled d-flex align-center gap-1" v-if="item.product.laboratory?.name" style="word-wrap: break-word; overflow-wrap: break-word; line-height: 1.3; white-space: normal;">
              <VIcon icon="tabler-building" size="14" />
              {{ item.product.laboratory.name }}
            </span>
          </div>
        </div>
      </template>

      <template #item.discrepancy="{ item }">
        <template
          v-if="item.product && typeof item.product.stock !== 'undefined'"
        >
          <span
            :class="{
              'text-success': item.counted_quantity - item.product.stock > 0,
              'text-error': item.counted_quantity - item.product.stock < 0,
            }"
            class="font-weight-medium"
          >
            {{ item.counted_quantity - item.product.stock > 0 ? "+" : ""
            }}{{ item.counted_quantity - item.product.stock }}
          </span>
        </template>
        <template v-else>
          <span class="text-disabled">N/A</span>
        </template>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex justify-center gap-2">
          <IconBtn @click="handleApproveProduct(item)" size="small" color="success">
            <VIcon icon="tabler-check" />
            <VTooltip activator="parent" location="top"> Aprobar </VTooltip>
          </IconBtn>

          <IconBtn @click="handleRejectProduct(item)" size="small" color="error">
            <VIcon icon="tabler-x" />
            <VTooltip activator="parent" location="top"> Rechazar </VTooltip>
          </IconBtn>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>

<style scoped>
:deep(.v-data-table td:nth-child(1)) {
  white-space: normal !important;
  word-wrap: break-word !important;
  overflow-wrap: break-word !important;
  max-width: 300px !important;
  width: 300px !important;
  vertical-align: top !important;
  padding-top: 12px !important;
  padding-bottom: 12px !important;
  overflow: hidden !important;
}

:deep(.v-data-table th:nth-child(1)) {
  max-width: 300px !important;
  width: 300px !important;
  white-space: normal !important;
}

:deep(.v-data-table__wrapper) {
  overflow-x: auto;
}
</style>
