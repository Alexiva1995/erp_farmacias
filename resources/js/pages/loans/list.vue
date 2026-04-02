<script setup>
import LoanEditDialog from "@/components/dialogs/LoanEditDialog.vue";
import LoanFilters from "@/components/LoanFilters.vue";
import LoanTable from "@/components/LoanTable.vue";
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";

import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";

const loans = ref([]);
const totalLoans = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const searchQuery = ref("");
const selectedYear = ref(null);
const statusFilter = ref(null);
const startDate = ref(null);
const endDate = ref(null);

const loanYears = ref([]);

const isEditDialogVisible = ref(false);
const currentLoan = ref({});

const loanFormErrors = ref({});

const isLoadingFilters = ref(false);

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const currentYear = new Date().getFullYear();
    const years = [];
    for (let year = currentYear; year >= 2010; year--) {
      years.push({ value: year, title: year.toString() });
    }
    loanYears.value = years;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchLoans = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    loanYear: selectedYear.value,
    ...(statusFilter.value !== null && {
      status: statusFilter.value,
    }),
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    startDate: startDate.value,
    endDate: endDate.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/loans", { params });
    loans.value = response.data.data;
    totalLoans.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los préstamos:", error);
    toast.error("Error al obtener los préstamos.");
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
    statusFilter,
    startDate,
    endDate,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchLoans(), 300);
  },
  { deep: true }
);

watch([searchQuery, selectedYear, statusFilter, startDate, endDate], () => {
  page.value = 1;
});

onMounted(() => {
  fetchSelectOptions();
  fetchLoans();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const handleEditLoan = (item) => {
  currentLoan.value = { ...item };
  loanFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleDeleteLoan = async (id) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "¡No podrás revertir la eliminación de este préstamo!",
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
      await axios.delete(`/loans/${id}`);
      toast.success("Préstamo eliminado con éxito.");
      fetchLoans();
    } catch (error) {
      console.error(`Error al borrar el préstamo ${id}:`, error);
      toast.error("No se pudo eliminar el préstamo.");
    }
  }
};

const handleSaveLoan = async (loanFormData) => {
  const isNewLoan = !currentLoan.value.id;
  const url = isNewLoan ? "/loans" : `/loans/${currentLoan.value.id}`;

  try {
    if (isNewLoan) {
      await axios.post(url, loanFormData);
    } else {
      await axios.put(url, loanFormData);
    }

    toast.success(`Préstamo ${isNewLoan ? "creado" : "actualizado"} con éxito`);
    isEditDialogVisible.value = false;
    await fetchLoans();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      loanFormErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al guardar/crear el préstamo:", error);
      toast.error("Hubo un error al guardar el préstamo.");
    }
  }
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedYear.value = null;
  statusFilter.value = null;
  startDate.value = null;
  endDate.value = null;
};

const handleAddLoan = () => {
  currentLoan.value = {};
  loanFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const clearFormErrors = () => {
  loanFormErrors.value = {};
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
  <div class="loans-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
    <LoanFilters
      class="mb-6"
      v-model:searchQuery="searchQuery"
      v-model:selectedYear="selectedYear"
      v-model:statusFilter="statusFilter"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      :loan-years="loanYears"
      :loading="isLoadingFilters"
      @clear="handleClearFilters"
      @add-loan="handleAddLoan"
      @sort="handleSort"
    />

    <LoanTable
      :loans="loans"
      :loading="loading"
      :total-loans="totalLoans"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @edit-loan="handleEditLoan"
      @delete-loan="handleDeleteLoan"
    />

    <LoanEditDialog
      v-model="isEditDialogVisible"
      :loan="currentLoan"
      :loan-years="loanYears"
      :errors="loanFormErrors"
      @save="handleSaveLoan"
      @clear-errors="clearFormErrors"
    />
    </div>
  </div>
</template>

<style scoped>
.letter-spacing-tight { letter-spacing: -0.02em; }
.letter-spacing-widest { letter-spacing: 0.1em !important; }
.shadow-soft { box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 8%) !important; }
</style>
