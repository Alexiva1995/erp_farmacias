<script setup>
import InvoiceFilters from "@/components/InvoiceFilters.vue";
import InvoiceTable from "@/components/InvoiceTable.vue";
import InvoiceDetailView from "@/pages/invoice/invoiceDetails.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, onUnmounted, ref, watch, computed } from "vue";
import { useRoute } from "vue-router";

const route = useRoute();
const currentView = ref("list");
const selectedInvoiceId = ref(null);

const invoices = ref([]);
const totalInvoices = ref(0);
const loading = ref(false);
const suppliers = ref([]);
const isLoadingFilters = ref(false);

const searchQuery = ref("");
const selectedSupplier = ref(null);
const startDate = ref(null);
const endDate = ref(null);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

// Propiedad computada para evaluar si hay algún filtro activo
const hasActiveFilters = computed(() => {
  return (
    searchQuery.value !== "" ||
    selectedSupplier.value !== null ||
    startDate.value !== null ||
    endDate.value !== null
  );
});

// Calcular el total acumulado en USD en base a las facturas actuales del listado
const statsTotalUSD = computed(() => {
  if (!invoices.value || invoices.value.length === 0) return 0;
  return invoices.value.reduce((acc, inv) => acc + (inv.total_usd || 0), 0);
});

const fetchSuppliers = async () => {
  isLoadingFilters.value = true;
  try {
    const response = await axios.get("/suppliers");
    suppliers.value = response.data.data ?? response.data;
  } catch (error) {
    console.error("Error al cargar proveedores:", error);
    toast.error("No se pudieron cargar los proveedores.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchOrderedInvoices = async () => {
  loading.value = true;
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    q: searchQuery.value,
    supplierId: selectedSupplier.value,
    startDate: startDate.value,
    endDate: endDate.value,
    status: "ordered",
  };

  Object.keys(params).forEach(
    (key) => (params[key] == null || params[key] === "") && delete params[key],
  );

  try {
    const response = await axios.get("/invoices", { params });
    invoices.value = response.data.data;
    totalInvoices.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener las facturas:", error);
    toast.error("Error al obtener las facturas finalizadas.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
    searchQuery,
    selectedSupplier,
    startDate,
    endDate,
  ],
  () => {
    if (currentView.value === "list") {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => fetchOrderedInvoices(), 300);
    }
  },
  { deep: true },
);

onMounted(() => {
  fetchSuppliers();
  fetchOrderedInvoices();

  // Si hay un invoiceId en la query, abrir esa factura directamente
  if (route.query.invoiceId) {
    const invoiceId = parseInt(route.query.invoiceId);
    if (invoiceId) {
      selectedInvoiceId.value = invoiceId;
      currentView.value = "detail";
    }
  }
});

// Limpieza de temporizadores al desmontar el componente para evitar fugas de memoria
onUnmounted(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer);
  }
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedSupplier.value = null;
  startDate.value = null;
  endDate.value = null;
  toast.success("Filtros limpiados correctamente.");
};

const handleViewDetails = (invoice) => {
  selectedInvoiceId.value = invoice.id;
  currentView.value = "detail";
};

const handleReturnToList = () => {
  selectedInvoiceId.value = null;
  currentView.value = "list";
  fetchOrderedInvoices();
};
</script>

<template>
  <div class="container-fluid py-4 max-w-7xl mx-auto">
    <div v-if="currentView === 'list'">
      <!-- Encabezado Premium -->
      <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-6">
        <div>
          <h1 class="text-h4 font-weight-bold text-high-emphasis">Facturas Ordenadas</h1>
          <p class="text-subtitle-2 text-medium-emphasis mt-1">
            Historial de facturas procesadas y listas para su almacenamiento e indexación en inventario.
          </p>
        </div>
      </div>

      <!-- Tarjetas de Estadísticas Rápidas con Skeletons -->
      <VRow class="mb-6">
        <VCol cols="12" sm="6" md="4">
          <VCard class="rounded-lg border shadow-sm" variant="flat">
            <VSkeletonLoader v-if="loading && totalInvoices === 0" type="list-item-two-line" />
            <VCardText v-else class="d-flex align-center justify-space-between p-4">
              <div>
                <span class="text-sm text-medium-emphasis">Total Facturas Ordenadas</span>
                <h3 class="text-h4 font-weight-bold mt-1 text-primary">{{ totalInvoices }}</h3>
              </div>
              <VAvatar color="primary" variant="tonal" size="48" class="rounded-lg">
                <VIcon icon="tabler-clipboard-check" size="24" />
              </VAvatar>
            </VCardText>
          </VCard>
        </VCol>
        
        <VCol cols="12" sm="6" md="4">
          <VCard class="rounded-lg border shadow-sm" variant="flat">
            <VSkeletonLoader v-if="loading && invoices.length === 0" type="list-item-two-line" />
            <VCardText v-else class="d-flex align-center justify-space-between p-4">
              <div>
                <span class="text-sm text-medium-emphasis">Monto Acumulado (USD)</span>
                <h3 class="text-h4 font-weight-bold mt-1 text-success">
                  ${{ statsTotalUSD.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                </h3>
              </div>
              <VAvatar color="success" variant="tonal" size="48" class="rounded-lg">
                <VIcon icon="tabler-currency-dollar" size="24" />
              </VAvatar>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Sección de Filtros -->
      <VCard class="mb-6 rounded-lg border shadow-sm" variant="flat">
        <VSkeletonLoader v-if="isLoadingFilters" type="card-heading, actions" />
        <InvoiceFilters
          v-else
          v-model:searchQuery="searchQuery"
          v-model:selectedSupplier="selectedSupplier"
          v-model:startDate="startDate"
          v-model:endDate="endDate"
          :suppliers="suppliers"
          :loading="isLoadingFilters"
          @clear="handleClearFilters"
        />
      </VCard>

      <!-- Tabla de Facturas o Estado Vacío -->
      <div v-if="invoices.length === 0 && !loading" class="text-center py-12 border rounded-lg bg-surface shadow-sm mb-6">
        <VAvatar size="72" color="secondary" variant="tonal" class="mb-4">
          <VIcon icon="tabler-folder-off" size="40" class="text-secondary" />
        </VAvatar>
        <h3 class="text-h6 font-weight-bold text-high-emphasis">No se encontraron facturas</h3>
        <p class="text-subtitle-2 text-medium-emphasis max-w-sm mx-auto mt-2">
          No hay facturas registradas en estado "Ordenado" que coincidan con los criterios de búsqueda actuales.
        </p>
        <VBtn
          v-if="hasActiveFilters"
          color="primary"
          variant="tonal"
          class="mt-4"
          @click="handleClearFilters"
        >
          Limpiar Filtros
        </VBtn>
      </div>

      <InvoiceTable
        v-else
        :invoices="invoices"
        :loading="loading"
        :total-invoices="totalInvoices"
        :items-per-page="itemsPerPage"
        :page="page"
        actionsMode="ordered"
        @update:options="updateTableOptions"
        @view-details="handleViewDetails"
      />
    </div>

    <div v-else-if="currentView === 'detail'">
      <InvoiceDetailView
        :invoice-id="selectedInvoiceId"
        mode="read-only"
        @back-to-list="handleReturnToList"
      />
    </div>
  </div>
</template>

<style scoped>
.max-w-7xl {
  max-width: 80rem;
}
.max-w-sm {
  max-width: 24rem;
}
</style>
