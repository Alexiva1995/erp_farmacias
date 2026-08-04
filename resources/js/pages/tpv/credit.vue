<script setup>
import CreditHeaderKpi from "@/components/CreditHeaderKpi.vue";
import CreditFilters from "@/components/CreditFilters.vue";
import CreditPaymentsFilters from "@/components/CreditPaymentsFilter.vue";
import CreditPaymentsTable from "@/components/CreditPaymentsTable.vue";
import CreditTable from "@/components/CreditTable.vue";
import CreditPrintContainers from "@/components/CreditPrintContainers.vue";
import { THERMAL_54MM_CSS } from "@/constants/thermalTicket54.js";
import CreditsModal from "@/components/dialogs/CreditsModal.vue";
import CreditsViewOrderModal from "@/components/dialogs/CreditsViewOrderModal.vue";
import axios from "@/plugins/axios";
import Swal from "sweetalert2";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import { nextTick, onMounted, onUnmounted, ref, watch, computed } from "vue";
import { useDisplay } from "vuetify";

const { isVendedor } = useAuthStore();
const { mobile } = useDisplay();

const activeTab = ref(0);

const credits = ref([]);
const totalCredits = ref(0);
const payments = ref([]);
const totalPayments = ref(0);
const loading = ref(false);
const paymentsLoading = ref(false);

const clientFilter = ref(null);
const dateFilter = ref(null);
const currencyFilter = ref(null);
const paymentsPage = ref(1);
const paymentsItemsPerPage = ref(10);
const paymentsSortBy = ref();
const paymentsOrderBy = ref();

const searchQuery = ref("");
const page = ref(1);
const itemsPerPage = ref(25);
const sortBy = ref();
const orderBy = ref();

const showCreditsModal = ref(false);
const creditsData = ref(null);

const isPrinting = ref(false);
const paymentsForPrint = ref([]);
const changeAmountForPrint = ref(0);
const creditAmountForPrint = ref(0);

const selectedClient = ref(null);
const showViewOrderModal = ref(false);
const isPrintingCreditOrder = ref(false);

let debounceTimer = null;
let paymentsDebouncer = null;

const totalPendingSum = computed(() => {
  if (!Array.isArray(credits.value)) return 0;
  return credits.value.reduce(
    (sum, item) => sum + (parseFloat(item.total_pending_amount) || 0),
    0
  );
});

const fetchCredits = async () => {
  loading.value = true;
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    search: searchQuery.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get("/tpv/credits", { params });
    credits.value = response.data.data || [];
    totalCredits.value = response.data.total || 0;
  } catch (error) {
    console.error("Hubo un error al obtener los créditos:", error);
    toast.error("Error al obtener los créditos.");
  } finally {
    loading.value = false;
  }
};

const fetchCreditPayments = async () => {
  paymentsLoading.value = true;
  const params = {
    page: paymentsPage.value,
    items_per_page: paymentsItemsPerPage.value,
    sort_by: paymentsSortBy.value,
    order_by: paymentsOrderBy.value,
    client: clientFilter.value,
    date: dateFilter.value,
    currency: currencyFilter.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const { data } = await axios.get("/tpv/credits/payments", { params });
    payments.value = data.data || [];
    totalPayments.value = data.total || 0;
  } catch (error) {
    console.error("Hubo un error al obtener los pagos de créditos:", error);
    toast.error("Error al obtener los pagos de créditos.");
  } finally {
    paymentsLoading.value = false;
  }
};

watch(
  [page, itemsPerPage, searchQuery],
  () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchCredits(), 300);
  },
  { deep: true }
);

watch(
  [
    paymentsPage,
    paymentsItemsPerPage,
    paymentsSortBy,
    paymentsOrderBy,
    clientFilter,
    dateFilter,
    currencyFilter,
  ],
  () => {
    if (paymentsDebouncer) clearTimeout(paymentsDebouncer);
    paymentsDebouncer = setTimeout(() => fetchCreditPayments(), 300);
  }
);

onUnmounted(() => {
  if (debounceTimer) clearTimeout(debounceTimer);
  if (paymentsDebouncer) clearTimeout(paymentsDebouncer);
});

