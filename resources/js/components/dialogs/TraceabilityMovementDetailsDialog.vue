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

const getUserDisplayName = (user) => {
  if (!user) return "N/A";
  
  // Si tiene employee con name y last_name, usar esos
  if (user.employee?.name && user.employee?.last_name) {
    return `${user.employee.name} ${user.employee.last_name}`;
  }
  
  // Si solo tiene employee.name
  if (user.employee?.name) {
    return user.employee.name;
  }
  
  // Fallback a username o email
  return user.username || user.email || "N/A";
};
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="900px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard class="d-flex flex-column">
      <VCardTitle class="d-flex align-center pa-4 pb-3 bg-primary">
        <VIcon 
          icon="tabler-info-circle" 
          size="24" 
          color="white" 
          class="me-2" 
        />
        <span class="text-h5 font-weight-bold text-white">Detalles del Movimiento</span>
        <VSpacer />
        <VBtn icon variant="text" color="white" size="small" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1 pa-4" style="overflow-y: auto" v-if="loading">
        <div class="d-flex justify-center align-center py-12">
          <VProgressCircular indeterminate color="primary" size="64" />
        </div>
      </VCardText>

      <VCardText
        class="flex-grow-1 pa-4"
        style="overflow-y: auto"
        v-else-if="movementDetails"
      >
        <!-- Tipo de Movimiento - Destacado -->
        <div class="mb-4">
          <div class="d-flex align-center mb-2">
            <VIcon icon="tabler-tag" size="20" class="me-2 text-primary" />
            <p class="text-h6 font-weight-medium mb-0">Tipo de Movimiento</p>
          </div>
          <VChip 
            color="primary" 
            size="large" 
            variant="flat"
            class="font-weight-medium"
          >
            {{ movementDetails.display_type }}
          </VChip>
        </div>

        <VDivider class="my-3" />

        <!-- Información General -->
        <div class="mb-4">
          <div class="d-flex align-center mb-3">
            <VIcon icon="tabler-file-info" size="20" class="me-2 text-primary" />
            <p class="text-h6 font-weight-medium mb-0">Información General</p>
          </div>
          <VRow dense>
            <VCol cols="12" md="6" class="pb-1">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Producto</span>
                <span class="text-body-1 font-weight-medium">
                  {{ movementDetails.movement?.product?.name || "N/A" }}
                </span>
              </div>
            </VCol>
            <VCol cols="12" md="6" class="pb-1">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Cantidad</span>
                <span
                  class="text-body-1 font-weight-bold"
                  :class="{
                    'text-success': movementDetails.movement?.quantity > 0,
                    'text-error': movementDetails.movement?.quantity < 0,
                  }"
                >
                  {{ movementDetails.movement?.quantity > 0 ? "+" : "" }}
                  {{ movementDetails.movement?.quantity }}
                </span>
              </div>
            </VCol>
            <VCol cols="12" md="6" class="pb-1">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Fecha</span>
                <span class="text-body-1 font-weight-medium">
                  {{ formatDate(movementDetails.movement?.movement_date) }}
                </span>
              </div>
            </VCol>
            <VCol cols="12" md="6" class="pb-1">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Operador</span>
                <span class="text-body-1 font-weight-medium">
                  {{ getUserDisplayName(movementDetails.movement?.user) }}
                </span>
              </div>
            </VCol>
          </VRow>
        </div>

        <!-- Detalles según el tipo -->
        <VDivider class="my-3" v-if="movementDetails.type !== 'general'" />

        <!-- Devolución -->
        <div v-if="movementDetails.type === 'return'" class="mb-4">
          <div class="d-flex align-center mb-3">
            <VIcon icon="tabler-arrow-back" size="20" class="me-2 text-primary" />
            <p class="text-h6 font-weight-medium mb-0">Información de Devolución</p>
          </div>
          <VRow dense>
            <VCol cols="12" md="6" class="pb-1">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Orden Original</span>
                <VBtn
                  v-if="movementDetails.original_order"
                  variant="text"
                  color="primary"
                  size="small"
                  class="justify-start pa-0 text-none"
                  @click="handleViewOrder(movementDetails.original_order.id)"
                >
                  Orden #{{ movementDetails.original_order.id }}
                  <VIcon icon="tabler-external-link" class="ms-2" size="16" />
                </VBtn>
                <span v-else class="text-body-1">N/A</span>
              </div>
            </VCol>
            <VCol cols="12" md="6" class="pb-1">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Procesada por</span>
                <span class="text-body-1 font-weight-medium">
                  {{ getUserDisplayName(movementDetails.processed_by) }}
                </span>
              </div>
            </VCol>
            <VCol cols="12" md="6" class="pb-1">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Estado</span>
                <VChip
                  :color="movementDetails.status === 'Approved' ? 'success' : 'error'"
                  size="small"
                  variant="flat"
                >
                  {{ getStatusLabel(movementDetails.status) }}
                </VChip>
              </div>
            </VCol>
          </VRow>
        </div>

        <!-- Venta -->
        <div v-if="movementDetails.type === 'sale'" class="mb-4">
          <div class="d-flex align-center mb-3">
            <VIcon icon="tabler-shopping-cart" size="20" class="me-2 text-primary" />
            <p class="text-h6 font-weight-medium mb-0">Información de Venta</p>
          </div>
          <VRow dense>
            <VCol cols="12" md="6" class="pb-1">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Cajero</span>
                <span class="text-body-1 font-weight-medium">
                  {{ getUserDisplayName(movementDetails.seller) }}
                </span>
              </div>
            </VCol>
            <VCol cols="12" md="6" class="pb-1">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Orden</span>
                <VBtn
                  v-if="movementDetails.order"
                  variant="text"
                  color="primary"
                  size="small"
                  class="justify-start pa-0 text-none"
                  @click="handleViewOrder(movementDetails.order.id)"
                >
                  #{{ movementDetails.order.id }}
                  <VIcon icon="tabler-external-link" class="ms-2" size="16" />
                </VBtn>
                <span v-else class="text-body-1">N/A</span>
              </div>
            </VCol>
            <VCol cols="12" md="6" class="pb-1" v-if="movementDetails.order?.url_recipe">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Recipe</span>
                <VBtn
                  variant="text"
                  color="info"
                  size="small"
                  class="justify-start pa-0 text-none"
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
              </div>
            </VCol>
          </VRow>
        </div>

        <!-- Compra -->
        <div v-if="movementDetails.type === 'purchase'" class="mb-4">
          <div class="d-flex align-center mb-3">
            <VIcon icon="tabler-shopping-bag" size="20" class="me-2 text-primary" />
            <p class="text-h6 font-weight-medium mb-0">Información de Compra</p>
          </div>
          <VRow dense>
            <VCol cols="12" md="6" class="pb-1">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Número de Factura</span>
                <VBtn
                  v-if="movementDetails.invoice && movementDetails.invoice.id"
                  variant="text"
                  color="primary"
                  size="small"
                  class="justify-start pa-0 text-none"
                  @click="handleViewInvoice(movementDetails.invoice.id)"
                >
                  {{ movementDetails.invoice.invoice_number ? movementDetails.invoice.invoice_number : movementDetails.invoice.id }}
                  <VIcon icon="tabler-external-link" class="ms-2" size="16" />
                </VBtn>
                <span v-else class="text-body-1">N/A</span>
              </div>
            </VCol>
            <VCol cols="12" md="6" class="pb-2">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Proveedor</span>
                <span class="text-body-1 font-weight-medium">
                  {{ movementDetails.supplier?.name || movementDetails.invoice?.supplier?.name || "N/A" }}
                </span>
              </div>
            </VCol>
            <VCol cols="12" md="6" class="pb-2" v-if="movementDetails.invoice?.control_number">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Número de Control</span>
                <span class="text-body-1 font-weight-medium">
                  {{ movementDetails.invoice.control_number }}
                </span>
              </div>
            </VCol>
            <VCol cols="12" md="6" class="pb-2" v-if="movementDetails.invoice?.total_amount">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Monto Total</span>
                <span class="text-body-1 font-weight-medium">
                  {{ new Intl.NumberFormat('es-VE', { style: 'currency', currency: movementDetails.invoice.currency || 'USD' }).format(movementDetails.invoice.total_amount) }}
                </span>
              </div>
            </VCol>
          </VRow>
        </div>

        <!-- Ajuste -->
        <div v-if="movementDetails.type === 'adjustment'" class="mb-4">
          <div class="d-flex align-center mb-3">
            <VIcon icon="tabler-adjustments" size="20" class="me-2 text-primary" />
            <p class="text-h6 font-weight-medium mb-0">Información de Ajuste</p>
          </div>
          <VRow dense>
            <VCol cols="12" md="6" class="pb-1">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Usuario que hizo el conteo</span>
                <span class="text-body-1 font-weight-medium">
                  {{ getUserDisplayName(movementDetails.counted_by) }}
                </span>
              </div>
            </VCol>
            <VCol cols="12" md="6" class="pb-1">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Usuario que aprobó</span>
                <span class="text-body-1 font-weight-medium">
                  {{ getUserDisplayName(movementDetails.approved_by) }}
                </span>
              </div>
            </VCol>
          </VRow>
        </div>

        <!-- Pérdida -->
        <div v-if="movementDetails.type === 'loss'" class="mb-4">
          <div class="d-flex align-center mb-3">
            <VIcon icon="tabler-alert-triangle" size="20" class="me-2 text-error" />
            <p class="text-h6 font-weight-medium mb-0">Información de Pérdida</p>
          </div>
          <VRow dense>
            <VCol cols="12" md="6" class="pb-1">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Usuario que hizo el conteo</span>
                <span class="text-body-1 font-weight-medium">
                  {{ getUserDisplayName(movementDetails.counted_by) }}
                </span>
              </div>
            </VCol>
            <VCol cols="12" md="6" class="pb-1">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Usuario que aprobó</span>
                <span class="text-body-1 font-weight-medium">
                  {{ getUserDisplayName(movementDetails.approved_by) }}
                </span>
              </div>
            </VCol>
          </VRow>
        </div>

        <!-- Caducado -->
        <div v-if="movementDetails.type === 'expired'" class="mb-4">
          <div class="d-flex align-center mb-3">
            <VIcon icon="tabler-clock-exclamation" size="20" class="me-2 text-warning" />
            <p class="text-h6 font-weight-medium mb-0">Información de Caducidad</p>
          </div>
          <VRow dense>
            <VCol cols="12" class="pb-1">
              <div class="d-flex flex-column">
                <span class="text-caption text-medium-emphasis mb-1">Usuario que caducó</span>
                <span class="text-body-1 font-weight-medium">
                  {{ getUserDisplayName(movementDetails.expired_by) }}
                </span>
              </div>
            </VCol>
          </VRow>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          block
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

