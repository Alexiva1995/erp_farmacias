<script setup>
import MyCleaningActivitiesFilters from "@/components/MyCleaningActivitiesFilters.vue";
import MyCleaningActivitiesTable from "@/components/MyCleaningActivitiesTable.vue";
import UpdateActivityStatusDialog from "@/components/dialogs/UpdateActivityStatusDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const myActivities = ref([]);
const totalRecords = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const searchQuery = ref("");
const selectedStatus = ref(null);

const isStatusDialogVisible = ref(false);
const currentActivity = ref({});
const dialogErrors = ref({});

// Función para obtener las ejecuciones del empleado logueado
const fetchMyActivities = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    status: selectedStatus.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/my-cleaning-activities", { params });
    myActivities.value = response.data.data.data;
    totalRecords.value = response.data.data.total;
  } catch (error) {
    console.error("Error al obtener las ejecuciones:", error);
    toast.error("Error al obtener tus actividades programadas.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery, selectedStatus],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchMyActivities(), 300);
  },
  { deep: true }
);

watch([searchQuery, selectedStatus], () => {
  page.value = 1;
});

onMounted(() => {
  fetchMyActivities();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedStatus.value = null;
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

const handleUpdateStatus = (activity) => {
  currentActivity.value = { ...activity };
  dialogErrors.value = {};
  isStatusDialogVisible.value = true;
};

const handleSaveStatus = async (formData) => {
  try {
    // Usar execution_id en lugar de activity_id
    await axios.post(
      `/my-cleaning-activities/${currentActivity.value.execution_id}/status`,
      formData,
      {
        headers: {
          "Content-Type": "multipart/form-data",
        },
      }
    );

    toast.success(
      "Actividad procesada con éxito. Esperando aprobación del supervisor."
    );
    isStatusDialogVisible.value = false;
    await fetchMyActivities();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      dialogErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al actualizar el estado:", error);
      toast.error("Hubo un error al procesar la actividad.");
    }
  }
};

const clearDialogErrors = () => {
  dialogErrors.value = {};
};
</script>

<template>
  <div>
    <MyCleaningActivitiesFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedStatus="selectedStatus"
      :loading="loading"
      @clear="handleClearFilters"
      @sort="handleSort"
    />

    <MyCleaningActivitiesTable
      :my-activities="myActivities"
      :loading="loading"
      :total-records="totalRecords"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @update-status="handleUpdateStatus"
    />

    <UpdateActivityStatusDialog
      v-model="isStatusDialogVisible"
      :activity="currentActivity"
      :errors="dialogErrors"
      @save="handleSaveStatus"
      @clear-errors="clearDialogErrors"
    />
  </div>
</template>