onMounted(() => {
  if (!isVendedor) {
    Promise.all([fetchCredits(), fetchCreditPayments()]);
  } else {
    fetchCredits();
  }
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const updatePaymentsTableOptions = (options) => {
  paymentsPage.value = options.page;
  paymentsItemsPerPage.value = options.itemsPerPage;
  paymentsSortBy.value = options.sortBy[0]?.key;
  paymentsOrderBy.value = options.sortBy[0]?.order;
};

const clearFilters = () => {
  searchQuery.value = "";
};

const clearPaymentFilters = () => {
  searchQuery.value = "";
  page.value = 1;
  itemsPerPage.value = 25;
  sortBy.value = null;
  orderBy.value = null;
  clientFilter.value = null;
  dateFilter.value = null;
  currencyFilter.value = null;
};

const openCreditsModal = (credit) => {
  creditsData.value = credit;
  showCreditsModal.value = true;
};

const viewOrderCreditsModal = async (credit) => {
  let creditIds;
  if (Array.isArray(credit.credit_ids)) {
    creditIds = credit.credit_ids.map((id) => parseInt(id));
  } else {
    creditIds = String(credit.credit_ids)
      .split(",")
      .map((id) => parseInt(id.trim()))
      .filter((n) => !isNaN(n));
  }
  try {
    const response = await axios.post("/tpv/credits/details", {
      credit_ids: creditIds,
    });
    const detailedCredits = response.data.data || response.data;
    creditsData.value = detailedCredits;
    showViewOrderModal.value = true;
  } catch (error) {
    console.error("Error al obtener los detalles de los créditos:", error);
    toast.error("No se pudo cargar el historial de la orden.");
  }
};

const closeCreditsModal = () => {
  showCreditsModal.value = false;
  creditsData.value = null;
};

const handleDeleteCredit = async (item) => {
  const creditIds = Array.isArray(item.credit_ids)
    ? item.credit_ids.map((id) => parseInt(id))
    : String(item.credit_ids || "")
        .split(",")
        .map((id) => parseInt(id.trim()))
        .filter((n) => !isNaN(n));

  if (!creditIds.length) {
    toast.error("No se encontraron créditos para eliminar.");
    return;
  }

  const { isConfirmed } = await Swal.fire({
    title: "¿Eliminar crédito?",
    text: "Esta acción no se puede deshacer. Se eliminarán los créditos seleccionados.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#6e7d88",
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
  });

  if (!isConfirmed) return;

  try {
    const response = await axios.delete("/tpv/credits", {
      data: { credit_ids: creditIds },
    });
    if (response.data?.success !== false) {
      toast.success("Crédito(s) eliminado(s) correctamente.");
      await fetchCredits();
    } else {
      toast.error(response.data?.message || "Error al eliminar el crédito.");
    }
  } catch (error) {
    const msg =
      error.response?.data?.message ||
      error.response?.data?.error ||
      "Error al eliminar el crédito.";
    toast.error(msg);
  }
};

const closeViewOrderCreditsModal = () => {
  showViewOrderModal.value = false;
  creditsData.value = null;
};

const handleCreditsCompletion = async (
  paymentsData,
  changeAmount,
  changeAmountUSD
) => {
  try {
    const clientId = creditsData.value?.client?.id;

    if (!clientId) {
      toast.error("No se pudo obtener el ID del cliente. Intente de nuevo.");
      return;
    }

    const payload = {
      clientId: clientId,
      payments: paymentsData,
      changeAmount: changeAmount,
      changeAmountUSD: changeAmountUSD,
    };
    const response = await axios.post(`/tpv/credits/complete`, payload);
    if (response.status === 200 || response.status === 201) {
      toast.success("¡Pago finalizado y registrado con éxito!");
      await fetchCredits();
      paymentsForPrint.value = [...paymentsData];
      changeAmountForPrint.value = changeAmount;
      isPrinting.value = true;
      await nextTick();
      const printContents = document.getElementById("CreditPrint");
      if (printContents) {
        const printWindow = window.open("", "", "height=600,width=800");
        if (printWindow) {
          printWindow.document.write(
            "<html><head><title>Farmacia Barrio Sucre</title>"
          );
          const styleSheets = document.styleSheets;
          for (let i = 0; i < styleSheets.length; i++) {
            const sheet = styleSheets[i];
            try {
              if (sheet.cssRules) {
                let cssText = "";
                for (let j = 0; j < sheet.cssRules.length; j++) {
                  cssText += sheet.cssRules[j].cssText;
                }
                printWindow.document.write(`<style>${cssText}</style>`);
              } else if (sheet.href) {
                printWindow.document.write(
                  `<link rel="stylesheet" href="${sheet.href}">`
                );
              }
            } catch (e) {
              console.warn("No se pudo acceder a la hoja de estilo:", e);
            }
          }
          printWindow.document.write("</head><body>");
          printWindow.document.write(printContents.innerHTML);
          printWindow.document.write("</body></html>");
          printWindow.document.close();
          printWindow.focus();
          printWindow.print();
          printWindow.close();
        }
      } else {
        window.print();
      }
    } else {
      toast.error(
        `Error al finalizar el pago: ${
          response.data.message || "Intente de nuevo."
        }`
      );
    }

    setTimeout(() => {
      isPrinting.value = false;
      paymentsForPrint.value = [];
      creditsData.value = null;
      selectedClient.value = null;
      changeAmountForPrint.value = 0;
      creditAmountForPrint.value = 0;
    }, 500);

    fetchCreditPayments();
  } catch (error) {
    console.error("Error al finalizar el pago del crédito:", error);
    const errorMessage =
      error.response?.data?.message ||
      "Hubo un problema al procesar su pago. Por favor, intente de nuevo.";
    toast.error(errorMessage);
    isPrinting.value = false;
    changeAmountForPrint.value = 0;
    creditAmountForPrint.value = 0;
  }
};

const printCreditOrders = async (credit) => {
  let creditIds;
  if (Array.isArray(credit.credit_ids)) {
    creditIds = credit.credit_ids.map((id) => parseInt(id));
  } else {
    creditIds = String(credit.credit_ids)
      .split(",")
      .map((id) => parseInt(id.trim()))
      .filter((n) => !isNaN(n));
  }

  try {
    const response = await axios.post("/tpv/credits/details", {
      credit_ids: creditIds,
    });
    const detailedCredits = response.data.data || response.data;
    creditsData.value = Array.isArray(detailedCredits) ? detailedCredits : [detailedCredits];

    isPrintingCreditOrder.value = true;
    await nextTick();
    const printContents = document.getElementById("CreditOrderPrintThermal54");

    if (printContents) {
      const win = window.open("", "", "height=400,width=280");
      if (win) {
        win.document.write("<html><head><title>Ticket 54mm - Crédito Pendiente</title>");
        win.document.write("<style>" + THERMAL_54MM_CSS + "</style>");
        win.document.write("</head><body>");
        win.document.write(printContents.innerHTML);
        win.document.write("</body></html>");
        win.document.close();
        win.focus();
        win.print();
        win.close();
      }
    } else {
      toast.error("No se encontró el contenido del ticket térmico.");
    }
    setTimeout(() => {
      isPrintingCreditOrder.value = false;
      creditsData.value = null;
    }, 500);
  } catch (error) {
    console.error("Error al obtener los detalles de los créditos:", error);
    toast.error("No se pudo cargar el historial de la orden.");
    isPrintingCreditOrder.value = false;
    creditsData.value = null;
  }
};
</script>

<template>
  <div class="credits-module-container">
    <!-- Encabezado KPI Desacoplado -->
    <CreditHeaderKpi
      :total-credits="totalCredits"
      :total-pending-sum="totalPendingSum"
    />

    <!-- Contenedor Premium de Filtros -->
    <CreditFilters
      v-if="activeTab === 0"
      v-model:search-query="searchQuery"
      @clear="clearFilters"
    />

    <CreditPaymentsFilters
      v-if="activeTab === 1 && !isVendedor"
      v-model:client="clientFilter"
      v-model:date="dateFilter"
      v-model:currency="currencyFilter"
      @clear="clearPaymentFilters"
    />

    <!-- Pestañas de Navegación -->
    <VTabs v-model="activeTab" class="mb-4 credits-tabs" density="comfortable">
      <VTab :value="0">
        <VIcon start icon="tabler-credit-card" />
        Créditos Pendientes
      </VTab>
      <VTab v-if="!isVendedor" :value="1">
        <VIcon start icon="tabler-history" />
        Historial de Pagos
      </VTab>
    </VTabs>

    <VWindow v-model="activeTab" class="disable-tab-transition">
      <!-- Pestaña 0: Créditos -->
      <VWindowItem :value="0">
        <CreditTable
          :credits="credits"
          :loading="loading"
          :total-credits="totalCredits"
          :items-per-page="itemsPerPage"
          :page="page"
          :mobile="mobile"
          @update:options="updateTableOptions"
          @open-payment-modal="openCreditsModal"
          @reload="fetchCredits"
          @view-order-modal="viewOrderCreditsModal"
          @print-order="printCreditOrders"
          @delete-credit="handleDeleteCredit"
        />
      </VWindowItem>

      <!-- Pestaña 1: Pagos (Solo Admin/Vendedor específico) -->
      <VWindowItem v-if="!isVendedor" :value="1">
        <CreditPaymentsTable
          :payments="payments"
          :loading="paymentsLoading"
          :total-payments="totalPayments"
          :items-per-page="paymentsItemsPerPage"
          :page="paymentsPage"
          :mobile="mobile"
          @update:options="updatePaymentsTableOptions"
        />
      </VWindowItem>
    </VWindow>

    <!-- Modales -->
    <CreditsModal
      v-if="showCreditsModal && creditsData"
      v-model:is-dialog-visible="showCreditsModal"
      :credits-data="creditsData"
      @modal-closed="closeCreditsModal"
      @purchase-completed="handleCreditsCompletion"
    />

    <CreditsViewOrderModal
      v-if="showViewOrderModal && creditsData"
      v-model:is-dialog-visible="showViewOrderModal"
      :credits-data="creditsData"
      @modal-closed="closeViewOrderCreditsModal"
    />

    <!-- Contenedores de Impresión Desacoplados -->
    <CreditPrintContainers
      :is-printing="isPrinting"
      :is-printing-credit-order="isPrintingCreditOrder"
      :credits-data="creditsData"
      :payments-for-print="paymentsForPrint"
      :change-amount-for-print="changeAmountForPrint"
      :credit-amount-for-print="creditAmountForPrint"
    />
  </div>
</template>

<style scoped>
.credits-module-container {
  width: 100%;
}

.credits-tabs :deep(.v-tab) {
  text-transform: uppercase;
  font-weight: 700;
  font-size: 0.8rem;
  letter-spacing: 0.5px;
}

.disable-tab-transition :deep(.v-window__container) {
  transition: none !important;
}
</style>
