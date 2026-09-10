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
          id: detail.product?.id ?? detail.dish?.id ?? detail.product_id ?? detail.dish_id,
          product_id: detail.product_id ?? detail.dish_id ?? detail.product?.id ?? detail.dish?.id,
          title: detail.product?.name ?? detail.dish?.name ?? 'S/N',
          active_ingredient: detail.product?.active_ingredient || null,
          laboratory: detail.product?.laboratory?.name ?? detail.product?.laboratory ?? (detail.dish ? 'PLATO' : 'S/L'),
          selectedQuantity: detail.quantity,
          taxRate: detail.product?.iva ?? 0,
          price_bs: parseFloat(detail.price_bs ?? detail.price) || 0,
          price_cop: parseFloat(detail.price_cop ?? detail.price) || 0,
          price: parseFloat(detail.price) || 0,
          price_before_discount: detail.price_before_discount ? parseFloat(detail.price_before_discount) : null,
          discount_percentage: parseFloat(detail.discount_percentage) || 0,
          discount_type: detail.discount_type || null,
          discount_source_id: detail.discount_source_id || null,
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

const getUserDisplayName = (user) => {
  if (!user) return "N/A";
  if (typeof user === 'string') return user;
  if (user.employee?.name || user.employee?.last_name) {
    const name = user.employee.name ? user.employee.name.trim().split(" ")[0] : "";
    const lastName = user.employee.last_name ? user.employee.last_name.trim().split(" ")[0] : "";
    return `${name} ${lastName}`.trim() || user.name || user.username || user.email || "N/A";
  }
  return user.name || user.username || user.email || "N/A";
};
</script>

