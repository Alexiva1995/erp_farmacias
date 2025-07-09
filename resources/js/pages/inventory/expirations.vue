<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { generateDonationPDF } from "@/utils/donationPdfGenerator";
import Swal from "sweetalert2";
import { computed, onMounted, ref, watch } from "vue";

import DonationLetterDialog from "@/components/DonationLetterDialog.vue";
import ExpirationsFilters from "@/components/ExpirationsFilters.vue";
import ExpirationsTable from "@/components/ExpirationsTable.vue";
import ExpiredDetailView from "@/components/ExpiredDetailView.vue";

const lots = ref([]);
const totalLots = ref(0);
const loadingLots = ref(false);
const selectedLots = ref([]);
const pageLots = ref(1);
const itemsPerPageLots = ref(10);
const sortByLots = ref("expiration_date");
const orderByLots = ref("asc");

const searchQueryLots = ref("");
const laboratories = ref([]);
const loadingLaboratories = ref(false);
const selectedLaboratoryLots = ref(null);
const startDateLots = ref(null);
const endDateLots = ref(null);

const fetchLaboratories = async () => {
  loadingLaboratories.value = true;
  try {
    const { data } = await axios.get("/laboratories", {
      params: { itemsPerPage: -1 },
    });
    laboratories.value = data;
  } catch (error) {
    console.error("Error al cargar laboratorios:", error);
  } finally {
    loadingLaboratories.value = false;
  }
};

const fetchLots = async () => {
  loadingLots.value = true;
  const params = {
    q: searchQueryLots.value,
    page: pageLots.value,
    itemsPerPage: itemsPerPageLots.value,
    sortBy: sortByLots.value,
    orderBy: orderByLots.value,
    laboratory_id: selectedLaboratoryLots.value,
    start_date: startDateLots.value,
    end_date: endDateLots.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const { data } = await axios.get("/products/expirations", { params });
    lots.value = data.data;
    totalLots.value = data.total;
  } catch (error) {
    console.error("Error al obtener lotes por vencer:", error);
    toast.error("Error al obtener la lista de lotes.");
  } finally {
    loadingLots.value = false;
  }
};

const handleExpireLot = async (lotToExpire) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: `Vas a marcar como caducado el lote Nº ${lotToExpire.lot_number} del producto "${lotToExpire.product.name}".`,
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Confirmar",
    reverseButtons: true,
  });
  if (result.isConfirmed) {
    try {
      await axios.put(`/lots/${lotToExpire.id}/expire`);
      toast.success("Lote marcado como caducado con éxito.");
      await fetchLots();
      await fetchSummaries();
    } catch (error) {
      console.error("Error al caducar el lote:", error);
      toast.error("No se pudo caducar el lote.");
    }
  }
};

const handleApplyDiscount = async (item) => {
  // Modificado para recibir solo el item ya que la tabla cambió
  try {
    // TODO: Implementar lógica de descuento cuando esté lista
    toast.info("Funcionalidad de descuento en desarrollo...");
  } catch (error) {
    console.error("Error al aplicar el descuento:", error);
    toast.error("No se pudo aplicar el descuento.");
  }
};

