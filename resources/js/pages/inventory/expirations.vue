<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { generateDonationPDF } from "@/utils/donationPdfGenerator";
import Swal from "sweetalert2";
import { computed, onMounted, ref, watch } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { useAbility } from "@casl/vue";

const { can } = useAbility();
const brandingStore = useBrandingStore();
const isRestaurant = computed(() => brandingStore.settings.business_type === 'restaurant');

import PriceAdjustmentDialog from "@/components/dialogs/PriceAdjustmentDialog.vue";
import DonationLetterDialog from "@/components/DonationLetterDialog.vue";
import ExpirationsFilters from "@/components/ExpirationsFilters.vue";
import ExpirationsTable from "@/components/ExpirationsTable.vue";
import ExpiredDetailView from "@/components/ExpiredDetailView.vue";
import { formatMonth, formatPrice as formatCurrency, formatDateSimple } from "@/utils/formatters";

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
const isStrictSearchLots = ref(false);

const allProducts = ref([]);
const loadingAllProducts = ref(false);

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

const fetchAllProducts = async () => {
  loadingAllProducts.value = true;
  try {
    const { data } = await axios.get("/products/all");
    allProducts.value = data;
  } catch (error) {
    console.error("Error al cargar productos:", error);
    toast.error("No se pudieron cargar los productos del sistema.");
  } finally {
    loadingAllProducts.value = false;
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
    startDate: startDateLots.value,
    endDate: endDateLots.value,
    isStrictSearch: isStrictSearchLots.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "" || params[key] === false) && delete params[key]
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
    text: `Vas a marcar como caducado el lote No ${lotToExpire.lot_number} del producto "${lotToExpire.product.name}".`,
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Confirmar",
    reverseButtons: true,
    didOpen: () => {
      const actions = Swal.getActions();
      const confirmButton = Swal.getConfirmButton();
      const cancelButton = Swal.getCancelButton();

      actions.style.display = "flex";
      actions.style.gap = "10px";
      actions.style.width = "100%";
      actions.style.padding = "0 20px";

      confirmButton.style.flex = "1";
      confirmButton.style.width = "50%";

      cancelButton.style.flex = "1";
      cancelButton.style.width = "50%";
    },
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
    didOpen: () => {
      const actions = Swal.getActions();
      const confirmButton = Swal.getConfirmButton();
      const cancelButton = Swal.getCancelButton();

      actions.style.display = "flex";
      actions.style.gap = "10px";
      actions.style.width = "100%";
      actions.style.padding = "0 20px";

      confirmButton.style.flex = "1";
      confirmButton.style.width = "50%";

      cancelButton.style.flex = "1";
      cancelButton.style.width = "50%";
    },
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
  isStrictSearchLots.value = false;
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

const isPriceAdjustmentModalVisible = ref(false);
const selectedMonthForAdjustment = ref(null);

const loadingAdjustmentForMonth = ref(null);

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
  return "Reporte de Caducados";
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

    donationsToProcess.forEach((donation) => {
      generateDonationPDF({
        institution: donation.institution_name,
        products: donation.products,
        exchangeRateBs: donation.exchange_rate_bs,
      });
    });
  } catch (error) {
    console.error("Error al obtener datos de donación:", error);
    toast.error("No se pudieron cargar los datos para la impresión.");
  }
};

const handlePrintMonthlyReport = (month) => {
  toast.info("Generando Acta de Desincorporación...");
  window.open(`/api/products/expirations/month/${month}/report`, "_blank");
};

const handlePriceAdjustmentExpired = async (month) => {
  if (loadingAdjustmentForMonth.value === month) return;

  try {
    loadingAdjustmentForMonth.value = month;

    await fetchAllProducts();

    selectedMonthForAdjustment.value = month;
    isPriceAdjustmentModalVisible.value = true;
  } catch (error) {
    console.error("Error al preparar reajuste de precios:", error);
    toast.error("No se pudo inicializar el reajuste de precios.");
  } finally {
    loadingAdjustmentForMonth.value = null;
  }
};

