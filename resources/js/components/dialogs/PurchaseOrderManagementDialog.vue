<script setup>
import axios from "@/plugins/axios";
import { pdfPurchaseOrderGenerator } from "@/plugins/pdfGenerator";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  purchaseOrder: { type: Object, default: () => ({}) },
  isAdmin: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "refresh"]);

const loading = ref(false);
const saving = ref(false);
const sending = ref(false);
const details = ref([]);
const page = ref(1);
const itemsPerPage = ref(10);
const totalDetails = ref(0);

// Estado de edición
const affectedRows = ref(new Map());
const isDirty = computed(() => {
  return details.value.some(
    (r) =>
      r.quantity !== affectedRows.value.get(r.id)?.quantity ||
      r.unit_cost !== affectedRows.value.get(r.id)?.unit_cost
  );
});

const closeDialog = () => {
  emit("update:modelValue", false);
  page.value = 1;
};

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString("es-ES", {
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  } catch (error) {
    return "Fecha inválida";
  }
};

const headers = [
  { title: "Producto", key: "product_name", sortable: false },
  { title: "Cantidad", key: "quantity", sortable: false, width: "150px" },
  { title: "Costo Unit. (USD)", key: "unit_cost", sortable: false },
  { title: "Subtotal", key: "subtotal", sortable: false },
  { title: "Estado", key: "actions", sortable: false, align: "center" },
];

const fetchDetails = async () => {
  if (!props.purchaseOrder?.id) return;
  loading.value = true;
  try {
    const { data } = await axios.get(`/suppliers/purchase-orders/${props.purchaseOrder.id}`, {
      params: { page: page.value, perPage: itemsPerPage.value },
    });
    details.value = data.data;
    totalDetails.value = data.total;

    // Inicializar estado para detección de cambios
    affectedRows.value = new Map(
      data.data.map((d) => [d.id, { quantity: d.quantity, unit_cost: d.unit_cost }])
    );
  } catch (error) {
    toast.error("Error al cargar los productos de la orden.");
  } finally {
    loading.value = false;
  }
};

const handleSave = async () => {
  const affected = details.value.filter(
    (r) =>
      r.quantity !== affectedRows.value.get(r.id)?.quantity ||
      r.unit_cost !== affectedRows.value.get(r.id)?.unit_cost
  );

  if (affected.length === 0) return;

  saving.value = true;
  try {
    const { data } = await axios.put(`/suppliers/purchase-orders/${props.purchaseOrder.id}`, {
      details: affected,
    });
    if (data.status === "ok" || data.success) {
      toast.success("Cambios guardados correctamente.");
      fetchDetails();
      emit("refresh");
    }
  } catch (error) {
    toast.error("Error al guardar los cambios.");
  } finally {
    saving.value = false;
  }
};

const handleConfirmSent = async () => {
  sending.value = true;
  try {
    await axios.post(`/suppliers/purchase-orders/${props.purchaseOrder.id}/confirm-sent`);
    toast.success("Orden marcada como enviada.");
    emit("refresh");
    closeDialog();
  } catch (error) {
    toast.error("Error al confirmar el envío.");
  } finally {
    sending.value = false;
  }
};

const handleExport = async () => {
  toast.info("Generando PDF...");
  try {
    const { data } = await axios.get(`/suppliers/purchase-orders/${props.purchaseOrder.id}/export`);
    pdfPurchaseOrderGenerator({
      details: data.data,
      supplier: props.purchaseOrder.supplier_name,
      total_quantity: props.purchaseOrder.total_quantity,
      total_cost: props.purchaseOrder.total_amount,
      id: props.purchaseOrder.id,
    });
  } catch (error) {
    toast.error("Error al exportar PDF.");
  }
};

const updateStatus = async (detailId, status) => {
  try {
    const { data } = await axios.put(`/suppliers/purchase-orders/details/update-status/${detailId}`, { status });
    if (data.status) {
      toast.success(data.message);
      fetchDetails();
    }
  } catch (error) {
    toast.error("Error al actualizar estado del producto.");
  }
};

const deleteDetail = async (id) => {
  try {
    await axios.delete(`/suppliers/purchase-orders/details/${id}`);
    toast.success("Producto eliminado de la orden.");
    fetchDetails();
    emit("refresh");
  } catch (error) {
    toast.error("Error al eliminar el producto.");
  }
};

