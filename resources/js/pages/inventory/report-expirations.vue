<script setup>
import DonationLetterDialog from "@/components/DonationLetterDialog.vue";
import ExpiredDetailView from "@/components/ExpiredDetailView.vue";
import ExpiredSummaryCard from "@/components/ExpiredSummaryCard.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref, watch } from "vue";

const isDetailViewVisible = ref(false);
const loading = ref(false);

const selectedMonth = ref(null);
const monthlySummaries = ref([]);

const expiredLogs = ref([]);
const totalExpiredLogs = ref(0);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref("created_at");
const orderBy = ref("desc");
const searchQuery = ref("");

const isDonationModalVisible = ref(false);
const allExpiredLogsForDonation = ref([]);
const isFetchingAllLogs = ref(false);

const viewTitle = computed(() => {
  if (isDetailViewVisible.value && selectedMonth.value) {
    const [year, month] = selectedMonth.value.split("-");
    const date = new Date(year, month - 1);
    const monthName = date.toLocaleString("es-CO", { month: "long" });
    return `Caducados de ${monthName} ${year}`;
  }
  return "Gestión de Caducados por Mes";
});

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
    q: searchQuery.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    month: selectedMonth.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
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

const fetchAllExpiredLogsForDonation = async () => {
  isFetchingAllLogs.value = true;
  try {
    const response = await axios.get("/expired-logs", {
      params: { itemsPerPage: -1 },
    });
    allExpiredLogsForDonation.value = response.data.data;
  } catch (error) {
    console.error(error);
    toast.error("Error al cargar productos para donación.");
  } finally {
    isFetchingAllLogs.value = false;
  }
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery],
  () => {
    if (isDetailViewVisible.value) {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => fetchExpiredLogs(), 300);
    }
  },
  { deep: true }
);

watch(searchQuery, () => (page.value = 1));

onMounted(fetchSummaries);

const updateDetailTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const showDetailView = (month) => {
  selectedMonth.value = month;
  isDetailViewVisible.value = true;
  fetchExpiredLogs();
};

const showSummaryView = () => {
  isDetailViewVisible.value = false;
  selectedMonth.value = null;
  searchQuery.value = "";
};

const handleActivateOffer = (item) => {
  toast.info(
    `Funcionalidad 'Activar Oferta' para "${item.product_name}" no implementada.`
  );
  console.log("Activar oferta para:", item);
};

const handleOpenDonationModal = async () => {
  isDonationModalVisible.value = true;
  await fetchAllExpiredLogsForDonation();
};

const handleGenerateDonation = async (donationData) => {
  try {
    await axios.post("/donations", {
      institution_name: donationData.institution,
      expired_log_ids: donationData.products.map((p) => p.id),
    });

    generateDonationPDF(donationData);

    toast.success("Carta de donación generada y registrada con éxito.");
    isDonationModalVisible.value = false;

    fetchSummaries();
  } catch (error) {
    console.error("Error en el proceso de donación:", error);
    const errorMessage =
      error.response?.data?.message || "No se pudo completar la donación.";
    toast.error(errorMessage);
  }
};
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <h4 class="text-h4 text-capitalize">
        {{ viewTitle }}
      </h4>
      <div>
        <VBtn
          v-if="!isDetailViewVisible"
          color="secondary"
          variant="outlined"
          @click="handleOpenDonationModal"
        >
          Carta de Donativo Global
        </VBtn>
        <VBtn
          v-if="isDetailViewVisible"
          variant="outlined"
          prepend-icon="tabler-arrow-left"
          @click="showSummaryView"
        >
          Volver a Resúmenes
        </VBtn>
      </div>
    </div>

    <div v-if="!isDetailViewVisible">
      <VProgressLinear v-if="loading" indeterminate color="primary" />
      <div v-else-if="monthlySummaries.length > 0">
        <ExpiredSummaryCard
          v-for="summary in monthlySummaries"
          :key="summary.month"
          :summary="summary"
          @show-details="showDetailView"
        />
      </div>
      <VAlert v-else type="info" variant="tonal">
        No se encontraron registros de lotes caducados.
      </VAlert>
    </div>

    <ExpiredDetailView
      v-else
      :logs="expiredLogs"
      :total-logs="totalExpiredLogs"
      :loading="loading"
      :page="page"
      :items-per-page="itemsPerPage"
      :search-query="searchQuery"
      @update:options="updateDetailTableOptions"
      @update:search-query="(value) => (searchQuery = value)"
      @activate-offer="handleActivateOffer"
    />

    <DonationLetterDialog
      v-model="isDonationModalVisible"
      :loading="isFetchingAllLogs"
      :initial-products="allExpiredLogsForDonation"
      @generate="handleGenerateDonation"
    />
  </div>
</template>
