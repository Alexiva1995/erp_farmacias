<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  purchaseOrder: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modalValue", "view"]);
const details = ref([]);

const page = ref(1);
const itemsPerPage = ref(10);
const totalDetails = ref(0);

const closeDialog = () => {
  emit("update:modelValue", false);
};

watch(
  () => props.purchaseOrder,
  (purchaseOrder) => purchaseOrder?.id && fetchPurchaseOrderHistory(purchaseOrder.id),
  { deep: true, immediate: true },
);

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  try {
    const date = new Date(dateString);
    const year = date.getUTCFullYear();
    const month = (date.getUTCMonth() + 1).toString().padStart(2, "0");
    const day = date.getUTCDate().toString().padStart(2, "0");
    return `${year}-${month}-${day}`;
  } catch (error) {
    return "Fecha inválida";
  }
};

const groupProductsHeaders = [
  { title: "Nombre", key: "product_name", sortable: false },
  { title: "Cantidad", key: "quantity", sortable: false },
  { title: "Precio Solicitud", key: "unit_cost", sortable: false },
  { title: "Subtotal", key: "subtotal", sortable: false },
  { title: "Precio Llegada", key: "final_cost", sortable: false },
];

const fetchPurchaseOrderHistory = async (id) => {
  try {
    const { data } = await axios.get(`/suppliers/purchase-orders/history/${id}`);
    details.value = data.data;
    totalDetails.value = data.total;
  } catch (error) {
    console.error(error);
    toast.error("Error al obtener los detalles de la orden de compra.");
  }
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard class="d-flex flex-column">
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold">Historial de Orden de Compra</span>

        <VSpacer />

        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VSheet color="#f5f5f5" variant="tonal" rounded="lg" class="pa-4">
        <p class="text-h6 font-weight-medium mb-4">Detalles de Orden</p>

        <VRow>
          <VCol cols="6">
            <div class="d-flex align-center gap-4 mb-4">
              <span class="font-weight-medium">Proveedor</span>
              <VChip color="primary" label>{{ purchaseOrder.supplier_name }}</VChip>
              <VSpacer />
            </div>
          </VCol>
          <VCol cols="6">
            <div class="d-flex align-center gap-4 mb-4">
              <span class="font-weight-medium">Fecha de solicitud</span>
              <VChip color="primary" label>{{ formatDate(purchaseOrder.order_date) }}</VChip>
              <VSpacer />
            </div>
          </VCol>
        </VRow>

        <VDataTable
          v-if="details.length > 0"
          :headers="groupProductsHeaders"
          :items="details"
          density="compact"
          class="mt-4 rounded-lg"
          no-data-text="Esta orden no tiene detalles."
          :itemsPerPage="itemsPerPage"
          :page="page"
          :total="totalDetails"
        >
          <template #item.product_name="{ item }">
            <span :class="{ 'text-error font-weight-bold': item.status === 2 }">{{ item.product_name }}</span>
          </template>
          <template #item.subtotal="{ item }">
            <span>{{ (item.unit_cost * item.quantity).toFixed(2) }}</span>
          </template>
          <template #body.append>
            <tr class="font-weight-bold">
              <td :colspan="groupProductsHeaders.length - 4" class="text-right">Total</td>
              <td class="text-right">
                {{ details.reduce((sum, r) => Number(sum) + Number(r.quantity), 0) }}
              </td>
              <td :colspan="groupProductsHeaders.length - 4" class="text-right">Total</td>
              <td class="text-right">
                {{ details.reduce((sum, r) => sum + r.quantity * r.unit_cost, 0).toFixed(2) }}
              </td>
            </tr>
          </template>
        </VDataTable>
      </VSheet>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn color="secondary" variant="outlined" @click="closeDialog" class="flex-grow-1 w-0 mr-4"> Cerrar </VBtn>
        <VBtn color="primary" variant="flat" @click="closeDialog" class="flex-grow-1 w-0"> Aceptar </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
