<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import ReturnsFilter from "@/components/ReturnsFilter.vue";
import LotDistributionModal from "@/components/dialogs/LotDistributionModal.vue";
import UnifiedReturnDialog from "@/components/dialogs/UnifiedReturnDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { formatCurrency } from "@/utils/currencyFormatter";

const route = useRoute();
const authStore = useAuthStore();
const brandingStore = useBrandingStore();

const activeTab = ref("new");

// Configuración del negocio desde brandingStore
const enableLots = computed(() => brandingStore.settings?.enable_lots ?? true);
const requireApproval = computed(() => brandingStore.settings?.require_return_approval ?? true);
const returnWindowDays = computed(() => brandingStore.settings?.return_window_days ?? 2);
const canApprove = computed(() => {
  const roleId = Number(authStore.user?.role_id ?? 0);
  return roleId === 1 || roleId === 2 || authStore.isAdmin;
});

// --- Pestaña 1: Nueva Devolución ---
const searchOrderQuery = ref("");
const orders = ref([]);
const totalOrders = ref(0);
const searchingOrders = ref(false);
const orderOptions = ref({ page: 1, itemsPerPage: 10, sortBy: [] });

const showReturnDialog = ref(false);
const selectedOrder = ref(null);
const submittingReturn = ref(false);

const fetchOrders = async (directOrderId = null) => {
  const query = String(searchOrderQuery.value || "").trim();
  if (!query && !directOrderId) {
    orders.value = [];
    totalOrders.value = 0;
    return;
  }

  searchingOrders.value = true;
  try {
    const response = await axios.post("/tpv/returns/search-orders", {
      identification: query || undefined,
      order_id: directOrderId || undefined,
      page: orderOptions.value.page,
      itemsPerPage: orderOptions.value.itemsPerPage,
      sortBy: orderOptions.value.sortBy[0]?.key,
      orderBy: orderOptions.value.sortBy[0]?.order,
    });

    orders.value = response.data.data || [];
    totalOrders.value = response.data.total || 0;

    if (directOrderId && orders.value.length > 0) {
      openReturnModal(orders.value[0]);
    }
  } catch (error) {
    toast.error(error.response?.data?.error || "Error al buscar órdenes.");
  } finally {
    searchingOrders.value = false;
  }
};

const clearOrderSearch = () => {
  searchOrderQuery.value = "";
  orders.value = [];
  totalOrders.value = 0;
};

const openReturnModal = (order) => {
  selectedOrder.value = order;
  showReturnDialog.value = true;
};

const handleReturnSubmit = async (payload) => {
  submittingReturn.value = true;
  try {
    const response = await axios.post("/tpv/returns/product", payload);
    toast.success(response.data?.message || "Devolución procesada exitosamente.");
    showReturnDialog.value = false;
    selectedOrder.value = null;

    if (searchOrderQuery.value) {
      await fetchOrders();
    }
    await fetchReturns();
    if (canApprove.value) {
      await fetchPendingReturns();
    }
  } catch (error) {
    toast.error(
      error.response?.data?.message ||
        error.response?.data?.error ||
        "No se pudo procesar la devolución."
    );
  } finally {
    submittingReturn.value = false;
  }
};

// --- Pestaña 2: Pendientes de Aprobación ---
const pendingReturns = ref([]);
const totalPending = ref(0);
const loadingPending = ref(false);
const approvingId = ref(null);
const locations = ref([]);
const showLotModal = ref(false);
const returnForLotDistribution = ref(null);
const lotsForDistribution = ref([]);

const fetchPendingReturns = async () => {
  loadingPending.value = true;
  try {
    const response = await axios.get("/tpv/returns", {
      params: { status: "pending", itemsPerPage: 25 },
    });
    pendingReturns.value = response.data.data || [];
    totalPending.value = response.data.total || 0;
  } catch {
    toast.error("Error al cargar devoluciones pendientes.");
  } finally {
    loadingPending.value = false;
  }
};

const fetchLocations = async () => {
  try {
    const { data } = await axios.get("/locations");
    locations.value = data.data || data || [];
  } catch {
    locations.value = [];
  }
};

