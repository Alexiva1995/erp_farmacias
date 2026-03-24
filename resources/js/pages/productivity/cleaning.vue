<script setup>
import ActivityFilters from "@/components/ActivityFilters.vue";
import ActivityTable from "@/components/ActivityTable.vue";
import ActivityEditDialog from "@/components/dialogs/ActivityEditDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

const activities = ref([]);
const totalActivities = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const searchQuery = ref("");
const selectedFrequency = ref(null);
const isStrictSearch = ref(false);

const frequencies = ref([
  { title: "Diaria", value: "Diaria" },
  { title: "Semanal", value: "Semanal" },
  { title: "Bimestral", value: "Bimestral" },
  { title: "Mensual", value: "Mensual" },
  { title: "Trimestral", value: "Trimestral" },
  { title: "Semestral", value: "Semestral" },
  { title: "Anual", value: "Anual" },
]);

const isEditDialogVisible = ref(false);
const currentActivity = ref({});
const activityFormErrors = ref({});

const fetchActivities = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    frequency: selectedFrequency.value,
    isStrictSearch: isStrictSearch.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/cleaning-activities", { params });
    activities.value = response.data.data;
    totalActivities.value = response.data.total;
  } catch (error) {
    console.error("Error al obtener las actividades:", error);
    toast.error("Error al obtener las actividades.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery, selectedFrequency, isStrictSearch],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchActivities(), 300);
  },
  { deep: true }
);

watch([searchQuery, selectedFrequency, isStrictSearch], () => {
  page.value = 1;
});

onMounted(() => {
  fetchActivities();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const handleEditActivity = (activity) => {
  currentActivity.value = { ...activity };
  activityFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleDeleteActivity = async (id) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "¡No podrás revertir la eliminación de esta actividad!",
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Eliminar",
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
      await axios.delete(`/cleaning-activities/${id}`);
      toast.success("Actividad eliminada con éxito.");
      fetchActivities();
    } catch (error) {
      console.error(`Error al borrar la actividad ${id}:`, error);
      toast.error("No se pudo eliminar la actividad.");
    }
  }
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedFrequency.value = null;
  isStrictSearch.value = false;
};

const handleAddActivity = () => {
  currentActivity.value = {};
  activityFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleSaveActivity = async (activityData) => {
  const isNewActivity = !currentActivity.value.id;
  const url = isNewActivity
    ? "/cleaning-activities"
    : `/cleaning-activities/${currentActivity.value.id}`;

  try {
    if (isNewActivity) {
      await axios.post(url, activityData);
    } else {
      await axios.put(url, activityData);
    }

    toast.success(
      `Actividad ${isNewActivity ? "creada" : "actualizada"} con éxito`
    );
    isEditDialogVisible.value = false;
    await fetchActivities();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      activityFormErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al guardar la actividad:", error);
      toast.error("Hubo un error al guardar la actividad.");
    }
  }
};

const clearFormErrors = () => {
  activityFormErrors.value = {};
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
</script>

<template>
  <div class="productivity-cleaning-page pa-4">
    <ActivityFilters
      v-model:searchQuery="searchQuery"
      v-model:selectedFrequency="selectedFrequency"
      v-model:isStrictSearch="isStrictSearch"
      :frequencies="frequencies"
      :loading="loading"
      @clear="handleClearFilters"
      @add-activity="handleAddActivity"
      @sort="handleSort"
    />

    <ActivityTable
      :activities="activities"
      :loading="loading"
      :total-activities="totalActivities"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @edit-activity="handleEditActivity"
      @delete-activity="handleDeleteActivity"
    />

    <ActivityEditDialog
      v-model="isEditDialogVisible"
      :activity="currentActivity"
      :frequencies="frequencies"
      :errors="activityFormErrors"
      @save="handleSaveActivity"
      @clear-errors="clearFormErrors"
    />
  </div>
</template>

<style scoped>
.productivity-cleaning-page {
  background-color: rgb(var(--v-theme-background));
  min-block-size: 100vh;
}
</style>
