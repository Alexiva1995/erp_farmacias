<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatCurrency } from "@/utils/currencyFormatter";
import { formatDateSimple } from "@/utils/formatters";
import AppFilterBase from "@/components/AppFilterBase.vue";

const orders = ref([]);
const loading = ref(false);
const processingAction = ref(false);

const activeTab = ref(0);
const isAdvancedFiltersVisible = ref(false);

// Filtros inteligentes
const filterSearchQuery = ref("");
const filterSearchQueryId = ref("");
const statusFilterAll = ref(null);

// Rango de fechas global
const toDateString = (d) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;

const getToday = () => toDateString(new Date());
const getFirstDayOfMonth = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-01`;
};

const globalStartDate = ref(getFirstDayOfMonth());
const globalEndDate = ref(getToday());

const setDateRange = (start, end) => {
  globalStartDate.value = start;
  globalEndDate.value = end;
};
const setDateHoy = () => {
  const t = new Date();
  setDateRange(toDateString(t), toDateString(t));
};
const setDateAyer = () => {
  const a = new Date();
  a.setDate(a.getDate() - 1);
  const s = toDateString(a);
  setDateRange(s, s);
};
const setDateSemana = () => {
  const h = new Date();
  const inicio = new Date(h);
  const dia = inicio.getDay();
  const diff = inicio.getDate() - dia + (dia === 0 ? -6 : 1);
  inicio.setDate(diff);
  setDateRange(toDateString(inicio), toDateString(h));
};
const setDateMes = () => {
  const h = new Date();
  setDateRange(getFirstDayOfMonth(), toDateString(h));
};
const setDateAno = () => {
  const h = new Date();
  const inicio = `${h.getFullYear()}-01-01`;
  setDateRange(inicio, toDateString(h));
};

const handleClearFilters = () => {
  filterSearchQuery.value = "";
  filterSearchQueryId.value = "";
  statusFilterAll.value = null;
  setDateHoy();
};

const selectedOrder = ref(null);
const isDetailOpen = ref(false);

// Opciones de cabecera de la tabla
const headers = [
  { title: 'Pedido ID', key: 'id', sortable: true, width: '110px' },
  { title: 'Cliente', key: 'customer_name', sortable: true },
  { title: 'Contacto / Teléfono', key: 'customer_phone', sortable: false },
  { title: 'Fecha de Compra', key: 'created_at', sortable: true },
  { title: 'Método Pago', key: 'payment_method', sortable: true },
  { title: 'Total del Pedido', key: 'total_amount', sortable: true, align: 'end' },
  { title: 'Estado Despacho', key: 'status', sortable: true, align: 'center', width: '140px' },
  { title: 'Detalles', key: 'actions', sortable: false, align: 'center', width: '90px' },
];

const fetchOrders = async () => {
  loading.value = true;
  try {
    const params = {};
    if (globalStartDate.value) params.start_date = globalStartDate.value;
    if (globalEndDate.value) params.end_date = globalEndDate.value;

    const response = await axios.get("/ecommerce/admin/orders", { params });
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

watch([globalStartDate, globalEndDate], () => {
  fetchOrders();
});

// Filtrado de las órdenes en memoria según filtros y rango de fechas
const applyFilters = (list) => {
  if (filterSearchQuery.value) {
    const q = filterSearchQuery.value.toLowerCase();
    list = list.filter(
      (o) =>
        o.id.toString().includes(q) ||
        (o.customer_name && o.customer_name.toLowerCase().includes(q)) ||
        (o.customer_phone && o.customer_phone.toLowerCase().includes(q)) ||
        (o.customer_email && o.customer_email.toLowerCase().includes(q))
    );
  }

  if (filterSearchQueryId.value) {
    const idQ = filterSearchQueryId.value.trim();
    list = list.filter((o) => o.id.toString() === idQ);
  }

  if (globalStartDate.value) {
    const start = new Date(globalStartDate.value + "T00:00:00");
    list = list.filter((o) => new Date(o.created_at) >= start);
  }

  if (globalEndDate.value) {
    const end = new Date(globalEndDate.value + "T23:59:59");
    list = list.filter((o) => new Date(o.created_at) <= end);
  }

  return list;
};

// Listas Computadas por Pestaña
const ordersPending = computed(() => applyFilters(orders.value.filter(o => o.status === 'Pending')));
const ordersPaid = computed(() => applyFilters(orders.value.filter(o => o.status === 'Paid')));
const ordersShipped = computed(() => applyFilters(orders.value.filter(o => o.status === 'Shipped')));
const ordersCompleted = computed(() => applyFilters(orders.value.filter(o => o.status === 'Completed')));
const ordersCancelled = computed(() => applyFilters(orders.value.filter(o => o.status === 'Cancelled')));
const ordersAll = computed(() => {
  let list = orders.value;
  if (statusFilterAll.value) {
    list = list.filter(o => o.status === statusFilterAll.value);
  }
  return applyFilters(list);
});

// Conteos Totales (ignoran los filtros de texto/fechas para los badges de las pestañas)
const countPending = computed(() => orders.value.filter(o => o.status === 'Pending').length);
const countPaid = computed(() => orders.value.filter(o => o.status === 'Paid').length);
const countShipped = computed(() => orders.value.filter(o => o.status === 'Shipped').length);
const countCompleted = computed(() => orders.value.filter(o => o.status === 'Completed').length);
const countCancelled = computed(() => orders.value.filter(o => o.status === 'Cancelled').length);
const countAll = computed(() => orders.value.length);

const getStatusColor = (status) => {
  switch (status) {
    case "Pending": return "warning";
    case "Paid": return "info";
    case "Shipped": return "secondary";
    case "Completed": return "success";
    case "Cancelled": return "error";
    default: return "grey";
  }
};

const getStatusLabel = (status) => {
  switch (status) {
    case "Pending": return "Pendiente";
    case "Paid": return "Aprobado";
    case "Shipped": return "Enviado";
    case "Completed": return "Completado";
    case "Cancelled": return "Cancelado";
    default: return status;
  }
};

// Traduce la clave interna del método de pago a texto legible en español
const getPaymentLabel = (method) => {
  const map = {
    mobile_payment: 'Pago Móvil',
    bank_transfer_bs: 'Transferencia Bancaria (Bs)',
    cash_bs: 'Efectivo Bolívares',
    binance: 'Binance Pay',
    paypal: 'PayPal',
    cash_usd: 'Efectivo USD',
    bank_transfer: 'Transferencia Bancaria',
    cash_cop: 'Efectivo COP',
  }
  return map[method] || method || 'Web'
}

const statusOptions = [
  { title: "Todos", value: null },
  { title: "Pendiente", value: "Pending" },
  { title: "Aprobado / Pagado", value: "Paid" },
  { title: "Enviado", value: "Shipped" },
  { title: "Completado", value: "Completed" },
  { title: "Cancelado", value: "Cancelled" },
];

const openDetails = (order) => {
  selectedOrder.value = order;
  isDetailOpen.value = true;
};

// Acciones del flujo
const handleApprove = async (orderId) => {
  processingAction.value = true;
  try {
    const response = await axios.post(`/ecommerce/admin/orders/${orderId}/approve`);
    if (response.data.success) {
      toast.success("Pago del pedido aprobado con éxito.");
      await fetchOrders();
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
  <div>
    <!-- Contenedor Estándar de Filtros -->
    <AppFilterBase
      :search="filterSearchQuery"
      :has-advanced-filters="isAdvancedFiltersVisible || !!(filterSearchQueryId || statusFilterAll)"
      :show-export="false"
      search-placeholder="Buscar por ID, Cliente, Teléfono o Correo..."
      class="py-1"
      @update:search="filterSearchQuery = $event"
      @clear="handleClearFilters"
    >
      <!-- Slot extra: Rango Rápido de Fechas -->
      <template #search-extra>
        <div class="d-none d-lg-flex align-center gap-2 ms-4 border-s ps-4">
          <span class="text-caption font-weight-bold text-uppercase text-disabled me-1">Rango:</span>
          <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateHoy">Hoy</VBtn>
          <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateAyer">Ayer</VBtn>
          <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateSemana">Semana</VBtn>
          <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateMes">Mes</VBtn>
          <VBtn color="primary" variant="tonal" size="x-small" class="rounded-pill px-3" @click="setDateAno">Año</VBtn>
        </div>
      </template>

      <!-- Slot Filtros Avanzados -->
      <template #advanced-filters>
        <VCol cols="12" sm="3" md="2">
          <AppDateTimePicker
            v-model="globalStartDate"
            placeholder="Fecha Inicial"
            prepend-inner-icon="tabler-calendar"
            clearable
            hide-details
            density="compact"
            :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <AppDateTimePicker
            v-model="globalEndDate"
            placeholder="Fecha Final"
            prepend-inner-icon="tabler-calendar"
            clearable
            hide-details
            density="compact"
            :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <VTextField
            v-model="filterSearchQueryId"
            placeholder="ID Pedido"
            prepend-inner-icon="tabler-hash"
            clearable
            hide-details
            density="compact"
            variant="outlined"
          />
        </VCol>
        <VCol v-if="activeTab === 5" cols="12" sm="3" md="2">
          <VSelect
            v-model="statusFilterAll"
            placeholder="Estado Despacho"
            prepend-inner-icon="tabler-adjustments-horizontal"
            :items="statusOptions"
            clearable
            hide-details
            density="compact"
            variant="outlined"
          />
        </VCol>
      </template>
    </AppFilterBase>

    <!-- Pestañas con badge de cantidad -->
    <VTabs v-model="activeTab" class="mb-4 orders-tabs" density="comfortable">
      <VTab :value="0" class="tab-with-badge">
        <span class="d-inline-flex align-center gap-2">
          Pendientes
          <VChip size="x-small" variant="tonal" color="warning" class="tab-count">
            {{ countPending }}
          </VChip>
        </span>
      </VTab>
      <VTab :value="1" class="tab-with-badge">
        <span class="d-inline-flex align-center gap-2">
          Aprobados
          <VChip size="x-small" variant="tonal" color="info" class="tab-count">
            {{ countPaid }}
          </VChip>
        </span>
      </VTab>
      <VTab :value="2" class="tab-with-badge">
        <span class="d-inline-flex align-center gap-2">
          Enviados
          <VChip size="x-small" variant="tonal" color="secondary" class="tab-count">
            {{ countShipped }}
          </VChip>
        </span>
      </VTab>
      <VTab :value="3" class="tab-with-badge">
        <span class="d-inline-flex align-center gap-2">
          Completados
          <VChip size="x-small" variant="tonal" color="success" class="tab-count">
            {{ countCompleted }}
          </VChip>
        </span>
      </VTab>
      <VTab :value="4" class="tab-with-badge">
        <span class="d-inline-flex align-center gap-2">
          Cancelados
          <VChip size="x-small" variant="tonal" color="error" class="tab-count">
            {{ countCancelled }}
          </VChip>
        </span>
      </VTab>
      <VTab :value="5" class="tab-with-badge">
        <span class="d-inline-flex align-center gap-2">
          Todos
          <VChip size="x-small" variant="tonal" color="primary" class="tab-count">
            {{ countAll }}
          </VChip>
        </span>
      </VTab>
    </VTabs>

    <VWindow v-model="activeTab" class="orders-window">
      <!-- Helper Macro Component para renderizar tablas réplicas -->
      <VWindowItem v-for="(tabItems, tabIdx) in [ordersPending, ordersPaid, ordersShipped, ordersCompleted, ordersCancelled, ordersAll]" :key="tabIdx" :value="tabIdx">
        <VCard variant="flat" border class="rounded-lg overflow-hidden shadow-sm">
          <VDataTable
            :headers="headers"
            :items="tabItems"
            :loading="loading"
            density="comfortable"
            class="orders-table"
            no-data-text="No se encontraron pedidos registrados"
          >
            <template #item.id="{ item }">
              <span class="font-weight-black text-primary">#{{ item.id }}</span>
            </template>

            <template #item.customer_name="{ item }">
              <div class="d-flex flex-column">
                <span class="font-weight-black text-high-emphasis leading-tight">{{ item.customer_name }}</span>
                <span class="text-super-xs text-disabled truncate" style="max-inline-size: 200px;">{{ item.customer_email || 'Sin correo' }}</span>
              </div>
            </template>

            <template #item.customer_phone="{ item }">
              <span class="font-weight-bold text-medium-emphasis">{{ item.customer_phone || '—' }}</span>
            </template>

            <template #item.created_at="{ item }">
              <span class="text-subtitle-2 font-weight-medium text-medium-emphasis">
                {{ formatDateSimple(item.created_at) }}
              </span>
            </template>

            <template #item.payment_method="{ item }">
              <VChip size="x-small" variant="tonal" color="primary" class="font-weight-bold rounded-lg text-uppercase">
                {{ item.payment_method || 'Web' }}
              </VChip>
            </template>

            <template #item.total_amount="{ item }">
              <span class="font-weight-black text-subtitle-2 text-primary">
                {{ Number(item.total_amount).toFixed(2) }}
              </span>
            </template>

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
      </VWindowItem>
    </VWindow>

    <!-- Modal de Detalles (diálogo centrado clásico) -->
    <VDialog v-model="isDetailOpen" max-width="580" :scrollable="false">
      <VCard v-if="selectedOrder" style="border-radius: 8px; overflow: hidden;">

        <!-- Cabecera -->
        <div class="header-gradient pa-3 d-flex align-center text-white">
          <VIcon icon="tabler-file-invoice" size="18" class="me-2" />
          <div>
            <div class="text-subtitle-2 font-weight-black text-white">Pedido #{{ selectedOrder.id }}</div>
            <div class="text-caption text-white opacity-70" style="font-size: 10px;">{{ formatDateSimple(selectedOrder.created_at) }}</div>
          </div>
          <VSpacer />
          <VChip :color="getStatusColor(selectedOrder.status)" size="x-small" variant="flat" class="font-weight-black me-2">
            {{ getStatusLabel(selectedOrder.status) }}
          </VChip>
          <VBtn icon="tabler-x" variant="tonal" color="white" size="x-small" @click="isDetailOpen = false" />
        </div>

        <VCardText class="pa-4" style="overflow: hidden;">

          <!-- Datos del Cliente: grid 2 columnas -->
          <div class="section-label mb-1">Datos del Cliente</div>
          <div class="info-grid mb-3">
            <div class="info-cell">
              <span class="info-key">Nombre</span>
              <span class="info-val font-weight-bold">{{ selectedOrder.customer_name }}</span>
            </div>
            <div class="info-cell">
              <span class="info-key">Documento</span>
              <span class="info-val">{{ (selectedOrder.customer_document_type || '') + (selectedOrder.customer_document_number || '—') }}</span>
            </div>
            <div class="info-cell">
              <span class="info-key">Teléfono</span>
              <span class="info-val">{{ selectedOrder.customer_phone || '—' }}</span>
            </div>
            <div class="info-cell">
              <span class="info-key">Correo</span>
              <span class="info-val" style="word-break: break-all;">{{ selectedOrder.customer_email || '—' }}</span>
            </div>
            <div class="info-cell">
              <span class="info-key">Método de Pago</span>
              <span class="info-val font-weight-bold text-primary">{{ getPaymentLabel(selectedOrder.payment_method) }}</span>
            </div>
            <div class="info-cell">
              <span class="info-key">Dirección de Entrega</span>
              <span class="info-val">{{ selectedOrder.shipping_address || 'Retiro en local' }}</span>
            </div>
          </div>

          <!-- Ítems del pedido -->
          <div class="section-label mb-1">Ítems del Pedido</div>
          <table class="items-table mb-2">
            <thead>
              <tr>
                <th class="text-left">Producto</th>
                <th class="text-center" style="width: 50px;">Cant.</th>
                <th class="text-right" style="width: 90px;">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in selectedOrder.items" :key="item.id">
                <td>
                  <div class="font-weight-medium" style="font-size: 12px;">{{ item.product_name }}</div>
                  <div v-if="item.variant_value" style="font-size: 10px; color: #999;">{{ item.variant_value }}</div>
                </td>
                <td class="text-center" style="font-size: 12px;">{{ item.quantity }}</td>
                <td class="text-right font-weight-bold" style="font-size: 12px;">{{ Number(item.price * item.quantity).toFixed(2) }}</td>
              </tr>
            </tbody>
          </table>

          <!-- Total -->
          <div class="d-flex justify-space-between align-center px-2 py-2" style="background: rgba(var(--v-theme-primary), 0.06); border-radius: 6px;">
            <span style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">Total General</span>
            <span class="text-primary font-weight-black" style="font-size: 18px;">{{ Number(selectedOrder.total_amount).toFixed(2) }}</span>
          </div>

          <!-- Notas (opcional) -->
          <div v-if="selectedOrder.notes" class="mt-2 pa-2" style="background: #f9f9f9; border-left: 3px solid #ddd; font-size: 11px; color: #666; font-style: italic;">
            "📋 {{ selectedOrder.notes }}"
          </div>

        </VCardText>

        <!-- Acciones: botones uno al lado del otro -->
        <VDivider />
        <VCardActions class="pa-3 gap-2">
          <!-- Aprobar Pago (Pending) -->
          <VBtn
            v-if="selectedOrder.status === 'Pending'"
            color="primary" variant="flat" height="38" class="font-weight-black flex-grow-1"
            :loading="processingAction" @click="handleApprove(selectedOrder.id)"
          >
            <VIcon icon="tabler-discount-check" size="16" class="me-1" /> Aprobar
          </VBtn>

          <!-- Enviar Pedido (Paid) -->
          <VBtn
            v-if="selectedOrder.status === 'Paid'"
            color="info" variant="flat" height="38" class="font-weight-black flex-grow-1"
            :loading="processingAction" @click="handleShip(selectedOrder.id)"
          >
            <VIcon icon="tabler-truck" size="16" class="me-1" /> Marcar Enviado
          </VBtn>

          <!-- Completar (Shipped) -->
          <VBtn
            v-if="selectedOrder.status === 'Shipped'"
            color="success" variant="flat" height="38" class="font-weight-black flex-grow-1"
            :loading="processingAction" @click="handleComplete(selectedOrder.id)"
          >
            <VIcon icon="tabler-circle-check" size="16" class="me-1" /> Completar Venta
          </VBtn>

          <!-- Cancelar (siempre visible si no es terminal) -->
          <VBtn
            v-if="selectedOrder.status !== 'Completed' && selectedOrder.status !== 'Cancelled'"
            color="error" variant="tonal" height="38" class="font-weight-black flex-grow-1"
            :loading="processingAction" @click="handleCancel(selectedOrder.id)"
          >
            <VIcon icon="tabler-circle-x" size="16" class="me-1" /> Cancelar
          </VBtn>

          <!-- Cerrar (estados terminales) -->
          <VBtn
            v-if="selectedOrder.status === 'Completed' || selectedOrder.status === 'Cancelled'"
            color="secondary" variant="tonal" height="38" class="font-weight-black flex-grow-1"
            @click="isDetailOpen = false"
          >
            Cerrar
          </VBtn>
        </VCardActions>

      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end)) 100%
  );
}

.header-indicator {
  inline-size: 4px;
  block-size: 14px;
  border-radius: 10px;
}
.header-indicator.primary  { background-color: rgb(var(--v-theme-primary)); }
.header-indicator.secondary { background-color: rgb(var(--v-theme-secondary)); }
.header-indicator.info      { background-color: rgb(var(--v-theme-info)); }

/* Etiqueta de sección dentro del modal */
.section-label {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), 0.5);
  border-left: 3px solid rgb(var(--v-theme-primary));
  padding-left: 8px;
}

/* Grid 2 columnas para datos del cliente */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px 12px;
  background: rgba(var(--v-theme-on-surface), 0.02);
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 6px;
  padding: 10px;
}

.info-cell {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.info-key {
  font-size: 10px;
  color: rgba(var(--v-theme-on-surface), 0.45);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.info-val {
  font-size: 13px;
  color: rgba(var(--v-theme-on-surface), 0.87);
}

/* Tabla de ítems compacta */
.items-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}
.items-table th {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), 0.45);
  padding: 4px 6px;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.15);
}
.items-table td {
  padding: 5px 6px;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.07);
  vertical-align: middle;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}
.letter-spacing-1 { letter-spacing: 1px !important; }
.leading-none     { line-height: 1 !important; }
.leading-tight    { line-height: 1.25 !important; }
.uppercase        { text-transform: uppercase; }
.truncate         { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.orders-tabs    { border-bottom: 1px solid rgba(var(--v-border-color), 0.08); }
.orders-window  { overflow: visible; }

.tab-with-badge .tab-count {
  font-size: 0.7rem;
  justify-content: center;
  font-weight: 600;
  min-inline-size: 1.5rem;
  padding-inline: 6px;
}

.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>
