<script setup>
import { computed, onMounted, ref } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatCurrency } from "@/utils/currencyFormatter";
import { formatDateSimple } from "@/utils/formatters";

const orders = ref([]);
const loading = ref(false);
const searchQuery = ref("");
const statusFilter = ref(null);

const selectedOrder = ref(null);
const isDetailOpen = ref(false);
const processingAction = ref(false);

// Filtro de estados para Vuetify VSelect
const statusOptions = [
  { title: "Todos", value: null },
  { title: "Pendiente", value: "Pending" },
  { title: "Aprobado / Pagado", value: "Paid" },
  { title: "Enviado", value: "Shipped" },
  { title: "Completado", value: "Completed" },
  { title: "Cancelado", value: "Cancelled" },
];

const fetchOrders = async () => {
  loading.value = true;
  try {
    const response = await axios.get("/ecommerce/admin/orders");
    if (response.data && response.data.success) {
      orders.value = response.data.data || [];
    }
  } catch (error) {
    console.error("[ECO-ORDERS] Error fetching orders:", error);
    toast.error("No se pudieron cargar los pedidos del e-commerce.");
  } finally {
    loading.value = false;
  }
};

const filteredOrders = computed(() => {
  let list = orders.value;

  if (statusFilter.value) {
    list = list.filter((o) => o.status === statusFilter.value);
  }

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(
      (o) =>
        o.id.toString().includes(q) ||
        (o.customer_name && o.customer_name.toLowerCase().includes(q)) ||
        (o.customer_phone && o.customer_phone.toLowerCase().includes(q)) ||
        (o.customer_email && o.customer_email.toLowerCase().includes(q))
    );
  }

  return list;
});

const getStatusColor = (status) => {
  switch (status) {
    case "Pending":
      return "warning";
    case "Paid":
      return "info";
    case "Shipped":
      return "secondary";
    case "Completed":
      return "success";
    case "Cancelled":
      return "error";
    default:
      return "grey";
  }
};

const getStatusLabel = (status) => {
  switch (status) {
    case "Pending":
      return "Pendiente";
    case "Paid":
      return "Aprobado";
    case "Shipped":
      return "Enviado";
    case "Completed":
      return "Completado";
    case "Cancelled":
      return "Cancelado";
    default:
      return status;
  }
};

const openDetails = (order) => {
  selectedOrder.value = order;
  isDetailOpen.value = true;
};

// Acciones de la máquina de estados
const handleApprove = async (orderId) => {
  processingAction.value = true;
  try {
    const response = await axios.post(`/ecommerce/admin/orders/${orderId}/approve`);
    if (response.data.success) {
      toast.success("Pago del pedido aprobado con éxito.");
      await fetchOrders();
      // Actualizar la orden seleccionada si el panel sigue abierto
      if (selectedOrder.value && selectedOrder.value.id === orderId) {
        selectedOrder.value.status = "Paid";
      }
    }
  } catch (error) {
    toast.error(error.response?.data?.message || "Error al aprobar la orden.");
  } finally {
    processingAction.value = false;
  }
};

const handleShip = async (orderId) => {
  processingAction.value = true;
  try {
    const response = await axios.post(`/ecommerce/admin/orders/${orderId}/ship`);
    if (response.data.success) {
      toast.success("Pedido marcado como enviado.");
      await fetchOrders();
      if (selectedOrder.value && selectedOrder.value.id === orderId) {
        selectedOrder.value.status = "Shipped";
      }
    }
  } catch (error) {
    toast.error(error.response?.data?.message || "Error al marcar como enviado.");
  } finally {
    processingAction.value = false;
  }
};

const handleComplete = async (orderId) => {
  processingAction.value = true;
  try {
    const response = await axios.post(`/ecommerce/admin/orders/${orderId}/complete`);
    if (response.data.success) {
      toast.success("Pedido completado y consolidado en ventas.");
      await fetchOrders();
      if (selectedOrder.value && selectedOrder.value.id === orderId) {
        selectedOrder.value.status = "Completed";
      }
      isDetailOpen.value = false;
    }
  } catch (error) {
    toast.error(error.response?.data?.message || "Error al completar el pedido.");
  } finally {
    processingAction.value = false;
  }
};

