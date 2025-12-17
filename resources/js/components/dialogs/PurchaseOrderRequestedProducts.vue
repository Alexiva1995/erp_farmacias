<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  purchaseOrder: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);

const loading = ref(false);
const details = ref([]);
const page = ref(1);
const itemsPerPage = ref(10);
const totalDetails = ref(0);

const closeDialog = () => {
  emit("update:modelValue", false);
  page.value = 1;
  itemsPerPage.value = 10;
};

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

const detailsHeaders = [
  { title: "Nombre", key: "product_name", sortable: false },
  { title: "Cantidad", key: "quantity", sortable: false },
  { title: "Coste", key: "unit_cost", sortable: false },
  { title: "Subtotal", key: "subtotal", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];

const fetchPurchaseOrder = async (id) => {
  loading.value = true;
  try {
    const params = {
      perPage: itemsPerPage.value,
      page: page.value,
    };
    const { data } = await axios.get(`/suppliers/purchase-orders/${id}`, {
      params,
    });
    details.value = data.data;
    totalDetails.value = data.total;
  } catch (error) {
    console.error(error);
    toast.error("Error al obtener los detalles de la orden de compra.");
  } finally {
    loading.value = false;
  }
};

const updatePurchaseOrderDetailStatus = async (detailId, status) => {
  try {
    const { data } = await axios.put(
      `/suppliers/purchase-orders/details/update-status/${detailId}`,
      {
        status,
      }
    );

    if (data.status) {
      toast.success(data.message);

      fetchPurchaseOrder(props.purchaseOrder?.id);
    } else {
      toast.error(data.error);
    }
  } catch (error) {
    console.error(error);
    toast.error("Error al actualizar el estado del producto");
  }
};

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
};

watch(
  [() => props.purchaseOrder, () => props.modelValue],
  ([purchaseOrder, modelValue]) => {
    if (purchaseOrder?.id && modelValue) {
      fetchPurchaseOrder(purchaseOrder.id);
    }
  },
  { deep: true }
);

let debounceTimer;
watch([page, itemsPerPage], () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(
    () => fetchPurchaseOrder(props.purchaseOrder.id),
    300
  );
});
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
        <span class="text-h5 font-weight-bold">Productos Solicitados</span>

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
              <VChip color="primary" label>{{
                purchaseOrder.supplier_name
              }}</VChip>
              <VSpacer />
            </div>
          </VCol>
          <VCol cols="6">
            <div class="d-flex align-center gap-4 mb-4">
              <span class="font-weight-medium">Fecha de solicitud</span>
              <VChip color="primary" label>{{
                formatDate(purchaseOrder.order_date)
              }}</VChip>
              <VSpacer />
            </div>
          </VCol>
        </VRow>

        <VDataTableServer
          v-if="details.length > 0"
          :headers="detailsHeaders"
          :items="details"
          density="compact"
          class="mt-4 rounded-lg"
          no-data-text="Esta orden no tiene detalles."
          :items-per-page="itemsPerPage"
          :items-length="totalDetails"
          :page="page"
          :total="totalDetails"
          @update:options="(options) => updateTableOptions(options)"
        >
          <template #item.product_name="{ item }">
            <span
              :class="
                item.received == null
                  ? 'text-primary'
                  : item.received
                  ? 'text-success'
                  : 'text-error'
              "
              >{{ item.product_name }}</span
            >
          </template>
          <template #item.quantity="{ item }">
            <span>{{ item.quantity }}</span>
          </template>
          <template #item.subtotal="{ item }">
            <span>{{ (item.unit_cost * item.quantity).toFixed(2) }}</span>
          </template>
          <template #item.actions="{ item }">
            <VRow v-if="item.received == null">
              <VTooltip text="Aprobar producto" location="top">
                <template #activator="{ props }">
                  <IconBtn
                    v-bind="props"
                    class="text-success"
                    @click="updatePurchaseOrderDetailStatus(item.id, true)"
                  >
                    <VIcon icon="tabler-check" />
                  </IconBtn>
                </template>
              </VTooltip>
              <VTooltip text="Rechazar producto" location="top">
                <template #activator="{ props }">
                  <IconBtn
                    v-bind="props"
                    class="text-error"
                    @click="updatePurchaseOrderDetailStatus(item.id, false)"
                  >
                    <VIcon icon="tabler-x" />
                  </IconBtn>
                </template>
              </VTooltip>
            </VRow>
          </template>
        </VDataTableServer>
      </VSheet>

      <VDivider />

      <!-- El VCardActions se mantiene igual, será el pie de página fijo -->
      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="grow mr-4"
        >
          Cancelar
        </VBtn>
        <VBtn color="primary" variant="flat" @click="submitForm" class="grow">
          Aceptar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
