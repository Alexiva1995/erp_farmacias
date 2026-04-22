<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { pdfPurchaseOrderGenerator } from "@/utils/pdfPurchaseOrderGenerator";
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  purchaseOrder: { type: Object, default: () => ({}) },
  isAdmin: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "refresh"]);

const { mobile } = useDisplay();

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

const getStatusLabel = (status) => {
  const labels = {
    0: "PENDIENTE",
    1: "ENVIADA",
    2: "COMPLETADA",
  };
  if (status === true || status === 2) return "COMPLETADA";
  return labels[status] || "PENDIENTE";
};

const getStatusColor = (status) => {
  const colors = {
    0: "warning",
    1: "info",
    2: "success",
  };
  if (status === true || status === 2) return "success";
  return colors[status] || "warning";
};

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
    const isFinishing = props.purchaseOrder.status === 1 || props.purchaseOrder.sent_at;
    const url = isFinishing 
      ? `/suppliers/purchase-orders/${props.purchaseOrder.id}/finish` 
      : `/suppliers/purchase-orders/${props.purchaseOrder.id}/confirm-sent`;
      
    await axios.post(url);
    toast.success(isFinishing ? "Orden finalizada correctamente." : "Orden marcada como enviada.");
    emit("refresh");
    closeDialog();
  } catch (error) {
    toast.error("Error al procesar la solicitud.");
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
    max-inline-size="1000px"
    :fullscreen="mobile"
    @update:model-value="closeDialog"
    persistent
    scrollable
  >
    <VCard class="management-dialog rounded-lg overflow-hidden">
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center text-white">
          <div class="d-flex align-center gap-3">
            <VAvatar color="white" variant="tonal" size="44" class="rounded-lg">
              <VIcon icon="tabler-clipboard-list" color="white" />
            </VAvatar>
            <div>
              <div class="text-h6 font-weight-black leading-tight">Orden de Compra {{ purchaseOrder.id }}</div>
              <div class="text-caption text-white opacity-80 font-weight-bold uppercase truncate" style="max-inline-size: 250px;">
                {{ purchaseOrder.supplier_name }}
              </div>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" @click="closeDialog" density="comfortable" class="rounded-lg">
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-0 bg-surface">
        <!-- Resumen Superior Adaptativo -->
        <div class="pa-4 bg-var-theme-background border-b shadow-inner-sm">
          <VRow no-gutters class="gap-y-4">
            <VCol cols="6" md="3">
              <div class="text-xxs text-uppercase text-disabled font-weight-black mb-1">Solicitud</div>
              <div class="d-flex align-center gap-1">
                <VIcon icon="tabler-calendar" size="16" class="text-primary" />
                <span class="text-sm font-weight-bold">{{ formatDate(purchaseOrder.order_date) }}</span>
              </div>
            </VCol>
            <VCol cols="6" md="3" class="text-right text-md-left">
              <div class="text-xxs text-uppercase text-disabled font-weight-black mb-1">Entrega Est.</div>
              <div class="d-flex align-center gap-1 justify-end justify-md-start">
                <span class="text-sm font-weight-bold" :class="purchaseOrder.tentative_delivery_date ? 'text-success' : 'text-warning'">
                  {{ formatDate(purchaseOrder.tentative_delivery_date) || 'No definida' }}
                </span>
                <VIcon icon="tabler-truck-delivery" size="16" :class="purchaseOrder.tentative_delivery_date ? 'text-success' : 'text-warning'" />
              </div>
            </VCol>
            <VCol cols="6" md="3">
              <div class="text-xxs text-uppercase text-disabled font-weight-black mb-1">Estado</div>
              <VChip
                :color="getStatusColor(purchaseOrder.status)"
                size="x-small"
                label
                variant="tonal"
                class="font-weight-black rounded"
              >
                {{ getStatusLabel(purchaseOrder.status) }}
              </VChip>
            </VCol>
            <VCol cols="6" md="3" class="text-right">
              <div class="text-xxs text-uppercase text-disabled font-weight-black mb-1">Total Orden</div>
              <div class="text-h6 font-weight-black text-primary leading-tight">
                $ {{ Number(purchaseOrder.total_amount).toLocaleString('es-ES', { minimumFractionDigits: 2 }) }}
              </div>
            </VCol>
          </VRow>
        </div>

        <!-- Vista Escritorio -->
        <VDataTableServer
          v-if="!mobile"
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :headers="headers"
          :items="details"
          :items-length="totalDetails"
          :loading="loading"
          @update:options="fetchDetails"
          density="comfortable"
          hover
          class="premium-table min-h-400"
        >
          <template #item.product_name="{ item }">
            <div class="d-flex flex-column py-2">
              <span class="font-weight-black text-sm text-high-emphasis">{{ item.product_name }}</span>
            </div>
          </template>
 
          <template #item.quantity="{ item }">
            <div class="d-flex align-center gap-2" :class="mobile ? 'py-2' : ''">
              <AppTextField
                v-if="purchaseOrder.status === 0"
                v-model.number="item.quantity"
                type="number"
                density="compact"
                hide-details
                class="max-inline-size-100 font-weight-black"
                @input="item.quantity = Math.max(0, item.quantity)"
              />
              <span v-else class="text-sm font-weight-black">{{ item.quantity }} u.</span>
            </div>
          </template>
 
          <template #item.unit_cost="{ item }">
            <span class="text-sm font-weight-bold text-disabled">$ {{ Number(item.unit_cost).toFixed(2) }}</span>
          </template>
 
          <template #item.subtotal="{ item }">
            <span class="font-weight-black text-sm text-primary">$ {{ (item.quantity * item.unit_cost).toFixed(2) }}</span>
          </template>
 
          <template #item.actions="{ item }">
            <div class="d-flex align-center justify-center gap-2">
              <template v-if="item.received === null">
                <VBtn
                  icon
                  size="32"
                  variant="tonal"
                  color="success"
                  class="rounded-lg"
                  @click="updateStatus(item.id, true)"
                >
                  <VIcon icon="tabler-check" size="18" />
                  <VTooltip activator="parent" location="top">Recibir</VTooltip>
                </VBtn>
                <VBtn
                  icon
                  size="32"
                  variant="tonal"
                  color="error"
                  class="rounded-lg"
                  @click="updateStatus(item.id, false)"
                >
                  <VIcon icon="tabler-x" size="18" />
                  <VTooltip activator="parent" location="top">Rechazar</VTooltip>
                </VBtn>
                <VBtn
                  v-if="purchaseOrder.status === 0"
                  icon
                  size="32"
                  variant="text"
                  color="secondary"
                  class="rounded-lg"
                  @click="deleteDetail(item.id)"
                >
                  <VIcon icon="tabler-trash" size="18" />
                  <VTooltip activator="parent" location="top">Eliminar</VTooltip>
                </VBtn>
              </template>
              <VChip
                v-else
                :color="item.received ? 'success' : 'error'"
                size="x-small"
                label
                class="font-weight-black rounded"
              >
                {{ item.received ? 'RECIBIDO' : 'RECHAZADO' }}
              </VChip>
            </div>
          </template>
        </VDataTableServer>
 
        <!-- Vista Móvil (Cards) -->
        <div v-else class="mobile-products-view pa-4 d-flex flex-column gap-4 bg-var-theme-background">
          <template v-if="details.length > 0">
            <VCard
              v-for="item in details"
              :key="item.id"
              variant="flat"
              border
              class="rounded-lg premium-card overflow-hidden"
            >
              <div class="pa-4 bg-white border-b position-relative">
                <div class="text-sm font-weight-black text-high-emphasis mb-2 uppercase leading-tight">
                  {{ item.product_name }}
                </div>
                <div class="d-flex justify-space-between align-center">
                  <VChip size="x-small" color="primary" variant="tonal" class="font-weight-black">
                    $ {{ Number(item.unit_cost).toFixed(2) }} /u
                  </VChip>
                  <div class="text-xs font-weight-black text-primary">
                    SUBTOTAL: $ {{ (item.quantity * item.unit_cost).toFixed(2) }}
                  </div>
                </div>
              </div>
 
              <VCardText class="pa-4 bg-var-theme-background-light">
                <div class="d-flex align-center justify-space-between gap-4">
                  <!-- Control de Cantidad -->
                  <div class="d-flex flex-column flex-grow-1">
                    <span class="text-xxs text-disabled font-weight-black uppercase mb-1">Cantidad</span>
                    <AppTextField
                      v-if="purchaseOrder.status === 0"
                      v-model.number="item.quantity"
                      type="number"
                      density="compact"
                      hide-details
                      class="max-inline-size-100 font-weight-black"
                      @input="item.quantity = Math.max(0, item.quantity)"
                    />
                    <span v-else class="text-sm font-weight-black text-primary">{{ item.quantity }} u.</span>
                  </div>
 
                  <!-- Acciones de Estado -->
                  <div class="d-flex align-center gap-2">
                    <template v-if="item.received === null">
                      <VBtn
                        icon
                        size="36"
                        variant="elevated"
                        color="success"
                        class="rounded-lg shadow-sm"
                        @click="updateStatus(item.id, true)"
                      >
                        <VIcon icon="tabler-check" size="20" />
                      </VBtn>
                      <VBtn
                        icon
                        size="36"
                        variant="elevated"
                        color="error"
                        class="rounded-lg shadow-sm"
                        @click="updateStatus(item.id, false)"
                      >
                        <VIcon icon="tabler-x" size="20" />
                      </VBtn>
                    </template>
                    <VChip
                      v-else
                      :color="item.received ? 'success' : 'error'"
                      size="small"
                      label
                      class="font-weight-black rounded px-4"
                    >
                      {{ item.received ? 'RECIVIDO' : 'RECHAZADO' }}
                    </VChip>
                  </div>
                </div>
 
                <div v-if="purchaseOrder.status === 0 && item.received === null" class="mt-4 pt-4 border-t d-flex justify-end">
                   <VBtn
                    variant="text"
                    color="error"
                    size="small"
                    class="font-weight-black"
                    prepend-icon="tabler-trash"
                    @click="deleteDetail(item.id)"
                  >
                    ELIMINAR PRODUCTO
                  </VBtn>
                </div>
              </VCardText>
            </VCard>
          </template>
 
          <!-- Paginación Móvil -->
          <div class="d-flex justify-center mt-4" v-if="totalDetails > itemsPerPage">
            <VPagination
              v-model="page"
              :length="Math.ceil(totalDetails / itemsPerPage)"
              total-visible="3"
              size="small"
              @update:model-value="fetchDetails"
            />
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-surface gap-3 flex-wrap flex-md-nowrap">
        <VBtn
          color="secondary"
          variant="tonal"
          prepend-icon="tabler-download"
          @click="handleExport"
          class="flex-grow-1 font-weight-black rounded-lg"
          size="large"
        >
          PDF
        </VBtn>

        <VBtn
          v-if="purchaseOrder.status !== 2"
          :color="purchaseOrder.status === 1 ? 'primary' : 'success'"
          variant="elevated"
          :prepend-icon="purchaseOrder.status === 1 ? 'tabler-circle-check' : 'tabler-send'"
          @click="handleConfirmSent"
          :loading="sending"
          class="flex-grow-1 font-weight-black rounded-lg shadow-sm"
          size="large"
        >
          {{ purchaseOrder.status === 1 ? 'Finalizar' : 'Confirmar Envío' }}
        </VBtn>

        <VBtn
          v-if="isDirty"
          color="warning"
          variant="elevated"
          prepend-icon="tabler-device-floppy"
          @click="handleSave"
          :loading="saving"
          class="flex-grow-1 font-weight-black rounded-lg shadow-sm"
          size="large"
        >
          Guardar Cambios
        </VBtn>
        
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1 font-weight-black rounded-lg d-none d-md-flex"
          size="large"
        >
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e293b 100%);
}

.shadow-inner-sm {
  box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 5%);
}

.max-inline-size-100 {
  max-inline-size: 100px;
}

.text-xxs {
  font-size: 0.65rem !important;
}

.text-disabled {
  color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity)) !important;
}

.min-h-400 {
  min-block-size: 400px;
}

.leading-tight {
  line-height: 1.25;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.premium-table :deep(th) {
  background-color: white !important;
  block-size: 52px !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.premium-table :deep(td) {
  padding-block: 8px !important;
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.1) !important;
}

@media (max-width: 600px) {
  .management-dialog {
    border-radius: 0 !important;
  }
}
</style>