<template>
  <VDialog
    v-model="isDialogVisible"
    :max-width="mobile ? '100%' : '750px'"
    :fullscreen="mobile"
    persistent
    transition="dialog-bottom-transition"
    scrollable
  >
    <VCard class="detail-dialog-card overflow-hidden">
      <!-- Cabecera Premium Compacta -->
      <VCardTitle class="pa-0">
        <div class="header-gradient px-4 py-2.5 d-flex align-center">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="32" class="me-2 elevation-1">
              <VIcon icon="tabler-history" size="18" color="primary" />
            </VAvatar>
            <div>
              <h2 class="text-subtitle-1 font-weight-black text-white leading-tight mb-0" style="color: white !important;">Detalles del Movimiento</h2>
              <span class="text-caption text-white opacity-75" v-if="movementDetails" style="color: white !important;">
                ID Movimiento: {{ props.movementId }}
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="text" color="white" size="small" density="compact" @click="closeDialog">
            <VIcon size="20">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-3 bg-light">
        <!-- Loader Cargando -->
        <div v-if="loading" class="d-flex flex-column align-center justify-center py-8">
          <VProgressCircular indeterminate color="primary" size="48" width="4" />
          <p class="mt-3 text-caption text-medium-emphasis font-weight-medium">Cargando detalles...</p>
        </div>

        <div v-else-if="movementDetails" class="d-flex flex-column gap-2.5">
          <!-- Banner de Tipo de Movimiento Compacto -->
          <VCard variant="flat" class="type-banner pa-2.5 d-flex align-center justify-space-between border">
            <div class="d-flex align-center">
              <VIcon icon="tabler-arrows-left-right" size="20" class="text-primary me-2" />
              <div>
                <span class="text-super-xs font-weight-black text-disabled leading-none text-uppercase">Tipo de Movimiento</span>
                <p class="text-body-1 font-weight-black mb-0 text-uppercase">{{ movementDetails.display_type }}</p>
              </div>
            </div>
            <VChip 
              :color="movementDetails.movement?.quantity > 0 ? 'success' : 'error'" 
              variant="tonal" 
              class="font-weight-black"
              size="small"
            >
              {{ movementDetails.movement?.quantity > 0 ? '+' : '' }}{{ movementDetails.movement?.quantity }} UNID.
            </VChip>
          </VCard>

          <VRow dense class="mt-0">
            <!-- Columna Izquierda: Info Producto & Stock -->
            <VCol cols="12" md="7">
              <VCard variant="flat" class="border pa-3 h-100">
                <div class="d-flex align-center mb-2">
                  <VIcon icon="tabler-package" size="18" class="text-primary me-1.5" />
                  <span class="text-xs font-weight-black text-uppercase">Información del Producto</span>
                </div>

                <div class="mb-2">
                  <h3 class="text-body-2 font-weight-black text-high-emphasis text-uppercase text-truncate mb-1" :title="movementDetails.movement?.product?.name">
                    {{ movementDetails.movement?.product?.name?.toUpperCase() }}
                  </h3>
                  <div class="d-flex align-center flex-wrap gap-1 text-super-xs">
                    <span class="text-disabled truncate" style="max-inline-size: 140px;">
                      {{ movementDetails.movement?.product?.active_ingredient || "Sin principio" }}
                    </span>
                    <span class="text-disabled mx-1">|</span>
                    <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 120px;">
                      {{ movementDetails.movement?.product?.laboratory?.name || 'S/L' }}
                    </span>
                    <span class="text-disabled mx-1">|</span>
                    <span class="text-medium-emphasis font-weight-bold">ID: {{ movementDetails.movement?.product_id }}</span>
                  </div>
                </div>

                <div class="stock-impact pa-2 rounded d-flex justify-space-around align-center">
                  <div class="text-center">
                    <span class="text-super-xs font-weight-bold text-disabled text-uppercase">Stock Antes</span>
                    <p class="text-body-1 font-weight-bold mb-0 text-medium-emphasis">{{ movementDetails.movement?.stock_before }}</p>
                  </div>
                  <VIcon icon="tabler-arrow-narrow-right" color="disabled" size="24" />
                  <div class="text-center">
                    <span class="text-super-xs font-weight-bold text-disabled text-uppercase">Stock Después</span>
                    <p class="text-body-1 font-weight-black mb-0 text-primary">{{ movementDetails.movement?.stock_after }}</p>
                  </div>
                </div>
              </VCard>
            </VCol>

            <!-- Columna Derecha: Trazabilidad & Usuario -->
            <VCol cols="12" md="5">
              <VCard variant="flat" class="border pa-3 h-100">
                <div class="d-flex align-center mb-2">
                  <VIcon icon="tabler-user-check" size="18" class="text-primary me-1.5" />
                  <span class="text-xs font-weight-black text-uppercase">Responsable & Fecha</span>
                </div>

                <div class="d-flex flex-column gap-y-2">
                  <div class="info-item">
                    <span class="text-super-xs text-disabled text-uppercase font-weight-black">Operador</span>
                    <span class="text-caption font-weight-bold text-high-emphasis truncate">
                      {{ getUserDisplayName(movementDetails.movement?.user) }}
                    </span>
                  </div>

                  <div class="info-item">
                    <span class="text-super-xs text-disabled text-uppercase font-weight-black">Fecha y Hora</span>
                    <span class="text-caption font-weight-medium text-high-emphasis">
                      {{ formatDate(movementDetails.movement?.movement_date) }}
                    </span>
                  </div>

                  <div class="info-item" v-if="movementDetails.movement?.product_lot_id">
                    <span class="text-super-xs text-disabled text-uppercase font-weight-black">Lote Afectado</span>
                    <div>
                      <VChip size="x-small" color="secondary" variant="tonal" class="font-weight-black">
                        {{ movementDetails.movement?.product_lot?.lot_number || movementDetails.movement?.productLot?.lot_number || 'N/A' }}
                      </VChip>
                    </div>
                  </div>
                </div>
              </VCard>
            </VCol>
          </VRow>

          <!-- Sección de Referencia (Contextual Compacta) -->
          <VCard variant="flat" class="border overflow-hidden" v-if="movementDetails.type !== 'general'">
            <div class="bg-primary-lighten-5 px-3 py-1.5 border-b d-flex align-center">
              <VIcon icon="tabler-link" size="16" class="text-primary me-1.5" />
              <span class="text-super-xs font-weight-black text-uppercase">Documento de Referencia</span>
            </div>

            <div class="pa-2.5">
              <!-- Caso Venta / Devolución -->
              <div v-if="movementDetails.type === 'sale' || movementDetails.type === 'return'" class="d-flex align-center justify-space-between flex-wrap gap-2">
                <div class="d-flex align-center">
                  <VIcon 
                    :icon="movementDetails.type === 'sale' ? 'tabler-shopping-cart' : 'tabler-arrow-back'" 
                    size="28" 
                    :color="movementDetails.type === 'sale' ? 'primary' : 'warning'" 
                    class="me-2 opacity-75"
                  />
                  <div>
                    <p class="text-caption font-weight-black mb-0">
                      {{ movementDetails.type === 'sale' ? 'Orden de Venta' : 'Devolución' }}
                    </p>
                    <div class="d-flex flex-column text-super-xs text-medium-emphasis">
                      <span><strong>N°:</strong> #ORD-{{ movementDetails.order?.id || movementDetails.original_order?.id || movementDetails.movement?.order_id || 'N/A' }}</span>
                      <span v-if="movementDetails.order?.client?.name" class="truncate" style="max-inline-size: 220px;">
                        <strong>Cliente:</strong> {{ movementDetails.order.client.name }}
                      </span>
                    </div>
                  </div>
                </div>
                <VBtn 
                  variant="tonal" 
                  color="primary" 
                  size="x-small" 
                  prepend-icon="tabler-eye"
                  :loading="compactOrderLoading"
                  @click="handleViewOrder(movementDetails.order?.id || movementDetails.original_order?.id || movementDetails.movement?.order_id)"
                  class="font-weight-black"
                >
                  Ver Orden
                </VBtn>
              </div>

              <!-- Caso Compra -->
              <div v-else-if="movementDetails.type === 'purchase'" class="d-flex align-center justify-space-between flex-wrap gap-2">
                <div class="d-flex align-center">
                  <VIcon icon="tabler-receipt" size="28" color="success" class="me-2 opacity-75" />
                  <div>
                    <p class="text-caption font-weight-black mb-0">Factura de Compra</p>
                    <div class="d-flex flex-column text-super-xs text-medium-emphasis">
                      <span><strong>N° Factura:</strong> {{ movementDetails.invoice?.invoice_number || (movementDetails.movement?.invoice_id ? ('FAC-' + movementDetails.movement.invoice_id) : 'S/N') }}</span>
                      <span class="truncate" style="max-inline-size: 220px;"><strong>Proveedor:</strong> {{ movementDetails.supplier?.name || movementDetails.invoice?.supplier?.name || "N/A" }}</span>
                    </div>
                  </div>
                </div>
                <VBtn 
                  v-if="movementDetails.invoice?.id || movementDetails.movement?.invoice_id"
                  variant="tonal" 
                  color="success" 
                  size="x-small" 
                  prepend-icon="tabler-file-text"
                  @click="handleViewInvoice(movementDetails.invoice?.id || movementDetails.movement?.invoice_id)"
                  class="font-weight-black"
                >
                  Ver Factura
                </VBtn>
              </div>

              <!-- Casos de Auditoría (Ajuste, Pérdida, Verificación) -->
              <div v-else-if="['adjustment', 'loss', 'verification'].includes(movementDetails.type)" class="d-flex flex-column gap-1.5">
                <div class="d-flex align-center justify-space-between border-b pb-1.5">
                  <div class="d-flex flex-column">
                    <span class="text-super-xs font-weight-black text-disabled text-uppercase">Conteo Físico Inicial</span>
                    <span v-if="movementDetails.count_date" class="text-super-xs text-disabled">
                      {{ formatDate(movementDetails.count_date) }}
                    </span>
                  </div>
                  <div class="text-end">
                    <span class="text-caption font-weight-black text-primary d-block">
                      {{ getUserDisplayName(movementDetails.counted_by || movementDetails.movement?.user) }}
                    </span>
                    <span v-if="movementDetails.counted_quantity !== undefined" class="text-super-xs font-weight-bold text-medium-emphasis">
                      Físico: {{ movementDetails.counted_quantity }} | Sistema: {{ movementDetails.system_quantity }}
                    </span>
                  </div>
                </div>

                <div class="d-flex align-center justify-space-between">
                  <div class="d-flex flex-column">
                    <span class="text-super-xs font-weight-black text-disabled text-uppercase">
                      {{ movementDetails.is_auto_approved ? 'Validación del Sistema' : 'Auditado por' }}
                    </span>
                    <span v-if="movementDetails.approval_date" class="text-super-xs text-disabled">
                      {{ formatDate(movementDetails.approval_date) }}
                    </span>
                  </div>
                  <div class="text-end">
                    <template v-if="movementDetails.is_auto_approved">
                      <VChip size="x-small" color="primary" variant="tonal" class="font-weight-black">
                        <VIcon start icon="tabler-robot" size="12" />
                        Automático
                      </VChip>
                    </template>
                    <template v-else>
                      <span class="text-caption font-weight-black text-success d-block">
                        {{ getUserDisplayName(movementDetails.approved_by || movementDetails.movement?.user) }}
                      </span>
                    </template>
                    <div class="d-flex align-center justify-end gap-1 mt-0.5">
                      <span v-if="movementDetails.audited_quantity !== undefined" class="text-super-xs font-weight-bold text-medium-emphasis">
                        {{ movementDetails.is_auto_approved ? 'Contado:' : 'Auditado:' }} {{ movementDetails.audited_quantity }}
                      </span>
                      <VChip v-if="movementDetails.discrepancy !== undefined" size="x-small" :color="movementDetails.discrepancy >= 0 ? 'success' : 'error'" variant="tonal" class="font-weight-black">
                        Discrepancia: {{ movementDetails.discrepancy >= 0 ? '+' : '' }}{{ movementDetails.discrepancy }}
                      </VChip>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Caso Caducado -->
              <div v-else-if="movementDetails.type === 'expired'" class="d-flex flex-column gap-1">
                <div class="d-flex align-center justify-space-between border-b pb-1">
                  <span class="text-super-xs font-weight-bold text-disabled text-uppercase">Desincorporado por:</span>
                  <span class="text-caption font-weight-black text-primary">
                    {{ getUserDisplayName(movementDetails.expired_by || movementDetails.movement?.user) }}
                  </span>
                </div>
                <div class="d-flex align-center justify-space-between">
                  <span class="text-super-xs font-weight-bold text-disabled text-uppercase">Motivo:</span>
                  <span class="text-caption font-weight-black text-error">Vencimiento / Caducidad</span>
                </div>
              </div>
            </div>
          </VCard>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-2 bg-light">
        <VBtn
          color="secondary"
          variant="tonal"
          @click="closeDialog"
          block
          size="small"
          class="font-weight-black"
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
  background: linear-gradient(135deg, #7A0099, #E20074) !important;
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
