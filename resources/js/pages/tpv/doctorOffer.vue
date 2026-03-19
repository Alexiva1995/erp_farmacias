<script setup>
import DoctorOfferFilters from "@/components/DoctorOfferFilters.vue";
import DoctorOfferTable from "@/components/DoctorOfferTable.vue";
import DoctorOfferModal from "@/components/dialogs/DoctorOfferModal.vue";
import DoctorViewOffer from "@/components/dialogs/DoctorViewOffer.vue";
import axios from "@/plugins/axios";
import Swal from "sweetalert2";
import { toast } from "@/plugins/sweetalert";
import { ref, onMounted, computed, watch } from "vue";

// Datos reactivos
const doctorsOfferData = ref([]);
const availableDoctors = ref([]);
const loadingDoctors = ref(false);
const pageDoctors = ref(1);
const itemsPerPageDoctors = ref(10);
const totalDoctors = ref(0);
const filterSearchQueryIdDoctorsOffer = ref("");
const filterSearchQueryDoctorsOffer = ref("");
const sortByDoctors = ref("id");
const orderByDoctors = ref("desc");

// Estados de Modales
const isOfferDialogVisible = ref(false);
const isViewOfferDialogVisible = ref(false);
const isLoadingDialogData = ref(false);
const currentOfferToEdit = ref(null);
const currentOfferToView = ref(null);
const isEditingMode = ref(false);

// Computed para los parámetros de búsqueda
const searchParams = computed(() => {
  const params = {
    page: pageDoctors.value,
    per_page: itemsPerPageDoctors.value,
    sort_by: sortByDoctors.value,
    sort_order: orderByDoctors.value,
  };

  const searchParts = [filterSearchQueryIdDoctorsOffer.value, filterSearchQueryDoctorsOffer.value].filter(Boolean);
  if (searchParts.length) {
    params.search = searchParts.join(" ").trim();
  }

  return params;
});

// Función para cargar las ofertas
const loadDoctorOffers = async () => {
  loadingDoctors.value = true;
  try {
    const response = await axios.get("/tpv/promotions/doctor-offer", {
      params: searchParams.value,
    });

    if (response.data.success) {
      doctorsOfferData.value = response.data.data;
      totalDoctors.value = response.data.total;
    } else {
      throw new Error("Error al cargar las ofertas");
    }
  } catch (error) {
    console.error("Error al cargar la ofertas de medicos:", error);
    toast.error("Error al cargar las ofertas");
  } finally {
    loadingDoctors.value = false;
  }
};

// Función para cargar médicos disponibles
const loadAvailableDoctors = async () => {
  try {
    const response = await axios.get("/crm/doctors");
    availableDoctors.value = response.data.data || [];
  } catch (error) {
    console.error("Error loading doctors:", error);
    toast.error("Error al cargar la lista de médicos");
  }
};

// Actualizar opciones de la tabla
const updateTableOptionsDoctors = (options) => {
  pageDoctors.value = options.page;
  itemsPerPageDoctors.value = options.itemsPerPage;
  
  if (options.sortBy && options.sortBy.length > 0) {
    sortByDoctors.value = options.sortBy[0].key;
    orderByDoctors.value = options.sortBy[0].order;
  } else {
    sortByDoctors.value = "id";
    orderByDoctors.value = "desc";
  }
  
  loadDoctorOffers();
};

// Limpiar filtros
const handleClearFiltersDoctorsOffer = () => {
  filterSearchQueryIdDoctorsOffer.value = "";
  filterSearchQueryDoctorsOffer.value = "";
  sortByDoctors.value = "id";
  orderByDoctors.value = "desc";
  pageDoctors.value = 1;
};

// Abrir modal para crear oferta
const handleAddDoctorsOfferModal = async () => {
  isLoadingDialogData.value = true;
  try {
    await loadAvailableDoctors();
    isEditingMode.value = false;
    currentOfferToEdit.value = null;
    isOfferDialogVisible.value = true;
  } catch (error) {
    console.error("Error al obtener datos para el modal:", error);
    toast.error("No se pudieron cargar los datos para crear la oferta.");
  } finally {
    isLoadingDialogData.value = false;
  }
};

