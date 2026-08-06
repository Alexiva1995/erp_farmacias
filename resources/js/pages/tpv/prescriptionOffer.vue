<script setup>
import PrescriptionFilters from "@/components/PrescriptionFilters.vue";
import PrescriptionTable from "@/components/PrescriptionTable.vue";
import PrescriptionDetailsModal from "@/components/dialogs/PrescriptionDetailsModal.vue";
import PrescriptionModal from "@/components/dialogs/PrescriptionModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

// Estados reactivos
const prescriptions = ref([]);
const totalPrescriptions = ref(0);
const loadingPrescription = ref(false);
const pagePrescription = ref(1);
const itemsPerPagePrescription = ref(10);
const sortByPrescription = ref("id");
const orderByPrescription = ref("desc");
const filterSearchQueryPrescriptions = ref("");
const filterSearchQueryIdPrescriptions = ref("");
const filterMode = ref("all");

const addPrescriptionModal = ref(false);
const viewPrescriptionModal = ref(false);
const prescriptionData = ref(null);
const selectedPrescription = ref(null);

let searchTimeout = null;

const debouncedFetchPrescriptions = () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }

  searchTimeout = setTimeout(() => {
    fetchPrescriptions();
  }, 400);
};

// Funciones API
const fetchPrescriptions = async () => {
  loadingPrescription.value = true;
  try {
    const params = {
      page: pagePrescription.value,
      per_page: itemsPerPagePrescription.value,
      sort_by: sortByPrescription.value,
      order: orderByPrescription.value,
    };

    if (filterSearchQueryIdPrescriptions.value) {
      params.id = filterSearchQueryIdPrescriptions.value;
    }

    if (filterSearchQueryPrescriptions.value) {
      params.search = filterSearchQueryPrescriptions.value;
    }

    if (filterMode.value === "active") {
      params.is_active = true;
    } else if (filterMode.value === "inactive") {
      params.is_active = false;
    }

    const response = await axios.get("/tpv/promotions/prescription-offer", { params });

    if (response.data.success) {
      prescriptions.value = response.data.data;
      totalPrescriptions.value = response.data.total || response.data.data.length;
    } else {
      console.error("Error obteniendo las ofertas de recetas:", response.data.message);
      toast.error("Error al cargar las ofertas de recetas");
    }
  } catch (error) {
    console.error("Error obteniendo las ofertas de recetas:", error);
    toast.error("Error al cargar las ofertas de recetas");
  } finally {
    loadingPrescription.value = false;
  }
};

const createPrescription = async (prescriptionData) => {
  try {
    const response = await axios.post("/tpv/promotions/prescription-offer", prescriptionData);
    return response.data;
  } catch (error) {
    console.error("Error creating prescription offer:", error);
    throw error;
  }
};

const updatePrescription = async (id, prescriptionData) => {
  try {
    const response = await axios.put(`/tpv/promotions/prescription-offer/${id}`, prescriptionData);
    return response.data;
  } catch (error) {
    console.error("Error actualizando la oferta de receta:", error);
    throw error;
  }
};

const deletePrescription = async (id) => {
  try {
    const response = await axios.delete(`/tpv/promotions/prescription-offer/${id}`);
    return response.data;
  } catch (error) {
    console.error("Error eliminando oferta de receta:", error);
    throw error;
  }
};

const updateTableOptionsPrescription = (options) => {
  pagePrescription.value = options.page;
  itemsPerPagePrescription.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortByPrescription.value = options.sortBy[0].key;
    orderByPrescription.value = options.sortBy[0].order;
  } else {
    sortByPrescription.value = "id";
    orderByPrescription.value = "desc";
  }
  fetchPrescriptions();
};

const handleClearFiltersPrescriptions = () => {
  filterSearchQueryIdPrescriptions.value = "";
  filterSearchQueryPrescriptions.value = "";
  filterMode.value = "all";
  sortByPrescription.value = "id";
  orderByPrescription.value = "desc";
  pagePrescription.value = 1;
  fetchPrescriptions();
};