watch(
  [() => props.purchaseOrder, () => props.modelValue],
  ([po, open]) => {
    if (po?.id && open) {
      fetchDetails();
    }
  },
  { deep: true }
);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="1000px"
    @update:model-value="closeDialog"
    persistent
    scrollable
  >
    <VCard class="management-dialog">
      <VCardTitle class="pa-4 d-flex align-center bg-light">
        <div class="d-flex align-center gap-2">
          <VAvatar color="primary" variant="tonal" size="40">
            <VIcon icon="tabler-clipboard-list" />
          </VAvatar>
          <div>
            <div class="text-h6 font-weight-bold">Gestión de Orden #{{ purchaseOrder.id }}</div>
            <div class="text-caption text-medium-emphasis">{{ purchaseOrder.supplier_name }}</div>
          </div>
        </div>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog" density="comfortable">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-0">
        <!-- Resumen Superior -->
        <div class="pa-4 bg-var-theme-background border-b">
          <VRow>
            <VCol cols="12" md="3">
              <div class="text-caption text-uppercase text-medium-emphasis font-weight-bold">Solicitud</div>
              <div class="d-flex align-center gap-1 mt-1">
                <VIcon icon="tabler-calendar" size="16" class="text-primary" />
                <span class="text-body-2">{{ formatDate(purchaseOrder.order_date) }}</span>
              </div>
            </VCol>
            <VCol cols="12" md="3">
              <div class="text-caption text-uppercase text-medium-emphasis font-weight-bold">Entrega Estimada</div>
              <div class="d-flex align-center gap-1 mt-1">
                <VIcon icon="tabler-truck-delivery" size="16" :class="purchaseOrder.tentative_delivery_date ? 'text-success' : 'text-warning'" />
                <span class="text-body-2">{{ formatDate(purchaseOrder.tentative_delivery_date) || 'No definida' }}</span>
              </div>
            </VCol>
            <VCol cols="12" md="3">
              <div class="text-caption text-uppercase text-medium-emphasis font-weight-bold">Estado Envío</div>
              <div class="mt-1">
                <VChip
                  :color="purchaseOrder.sent_at ? 'success' : 'warning'"
                  size="x-small"
                  label
                  variant="flat"
                  class="font-weight-bold"
                >
                  {{ purchaseOrder.sent_at ? 'ENVIADA' : 'PENDIENTE' }}
                </VChip>
                <div v-if="purchaseOrder.sent_at" class="text-xxs text-medium-emphasis mt-1">
                  {{ formatDate(purchaseOrder.sent_at) }}
                </div>
              </div>
            </VCol>
            <VCol cols="12" md="3" class="text-right">
              <div class="text-caption text-uppercase text-medium-emphasis font-weight-bold">Total Orden</div>
              <div class="text-h6 font-weight-bold text-primary mt-1">
                $ {{ Number(purchaseOrder.total_amount).toLocaleString('es-ES', { minimumFractionDigits: 2 }) }}
              </div>
            </VCol>
          </VRow>
        </div>

        <!-- Tabla de Productos -->
        <VDataTableServer
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :headers="headers"
          :items="details"
          :items-length="totalDetails"
          :loading="loading"
          @update:options="fetchDetails"
          density="comfortable"
          hover
          class="po-details-table"
        >
          <template #item.product_name="{ item }">
            <div class="d-flex flex-column">
              <span class="font-weight-medium text-body-2">{{ item.product_name }}</span>
              <span class="text-caption text-medium-emphasis">{{ item.cod || 'S/C' }}</span>
            </div>
          </template>

          <template #item.quantity="{ item }">
            <VTextField
              v-if="!purchaseOrder.sent_at"
              v-model.number="item.quantity"
              type="number"
              density="compact"
              variant="outlined"
              hide-details
              class="max-w-100"
            />
            <span v-else class="text-body-2">{{ item.quantity }} u.</span>
          </template>

          <template #item.unit_cost="{ item }">
            <span class="text-body-2">$ {{ Number(item.unit_cost).toFixed(2) }}</span>
          </template>

          <template #item.subtotal="{ item }">
            <span class="font-weight-bold text-body-2">$ {{ (item.quantity * item.unit_cost).toFixed(2) }}</span>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex align-center justify-center gap-1">
              <template v-if="item.received === null">
                <VBtn
                  icon
                  size="x-small"
                  variant="tonal"
                  color="success"
                  @click="updateStatus(item.id, true)"
                >
                  <VIcon icon="tabler-check" size="16" />
                </VBtn>
                <VBtn
                  icon
                  size="x-small"
                  variant="tonal"
                  color="error"
                  @click="updateStatus(item.id, false)"
                >
                  <VIcon icon="tabler-x" size="16" />
                </VBtn>
                <VBtn
                  v-if="!purchaseOrder.sent_at"
                  icon
                  size="x-small"
                  variant="text"
                  color="secondary"
                  @click="deleteDetail(item.id)"
                >
                  <VIcon icon="tabler-trash" size="16" />
                </VBtn>
              </template>
              <VChip
                v-else
                :color="item.received ? 'success' : 'error'"
                size="x-small"
                label
                class="font-weight-bold"
              >
                {{ item.received ? 'RECIBIDO' : 'RECHAZADO' }}
              </VChip>
            </div>
          </template>
        </VDataTableServer>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light">
        <VBtn
          color="info"
          variant="tonal"
          prepend-icon="tabler-download"
          @click="handleExport"
          class="flex-grow-1"
        >
          PDF
        </VBtn>

        <VBtn
          v-if="!purchaseOrder.sent_at"
          color="success"
          variant="tonal"
          prepend-icon="tabler-send"
          @click="handleConfirmSent"
          :loading="sending"
          class="flex-grow-1"
        >
          Confirmar Envío
        </VBtn>

        <VBtn
          v-if="isDirty"
          color="primary"
          variant="flat"
          prepend-icon="tabler-device-floppy"
          @click="handleSave"
          :loading="saving"
          class="flex-grow-1"
        >
          Guardar Cambios
        </VBtn>
        
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1"
        >
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.max-w-100 {
  max-inline-size: 100px;
}

.text-xxs {
  font-size: 0.65rem;
}

.po-details-table {
  min-block-size: 400px;
}

.bg-light {
  background-color: rgb(var(--v-theme-surface));
}
</style>