const handleApplyOfferSelected = async () => {
  const selectedCount = selectedLots.value.length;
  if (selectedCount === 0) {
    toast.info("Por favor, selecciona al menos un lote.");
    return;
  }

  const result = await Swal.fire({
    title: `¿Estás seguro de aplicar esta oferta?`,
    text: `Se aplicará la oferta a ${selectedCount} lotes seleccionados.`,
    icon: "question",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Sí, aplicar oferta",
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    toast.info("Funcionalidad de ofertas masivas en desarrollo...");
  }
};

const handleExpireSelected = async () => {
  const selectedCount = selectedLots.value.length;
  if (selectedCount === 0) {
    toast.info("Por favor, selecciona al menos un lote.");
    return;
  }
  const result = await Swal.fire({
    title: `¿Estás seguro de caducar ${selectedCount} lotes?`,
    text: "Esta acción marcará todos los lotes seleccionados como caducados.",
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Sí, caducar todos",
    reverseButtons: true,
  });
  if (result.isConfirmed) {
    try {
      const { data } = await axios.post("/lots/expire-multiple", {
        lot_ids: selectedLots.value,
      });
      toast.success(data.message || "Lotes caducados con éxito.");
      selectedLots.value = [];
      await fetchLots();
      await fetchSummaries();
    } catch (error) {
      console.error("Error al caducar lotes:", error);
      toast.error(error.response?.data?.message || "Ocurrió un error.");
    }
  }
};

const updateTableOptionsLots = (options) => {
  pageLots.value = options.page;
  itemsPerPageLots.value = options.itemsPerPage;
  sortByLots.value = options.sortBy[0]?.key || "expiration_date";
  orderByLots.value = options.sortBy[0]?.order || "asc";
};

const handleClearFiltersLots = () => {
  searchQueryLots.value = "";
  selectedLaboratoryLots.value = null;
  startDateLots.value = null;
  endDateLots.value = null;
};

const isDetailViewVisible = ref(false);
const loadingReports = ref(false);
const selectedMonth = ref(null);
const monthlySummaries = ref([]);

const expiredLogs = ref([]);
const totalExpiredLogs = ref(0);
const pageReports = ref(1);
const itemsPerPageReports = ref(10);
const sortByReports = ref("created_at");
const orderByReports = ref("desc");
const selectedLogsInDetail = ref([]);

const isDonationModalVisible = ref(false);
const productsForDonation = ref([]);

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

const fetchSummaries = async () => {
  loadingReports.value = true;
  try {
    const { data } = await axios.get("/expired-logs/summary");
    monthlySummaries.value = data;
  } catch (error) {
    console.error(error);
    toast.error("Error al cargar resúmenes.");
  } finally {
    loadingReports.value = false;
  }
};

const fetchExpiredLogs = async () => {
  loadingReports.value = true;
  const params = {
    page: pageReports.value,
    itemsPerPage: itemsPerPageReports.value,
    sortBy: sortByReports.value,
    orderBy: orderByReports.value,
    month: selectedMonth.value,
  };
  try {
    const { data } = await axios.get("/expired-logs", { params });
    expiredLogs.value = data.data;
    totalExpiredLogs.value = data.total;
  } catch (error) {
    console.error(error);
    toast.error("Error al cargar el listado de caducados.");
  } finally {
    loadingReports.value = false;
  }
};

const handleOpenDonationFromSelection = () => {
  if (selectedLogsInDetail.value.length === 0) {
    toast.info("Por favor, selecciona al menos un producto para donar.");
    return;
  }
  const selectedProductsObjects = expiredLogs.value.filter((log) =>
    selectedLogsInDetail.value.includes(log.id)
  );
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

    if (isDetailViewVisible.value) {
      selectedLogsInDetail.value = [];
      await fetchExpiredLogs();
    }
  } catch (error) {
    console.error("Error en el proceso de donación:", error);
    toast.error(
      error.response?.data?.message || "No se pudo completar la donación."
    );
  }
};

