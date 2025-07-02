<script setup>
import DonationLetterDialog from "@/components/DonationLetterDialog.vue";
import ExpiredDetailView from "@/components/ExpiredDetailView.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { generateDonationPDF } from "@/utils/donationPdfGenerator"; // Asegúrate que esta ruta es correcta
import { computed, onMounted, ref, watch } from "vue";

// --- ESTADO ---
const isDetailViewVisible = ref(false);
const loading = ref(false);
const selectedMonth = ref(null);
const monthlySummaries = ref([]);

// Estado para la vista de detalle
const expiredLogs = ref([]);
const totalExpiredLogs = ref(0);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref("created_at");
const orderBy = ref("desc");
const selectedLogsInDetail = ref([]); // <-- Productos seleccionados en la tabla de detalle

// Estado para el modal de donación
const isDonationModalVisible = ref(false);
const productsForDonation = ref([]);

// --- CONFIGURACIÓN DE TABLAS ---
const headersSummaries = [
  { title: "Mes", key: "month", sortable: true },
  {
    title: "Productos Caducados",
    key: "total_products",
    align: "center",
    sortable: false,
  },
  { title: "Costo Total", key: "total_cost", align: "end", sortable: false },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const viewTitle = computed(() => {
  if (isDetailViewVisible.value && selectedMonth.value) {
    return `Detalle de Caducados - ${formatMonth(selectedMonth.value)}`;
  }
  return "Gestión de Caducados por Mes";
});

// --- LLAMADAS A LA API ---
const fetchSummaries = async () => {
  loading.value = true;
  try {
    const response = await axios.get("/expired-logs/summary");
    monthlySummaries.value = response.data;
  } catch (error) {
    console.error(error);
    toast.error("Error al cargar resúmenes.");
  } finally {
    loading.value = false;
  }
};

const fetchExpiredLogs = async () => {
  loading.value = true;
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    month: selectedMonth.value,
  };
  try {
    const response = await axios.get("/expired-logs", { params });
    expiredLogs.value = response.data.data;
    totalExpiredLogs.value = response.data.total;
  } catch (error) {
    console.error(error);
    toast.error("Error al cargar el listado.");
  } finally {
    loading.value = false;
  }
};

// --- MANEJADORES DE EVENTOS ---
const handleOpenDonationFromSelection = () => {
  // `selectedLogsInDetail.value` contiene los IDs: [3, 2, 1]
  // `expiredLogs.value` contiene la lista completa de objetos de productos mostrados en la tabla

  if (selectedLogsInDetail.value.length === 0) {
    toast.info("Por favor, selecciona al menos un producto para donar.");
    return;
  }

  // --- CAMBIO CLAVE AQUÍ ---
  // Filtramos la lista completa de logs (`expiredLogs`) para quedarnos solo
  // con aquellos cuyo ID esté incluido en nuestra lista de selección (`selectedLogsInDetail`).
  const selectedProductsObjects = expiredLogs.value.filter((log) =>
    selectedLogsInDetail.value.includes(log.id)
  );

  // Depuración opcional: verifica que ahora tienes los objetos correctos
  console.log("Objetos de productos para donar:", selectedProductsObjects);

  // Pasamos el array de objetos completos al modal
  productsForDonation.value = selectedProductsObjects;
  isDonationModalVisible.value = true;
};

const handleGenerateDonation = async (donationData) => {
  try {
    await axios.post("/donations", {
      institution_name: donationData.institution,
      expired_log_ids: donationData.products.map((p) => p.id),
    });
    toast.success("Carta de donación registrada con éxito.");
    isDonationModalVisible.value = false;
    await fetchSummaries();

    // Si aún estamos en la vista de detalle, la refrescamos y limpiamos la selección
    if (isDetailViewVisible.value) {
      selectedLogsInDetail.value = [];
      await fetchExpiredLogs();
    }
  } catch (error) {
    console.error("Error en el proceso de donación:", error);
    const errorMessage =
      error.response?.data?.message || "No se pudo completar la donación.";
    toast.error(errorMessage);
  }
};