const handleApprovePending = async (item) => {
  const needsLotModal = enableLots.value && item.restock_inventory !== false;
  if (needsLotModal) {
    try {
      const { data: lotsData } = await axios.get(`/tpv/returns/product/${item.product_id}/lots`);
      const lots = lotsData.lots ?? [];
      if (lots.length > 0) {
        returnForLotDistribution.value = item;
        lotsForDistribution.value = lots;
        showLotModal.value = true;
        return;
      }
    } catch {
      // Si falla carga de lotes, aprobar directo
    }
  }

  await approveDirect(item.id);
};

const approveDirect = async (returnId) => {
  approvingId.value = returnId;
  try {
    await axios.post(`/tpv/returns/${returnId}/approve`, {});
    toast.success("Devolución aprobada correctamente.");
    await fetchPendingReturns();
    await fetchReturns();
  } catch (error) {
    toast.error(error.response?.data?.message || "No se pudo aprobar la devolución.");
  } finally {
    approvingId.value = null;
  }
};

const handleLotsDistributed = async (payload) => {
  const returnItem = returnForLotDistribution.value;
  if (!returnItem?.id) return;

  try {
    await axios.post(`/tpv/returns/${returnItem.id}/approve`, {
      updated_lots: payload.updatedLots ?? [],
      new_lots: payload.newLots ?? [],
    });
    toast.success("Devolución aprobada y distribuida en lotes.");
    showLotModal.value = false;
    returnForLotDistribution.value = null;
    lotsForDistribution.value = [];
    await fetchPendingReturns();
    await fetchReturns();
  } catch (error) {
    toast.error(error.response?.data?.message || "Error al aprobar con lotes.");
  }
};

const handleRejectPending = async (item) => {
  approvingId.value = item.id;
  try {
    await axios.patch(`/tpv/returns/${item.id}/Rejected`);
    toast.success("Devolución rechazada.");
    await fetchPendingReturns();
    await fetchReturns();
  } catch (error) {
    toast.error(error.response?.data?.message || "No se pudo rechazar la devolución.");
  } finally {
    approvingId.value = null;
  }
};

// --- Pestaña 3: Historial ---
const returns = ref([]);
const totalReturns = ref(0);
const loadingHistory = ref(false);
const retryingFiscalId = ref(null);

const search = ref("");
const status = ref("");
const supplier = ref(null);
const seller = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const sellers = ref([]);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const historyHeaders = [
  { title: "ID", key: "id", sortable: true, width: "80px" },
  { title: "Orden / Factura", key: "order_id", sortable: true },
  { title: "Cliente", key: "client", sortable: false },
  { title: "Producto", key: "product", sortable: false },
  { title: "Reembolso", key: "amount_refunded", sortable: true },
  { title: "Inventario", key: "restock_inventory", sortable: false },
  { title: "Estado", key: "status", sortable: true },
  { title: "Fiscal (NC)", key: "fiscal_status", sortable: false },
];

const refundMethodLabels = {
  balance: "Saldo a favor",
  cash: "Efectivo",
  mobile_payment: "Pago Móvil",
  transfer: "Transferencia",
};

const fiscalBadgeMap = {
  not_applicable: { color: "secondary", text: "No aplica" },
  pending: { color: "warning", text: "NC Pendiente" },
  emitted: { color: "success", text: "NC Emitida" },
  failed: { color: "error", text: "NC Fallida" },
};

const statusBadgeMap = {
  Approved: { color: "success", text: "Aprobada" },
  Rejected: { color: "error", text: "Rechazada" },
};

const fetchSellers = async () => {
  try {
    const { data } = await axios.get("/users");
    sellers.value = data.data || [];
  } catch {
    sellers.value = [];
  }
};

const fetchReturns = async () => {
  loadingHistory.value = true;
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    search: search.value,
    status: status.value,
    seller: seller.value,
    startDate: startDate.value,
    endDate: endDate.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/tpv/returns", { params });
    returns.value = response.data.data || [];
    totalReturns.value = response.data.total || 0;
  } catch {
    toast.error("Error al obtener el historial de devoluciones.");
  } finally {
    loadingHistory.value = false;
  }
};

const handleRetryCreditNote = async (item) => {
  retryingFiscalId.value = item.id;
  try {
    await axios.post(`/tpv/returns/${item.id}/retry-credit-note`);
    toast.success("Nota de Crédito reencolada para impresión fiscal.");
    await fetchReturns();
  } catch (error) {
    toast.error(error.response?.data?.message || "No se pudo reencolar la Nota de Crédito.");
  } finally {
    retryingFiscalId.value = null;
  }
};

const updateHistoryOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const clearFilters = () => {
  search.value = "";
  status.value = "";
  seller.value = null;
  supplier.value = null;
  startDate.value = null;
  endDate.value = null;
  page.value = 1;
};

let debounceTimer = null;
watch(
  [page, itemsPerPage, sortBy, orderBy, search, status, seller, startDate, endDate],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchReturns(), 300);
  },
  { deep: true }
);

onMounted(async () => {
  fetchSellers();
  fetchReturns();
  if (canApprove.value) {
    fetchPendingReturns();
    if (enableLots.value) {
      fetchLocations();
    }
  }

  const queryOrderId = Number(route.query?.order_id || 0);
  if (queryOrderId > 0) {
    searchOrderQuery.value = String(queryOrderId);
    await fetchOrders(queryOrderId);
  }
});
</script>

<template>
  <div>
    <VCard class="mb-4 rounded-lg" border variant="flat">
      <VCardText class="d-flex align-center justify-space-between flex-wrap gap-3 py-3">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" size="42">
            <VIcon icon="tabler-arrow-back-up" size="22" />
          </VAvatar>
          <div>
            <h4 class="text-h6 font-weight-bold mb-0">Gestión de Devoluciones</h4>
            <span class="text-caption text-medium-emphasis">
              Ventana permitida: últimos {{ returnWindowDays }} días ·
              {{ requireApproval ? 'Con aprobación de supervisor' : 'Aprobación directa (PYME)' }}
            </span>
          </div>
        </div>

        <VTabs v-model="activeTab" color="primary" density="comfortable">
          <VTab value="new" prepend-icon="tabler-plus">Nueva Devolución</VTab>
          <VTab
            v-if="canApprove"
            value="pending"
            prepend-icon="tabler-clock"
          >
            Pendientes
            <VBadge
              v-if="totalPending > 0"
              :content="totalPending"
              color="warning"
              inline
              class="ms-2"
            />
          </VTab>
          <VTab value="history" prepend-icon="tabler-history">Historial</VTab>
        </VTabs>
      </VCardText>
    </VCard>

    <VWindow v-model="activeTab">
      <!-- Pestaña 1: Nueva Devolución -->
      <VWindowItem value="new">
        <VCard class="mb-4 rounded-lg" border variant="flat">
          <VCardText>
            <VRow align="center">
              <VCol cols="12" md="8">
                <VTextField
                  v-model="searchOrderQuery"
                  label="Buscar venta para devolver"
                  placeholder="N° de orden, N° de factura fiscal, cédula/RIF o nombre del cliente..."
                  prepend-inner-icon="tabler-search"
                  variant="outlined"
                  density="comfortable"
                  clearable
                  hide-details
                  @keyup.enter="fetchOrders()"
                  @click:clear="clearOrderSearch"
                />
              </VCol>
              <VCol cols="12" md="4" class="d-flex gap-2">
                <VBtn
                  color="primary"
                  variant="flat"
                  prepend-icon="tabler-search"
                  :loading="searchingOrders"
                  @click="fetchOrders()"
                >
                  Buscar Venta
                </VBtn>
                <VBtn
                  variant="outlined"
                  color="secondary"
                  @click="clearOrderSearch"
                >
                  Limpiar
                </VBtn>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <VAlert
          v-if="searchOrderQuery && !searchingOrders && totalOrders === 0"
          type="info"
          variant="tonal"
          class="mb-4"
        >
          No se encontraron órdenes completadas en los últimos {{ returnWindowDays }} días para "{{ searchOrderQuery }}".
        </VAlert>

        <VCard class="rounded-lg" border variant="flat">
          <VDataTable
            :headers="[
              { title: 'N° Orden', key: 'id' },
              { title: 'Factura Fiscal', key: 'fiscal' },
              { title: 'Cliente', key: 'client' },
              { title: 'Total', key: 'total_amount', align: 'end' },
              { title: 'Fecha', key: 'order_date' },
              { title: 'Acción', key: 'actions', align: 'end', sortable: false },
            ]"
            :items="orders"
            :loading="searchingOrders"
            no-data-text="Busque una orden por número, factura fiscal o cliente para iniciar una devolución."
          >
            <template #item.id="{ item }">
              <span class="font-weight-bold text-primary">#{{ item.id }}</span>
            </template>

            <template #item.fiscal="{ item }">
              <VChip
                v-if="item.fiscal_history?.invoice_number"
                color="info"
                size="small"
                variant="tonal"
              >
                Factura #{{ item.fiscal_history.invoice_number }}
              </VChip>
              <span v-else class="text-caption text-medium-emphasis">Sin factura fiscal</span>
            </template>

            <template #item.client="{ item }">
              <div class="font-weight-medium">
                {{ item.client?.name }} {{ item.client?.last_name }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.client?.identification_type }}{{ item.client?.identification }}
              </div>
            </template>

            <template #item.total_amount="{ item }">
              <span class="font-weight-bold">
                {{ formatCurrency(item.total_amount, item.currency) }}
              </span>
            </template>

            <template #item.order_date="{ item }">
              <span>{{ String(item.order_date || item.created_at || '').slice(0, 10) }}</span>
            </template>

            <template #item.actions="{ item }">
              <VBtn
                color="primary"
                variant="tonal"
                size="small"
                prepend-icon="tabler-arrow-back-up"
                @click="openReturnModal(item)"
              >
                Devolver
              </VBtn>
            </template>
          </VDataTable>
        </VCard>
      </VWindowItem>

      <!-- Pestaña 2: Pendientes de Aprobación -->
      <VWindowItem v-if="canApprove" value="pending">
        <VCard class="rounded-lg" border variant="flat">
          <VDataTable
            :headers="[
              { title: 'ID', key: 'id' },
              { title: 'Orden', key: 'order_id' },
              { title: 'Producto', key: 'product' },
              { title: 'Cant.', key: 'quantity', align: 'center' },
              { title: 'Reembolso', key: 'amount_refunded' },
              { title: 'Destino', key: 'restock_inventory' },
              { title: 'Acciones', key: 'actions', align: 'end', sortable: false },
            ]"
            :items="pendingReturns"
            :loading="loadingPending"
            no-data-text="No hay devoluciones pendientes de aprobación."
          >
            <template #item.id="{ item }">
              <span class="font-weight-bold">#{{ item.id }}</span>
            </template>

            <template #item.order_id="{ item }">
              <div>
                <span class="font-weight-bold text-primary">#{{ item.order_id }}</span>
              </div>
              <span v-if="item.order?.fiscal_history?.invoice_number" class="text-caption text-info">
                Fac. #{{ item.order.fiscal_history.invoice_number }}
              </span>
            </template>

            <template #item.product="{ item }">
              <div class="font-weight-medium">{{ item.product?.name || '—' }}</div>
              <div v-if="item.reason" class="text-caption text-medium-emphasis">
                Motivo: {{ item.reason }}
              </div>
            </template>

            <template #item.amount_refunded="{ item }">
              <div class="font-weight-bold text-success">
                ${{ Number(item.amount_refunded).toFixed(2) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ refundMethodLabels[item.refund_method] || item.refund_method }}
              </div>
            </template>

            <template #item.restock_inventory="{ item }">
              <VChip
                size="small"
                :color="item.restock_inventory !== false ? 'success' : 'warning'"
                variant="tonal"
              >
                {{ item.restock_inventory !== false ? 'Reingresa a stock' : 'Merma / Dañado' }}
              </VChip>
            </template>

            <template #item.actions="{ item }">
              <div class="d-flex justify-end gap-2">
                <VBtn
                  color="success"
                  size="small"
                  variant="tonal"
                  prepend-icon="tabler-check"
                  :loading="approvingId === item.id"
                  @click="handleApprovePending(item)"
                >
                  Aprobar
                </VBtn>
                <VBtn
                  color="error"
                  size="small"
                  variant="outlined"
                  prepend-icon="tabler-x"
                  :loading="approvingId === item.id"
                  @click="handleRejectPending(item)"
                >
                  Rechazar
                </VBtn>
              </div>
            </template>
          </VDataTable>
        </VCard>
      </VWindowItem>

      <!-- Pestaña 3: Historial -->
      <VWindowItem value="history">
        <ReturnsFilter
          :search="search"
          :supplier="supplier"
          :status="status"
          :start-date="startDate"
          :end-date="endDate"
          :sellers="sellers"
          :seller="seller"
          @update:search="search = $event"
          @update:status="status = $event"
          @update:supplier="supplier = $event"
          @update:start-date="startDate = $event"
          @update:end-date="endDate = $event"
          @update:seller="seller = $event"
          @clear="clearFilters"
        />

        <VCard class="rounded-lg mt-4" border variant="flat">
          <VDataTableServer
            :items-per-page="itemsPerPage"
            :page="page"
            :headers="historyHeaders"
            :items="returns"
            :items-length="totalReturns"
            :loading="loadingHistory"
            item-key="id"
            no-data-text="No hay devoluciones registradas con los filtros seleccionados."
            @update:options="updateHistoryOptions"
          >
            <template #item.id="{ item }">
              <span class="font-weight-bold text-primary">#{{ item.id }}</span>
            </template>

            <template #item.order_id="{ item }">
              <div class="font-weight-bold">#{{ item.order_id }}</div>
              <div v-if="item.order?.fiscal_history?.invoice_number" class="text-caption text-info">
                Fac. #{{ item.order.fiscal_history.invoice_number }}
              </div>
            </template>

            <template #item.client="{ item }">
              <div class="font-weight-medium">
                {{ item.order?.client?.name }} {{ item.order?.client?.last_name }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.order?.client?.identification_type }}{{ item.order?.client?.identification }}
              </div>
            </template>

            <template #item.product="{ item }">
              <div class="font-weight-medium">
                {{ item.product?.name || '—' }} (x{{ item.quantity }})
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ String(item.return_date || '').slice(0, 10) }}
                <span v-if="item.reason">· {{ item.reason }}</span>
              </div>
            </template>

            <template #item.amount_refunded="{ item }">
              <div class="font-weight-bold text-success">
                ${{ Number(item.amount_refunded).toFixed(2) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ refundMethodLabels[item.refund_method] || item.refund_method }}
              </div>
            </template>

            <template #item.restock_inventory="{ item }">
              <VChip
                size="small"
                :color="item.restock_inventory !== false ? 'success' : 'warning'"
                variant="tonal"
              >
                {{ item.restock_inventory !== false ? 'Stock' : 'Merma' }}
              </VChip>
            </template>

            <template #item.status="{ item }">
              <VChip
                size="small"
                :color="statusBadgeMap[item.status]?.color || 'warning'"
                variant="tonal"
              >
                {{ statusBadgeMap[item.status]?.text || 'Pendiente' }}
              </VChip>
            </template>

            <template #item.fiscal_status="{ item }">
              <div class="d-flex align-center gap-2">
                <VChip
                  size="small"
                  :color="fiscalBadgeMap[item.fiscal_status]?.color || 'secondary'"
                  variant="tonal"
                >
                  {{
                    item.credit_note_number
                      ? `NC #${item.credit_note_number}`
                      : fiscalBadgeMap[item.fiscal_status]?.text || 'No aplica'
                  }}
                </VChip>
                <VBtn
                  v-if="item.fiscal_status === 'failed' && item.status === 'Approved'"
                  icon="tabler-refresh"
                  size="x-small"
                  color="error"
                  variant="tonal"
                  title="Reintentar Nota de Crédito"
                  :loading="retryingFiscalId === item.id"
                  @click="handleRetryCreditNote(item)"
                />
              </div>
            </template>
          </VDataTableServer>
        </VCard>
      </VWindowItem>
    </VWindow>

    <UnifiedReturnDialog
      v-model="showReturnDialog"
      :order="selectedOrder"
      :loading="submittingReturn"
      :require-approval="requireApproval"
      :can-direct-approve="canApprove"
      @submit="handleReturnSubmit"
    />

    <LotDistributionModal
      v-model="showLotModal"
      mode="return"
      :product-name="returnForLotDistribution?.product?.name ?? 'Producto'"
      :product-id="returnForLotDistribution?.product?.id ?? ''"
      :laboratory="returnForLotDistribution?.product?.laboratory?.name ?? ''"
      :lots="lotsForDistribution"
      :target-quantity="returnForLotDistribution?.quantity ?? 0"
      :locations="locations"
      @save="handleLotsDistributed"
    />
  </div>
</template>
