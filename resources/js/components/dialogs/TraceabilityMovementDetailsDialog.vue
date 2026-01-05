<script setup>
import OrderViewModal from "@/components/dialogs/OrderViewModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";
import { useRouter } from "vue-router";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  movementId: { type: [Number, null], default: null },
});

const emit = defineEmits(["update:modelValue"]);

const router = useRouter();
const loading = ref(false);
const movementDetails = ref(null);
const orderData = ref(null);
const orderItems = ref([]);
const currency = ref("");
const paymentsForPrint = ref([]);
const changeAmountForPrint = ref(0);
const amountForPrint = ref(0);
const creditAmountForPrint = ref(0);
const creditForPrint = ref(false);
const showOrderModal = ref(false);

const isDialogVisible = computed({
  get() {
    return props.modelValue;
  },
  set(value) {
    emit("update:modelValue", value);
  },
});

const fetchMovementDetails = async () => {
  if (!props.movementId) return;

  loading.value = true;
  try {
    const response = await axios.get(`/sales/report/movement/${props.movementId}`);
    movementDetails.value = response.data.data;
  } catch (error) {
    console.error("Error al cargar los detalles del movimiento:", error);
    toast.error("Error al cargar los detalles del movimiento.");
  } finally {
    loading.value = false;
  }
};

watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible && props.movementId) {
      fetchMovementDetails();
    } else if (!isVisible) {
      movementDetails.value = null;
    }
  },
  { immediate: true }
);

watch(
  () => props.movementId,
  () => {
    if (props.modelValue && props.movementId) {
      fetchMovementDetails();
    }
  }
);

const closeDialog = () => {
  emit("update:modelValue", false);
  movementDetails.value = null;
};

const handleViewOrder = async (orderId) => {
  try {
    const response = await axios.get(`/tpv/orders/${orderId}/print`);
    if (response.data && response.data.data && response.data.data.order) {
      orderData.value = response.data.data.order;
      currency.value = response.data.data.order.currency.toUpperCase();
      orderItems.value = response.data.data.order.details.map((detail) => ({
        title: detail.product.name,
        selectedQuantity: detail.quantity,
        taxRate: detail.product.iva,
        price_bs: parseFloat(detail.price),
        price_cop: parseFloat(detail.price),
        price: parseFloat(detail.price),
        price_before_discount: parseFloat(detail.price_before_discount),
      }));
      paymentsForPrint.value = response.data.data.order.payment_methods;
      changeAmountForPrint.value = parseFloat(
        response.data.data.order.money_returns
      );
      amountForPrint.value = parseFloat(response.data.data.order.total_amount);
      creditAmountForPrint.value = response.data.data.hasCreditPayment
        ? parseFloat(response.data.data.order.total_amount)
        : 0;
      creditForPrint.value = response.data.data.hasCreditPayment;
      showOrderModal.value = true;
    } else {
      toast.error("La respuesta del servidor no tiene el formato esperado.");
    }
  } catch (error) {
    console.error("Error al obtener los detalles de la orden:", error);
    toast.error("Error al obtener los detalles de la orden.");
  }
};

const handleViewInvoice = (invoiceId) => {
  // Navigate to ordered invoices page and open the invoice directly
  router.push({ 
    name: 'invoice-invoice-ordered',
    query: { invoiceId: invoiceId }
  });
  closeDialog();
};

const formatDate = (date) => {
  if (!date) return "N/A";
  return new Date(date).toLocaleDateString();
};

