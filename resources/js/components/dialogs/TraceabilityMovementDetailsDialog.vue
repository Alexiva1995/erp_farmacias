<script setup>
import { useDisplay } from "vuetify";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";
import { useRouter } from "vue-router";
import CompactOrderViewDialog from "@/components/dialogs/CompactOrderViewDialog.vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  movementId: { type: [Number, null], default: null },
});

const emit = defineEmits(["update:modelValue"]);

const router = useRouter();
const { mobile } = useDisplay();
const loading = ref(false);
const movementDetails = ref(null);

// Estados para el visor compacto de órdenes
const isCompactOrderVisible = ref(false);
const compactOrderLoading = ref(false);
const compactOrderData = ref({
  order: {},
  products: [],
  payments: [],
  total: 0,
  change: 0,
  credit: 0,
  hasCredit: false,
  currency: 'COP'
});

const isDialogVisible = computed({
  get() {
    return props.modelValue;
  },
  set(value) {
    emit("update:modelValue", value);
  },
});

const fetchMovementDetails = async (id) => {
  if (!id) return;

  loading.value = true;
  movementDetails.value = null;
  try {
    const response = await axios.get(`/sales/report/movement/${id}`);
    if (response.data && response.data.data) {
      movementDetails.value = response.data.data;
    } else {
      toast.error("No se encontraron detalles para este movimiento.");
      closeDialog();
    }
  } catch (error) {
    console.error("Error al cargar los detalles del movimiento:", error);
    toast.error("Error al cargar los detalles del movimiento.");
    closeDialog();
  } finally {
    loading.value = false;
  }
};

watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      if (props.movementId) {
        fetchMovementDetails(props.movementId);
      } else {
        toast.error("ID de movimiento no válido.");
        closeDialog();
      }
    } else {
      movementDetails.value = null;
    }
  }
);

const closeDialog = () => {
  emit("update:modelValue", false);
  movementDetails.value = null;
};

const handleViewOrder = async (orderId) => {
  if (!orderId) return;
  
  compactOrderLoading.value = true;
  try {
    const response = await axios.get(`/tpv/orders/${orderId}/print`);
    if (response.data?.data?.order) {
      const { order, hasCreditPayment } = response.data.data;
      
      compactOrderData.value = {
        order: order,
        currency: order.currency?.toUpperCase() || 'COP',
        products: order.details.map((detail) => ({
          id: detail.product?.id ?? detail.product_id,
          product_id: detail.product_id ?? detail.product?.id,
          title: detail.product?.name,
          active_ingredient: detail.product?.active_ingredient || null,
          laboratory: detail.product?.laboratory?.name ?? detail.product?.laboratory ?? null,
          selectedQuantity: detail.quantity,
          taxRate: detail.product?.iva,
          price_bs: parseFloat(detail.price),
          price_cop: parseFloat(detail.price),
          price: parseFloat(detail.price),
          price_before_discount: parseFloat(detail.price_before_discount),
        })),
        payments: order.payment_methods || [],
        change: parseFloat(order.money_returns || 0),
        total: parseFloat(order.total_amount || 0),
        credit: hasCreditPayment ? parseFloat(order.total_amount) : 0,
        hasCredit: hasCreditPayment
      };
      
      isCompactOrderVisible.value = true;
    } else {
      toast.error("No se pudo obtener la información de la orden.");
    }
  } catch (error) {
    console.error("Error al cargar la orden:", error);
    toast.error("Error al cargar los detalles de la orden.");
  } finally {
    compactOrderLoading.value = false;
  }
};

const handleViewInvoice = (invoiceId) => {
  const route = router.resolve({ name: 'invoice-invoice-ordered', query: { invoiceId } });
  const url = route.href.startsWith('http') ? route.href : `${window.location.origin}${route.href}`;
  window.open(url, '_blank');
};

const formatDate = (date) => {
  if (!date) return "N/A";
  return new Date(date).toLocaleString('es-ES', { 
    year: 'numeric', 
    month: 'short', 
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  });
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
  if (user.employee?.name && user.employee?.last_name) {
    return `${user.employee.name} ${user.employee.last_name}`;
  }
  return user.employee?.name || user.username || user.email || "N/A";
};
</script>