const handleGeneratePriceAdjustment = async (adjustmentData) => {
  isPriceAdjustmentModalVisible.value = false;

  const payload = {
    month: selectedMonthForAdjustment.value,
    excludedProductIds: adjustmentData.excludedProducts.map((p) => p.id),
  };

  try {
    Swal.fire({
      title: "Calculando reajuste...",
      text: "Por favor espera mientras obtenemos los datos para la confirmación.",
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    const { data: preview } = await axios.post(
      "/expirations/adjust-prices/preview",
      payload
    );

    const result = await Swal.fire({
      title: "Confirmar Reajuste de Precios",
      html: `
        <div style="text-align: left; padding: 0 1rem; font-size: 1rem;">
          <p>Estás a punto de redistribuir el costo de los productos caducados. Por favor, revisa los detalles:</p>
          <hr style="margin: 1rem 0;" />
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span>Monto total a redistribuir:</span>
            <strong>${formatCurrency(preview.total_lost_value)}</strong>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span>Total de unidades activas:</span>
            <strong>${preview.total_active_stock.toLocaleString(
              "es-CO"
            )} unidades</strong>
          </div>
           <div style="display: flex; justify-content: space-between; margin-top: 0.25rem; color: #6c757d;">
            <small>(en ${preview.affected_products_count.toLocaleString(
              "es-CO"
            )} productos)</small>
          </div>
          <hr style="margin: 1rem 0;" />
          <div style="display: flex; justify-content: space-between; font-size: 1.15rem;">
            <span>Ajuste por cada unidad:</span>
            <strong style="color: #28a745;">+ ${formatCurrency(
              preview.cost_adjustment_per_unit
            )}</strong>
          </div>
        </div>
      `,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sí, aplicar reajuste",
      cancelButtonText: "Cancelar",
      reverseButtons: true,
      customClass: {
        htmlContainer: "text-left",
      },
      didOpen: () => {
        const actions = Swal.getActions();
        const confirmButton = Swal.getConfirmButton();
        const cancelButton = Swal.getCancelButton();

        actions.style.display = "flex";
        actions.style.gap = "10px";
        actions.style.width = "100%";
        actions.style.padding = "0 20px";

        confirmButton.style.flex = "1";
        confirmButton.style.width = "50%";

        cancelButton.style.flex = "1";
        cancelButton.style.width = "50%";
      },
    });

    if (result.isConfirmed) {
      Swal.fire({
        title: "Aplicando reajuste...",
        text: "Esta operación puede tardar unos segundos. No cierres esta ventana.",
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        },
      });

      const { data: responseData } = await axios.post(
        "/expirations/adjust-expired-prices",
        payload
      );

      toast.success(
        responseData.message || "Reajuste de precios aplicado correctamente."
      );

      await fetchSummaries();
    }
  } catch (error) {
    console.error("Error en el proceso de reajuste de precios:", error);
    Swal.fire({
      icon: "error",
      title: "Operación cancelada",
      text:
        error.response?.data?.message || "No se pudo completar la operación.",
    });
  }
};

const handleExport = (format) => {
  const params = new URLSearchParams();
  params.append("format", format);
  if (searchQueryLots.value) params.append("q", searchQueryLots.value);
  if (selectedLaboratoryLots.value)
    params.append("laboratory_id", selectedLaboratoryLots.value);
  if (startDateLots.value) params.append("startDate", startDateLots.value);
  if (endDateLots.value) params.append("endDate", endDateLots.value);
  if (isStrictSearchLots.value) params.append("isStrict", "true");

  toast.info(`Generando reporte ${format.toUpperCase()}...`);
  window.open(`/api/products/expirations/export?${params.toString()}`, "_blank");
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
    isStrictSearchLots,
  ],
  () => {
    clearTimeout(debounceTimerLots);
    debounceTimerLots = setTimeout(() => fetchLots(), 300);
  },
  { deep: true }
);