const getStatusLabel = (status) => {
  const statusMap = {
    Approved: "Aprobado",
    Rejected: "Rechazado",
    pending: "Pendiente",
    approved: "Aprobado",
    rejected: "Rechazado",
  };
  return statusMap[status] || status;
};
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="800px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard class="d-flex flex-column">
      <VCardTitle class="d-flex align-center pa-6">
        <span class="text-h5 font-weight-bold">Detalles del Movimiento</span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1 pa-6" style="overflow-y: auto" v-if="loading">
        <div class="d-flex justify-center py-8">
          <VProgressCircular indeterminate color="primary" />
        </div>
      </VCardText>

      <VCardText
        class="flex-grow-1 pa-6"
        style="overflow-y: auto"
        v-else-if="movementDetails"
      >
        <!-- Tipo de Movimiento -->
        <div class="mb-6">
          <p class="text-h6 font-weight-medium mb-2">Tipo de Movimiento</p>
          <VChip color="primary" size="large">{{ movementDetails.display_type }}</VChip>
        </div>

        <VDivider class="my-4" />

        <!-- Información General -->
        <div class="mb-6">
          <p class="text-h6 font-weight-medium mb-4">Información General</p>
          <VRow>
            <VCol cols="12" md="6">
              <p class="text-body-2 text-medium-emphasis mb-1">Producto</p>
              <p class="text-body-1 font-weight-medium">
                {{ movementDetails.movement?.product?.name || "N/A" }}
              </p>
            </VCol>
            <VCol cols="12" md="6">
              <p class="text-body-2 text-medium-emphasis mb-1">Cantidad</p>
              <p
                class="text-body-1 font-weight-medium"
                :class="{
                  'text-success': movementDetails.movement?.quantity > 0,
                  'text-error': movementDetails.movement?.quantity < 0,
                }"
              >
                {{ movementDetails.movement?.quantity > 0 ? "+" : "" }}
                {{ movementDetails.movement?.quantity }}
              </p>
            </VCol>
            <VCol cols="12" md="6">
              <p class="text-body-2 text-medium-emphasis mb-1">Fecha</p>
              <p class="text-body-1 font-weight-medium">
                {{ formatDate(movementDetails.movement?.movement_date) }}
              </p>
            </VCol>
            <VCol cols="12" md="6">
              <p class="text-body-2 text-medium-emphasis mb-1">Operador</p>
              <p class="text-body-1 font-weight-medium">
                {{ movementDetails.movement?.user?.email || "N/A" }}
              </p>
            </VCol>
          </VRow>
        </div>

        <!-- Detalles según el tipo -->
        <VDivider class="my-4" />

        <!-- Devolución -->
        <div v-if="movementDetails.type === 'return'" class="mb-6">
          <p class="text-h6 font-weight-medium mb-4">Información de Devolución</p>
          <VRow>
            <VCol cols="12" md="6">
              <p class="text-body-2 text-medium-emphasis mb-1">Orden Original</p>
              <VBtn
                v-if="movementDetails.original_order"
                variant="text"
                color="primary"
                @click="handleViewOrder(movementDetails.original_order.id)"
              >
                Orden #{{ movementDetails.original_order.id }}
                <VIcon icon="tabler-external-link" class="ms-2" size="16" />
              </VBtn>
              <p v-else class="text-body-1">N/A</p>
            </VCol>
            <VCol cols="12" md="6">
              <p class="text-body-2 text-medium-emphasis mb-1">Procesada por</p>
              <p class="text-body-1 font-weight-medium">
                {{ movementDetails.processed_by?.email || "N/A" }}
              </p>
            </VCol>
            <VCol cols="12" md="6">
              <p class="text-body-2 text-medium-emphasis mb-1">Estado</p>
              <VChip
                :color="movementDetails.status === 'Approved' ? 'success' : 'error'"
                size="small"
              >
                {{ getStatusLabel(movementDetails.status) }}
              </VChip>
            </VCol>
          </VRow>
        </div>

        <!-- Venta -->
        <div v-if="movementDetails.type === 'sale'" class="mb-6">
          <p class="text-h6 font-weight-medium mb-4">Información de Venta</p>
          <VRow>
            <VCol cols="12" md="6">
              <p class="text-body-2 text-medium-emphasis mb-1">Cajero</p>
              <p class="text-body-1 font-weight-medium">
                {{ movementDetails.seller?.email || "N/A" }}
              </p>
            </VCol>
            <VCol cols="12" md="6">
              <p class="text-body-2 text-medium-emphasis mb-1">Orden</p>
              <VBtn
                v-if="movementDetails.order"
                variant="text"
                color="primary"
                @click="handleViewOrder(movementDetails.order.id)"
              >
                {{ movementDetails.order.id }}
                <VIcon icon="tabler-external-link" class="ms-2" size="16" />
              </VBtn>
              <p v-else class="text-body-1">N/A</p>
            </VCol>
            <VCol cols="12" md="6" v-if="movementDetails.order?.url_recipe">
              <p class="text-body-2 text-medium-emphasis mb-1">Recipe</p>
              <VBtn
                variant="text"
                color="info"
                @click="() => {
                  const newWindow = window.open(movementDetails.order.url_recipe, '_blank');
                  if (!newWindow) {
                    toast.error('No se pudo abrir la ventana. Por favor, verifica que los pop-ups no estén bloqueados.');
                  }
                }"
              >
                Ver Recipe
                <VIcon icon="tabler-external-link" class="ms-2" size="16" />
              </VBtn>
            </VCol>
          </VRow>
        </div>

        <!-- Compra -->
        <div v-if="movementDetails.type === 'purchase'" class="mb-6">
          <p class="text-h6 font-weight-medium mb-4">Información de Compra</p>
          <VRow>
            <VCol cols="12" md="6">
              <p class="text-body-2 text-medium-emphasis mb-1">Número de Factura</p>
              <VBtn
                v-if="movementDetails.invoice && movementDetails.invoice.id"
                variant="text"
                color="primary"
                @click="handleViewInvoice(movementDetails.invoice.id)"
              >
                {{ movementDetails.invoice.invoice_number ? movementDetails.invoice.invoice_number : movementDetails.invoice.id }}
                <VIcon icon="tabler-external-link" class="ms-2" size="16" />
              </VBtn>
              <p v-else class="text-body-1">N/A</p>
            </VCol>
            <VCol cols="12" md="6">
              <p class="text-body-2 text-medium-emphasis mb-1">Proveedor</p>
              <p class="text-body-1 font-weight-medium">
                {{ movementDetails.supplier?.name || movementDetails.invoice?.supplier?.name || "N/A" }}
              </p>
            </VCol>
            <VCol cols="12" md="6" v-if="movementDetails.invoice?.control_number">
              <p class="text-body-2 text-medium-emphasis mb-1">Número de Control</p>
              <p class="text-body-1 font-weight-medium">
                {{ movementDetails.invoice.control_number }}
              </p>
            </VCol>
            <VCol cols="12" md="6" v-if="movementDetails.invoice?.total_amount">
              <p class="text-body-2 text-medium-emphasis mb-1">Monto Total</p>
              <p class="text-body-1 font-weight-medium">
                {{ new Intl.NumberFormat('es-VE', { style: 'currency', currency: movementDetails.invoice.currency || 'USD' }).format(movementDetails.invoice.total_amount) }}
              </p>
            </VCol>
          </VRow>
        </div>

        <!-- Ajuste -->
        <div v-if="movementDetails.type === 'adjustment'" class="mb-6">
          <p class="text-h6 font-weight-medium mb-4">Información de Ajuste</p>
          <VRow>
            <VCol cols="12" md="6">
              <p class="text-body-2 text-medium-emphasis mb-1">Usuario que hizo el conteo</p>
              <p class="text-body-1 font-weight-medium">
                {{ movementDetails.counted_by?.email || "N/A" }}
              </p>
            </VCol>
            <VCol cols="12" md="6">
              <p class="text-body-2 text-medium-emphasis mb-1">Usuario que aprobó</p>
              <p class="text-body-1 font-weight-medium">
                {{ movementDetails.approved_by?.email || "N/A" }}
              </p>
            </VCol>
          </VRow>
        </div>

        <!-- Pérdida -->
        <div v-if="movementDetails.type === 'loss'" class="mb-6">
          <p class="text-h6 font-weight-medium mb-4">Información de Pérdida</p>
          <VRow>
            <VCol cols="12" md="6">
              <p class="text-body-2 text-medium-emphasis mb-1">Usuario que hizo el conteo</p>
              <p class="text-body-1 font-weight-medium">
                {{ movementDetails.counted_by?.email || "N/A" }}
              </p>
            </VCol>
            <VCol cols="12" md="6">
              <p class="text-body-2 text-medium-emphasis mb-1">Usuario que aprobó</p>
              <p class="text-body-1 font-weight-medium">
                {{ movementDetails.approved_by?.email || "N/A" }}
              </p>
            </VCol>
          </VRow>
        </div>

        <!-- Caducado -->
        <div v-if="movementDetails.type === 'expired'" class="mb-6">
          <p class="text-h6 font-weight-medium mb-4">Información de Caducidad</p>
          <VRow>
            <VCol cols="12">
              <p class="text-body-2 text-medium-emphasis mb-1">Usuario que caducó</p>
              <p class="text-body-1 font-weight-medium">
                {{ movementDetails.expired_by?.email || "N/A" }}
              </p>
            </VCol>
          </VRow>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1 w-0"
          size="large"
        >
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>

    <!-- Order Modal -->
    <OrderViewModal
      :is-dialog-visible="showOrderModal"
      :order-data="orderData"
      :order-products="orderItems"
      :selected-currency="currency"
      :payments="paymentsForPrint"
      :change-amount="changeAmountForPrint"
      :total-amount="amountForPrint"
      :credit-amount="creditAmountForPrint"
      :credit="creditForPrint"
      @update:is-dialog-visible="showOrderModal = $event"
    />
  </VDialog>
</template>