const handlePrintDonation = async (month) => {
  toast.info("Preparando datos para la impresión...");
  try {
    const response = await axios.get(`/donations/month/${month}/data`);
    const donationDataForPdf = {
      institution: response.data.institution_name,
      products: response.data.products.map((p) => ({
        product_name: p.product_name,
        expired_quantity: p.expired_quantity,
        cost_per_unit: p.cost_per_unit,
      })),
    };
    generateDonationPDF(donationDataForPdf);
  } catch (error) {
    console.error("Error al obtener datos para la donación:", error);
    const errorMessage =
      error.response?.data?.message ||
      "No se pudieron obtener los datos para el PDF.";
    toast.error(errorMessage);
  }
};

const showDetailView = (month) => {
  selectedMonth.value = month;
  isDetailViewVisible.value = true;
  fetchExpiredLogs();
};

const showSummaryView = () => {
  isDetailViewVisible.value = false;
  selectedMonth.value = null;
  selectedLogsInDetail.value = []; // Limpiar selección al volver
};

const updateDetailTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key || "created_at";
  orderBy.value = options.sortBy[0]?.order || "desc";
};

// --- WATCHERS Y LIFECYCLE ---
watch(
  [page, itemsPerPage, sortBy, orderBy],
  () => {
    if (isDetailViewVisible.value) {
      fetchExpiredLogs();
    }
  },
  { deep: true }
);

onMounted(fetchSummaries);

// --- FORMATEADORES ---
const formatMonth = (monthStr) => {
  if (!monthStr) return "";
  const [year, month] = monthStr.split("-");
  const date = new Date(year, month - 1);
  return date.toLocaleString("es-CO", { month: "long", year: "numeric" });
};

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    minimumFractionDigits: 0,
  }).format(value);
};
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <h4 class="text-h4 text-capitalize">
        {{ viewTitle }}
      </h4>
      <!-- El único botón en la cabecera es para volver -->
      <VBtn
        v-if="isDetailViewVisible"
        variant="outlined"
        prepend-icon="tabler-arrow-left"
        @click="showSummaryView"
      >
        Volver a Resúmenes
      </VBtn>
    </div>

    <!-- VISTA DE RESÚMENES -->
    <div v-if="!isDetailViewVisible">
      <VCard v-if="monthlySummaries.length > 0">
        <VDataTable
          :headers="headersSummaries"
          :items="monthlySummaries"
          :loading="loading"
          item-value="month"
          class="text-no-wrap"
        >
          <template #item.month="{ item }">
            <span class="text-capitalize font-weight-medium">{{
              formatMonth(item.month)
            }}</span>
          </template>
          <template #item.total_cost="{ item }">
            <span class="font-weight-medium">{{
              formatCurrency(item.total_cost)
            }}</span>
          </template>
          <template #item.total_products="{ item }">
            <span class="font-weight-medium">{{ item.total_products }}</span>
          </template>
          <template #item.actions="{ item }">
            <VTooltip text="Ver Productos del Mes">
              <template #activator="{ props: tooltipProps }">
                <IconBtn
                  v-bind="tooltipProps"
                  @click="showDetailView(item.month)"
                >
                  <VIcon icon="tabler-eye" />
                </IconBtn>
              </template>
            </VTooltip>
            <VTooltip text="Imprimir Carta de Donación">
              <template #activator="{ props: tooltipProps }">
                <div v-bind="tooltipProps" class="d-inline-block">
                  <IconBtn
                    :disabled="item.donation_count === 0"
                    @click="handlePrintDonation(item.month)"
                  >
                    <VIcon icon="tabler-printer" />
                  </IconBtn>
                </div>
              </template>
            </VTooltip>
          </template>
        </VDataTable>
      </VCard>
      <VAlert v-else-if="!loading" type="info" variant="tonal">
        No se encontraron registros de lotes caducados.
      </VAlert>
      <VProgressLinear v-if="loading" indeterminate color="primary" />
    </div>

    <!-- VISTA DE DETALLE -->
    <ExpiredDetailView
      v-else
      v-model:selected-logs="selectedLogsInDetail"
      :logs="expiredLogs"
      :total-logs="totalExpiredLogs"
      :loading="loading"
      :page="page"
      :items-per-page="itemsPerPage"
      @update:options="updateDetailTableOptions"
      @generate-donation="handleOpenDonationFromSelection"
    />

    <!-- DIÁLOGO DE DONACIÓN -->
    <DonationLetterDialog
      v-model="isDonationModalVisible"
      :initial-products="productsForDonation"
      @generate="handleGenerateDonation"
    />
  </div>
</template>