// Abrir modal para editar oferta
const handleEditDoctorOffer = async (offer) => {
  isLoadingDialogData.value = true;
  try {
    await loadAvailableDoctors();
    isEditingMode.value = true;
    isOfferDialogVisible.value = true;
    currentOfferToEdit.value = { ...offer };
  } catch (error) {
    console.error("Error al cargar los datos:", error);
    toast.error("Error al cargar los datos de la oferta");
  } finally {
    isLoadingDialogData.value = false;
  }
};

// Ver detalles de la oferta
const handleViewDoctorOffer = async (offer) => {
  isLoadingDialogData.value = true;
  currentOfferToView.value = { ...offer };
  isViewOfferDialogVisible.value = true;
  isLoadingDialogData.value = false;
};

// Eliminar oferta
const handleDeleteDoctorOffer = async (offer) => {
  const result = await Swal.fire({
    title: '¿Estás seguro?',
    text: `Esta acción eliminará la oferta #${offer.id} permanentemente`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#ff4d4f',
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.delete(`/tpv/promotions/doctor-offer/${offer.id}`);
      
      if (response.data.success) {
        toast.success('Oferta eliminada exitosamente');
        loadDoctorOffers(); // Recargar la lista
      } else {
        throw new Error(response.data.message);
      }
    } catch (error) {
      console.error("Error al eliminar oferta:", error);
      toast.error(error.response?.data?.message || 'Error al eliminar la oferta');
    }
  }
};

// Guardar oferta (crear o actualizar)

const handleSaveDoctorOffer = async () => {
  await loadDoctorOffers(); // Refresh table
  closeDoctorsOfferModal();
};

// Cerrar modal
const closeDoctorsOfferModal = () => {
  isOfferDialogVisible.value = false;
  currentOfferToEdit.value = null;
  isEditingMode.value = false;
};

// Cerrar modal de Visualizacion
const closeViewOfferModal = () => {
  isViewOfferDialogVisible.value = false;
  currentOfferToView.value = null;
};

// Watchers para recargar datos cuando cambien los filtros
watch(
  [() => filterSearchQueryIdDoctorsOffer.value, () => filterSearchQueryDoctorsOffer.value],
  () => {
    pageDoctors.value = 1;
    loadDoctorOffers();
  },
  { immediate: false }
);


watch([pageDoctors, itemsPerPageDoctors, sortByDoctors, orderByDoctors], () => {
  loadDoctorOffers();
  },
  { immediate: false }
);

// Cargar datos iniciales
onMounted(async () => {
  await loadDoctorOffers();
});
</script>

<template>
  <div>
    <DoctorOfferFilters
      v-model:id-search-query="filterSearchQueryIdDoctorsOffer"
      v-model:search-query="filterSearchQueryDoctorsOffer"
      :add-offer-loading="isLoadingDialogData"
      @clear="handleClearFiltersDoctorsOffer"
      @add-doctors="handleAddDoctorsOfferModal"
    />

    <DoctorOfferTable
      :doctors-offer="doctorsOfferData"
      :loading="loadingDoctors"
      :items-per-page="itemsPerPageDoctors"
      :page="pageDoctors"
      :totaldoctors="totalDoctors"
      @update:options="updateTableOptionsDoctors"
      @view="handleViewDoctorOffer"
      @edit="handleEditDoctorOffer"
      @delete="handleDeleteDoctorOffer"
    />

    <DoctorOfferModal
      v-model="isOfferDialogVisible"
      :loading="isLoadingDialogData"
      :doctors-data="availableDoctors"
      :is-editing="isEditingMode"
      :doctors-offer-to-edit="currentOfferToEdit"
      @modal-closed="closeDoctorsOfferModal"
      @saved="handleSaveDoctorOffer"
    />
  </div>
  <DoctorViewOffer
      v-model="isViewOfferDialogVisible"
      :offer-data="currentOfferToView"
      :loading="isLoadingDialogData"
      @modal-closed-view="closeViewOfferModal"
    />
</template>
