<script setup>
import FurnitureEditDialog from "@/components/dialogs/FurnitureEditDialog.vue";
import FurnitureFilters from "@/components/FurnitureFilters.vue";
import FurnitureTable from "@/components/FurnitureTable.vue";
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";

import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";

const furniture = ref([]);
const totalFurniture = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const searchQuery = ref("");
const selectedYear = ref(null);
const depreciationFilter = ref(null);
const startDate = ref(null);
const endDate = ref(null);

const acquisitionYears = ref([]);

const isEditDialogVisible = ref(false);
const currentFurniture = ref({});

const furnitureFormErrors = ref({});

const isLoadingFilters = ref(false);

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const currentYear = new Date().getFullYear();
    const years = [];
    for (let year = currentYear; year >= 2010; year--) {
      years.push({ value: year, title: year.toString() });
    }
    acquisitionYears.value = years;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchFurniture = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    acquisitionYear: selectedYear.value,
    ...(depreciationFilter.value !== null && {
      depreciationRange: depreciationFilter.value,
    }),
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    startDate: startDate.value,
    endDate: endDate.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );

  try {
    const response = await axios.get("/furniture", { params });
    furniture.value = response.data.data;
    totalFurniture.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener el mobiliario:", error);
    toast.error("Error al obtener el mobiliario.");
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
    selectedYear,
    depreciationFilter,
    startDate,
    endDate,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchFurniture(), 300);
  },
  { deep: true },
);

watch(
  [searchQuery, selectedYear, depreciationFilter, startDate, endDate],
  () => {
    page.value = 1;
  },
);

onMounted(() => {
  fetchSelectOptions();
  fetchFurniture();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const handleEditFurniture = (item) => {
  currentFurniture.value = { ...item };
  furnitureFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleDeleteFurniture = async (id) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "¡No podrás revertir la eliminación de este mobiliario!",
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
      await axios.delete(`/furniture/${id}`);
      toast.success("Mobiliario eliminado con éxito.");
      fetchFurniture();
    } catch (error) {
      console.error(`Error al borrar el mobiliario ${id}:`, error);
      toast.error("No se pudo eliminar el mobiliario.");
    }
  }
};

const handleSaveFurniture = async (furnitureFormData) => {
  const isNewFurniture = !currentFurniture.value.id;
  const url = isNewFurniture
    ? "/furniture"
    : `/furniture/${currentFurniture.value.id}`;

  try {
    if (isNewFurniture) {
      await axios.post(url, furnitureFormData);
    } else {
      await axios.put(url, furnitureFormData);
    }

    toast.success(
      `Mobiliario ${isNewFurniture ? "creado" : "actualizado"} con éxito`,
    );
    isEditDialogVisible.value = false;
    await fetchFurniture();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      furnitureFormErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al guardar/crear el mobiliario:", error);
      toast.error("Hubo un error al guardar el mobiliario.");
    }
  }
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedYear.value = null;
  depreciationFilter.value = null;
  startDate.value = null;
  endDate.value = null;
};

const handleAddFurniture = () => {
  currentFurniture.value = {};
  furnitureFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const clearFormErrors = () => {
  furnitureFormErrors.value = {};
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
  <div class="furniture-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <FurnitureFilters
        class="mb-6"
        v-model:searchQuery="searchQuery"
        v-model:selectedYear="selectedYear"
        v-model:depreciationFilter="depreciationFilter"
        v-model:startDate="startDate"
        v-model:endDate="endDate"
        :acquisition-years="acquisitionYears"
        :loading="isLoadingFilters"
        @clear="handleClearFilters"
        @add-furniture="handleAddFurniture"
        @sort="handleSort"
      />

      <FurnitureTable
        :furniture="furniture"
        :loading="loading"
        :total-furniture="totalFurniture"
        :items-per-page="itemsPerPage"
        :page="page"
        @update:options="updateTableOptions"
        @edit-furniture="handleEditFurniture"
        @delete-furniture="handleDeleteFurniture"
      />

      <FurnitureEditDialog
        v-model="isEditDialogVisible"
        :furniture="currentFurniture"
        :acquisition-years="acquisitionYears"
        :errors="furnitureFormErrors"
        @save="handleSaveFurniture"
        @clear-errors="clearFormErrors"
      />
    </div>
  </div>
</template>

<style scoped>
.furniture-view {
  background-color: #f8fafc;
  min-block-size: 100vh;
}

.letter-spacing-tight {
  letter-spacing: -0.02em;
}
.letter-spacing-widest {
  letter-spacing: 0.1em !important;
}

.shadow-soft {
  box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 8%) !important;
}
</style>
