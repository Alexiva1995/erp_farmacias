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
  "approve-product",
  "reject-product",
]);

const headers = [
  { title: "Producto", key: "product.name" },
  { title: "Stock Sistema", key: "system_quantity", align: "center" },
  { title: "Cant. Contada", key: "counted_quantity", align: "center" },
  { title: "Diferencia", key: "discrepancy", sortable: false, align: "center" },
  { title: "Usuario", key: "user.email" },
  { title: "Acción", key: "actions", sortable: false, align: "center" },
];

const handleApproveProduct = async (product) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: `Vas a aprobar el conteo para "${product.product.name}".`,
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Aprobar",
    cancelButtonText: "Cancelar",
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
    text: `Vas a rechazar el conteo de "${product.product.name}" y abrir el modal de corrección.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Abrir Corrección",
    cancelButtonText: "Cancelar",
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
    <VCardTitle>Conteos de Factura Pendientes</VCardTitle>
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
      <template #item.product.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar
            v-if="item.product.photo_url"
            size="38"
            variant="tonal"
            rounded
            :image="item.product.photo_url"
          />
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">{{
              item.product.name
            }}</span>
            <span class="text-sm text-disabled">{{
              item.product.active_ingredient
            }}</span>
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
        <div class="d-flex justify-center gap-2">
          <IconBtn @click="handleApproveProduct(item)" size="small" color="success">
            <VIcon icon="tabler-check" />
            <VTooltip activator="parent" location="top">Aprobar</VTooltip>
          </IconBtn>
          <IconBtn @click="handleRejectProduct(item)" size="small" color="error">
            <VIcon icon="tabler-x" />
            <VTooltip activator="parent" location="top">Rechazar</VTooltip>
          </IconBtn>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