const handleAddPrescriptionModal = () => {
  prescriptionData.value = null;
  addPrescriptionModal.value = true;
};

const handleViewPrescription = (prescription) => {
  selectedPrescription.value = prescription;
  viewPrescriptionModal.value = true;
};

const handleEditPrescription = (prescription) => {
  prescriptionData.value = prescription;
  addPrescriptionModal.value = true;
};

const handleDeletePrescription = async (prescription) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: `Esta acción eliminará la oferta de receta con ${prescription.discount_percentage}% de descuento. Esta acción no se puede deshacer.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
  });

  if (result.isConfirmed) {
    try {
      const response = await deletePrescription(prescription.id);
      if (response.success) {
        toast.success("Oferta de receta eliminada exitosamente");
        fetchPrescriptions();
      } else {
        toast.error(response.message || "Error al eliminar");
      }
    } catch (error) {
      toast.error("Error al eliminar la oferta de receta");
    }
  }
};

const handlePrescriptionSaved = async (prescriptionData) => {
  try {
    let response;
    if (prescriptionData.id) {
      response = await updatePrescription(prescriptionData.id, prescriptionData);
    } else {
      response = await createPrescription(prescriptionData);
    }

    if (response.success) {
      toast.success(`Oferta de receta ${prescriptionData.id ? "actualizada" : "creada"} exitosamente`);
      fetchPrescriptions();
      closePrescriptionModal();
    } else {
      if (response.errors) {
        const errorMessages = Object.values(response.errors).flat().join(", ");
        toast.error(`Error: ${errorMessages}`);
      } else {
        toast.error(response.message || "Error al guardar");
      }
      throw new Error(response.message);
    }
  } catch (error) {
    console.error("Error saving prescription offer:", error);
    throw error;
  }
};

const closePrescriptionModal = () => {
  addPrescriptionModal.value = false;
  prescriptionData.value = null;
};

const closeViewPrescriptionModal = () => {
  viewPrescriptionModal.value = false;
  selectedPrescription.value = null;
};

watch(
  [
    filterSearchQueryIdPrescriptions,
    filterSearchQueryPrescriptions,
    filterMode,
  ],
  () => {
    pagePrescription.value = 1;
    debouncedFetchPrescriptions();
  }
);

watch(
  [
    pagePrescription,
    itemsPerPagePrescription,
    sortByPrescription,
    orderByPrescription,
  ],
  () => {
    if (searchTimeout) {
      clearTimeout(searchTimeout);
    }
    fetchPrescriptions();
  }
);

onMounted(() => {
  fetchPrescriptions();
});
</script>

<template>
  <div>
    <PrescriptionFilters
      v-model:idSearchQuery="filterSearchQueryIdPrescriptions"
      v-model:searchQuery="filterSearchQueryPrescriptions"
      v-model:mode="filterMode"
      :loading="loadingPrescription"
      @clear="handleClearFiltersPrescriptions"
      @add-prescription="handleAddPrescriptionModal"
    />

    <PrescriptionTable
      :prescriptions="prescriptions"
      :loading="loadingPrescription"
      :total-prescriptions="totalPrescriptions"
      :items-per-page="itemsPerPagePrescription"
      :page="pagePrescription"
      @update:options="updateTableOptionsPrescription"
      @edit-prescription="handleEditPrescription"
      @delete-prescription="handleDeletePrescription"
      @view-prescription="handleViewPrescription"
    />

    <PrescriptionModal
      v-model:is-dialog-visible="addPrescriptionModal"
      :prescription-data="prescriptionData"
      @modal-closed="closePrescriptionModal"
      @prescription-saved="handlePrescriptionSaved"
    />

    <PrescriptionDetailsModal
      v-model:is-dialog-visible="viewPrescriptionModal"
      :prescription-data="selectedPrescription"
    />
  </div>
</template>