const handlePrintDonation = async (month) => {
  toast.info("Preparando datos para la impresión...");
  try {
    const { data: responseData } = await axios.get(
      `/donations/month/${month}/data`
    );

    console.log("Datos crudos recibidos de la API:", responseData);

    let donationsToProcess = [];

    if (Array.isArray(responseData)) {
      donationsToProcess = responseData;
    } else if (responseData && typeof responseData === "object") {
      donationsToProcess = [responseData];
    }

    if (donationsToProcess.length === 0) {
      toast.warning(
        "No se encontraron donaciones registradas para imprimir en este mes."
      );
      return;
    }

    donationsToProcess.forEach((donationData) => {
      if (donationData && donationData.institution_name) {
        generateDonationPDF({
          institution: donationData.institution_name,
          products: donationData.products || [],
          donationDate: donationData.donation_date,
          totalCost: donationData.total_cost,
        });
      }
    });
  } catch (error) {
    console.error("Error al obtener datos para la donación:", error);
    toast.error(
      error.response?.data?.message ||
        "No se pudieron obtener los datos para el PDF."
    );
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
  selectedLogsInDetail.value = [];
};

const updateDetailTableOptions = (options) => {
  pageReports.value = options.page;
  itemsPerPageReports.value = options.itemsPerPage;
  sortByReports.value = options.sortBy[0]?.key || "created_at";
  orderByReports.value = options.sortBy[0]?.order || "desc";
};

let debounceTimerLots;
watch(
  [
    pageLots,
    itemsPerPageLots,
    sortByLots,
    orderByLots,
    searchQueryLots,
    selectedLaboratoryLots,
    startDateLots,
    endDateLots,
  ],
  () => {
    clearTimeout(debounceTimerLots);
    debounceTimerLots = setTimeout(() => fetchLots(), 300);
  },
  { deep: true }
);

watch(
  [searchQueryLots, selectedLaboratoryLots, startDateLots, endDateLots],
  () => {
    pageLots.value = 1;
  }
);

watch(
  [pageReports, itemsPerPageReports, sortByReports, orderByReports],
  () => {
    if (isDetailViewVisible.value) {
      fetchExpiredLogs();
    }
  },
  { deep: true }
);

onMounted(() => {
  fetchLots();
  fetchSummaries();
  fetchLaboratories();
});

const formatMonth = (monthStr) => {
  if (!monthStr) return "";
  const [year, month] = monthStr.split("-");
  return new Date(year, month - 1).toLocaleString("es-CO", {
    month: "long",
    year: "numeric",
  });
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
    <!-- Sección de Lotes por Vencer -->
    <div>
      <ExpirationsFilters
        v-model:searchQuery="searchQueryLots"
        v-model:selectedLaboratory="selectedLaboratoryLots"
        v-model:startDate="startDateLots"
        v-model:endDate="endDateLots"
        :laboratories="laboratories"
        :loading="loadingLaboratories"
        :selected-lots="selectedLots"
        @clear="handleClearFiltersLots"
        @expire-selected="handleExpireSelected"
        @apply-offer-selected="handleApplyOfferSelected"
      />

      <!-- Tabla con título integrado -->
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between">
          <div>
            <h4 class="text-h4 mb-1">Lotes por Vencer</h4>
            <p class="text-subtitle-1 text-medium-emphasis mb-0">
              Gestiona los lotes próximos a su fecha de caducidad.
            </p>
          </div>
        </VCardTitle>

        <VCardText class="pa-0">
          <ExpirationsTable
            v-model="selectedLots"
            :lots="lots"
            :loading="loadingLots"
            :total-lots="totalLots"
            :items-per-page="itemsPerPageLots"
            :page="pageLots"
            @update:options="updateTableOptionsLots"
            @apply-discount="handleApplyDiscount"
            @expire-lot="handleExpireLot"
          />
        </VCardText>
      </VCard>
    </div>

    <VDivider class="my-8" />

    <!-- Sección de Reportes de Caducidad -->
    <div>
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between">
          <h4 class="text-h4 text-capitalize">
            {{ viewTitle }}
          </h4>
          <VBtn
            v-if="isDetailViewVisible"
            variant="outlined"
            prepend-icon="tabler-arrow-left"
            @click="showSummaryView"
          >
            Volver a Resúmenes
          </VBtn>
        </VCardTitle>

        <VCardText class="pa-0">
          <div v-if="!isDetailViewVisible">
            <VDataTable
              v-if="monthlySummaries.length > 0"
              :headers="headersSummaries"
              :items="monthlySummaries"
              :loading="loadingReports"
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
                <span class="font-weight-medium">{{
                  item.total_products
                }}</span>
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
                <VTooltip text="Imprimir Carta(s) de Donación">
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

            <div v-else-if="!loadingReports" class="pa-6">
              <VAlert type="info" variant="tonal">
                No se encontraron registros de lotes caducados.
              </VAlert>
            </div>

            <VProgressLinear
              v-if="loadingReports"
              indeterminate
              color="primary"
            />
          </div>

          <ExpiredDetailView
            v-else
            v-model:selected-logs="selectedLogsInDetail"
            :logs="expiredLogs"
            :total-logs="totalExpiredLogs"
            :loading="loadingReports"
            :page="pageReports"
            :items-per-page="itemsPerPageReports"
            @update:options="updateDetailTableOptions"
            @generate-donation="handleOpenDonationFromSelection"
          />
        </VCardText>
      </VCard>
    </div>

    <DonationLetterDialog
      v-model="isDonationModalVisible"
      :initial-products="productsForDonation"
      @generate="handleGenerateDonation"
    />
  </div>
</template>
