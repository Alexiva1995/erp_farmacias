<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  purchaseOrder: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modalValue"]);
const details = ref([]);
const id = ref(0);

const loading = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const totalRecords = ref(0);

const closeDialog = () => {
  page.value = 1;
  itemsPerPage.value = 10;
  emit("update:modelValue", false);
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

const showHeaders = [
  { title: "Nombre", key: "product_name", sortable: false },
  { title: "Cantidad", key: "quantity", sortable: false },
  { title: "Coste", key: "unit_cost", sortable: false },
  { title: "Subtotal", key: "subtotal", sortable: false },
];

const fetchPurchaseOrder = async () => {
  loading.value = true;
  try {
    const params = {
      perPage: itemsPerPage.value,
      page: page.value,
    };
    const { data } = await axios.get(`/suppliers/purchase-orders/${id.value}`, {
      params,
    });
    details.value = data.data;
    totalRecords.value = data.total;
  } catch (error) {
    console.error(error);
    toast.error("Error al obtener los detalles de la orden de compra.");
  } finally {
    loading.value = false;
  }
};

const handleExport = async () => {
  toast.info("Se está generando el PDF.");
  try {
    const { data } = await axios.get(
      `/suppliers/purchase-orders/${id.value}/export`
    );
    const payload = {
      details: data.data,
      supplier: props.purchaseOrder.supplier_name,
      total_quantity: props.purchaseOrder.total_quantity,
      total_cost: props.purchaseOrder.total_amount,
      id: id.value,
    };

    pdfPurchaseOrderGenerator(payload);
    closeDialog();
    toast.success("PDF exportado exitosamente.");
  } catch (error) {
    toast.error("Error al exportar los datos.");
    console.error("Error al exportar los datos:", error);
  }
};

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
};

watch(
  [() => props.purchaseOrder, () => props.modelValue],
  ([purchaseOrder, modelValue]) => {
    if (purchaseOrder.id && modelValue) {
      const poID = purchaseOrder.id;
      id.value = poID;
      fetchPurchaseOrder();
    }
  },
  { deep: true }
);

let debounceTimer;
watch(
  [page, itemsPerPage],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchPurchaseOrder(), 300);
  },
  { deep: true }
);
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
        <span class="text-h5 font-weight-bold">Ver Orden de Compra</span>

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
          :headers="showHeaders"
          :items="details"
          :page="page"
          :items-per-page="itemsPerPage"
          :items-length="totalRecords"
          :loading="loading"
          density="compact"
          class="mt-4 rounded-lg"
          @update:options="(options) => updateTableOptions(options)"
        >
          <template #item.unit_cost="{ item }">
            <span>{{
              Intl.NumberFormat("es", {
                maximumFractionDigits: 2,
                minimumFractionDigits: 2,
              }).format(item.unit_cost)
            }}</span>
          </template>
          <template #item.quantity="{ item }">
            <span>{{
              Intl.NumberFormat("es", {
                maximumFractionDigits: 2,
                minimumFractionDigits: 2,
              }).format(item.quantity)
            }}</span>
          </template>
          <template #item.subtotal="{ item }">
            <span>{{
              Intl.NumberFormat("es", {
                maximumFractionDigits: 2,
                minimumFractionDigits: 2,
              }).format(item.unit_cost * item.quantity)
            }}</span>
          </template>
          <template #body.append>
            <tr class="font-weight-bold">
              <td :colspan="showHeaders.length - 4" class="text-right">
                Total
              </td>
              <td class="text-right">
                {{
                  Intl.NumberFormat("es", {
                    maximumFractionDigits: 2,
                    minimumFractionDigits: 2,
                  }).format(
                    details.reduce(
                      (sum, r) => Number(sum) + Number(r.quantity),
                      0
                    )
                  )
                }}
              </td>
              <td :colspan="showHeaders.length - 4" class="text-right">
                Total
              </td>
              <td class="text-right">
                {{
                  Intl.NumberFormat("es", {
                    maximumFractionDigits: 2,
                    minimumFractionDigits: 2,
                  }).format(
                    details.reduce(
                      (sum, r) => sum + r.quantity * r.unit_cost,
                      0
                    )
                  )
                }}
              </td>
            </tr>
          </template>
        </VDataTableServer>
      </VSheet>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1 w-0 mr-4"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="handleExport"
          class="flex-grow-1 w-0"
        >
          Descargar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