const handleCancel = async (orderId) => {
  processingAction.value = true;
  try {
    const response = await axios.post(`/ecommerce/admin/orders/${orderId}/cancel`);
    if (response.data.success) {
      toast.success("Pedido cancelado y stock devuelto.");
      await fetchOrders();
      if (selectedOrder.value && selectedOrder.value.id === orderId) {
        selectedOrder.value.status = "Cancelled";
      }
      isDetailOpen.value = false;
    }
  } catch (error) {
    toast.error(error.response?.data?.message || "Error al cancelar el pedido.");
  } finally {
    processingAction.value = false;
  }
};

onMounted(() => {
  fetchOrders();
});
</script>

<template>
  <VContainer fluid class="ecommerce-orders-page pa-4">
    <!-- Encabezado de Página -->
    <VRow class="mb-4">
      <VCol cols="12" class="d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="flat" size="48" class="elevation-2 rounded-xl">
            <VIcon icon="tabler-truck-delivery" size="28" color="white" />
          </VAvatar>
          <div>
            <h1 class="text-h5 font-weight-black text-high-emphasis mb-0 uppercase leading-none">
              Pedidos Eco
            </h1>
            <span class="text-xs text-disabled font-weight-bold uppercase letter-spacing-1 mt-1 d-inline-block">
              Gestión y Despacho de Pedidos E-commerce
            </span>
          </div>
        </div>
        <VBtn
          color="primary"
          prepend-icon="tabler-refresh"
          variant="flat"
          class="rounded-lg font-weight-bold"
          :loading="loading"
          @click="fetchOrders"
        >
          Sincronizar
        </VBtn>
      </VCol>
    </VRow>

    <!-- Filtros Inteligentes -->
    <VCard variant="flat" border class="pa-4 rounded-xl mb-6 shadow-sm bg-surface">
      <VRow dense align="center">
        <VCol cols="12" sm="6" md="4">
          <VTextField
            v-model="searchQuery"
            placeholder="Buscar por ID, Cliente, Teléfono..."
            density="compact"
            variant="outlined"
            prepend-inner-icon="tabler-search"
            clearable
            hide-details
            class="rounded-lg"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            v-model="statusFilter"
            :items="statusOptions"
            placeholder="Filtrar por Estado"
            density="compact"
            variant="outlined"
            prepend-inner-icon="tabler-adjustments-horizontal"
            hide-details
            class="rounded-lg"
          />
        </VCol>
      </VRow>
    </VCard>

    <!-- Listado Principal de Pedidos -->
    <VCard variant="flat" border class="rounded-xl overflow-hidden shadow-sm bg-surface">
      <VDataTable
        :headers="[
          { title: 'Pedido ID', key: 'id', sortable: true, width: '110px' },
          { title: 'Cliente', key: 'customer_name', sortable: true },
          { title: 'Contacto / Teléfono', key: 'customer_phone', sortable: false },
          { title: 'Fecha de Compra', key: 'created_at', sortable: true },
          { title: 'Método Pago', key: 'payment_method', sortable: true },
          { title: 'Total del Pedido', key: 'total_amount', sortable: true, align: 'end' },
          { title: 'Estado Despacho', key: 'status', sortable: true, align: 'center', width: '140px' },
          { title: 'Detalles', key: 'actions', sortable: false, align: 'center', width: '90px' },
        ]"
        :items="filteredOrders"
        :loading="loading"
        density="comfortable"
        class="orders-table"
        no-data-text="No se encontraron pedidos registrados"
      >
        <!-- Formateo ID -->
        <template #item.id="{ item }">
          <span class="font-weight-black text-primary">#{{ item.id }}</span>
        </template>

        <!-- Formateo Cliente -->
        <template #item.customer_name="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-black text-high-emphasis leading-tight">{{ item.customer_name }}</span>
            <span class="text-super-xs text-disabled truncate" style="max-inline-size: 180px;">{{ item.customer_email || 'Sin correo electrónico' }}</span>
          </div>
        </template>

        <!-- Formateo Teléfono -->
        <template #item.customer_phone="{ item }">
          <span class="font-weight-bold text-medium-emphasis">{{ item.customer_phone || '—' }}</span>
        </template>

        <!-- Formateo Fecha -->
        <template #item.created_at="{ item }">
          <span class="text-subtitle-2 font-weight-medium text-medium-emphasis">
            {{ formatDateSimple(item.created_at) }}
          </span>
        </template>

        <!-- Formateo Pago -->
        <template #item.payment_method="{ item }">
          <VChip size="x-small" variant="tonal" color="primary" class="font-weight-bold rounded-lg text-uppercase">
            {{ item.payment_method || 'Web' }}
          </VChip>
        </template>

        <!-- Formateo Total -->
        <template #item.total_amount="{ item }">
          <span class="font-weight-black text-subtitle-2 text-primary">
            {{ formatCurrency(item.total_amount, 'COP') }}
          </span>
        </template>

        <!-- Formateo Estado -->
        <template #item.status="{ item }">
          <VChip
            :color="getStatusColor(item.status)"
            size="small"
            variant="flat"
            class="font-weight-black uppercase px-3 shadow-sm rounded-lg"
            style="min-inline-size: 100px; text-align: center; justify-content: center;"
          >
            {{ getStatusLabel(item.status) }}
          </VChip>
        </template>

        <!-- Acciones -->
        <template #item.actions="{ item }">
          <VBtn
            icon="tabler-eye"
            color="primary"
            variant="tonal"
            size="small"
            class="rounded-lg"
            @click="openDetails(item)"
          />
        </template>
      </VDataTable>
    </VCard>

    <!-- Modal Lateral de Detalles Premium -->
    <VNavigationDrawer
      v-model="isDetailOpen"
      location="end"
      temporary
      width="500"
      class="detail-drawer"
      scrim="rgba(0,0,0,0.3)"
    >
      <div v-if="selectedOrder" class="d-flex flex-column h-100 bg-light">
        <!-- Cabecera Premium -->
        <div class="header-gradient pa-4 d-flex align-center text-white shadow-sm">
          <VAvatar color="white" variant="flat" size="36" class="me-3 elevation-1">
            <VIcon icon="tabler-file-invoice" size="20" color="primary" />
          </VAvatar>
          <div>
            <h3 class="text-subtitle-1 font-weight-black leading-none mb-0 text-white">
              Pedido #{{ selectedOrder.id }}
            </h3>
            <span class="text-super-xs text-white opacity-75 font-weight-bold uppercase mt-1 d-inline-block">
              Detalles e Historial
            </span>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="isDetailOpen = false"
          />
        </div>

        <!-- Contenido Desplazable -->
        <div class="flex-grow-1 overflow-y-auto pa-4 d-flex flex-column gap-4">
          <!-- Tarjeta Cliente -->
          <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm">
            <div class="d-flex align-center gap-2 mb-3">
              <div class="header-indicator primary" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Datos del Cliente</span>
            </div>
            <div class="d-flex flex-column gap-2 text-subtitle-2">
              <div class="d-flex justify-space-between border-b pb-1">
                <span class="text-disabled">Nombre:</span>
                <span class="font-weight-black text-high-emphasis">{{ selectedOrder.customer_name }}</span>
              </div>
              <div class="d-flex justify-space-between border-b pb-1">
                <span class="text-disabled">Teléfono:</span>
                <span class="font-weight-bold text-medium-emphasis">{{ selectedOrder.customer_phone || '—' }}</span>
              </div>
              <div class="d-flex justify-space-between border-b pb-1">
                <span class="text-disabled">Correo:</span>
                <span class="font-weight-medium text-medium-emphasis truncate" style="max-inline-size: 250px;">{{ selectedOrder.customer_email || '—' }}</span>
              </div>
              <div class="d-flex justify-space-between">
                <span class="text-disabled">Método Pago:</span>
                <span class="font-weight-black text-primary text-uppercase">{{ selectedOrder.payment_method || 'Web' }}</span>
              </div>
            </div>
          </VCard>

          <!-- Tarjeta Envío / Despacho -->
          <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm">
            <div class="d-flex align-center gap-2 mb-3">
              <div class="header-indicator secondary" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Información de Envío</span>
            </div>
            <div class="d-flex flex-column gap-2">
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Dirección de Entrega</span>
                <div class="pa-3 bg-light rounded-lg border text-subtitle-2 font-weight-medium text-high-emphasis">
                  {{ selectedOrder.shipping_address || 'Retiro en local / Sin dirección' }}
                </div>
              </div>
              <div class="d-flex flex-column mt-2" v-if="selectedOrder.notes">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Notas del Pedido</span>
                <div class="pa-3 bg-light rounded-lg border border-dashed text-caption italic text-medium-emphasis">
                  "{{ selectedOrder.notes }}"
                </div>
              </div>
            </div>
          </VCard>

          <!-- Desglose de Productos -->
          <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm">
            <div class="d-flex align-center gap-2 mb-3">
              <div class="header-indicator info" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Ítems del Pedido</span>
            </div>

            <div class="d-flex flex-column gap-3">
              <div
                v-for="item in selectedOrder.items"
                :key="item.id"
                class="d-flex align-center justify-space-between border-b pb-2"
              >
                <div class="d-flex flex-column min-width-0">
                  <span class="font-weight-black text-subtitle-2 text-high-emphasis truncate" style="max-inline-size: 280px;">
                    {{ item.product_name }}
                  </span>
                  <span class="text-super-xs text-disabled" v-if="item.variant_value">
                    Variante: {{ item.variant_value }}
                  </span>
                  <span class="text-super-xs text-primary font-weight-bold">
                    {{ item.quantity }} unid. x {{ formatCurrency(item.price, 'COP') }}
                  </span>
                </div>
                <span class="font-weight-black text-subtitle-2 text-high-emphasis ms-2">
                  {{ formatCurrency(item.price * item.quantity, 'COP') }}
                </span>
              </div>
            </div>

            <!-- Total General -->
            <div class="d-flex justify-space-between align-center pt-3 mt-2 border-t border-dashed">
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase">Total General</span>
              <span class="text-h5 font-weight-black text-primary">
                {{ formatCurrency(selectedOrder.total_amount, 'COP') }}
              </span>
            </div>
          </VCard>
        </div>

        <!-- Barra de Acciones del Estado -->
        <div class="pa-4 bg-white border-t d-flex flex-column gap-2">
          <!-- Estado Actual -->
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-xs font-weight-black text-disabled uppercase">Estado Actual:</span>
            <VChip
              :color="getStatusColor(selectedOrder.status)"
              size="small"
              variant="flat"
              class="font-weight-black uppercase px-3 shadow-sm rounded-lg"
            >
              {{ getStatusLabel(selectedOrder.status) }}
            </VChip>
          </div>

          <!-- Botones de Transición -->
          <div class="d-flex flex-column gap-2">
            <!-- Caso Pending: Aprobar Pago -->
            <VBtn
              v-if="selectedOrder.status === 'Pending'"
              color="primary"
              variant="flat"
              block
              height="44"
              class="font-weight-black rounded-lg"
              :loading="processingAction"
              @click="handleApprove(selectedOrder.id)"
            >
              <VIcon icon="tabler-discount-check" class="me-2" size="18" />
              Aprobar Pago
            </VBtn>

            <!-- Caso Paid: Enviar pedido -->
            <VBtn
              v-if="selectedOrder.status === 'Paid'"
              color="info"
              variant="flat"
              block
              height="44"
              class="font-weight-black rounded-lg"
              :loading="processingAction"
              @click="handleShip(selectedOrder.id)"
            >
              <VIcon icon="tabler-truck" class="me-2" size="18" />
              Marcar como Enviado
            </VBtn>

            <!-- Caso Shipped: Completar entrega y consolidar -->
            <VBtn
              v-if="selectedOrder.status === 'Shipped'"
              color="success"
              variant="flat"
              block
              height="44"
              class="font-weight-black rounded-lg"
              :loading="processingAction"
              @click="handleComplete(selectedOrder.id)"
            >
              <VIcon icon="tabler-circle-check" class="me-2" size="18" />
              Completar y Consolidar Venta
            </VBtn>

            <!-- Cancelar (Disponible para cualquier estado no terminal) -->
            <VBtn
              v-if="selectedOrder.status !== 'Completed' && selectedOrder.status !== 'Cancelled'"
              color="error"
              variant="tonal"
              block
              height="44"
              class="font-weight-black rounded-lg"
              :loading="processingAction"
              @click="handleCancel(selectedOrder.id)"
            >
              <VIcon icon="tabler-circle-x" class="me-2" size="18" />
              Cancelar Pedido (Devolver Stock)
            </VBtn>
          </div>
        </div>
      </div>
    </VNavigationDrawer>
  </VContainer>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end)) 100%
  );
}

.detail-drawer {
  border-inline-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 14px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.header-indicator.secondary {
  background-color: rgb(var(--v-theme-secondary));
}

.header-indicator.info {
  background-color: rgb(var(--v-theme-info));
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-none {
  line-height: 1 !important;
}

.leading-tight {
  line-height: 1.25 !important;
}

.orders-table :deep(.v-data-table-header) {
  background-color: #f1f5f9;
}

.orders-table :deep(.v-data-table-header th) {
  color: #64748b !important;
  font-size: 0.65rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  border-bottom: 2px solid #e2e8f0 !important;
}

.orders-table :deep(td) {
  padding-block: 12px !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.05) !important;
}

.uppercase {
  text-transform: uppercase;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
