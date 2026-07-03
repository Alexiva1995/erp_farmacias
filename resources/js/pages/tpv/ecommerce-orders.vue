<script setup>
import { computed, onMounted, ref, watch } from "vue";
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
        <VCard variant="flat" border class="rounded-xl overflow-hidden shadow-sm bg-surface">
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
                {{ formatCurrency(item.total_amount, 'COP') }}
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

            <!-- Caso Shipped: Completar entrega -->
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

.orders-tabs {
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08);
}

.tab-with-badge .tab-count {
  font-size: 0.7rem;
  justify-content: center;
  font-weight: 600;
  min-inline-size: 1.5rem;
  padding-inline: 6px;
}

.orders-window {
  overflow: visible;
}



.uppercase {
  text-transform: uppercase;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>
