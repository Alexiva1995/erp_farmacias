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
    toast.error("Error al obtener las facturas ordenadas.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;

// Watchers optimizados: reinician a la página 1 cuando los filtros cambian
watch([searchQuery, selectedSupplier, startDate, endDate], () => {
  page.value = 1;
  if (currentView.value === "list") {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchOrderedInvoices(), 300);
  }
});

// Paginación y ordenamiento ejecutan consulta inmediatamente sin debounce innecesario
watch([page, itemsPerPage, sortBy, orderBy], () => {
  if (currentView.value === "list") {
    fetchOrderedInvoices();
  }
});

onMounted(() => {
  fetchSuppliers();
  fetchOrderedInvoices();

  if (route.query.invoiceId) {
    const invoiceId = parseInt(route.query.invoiceId);
    if (invoiceId) {
      selectedInvoiceId.value = invoiceId;
      currentView.value = "detail";
    }
  }
});

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
  <div>
    <div v-if="currentView === 'list'">


      <!-- Sección de Filtros -->
      <VSkeletonLoader v-if="isLoadingFilters" type="card-heading, actions" class="mb-6 rounded-lg" />
      <InvoiceFilters
        v-else
        v-model:searchQuery="searchQuery"
        v-model:selectedSupplier="selectedSupplier"
        v-model:startDate="startDate"
        v-model:endDate="endDate"
        :suppliers="suppliers"
        :loading="isLoadingFilters"
        :show-add="false"
        :show-bulk-delete="false"
        @clear="handleClearFilters"
        class="mb-6"
      />

      <!-- Tabla de Facturas o Estado Vacío -->
      <VCard
        v-if="invoices.length === 0 && !loading"
        class="text-center py-12 px-4 rounded-lg mb-6"
        variant="outlined"
      >
        <VAvatar size="72" color="secondary" variant="tonal" class="mb-4">
          <VIcon icon="tabler-folder-off" size="40" class="text-secondary" />
        </VAvatar>
        <h3 class="text-h6 font-weight-bold text-high-emphasis">No se encontraron facturas</h3>
        <p class="text-subtitle-2 text-medium-emphasis mt-2">
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
      </VCard>

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
