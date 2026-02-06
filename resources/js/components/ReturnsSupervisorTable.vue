<script setup>
import Swal from "sweetalert2";

const props = defineProps({
  returns: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalReturns: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});
const emit = defineEmits(["update:options", "status", "open-approve-lots"]);
const expanded = ref([]);

const headers = [
  { title: "ID", key: "order_id", sortable: true, width: "100px" },
  { title: "Usuario", key: "client", sortable: true },
  { title: "Identificación", key: "identificacion", sortable: true },
  { title: "Monto", key: "amount_refunded", sortable: true },
  { title: "Fecha", key: "date", sortable: true },
  { title: "Producto", key: "product", sortable: false },
  { title: "Estado", key: "status", sortable: false },
  { title: "Acción", key: "actions", sortable: false },
];

const date = (order) => {
  const time = new Date(order);
  return time.toISOString().split("T")[0];
};

const handleApproveReturn = async (item) => {
  const result = await Swal.fire({
    title: "Aprobar devolución",
    html: `
      <p class="text-start mb-2">Para aprobar debe distribuir las <strong>${item.quantity ?? 0} unidades</strong> devueltas en lotes (stock actual + unidades devueltas).</p>
      <p class="text-start text-body-2 text-medium-emphasis">Se abrirá el modal de ajuste de lotes. La devolución se aprobará al guardar.</p>
    `,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Aceptar",
    cancelButtonText: "Cancelar",
  });

  if (result.isConfirmed) {
    emit("open-approve-lots", item);
  }
};

const handleRejectReturn = async (item) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "¿Desea rechazar devolver el producto?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Continuar",
    cancelButtonText: "Cancelar",
  });

  if (result.isConfirmed) {
    emit("status", item, "Rejected");
  }
};
</script>

<template>
  <VCard>
    <VDataTableServer
      v-model:expanded="expanded"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.returns"
      :items-length="props.totalReturns"
      :loading="props.loading"
      item-key="id"
      class="text-no-wrap"
      fixed-header
      height="auto"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.client="{ item }">
        <span class="font-weight-medium">
          {{ item.order?.seller?.username ?? "—" }}
        </span>
      </template>

      <template #item.identificacion="{ item }">
        <span class="font-weight-medium">
          {{ item.order.client.identification_type }}
          {{ item.order.client.identification }}
        </span>
      </template>

      <template #item.date="{ item }">
        <span>{{ date(item.return_date) }}</span>
      </template>

      <template #item.product="{ item }">
        <div class="d-flex align-center gap-x-4">
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">{{
              item.product.name
            }}</span>
            <span v-if="item.product?.laboratory?.name" class="text-caption text-medium-emphasis">
              {{ item.product.laboratory.name }}
            </span>
          </div>
        </div>
      </template>

      <template v-slot:item.status="{ item }">
        <VChip
          :color="
            item.status == null
              ? 'warning'
              : item.status === 'Approved'
              ? 'success'
              : 'error'
          "
        >
          <span v-if="item.status == null">Pendiente</span>
          <span v-if="item.status === 'Rejected'">Rechazado</span>
          <span v-else-if="item.status === 'Approved'">Aprobado</span>
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div v-if="item.status == null" class="d-flex align-center gap-2">
          <VTooltip text="Aprobar" location="top">
            <template #activator="{ props }">
              <IconBtn
                v-bind="props"
                @click="handleApproveReturn(item)"
                color="success"
              >
                <VIcon icon="tabler-circle-check" />
              </IconBtn>
            </template>
          </VTooltip>

          <VTooltip text="Rechazar" location="top">
            <template #activator="{ props }">
              <IconBtn
                v-bind="props"
                @click="handleRejectReturn(item)"
                color="error"
              >
                <VIcon icon="tabler-circle-x" />
              </IconBtn>
            </template>
          </VTooltip>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