watch(
  [searchQueryLots, selectedLaboratoryLots, startDateLots, endDateLots, isStrictSearchLots],
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

// Las funciones de formateo ahora se manejan centralizadamente en @/utils/formatters.js
// y son utilizadas directamente por los componentes ExpirationsTable y ExpirationsFilters.
</script>

<template>
  <div>
    <div>
      <ExpirationsFilters
        v-model:searchQuery="searchQueryLots"
        v-model:selectedLaboratory="selectedLaboratoryLots"
        v-model:startDate="startDateLots"
        v-model:endDate="endDateLots"
        v-model:isStrictSearch="isStrictSearchLots"
        :laboratories="laboratories"
        :loading="loadingLaboratories"
        :selected-lots="selectedLots"
        @clear="handleClearFiltersLots"
        @expire-selected="handleExpireSelected"
        @export="handleExport"
      />

      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between">
          <div>
            <h4 class="text-h4 mb-1">Productos por Caducar</h4>

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
            @expire-lot="handleExpireLot"
          />
        </VCardText>
      </VCard>
    </div>

    <VDivider v-if="can('manage', 'admin')" class="my-8" />

    <div v-if="can('manage', 'admin')">
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between pa-4">
          <div class="d-flex align-center gap-x-3">
            <VBtn
              v-if="isDetailViewVisible"
              icon
              variant="tonal"
              color="primary"
              size="small"
              @click="showSummaryView"
            >
              <VIcon icon="tabler-arrow-left" />
            </VBtn>
            <h4 class="text-h4 text-capitalize mb-0">
              {{ viewTitle }}
            </h4>
          </div>
          
          <VTooltip text="Volver a Resúmenes" location="top">
            <template #activator="{ props: tooltipProps }">
              <IconBtn
                v-bind="tooltipProps"
                v-if="isDetailViewVisible"
                color="primary"
                class="d-none d-md-flex"
                @click="showSummaryView"
              >
                <VIcon icon="tabler-arrow-left" />
              </IconBtn>
            </template>
          </VTooltip>
        </VCardTitle>

        <VCardText class="pa-0">
          <div v-if="!isDetailViewVisible">
            <!-- Vista de Escritorio (Tabla) -->
            <div class="d-none d-md-block">
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
                        color="info"
                        @click="showDetailView(item.month)"
                      >
                        <VIcon icon="tabler-eye" />
                      </IconBtn>
                    </template>
                  </VTooltip>
                  <VTooltip text="Imprimir Acta de Desincorporación (Bajas)">
                    <template #activator="{ props: tooltipProps }">
                      <IconBtn
                        v-bind="tooltipProps"
                        color="warning"
                        @click="handlePrintMonthlyReport(item.month)"
                      >
                        <VIcon icon="tabler-file-report" />
                      </IconBtn>
                    </template>
                  </VTooltip>
                  <VTooltip v-if="!isRestaurant" text="Imprimir Carta(s) de Donación">
                    <template #activator="{ props: tooltipProps }">
                      <div v-bind="tooltipProps" class="d-inline-block">
                        <IconBtn
                          color="primary"
                          :disabled="item.donation_count === 0"
                          @click="handlePrintDonation(item.month)"
                        >
                          <VIcon icon="tabler-printer" />
                        </IconBtn>
                      </div>
                    </template>
                  </VTooltip>
                  <VTooltip text="Reajustar Precios">
                    <template #activator="{ props: tooltipProps }">
                      <div
                        v-bind="tooltipProps"
                        class="d-inline-block"
                        style="width: 36px; height: 36px; text-align: center"
                      >
                        <VProgressCircular
                          v-if="loadingAdjustmentForMonth === item.month"
                          indeterminate
                          size="20"
                          width="2"
                          color="warning"
                          class="mt-2"
                        />

                        <IconBtn
                          v-else
                          color="warning"
                          :disabled="item.has_price_adjustment"
                          @click="handlePriceAdjustmentExpired(item.month)"
                        >
                          <VIcon
                            :icon="
                              item.has_price_adjustment
                                ? 'tabler-currency-dollar-off'
                                : 'tabler-currency-dollar'
                            "
                            :class="
                              item.has_price_adjustment ? 'text-disabled' : ''
                            "
                          />
                        </IconBtn>
                      </div>
                    </template>
                  </VTooltip>
                </template>
              </VDataTable>
            </div>

            <!-- Vista de Móvil (Tarjetas de Resumen) -->
            <div class="d-block d-md-none pa-4">
              <div v-if="monthlySummaries.length > 0" class="d-flex flex-column gap-3">
                <VCard
                  v-for="item in monthlySummaries"
                  :key="item.month"
                  variant="outlined"
                  class="bg-var-theme-background border-dashed-thin"
                  style="border-radius: 12px !important;"
                >
                  <div class="pa-4">
                    <div class="d-flex justify-space-between align-center mb-4">
                      <h4 class="text-h5 text-capitalize text-primary font-weight-black">
                        {{ formatMonth(item.month) }}
                      </h4>
                      <VChip color="error" size="small" label font-weight-bold>
                        {{ item.total_products }} PRODS
                      </VChip>
                    </div>

                    <div class="d-flex justify-space-between align-center py-2 px-3 rounded bg-white shadow-sm mb-4">
                      <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Pérdida Total</span>
                      <span class="text-lg font-weight-black text-error">
                        {{ formatCurrency(item.total_cost) }}
                      </span>
                    </div>

                    <VRow no-gutters class="border-t">
                      <VCol :cols="isRestaurant ? 6 : 4" class="border-e">
                        <VBtn
                          block
                          variant="text"
                          color="info"
                          height="48"
                          class="rounded-0"
                          @click="showDetailView(item.month)"
                        >
                          <VIcon icon="tabler-eye" size="22" />
                        </VBtn>
                      </VCol>
                      <VCol v-if="!isRestaurant" cols="4" class="border-e">
                        <VBtn
                          block
                          variant="text"
                          color="primary"
                          height="48"
                          class="rounded-0"
                          :disabled="item.donation_count === 0"
                          @click="handlePrintDonation(item.month)"
                        >
                          <VIcon icon="tabler-printer" size="22" />
                        </VBtn>
                      </VCol>
                      <VCol :cols="isRestaurant ? 6 : 4">
                        <VBtn
                          block
                          variant="text"
                          color="warning"
                          height="48"
                          class="rounded-0"
                          :disabled="item.has_price_adjustment"
                          :loading="loadingAdjustmentForMonth === item.month"
                          @click="handlePriceAdjustmentExpired(item.month)"
                        >
                          <VIcon :icon="item.has_price_adjustment ? 'tabler-currency-dollar-off' : 'tabler-currency-dollar'" size="22" />
                        </VBtn>
                      </VCol>
                    </VRow>
                  </div>
                </VCard>
              </div>
            </div>

            <div v-if="monthlySummaries.length === 0 && !loadingReports" class="pa-6">
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

    <PriceAdjustmentDialog
      v-model="isPriceAdjustmentModalVisible"
      :all-products="allProducts"
      :month-name="formatMonth(selectedMonthForAdjustment)"
      @adjust-prices="handleGeneratePriceAdjustment"
    />
  </div>
</template>