<template>
  <VDialog
    v-model="isDialogVisible"
    :max-width="mobile ? '100%' : '800px'"
    :fullscreen="mobile"
    persistent
    transition="dialog-bottom-transition"
    scrollable
  >
    <VCard class="detail-dialog-card overflow-hidden">
      <!-- Cabecera Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <VIcon icon="tabler-history" color="primary" />
            </VAvatar>
            <div>
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">Detalles del Movimiento</h2>
              <span class="text-caption text-white opacity-75" v-if="movementDetails">
                ID Movimiento: #{{ props.movementId }}
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="small" @click="closeDialog">
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 bg-light">
        <!-- Loader Cargando -->
        <div v-if="loading" class="d-flex flex-column align-center justify-center py-12">
          <VProgressCircular indeterminate color="primary" size="64" width="6" />
          <p class="mt-4 text-medium-emphasis font-weight-medium">Cargando detalles...</p>
        </div>

        <div v-else-if="movementDetails" class="d-flex flex-column gap-4">
          <!-- Banner de Tipo de Movimiento -->
          <VCard variant="flat" class="type-banner elevation-1">
            <div class="pa-4 d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <VIcon icon="tabler-arrows-left-right" size="24" class="text-primary me-3" />
                <div>
                  <span class="text-overline font-weight-black text-disabled leading-none">Tipo de Movimiento</span>
                  <p class="text-h6 font-weight-black mb-0 text-uppercase">{{ movementDetails.display_type }}</p>
                </div>
              </div>
              <VChip 
                :color="movementDetails.movement?.quantity > 0 ? 'success' : 'error'" 
                variant="flat" 
                class="font-weight-black elevation-1"
                size="large"
              >
                {{ movementDetails.movement?.quantity > 0 ? '+' : '' }}{{ movementDetails.movement?.quantity }} UNID.
              </VChip>
            </div>
          </VCard>

          <VRow dense>
            <!-- Columna Izquierda: Info Producto & Stock -->
            <VCol cols="12" md="7">
              <VCard variant="flat" class="border pa-4 h-100">
                <div class="d-flex align-center mb-4">
                  <VIcon icon="tabler-package" size="20" class="text-primary me-2" />
                  <span class="text-subtitle-2 font-weight-black text-uppercase">Información del Producto</span>
                </div>

                <div class="d-flex gap-3 align-start mb-6">
                  <VAvatar
                    v-if="movementDetails.movement?.product?.photo_url"
                    size="60"
                    variant="tonal"
                    rounded
                    :image="movementDetails.movement.product.photo_url"
                    class="border"
                  />
                  <div class="flex-grow-1 min-width-0">
                    <h3 class="text-h6 font-weight-black text-high-emphasis leading-tight mb-1 truncate">
                      {{ movementDetails.movement?.product?.name?.toUpperCase() }}
                    </h3>
                    <div class="d-flex flex-wrap gap-x-2 text-caption">
                      <span class="text-primary font-weight-bold">{{ movementDetails.movement?.product?.laboratory?.name }}</span>
                      <VDivider vertical class="mx-1" />
                      <span class="text-medium-emphasis">ID: {{ movementDetails.movement?.product_id }}</span>
                    </div>
                  </div>
                </div>

                <div class="stock-impact pa-3 rounded-lg d-flex justify-space-around align-center">
                  <div class="text-center">
                    <span class="text-overline font-weight-bold text-disabled">Antes</span>
                    <p class="text-h5 font-weight-black mb-0">{{ movementDetails.movement?.stock_before }}</p>
                  </div>
                  <VIcon icon="tabler-arrow-narrow-right" color="disabled" size="32" />
                  <div class="text-center">
                    <span class="text-overline font-weight-bold text-disabled">Después</span>
                    <p class="text-h5 font-weight-black mb-0 text-primary">{{ movementDetails.movement?.stock_after }}</p>
                  </div>
                </div>
              </VCard>
            </VCol>

            <!-- Columna Derecha: Trazabilidad & Usuario -->
            <VCol cols="12" md="5">
              <VCard variant="flat" class="border pa-4 h-100">
                <div class="d-flex align-center mb-4">
                  <VIcon icon="tabler-user-check" size="20" class="text-primary me-2" />
                  <span class="text-subtitle-2 font-weight-black text-uppercase">Responsable & Fecha</span>
                </div>

                <div class="d-flex flex-column gap-y-4">
                  <div class="info-item">
                    <span class="text-super-xs text-disabled text-uppercase font-weight-black">Operador</span>
                    <div class="d-flex align-center mt-1">
                      <VAvatar size="28" color="primary" variant="tonal" class="me-2 text-xs font-weight-bold">
                        {{ getUserDisplayName(movementDetails.movement?.user).charAt(0).toUpperCase() }}
                      </VAvatar>
                      <span class="text-body-2 font-weight-bold">{{ getUserDisplayName(movementDetails.movement?.user) }}</span>
                    </div>
                  </div>

                  <div class="info-item">
                    <span class="text-super-xs text-disabled text-uppercase font-weight-black">Fecha y Hora</span>
                    <div class="d-flex align-center mt-1">
                      <VIcon icon="tabler-calendar" size="18" class="text-medium-emphasis me-2" />
                      <span class="text-body-2 font-weight-medium">{{ formatDate(movementDetails.movement?.movement_date) }}</span>
                    </div>
                  </div>

                  <div class="info-item" v-if="movementDetails.movement?.product_lot_id">
                    <span class="text-super-xs text-disabled text-uppercase font-weight-black">Lote Afectado</span>
                    <div class="d-flex align-center mt-1">
                      <VChip size="x-small" color="secondary" variant="tonal" class="font-weight-black">
                        {{ movementDetails.movement?.product_lot?.lot_number || 'N/A' }}
                      </VChip>
                    </div>
                  </div>
                </div>
              </VCard>
            </VCol>
          </VRow>

          <!-- Sección de Referencia (Contextual) -->
          <VCard variant="flat" class="border elevation-0 overflow-hidden shadow-sm" v-if="movementDetails.type !== 'general'">
            <div class="bg-primary-lighten-5 pa-3 border-b d-flex align-center">
              <VIcon icon="tabler-link" size="20" class="text-primary me-2" />
              <span class="text-subtitle-2 font-weight-black text-uppercase">Documento de Referencia</span>
            </div>

            <div class="pa-4">
              <!-- Caso Venta / Devolución -->
              <div v-if="movementDetails.type === 'sale' || movementDetails.type === 'return'" class="d-flex align-center justify-space-between flex-wrap gap-3">
                <div class="d-flex align-center">
                  <VIcon 
                    :icon="movementDetails.type === 'sale' ? 'tabler-shopping-cart' : 'tabler-arrow-back'" 
                    size="40" 
                    :color="movementDetails.type === 'sale' ? 'primary' : 'warning'" 
                    class="me-3 opacity-75"
                  />
                  <div>
                    <p class="text-subtitle-1 font-weight-black mb-0">
                      {{ movementDetails.type === 'sale' ? 'Orden de Venta' : 'Solicitud de Devolución' }}
                    </p>
                    <span class="text-body-2 text-medium-emphasis" v-if="movementDetails.order || movementDetails.original_order">
                      Referencia: #{{ movementDetails.order?.id || movementDetails.original_order?.id }}
                    </span>
                  </div>
                </div>
                <VBtn 
                  variant="flat" 
                  color="primary" 
                  size="small" 
                  prepend-icon="tabler-eye"
                  :loading="compactOrderLoading"
                  @click="handleViewOrder(movementDetails.order?.id || movementDetails.original_order?.id)"
                  class="elevation-1"
                >
                  Ver Documento
                </VBtn>
              </div>

              <!-- Caso Compra -->
              <div v-else-if="movementDetails.type === 'purchase'" class="d-flex align-center justify-space-between flex-wrap gap-3">
                <div class="d-flex align-center">
                  <VIcon icon="tabler-receipt" size="40" color="success" class="me-3 opacity-75" />
                  <div>
                    <p class="text-subtitle-1 font-weight-black mb-0">Factura de Compra</p>
                    <span class="text-body-2 text-medium-emphasis">
                       Distribuidor: {{ movementDetails.supplier?.name || movementDetails.invoice?.supplier?.name || "N/A" }}
                    </span>
                  </div>
                </div>
                <VBtn 
                  v-if="movementDetails.invoice?.id"
                  variant="flat" 
                  color="success" 
                  size="small" 
                  prepend-icon="tabler-file-text"
                  @click="handleViewInvoice(movementDetails.invoice.id)"
                  class="elevation-1"
                >
                  Ver Factura
                </VBtn>
              </div>

              <!-- Casos de Auditoría (Ajuste, Pérdida) -->
              <div v-else-if="['adjustment', 'loss'].includes(movementDetails.type)" class="d-flex flex-column gap-2">
                <div class="d-flex align-center justify-space-between border-b pb-2 mb-2">
                  <span class="text-caption font-weight-bold text-disabled">Auditado por:</span>
                  <span class="text-body-2 font-weight-black text-primary">{{ getUserDisplayName(movementDetails.counted_by) }}</span>
                </div>
                <div class="d-flex align-center justify-space-between">
                  <span class="text-caption font-weight-bold text-disabled">Aprobado por:</span>
                  <span class="text-body-2 font-weight-black text-success">{{ getUserDisplayName(movementDetails.approved_by) }}</span>
                </div>
              </div>
            </div>
          </VCard>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light">
        <VBtn
          color="secondary"
          variant="tonal"
          @click="closeDialog"
          block
          class="font-weight-black py-3"
          height="44"
        >
          CERRAR DETALLES
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Visor Compacto de Órdenes -->
  <CompactOrderViewDialog
    v-model:is-dialog-visible="isCompactOrderVisible"
    :order-data="compactOrderData.order"
    :order-products="compactOrderData.products"
    :payments="compactOrderData.payments"
    :total-amount="compactOrderData.total"
    :selected-currency="compactOrderData.currency"
    :change-amount="compactOrderData.change"
    :credit-amount="compactOrderData.credit"
    :credit="compactOrderData.hasCredit"
  />
</template>

<style scoped>
.detail-dialog-card {
  border-radius: 16px !important;
}

.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.05) !important;
}

.type-banner {
  background: white;
  border-radius: 12px;
  border: 1px solid rgba(var(--v-border-color), 0.5);
}

.stock-impact {
  background-color: rgba(var(--v-theme-primary), 0.04);
  border: 1px dashed rgba(var(--v-theme-primary), 0.2);
}

.info-item {
  display: flex;
  flex-direction: column;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.5px;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.gap-3 { gap: 12px !important; }
.gap-4 { gap: 16px !important; }

/* Transiciones */
.dialog-bottom-transition-enter-active,
.dialog-bottom-transition-leave-active {
  transition: transform 0.3s ease-in-out;
}
</style>

