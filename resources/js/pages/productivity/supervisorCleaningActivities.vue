<script setup>
import SupervisorCleaningFilters from "@/components/SupervisorCleaningFilters.vue";
import SupervisorCleaningTable from "@/components/SupervisorCleaningTable.vue";
import SupervisorReviewDialog from "@/components/dialogs/SupervisorReviewDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const executions = ref([]);
const totalRecords = ref(0);
const loading = ref(false);
const stats = ref({
  pending_review: 0,
  approved_total: 0,
  rejected_total: 0,
  overdue_total: 0,
  cancelled_total: 0,
  processed_today: 0,
});

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const searchQuery = ref("");
const selectedStatus = ref("Procesada"); // Por defecto mostrar las que necesitan revisión
const selectedEmployee = ref(null);
const dateFrom = ref("");
const dateTo = ref("");

const isReviewDialogVisible = ref(false);
const currentExecution = ref({});
const dialogErrors = ref({});

// Función para obtener las ejecuciones
const fetchExecutions = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    status: selectedStatus.value,
    employee_id: selectedEmployee.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/supervisor/cleaning-executions", {
      params,
    });
    executions.value = response.data.data.data;
    totalRecords.value = response.data.data.total;
  } catch (error) {
    console.error("Error al obtener las ejecuciones:", error);
    toast.error("Error al obtener las actividades para revisión.");
  } finally {
    loading.value = false;
  }
};

// Función para obtener estadísticas
const fetchStats = async () => {
  try {
    const response = await axios.get("/supervisor/cleaning-executions/stats");
    stats.value = response.data.data;
  } catch (error) {
    console.error("Error al obtener estadísticas:", error);
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
    selectedStatus,
    selectedEmployee,
    dateFrom,
    dateTo,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchExecutions(), 300);
  },
  { deep: true }
);

watch([searchQuery, selectedStatus, selectedEmployee, dateFrom, dateTo], () => {
  page.value = 1;
});

onMounted(() => {
  fetchStats();
  fetchExecutions();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedStatus.value = "Procesada";
  selectedEmployee.value = null;
  dateFrom.value = "";
  dateTo.value = "";
};

const handleSort = (sortOptions) => {
  if (sortOptions.key === undefined && sortOptions.order === undefined) {
    sortBy.value = undefined;
    orderBy.value = undefined;
  } else {
    sortBy.value = sortOptions.key;
    orderBy.value = sortOptions.order;
  }
};

const handleReview = (execution) => {
  currentExecution.value = { ...execution };
  dialogErrors.value = {};
  isReviewDialogVisible.value = true;
};

const handleApprove = async (data) => {
  try {
    await axios.post(
      `/supervisor/cleaning-executions/${currentExecution.value.execution_id}/approve`,
      data
    );

    toast.success("Actividad aprobada exitosamente");
    isReviewDialogVisible.value = false;
    await fetchExecutions();
    await fetchStats();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      dialogErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al aprobar:", error);
      toast.error("Hubo un error al aprobar la actividad.");
    }
  }
};

const handleReject = async (data) => {
  try {
    await axios.post(
      `/supervisor/cleaning-executions/${currentExecution.value.execution_id}/reject`,
      data
    );

    toast.success("Actividad devuelta al empleado");
    isReviewDialogVisible.value = false;
    await fetchExecutions();
    await fetchStats();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      dialogErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al rechazar:", error);
      toast.error("Hubo un error al rechazar la actividad.");
    }
  }
};

const handleCancel = async (data) => {
  try {
    await axios.post(
      `/supervisor/cleaning-executions/${currentExecution.value.execution_id}/cancel`,
      data
    );

    toast.success("Actividad cancelada");
    isReviewDialogVisible.value = false;
    await fetchExecutions();
    await fetchStats();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      dialogErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al cancelar:", error);
      toast.error("Hubo un error al cancelar la actividad.");
    }
  }
};

const clearDialogErrors = () => {
  dialogErrors.value = {};
};
</script>

<template>
  <div>
    <!-- Tarjetas de Estadísticas -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="4" lg="2">
        <VCard>
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-disabled mb-1">Pendientes</div>
              <div class="text-h5 font-weight-bold">
                {{ stats.pending_review }}
              </div>
            </div>
            <VAvatar color="warning" variant="tonal" size="40">
              <VIcon icon="tabler-clock" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="4" lg="2">
        <VCard>
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-disabled mb-1">Aprobadas</div>
              <div class="text-h5 font-weight-bold">
                {{ stats.approved_total }}
              </div>
            </div>
            <VAvatar color="success" variant="tonal" size="40">
              <VIcon icon="tabler-check" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="4" lg="2">
        <VCard>
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-disabled mb-1">Rechazadas</div>
              <div class="text-h5 font-weight-bold">
                {{ stats.rejected_total }}
              </div>
            </div>
            <VAvatar color="error" variant="tonal" size="40">
              <VIcon icon="tabler-x" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="4" lg="2">
        <VCard>
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-disabled mb-1">Vencidas</div>
              <div class="text-h5 font-weight-bold">
                {{ stats.overdue_total }}
              </div>
            </div>
            <VAvatar color="error" variant="tonal" size="40">
              <VIcon icon="tabler-alert-triangle" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="4" lg="2">
        <VCard>
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-disabled mb-1">Canceladas</div>
              <div class="text-h5 font-weight-bold">
                {{ stats.cancelled_total }}
              </div>
            </div>
            <VAvatar color="secondary" variant="tonal" size="40">
              <VIcon icon="tabler-ban" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="4" lg="2">
        <VCard>
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption text-disabled mb-1">Hoy</div>
              <div class="text-h5 font-weight-bold">
                {{ stats.processed_today }}
              </div>
            </div>
            <VAvatar color="info" variant="tonal" size="40">
              <VIcon icon="tabler-calendar-check" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filtros -->
    <SupervisorCleaningFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedStatus="selectedStatus"
      v-model:selectedEmployee="selectedEmployee"
      v-model:dateFrom="dateFrom"
      v-model:dateTo="dateTo"
      :loading="loading"
      @clear="handleClearFilters"
      @sort="handleSort"
    />

    <!-- Tabla -->
    <SupervisorCleaningTable
      :executions="executions"
      :loading="loading"
      :total-records="totalRecords"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @review="handleReview"
    />

    <!-- Modal de Revisión -->
    <SupervisorReviewDialog
      v-model="isReviewDialogVisible"
      :execution="currentExecution"
      :errors="dialogErrors"
      @approve="handleApprove"
      @reject="handleReject"
      @cancel="handleCancel"
      @clear-errors="clearDialogErrors"
    />
  </div>
</template>
