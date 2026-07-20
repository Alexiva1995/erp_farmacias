<script setup>
import CompanyOfferFilters from "@/components/CompanyOfferFilters.vue";
import CompanyOfferTable from "@/components/CompanyOfferTable.vue";
import CompanyCreateOffer from "@/components/dialogs/CompanyOfferModal.vue";
import CompanyViewOffer from "@/components/dialogs/CompanyViewOffer.vue";
import axios from "@/plugins/axios";
import Swal from "sweetalert2";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";

// Estados reactivos
const companiesOfferData = ref([]);
const totalCompanies = ref(0);
const loadingCompanies = ref(false);

// Filtros de paginacion
const filterSearchQueryIdCompaniesOffer = ref("");
const filterSearchQueryCompaniesOffer = ref("");
const pageCompanies = ref(1);
const itemsPerPageCompanies = ref(10);
const sortByCompanies = ref(null);
const orderByCompanies = ref(null);

// Estados reactivos del modal
const availableCompanies = ref([]);
const isOfferDialogVisible = ref(false);
const isViewOfferDialogVisible = ref(false);
const isLoadingDialogData = ref(false);
const currentOfferToEdit = ref(null);
const currentOfferToView = ref(null);
const isEditingMode = ref(false);

// Obtener las ofertas de empresas
const fetchCompaniesOffers = async () => {
  loadingCompanies.value = true;
  try {
    const params = {
      page: pageCompanies.value,
      items_per_page: itemsPerPageCompanies.value,
      ...(filterSearchQueryCompaniesOffer.value && { search: filterSearchQueryCompaniesOffer.value }),
      ...(filterSearchQueryIdCompaniesOffer.value && { search: filterSearchQueryIdCompaniesOffer.value }),
      ...(sortByCompanies.value && { sort_by: sortByCompanies.value }),
      ...(orderByCompanies.value && { order_by: orderByCompanies.value }),
    };

    const response = await axios.get("/tpv/promotions/company-offer", { params });
    
    companiesOfferData.value = response.data.data.map(offer => ({
      ...offer,
      company_name: offer.company?.name || 'N/A',
      scales: offer.scales || []
    }));
    
    totalCompanies.value = response.data.total;
    
  } catch (error) {
    console.error("Error al obtener las ofertas de empresas:", error);
    toast.error("Error al cargar las ofertas");
  } finally {
    loadingCompanies.value = false;
  }
};

/*const fetchCompaniesOffers = async () => {
  loadingCompanies.value = true;
  try {
    const params = {
      page: pageCompanies.value,
      items_per_page: itemsPerPageCompanies.value,
      ...(filterSearchQueryCompaniesOffer.value && {
        search: filterSearchQueryCompaniesOffer.value,
      }),
      ...(filterSearchQueryIdCompaniesOffer.value && {
        search: filterSearchQueryIdCompaniesOffer.value,
      }),
      ...(sortByCompanies.value && { sort_by: sortByCompanies.value }),
      ...(orderByCompanies.value && { order_by: orderByCompanies.value }),
    };

    const response = await axios.get("/tpv/promotions/company-offer", { params });

    companiesOfferData.value = response.data.data.map((offer) => ({
      ...offer,
      company_name: offer.company?.name || "N/A",
      scales: offer.scales || [],
    }));

    totalCompanies.value = response.data.total;
  } catch (error) {
    console.error("Error al obtener las ofertas de empresas:", error);
    toast.error("Error al cargar las ofertas");
  } finally {
    loadingCompanies.value = false;
  }
};*/

// Obtener las empresas disponibles
const fetchAvailableCompanies = async () => {
  try {
    const response = await axios.get("/crm/companies");

    availableCompanies.value = response.data.data;
  } catch (error) {
    console.error("Error al obtener las empresas:", error);
    toast.error("Error al cargar las empresas");
  }
};

// Manejo de las opciones de datos de la tabla
const updateTableOptionsCompanies = (options) => {
  pageCompanies.value = options.page;
  itemsPerPageCompanies.value = options.itemsPerPage;

  if (options.sortBy && options.sortBy.length > 0) {
    sortByCompanies.value = options.sortBy[0].key;
    orderByCompanies.value = options.sortBy[0].order;
  } else {
    sortByCompanies.value = null;
    orderByCompanies.value = null;
  }
};

// Limpieza de los filtros
const handleClearFiltersCompaniesOffer = () => {
  filterSearchQueryIdCompaniesOffer.value = "";
  filterSearchQueryCompaniesOffer.value = "";
  sortByCompanies.value = undefined;
  orderByCompanies.value = undefined;
  pageCompanies.value = 1;
};

// Agregar una nueva oferta
const handleAddCompaniesOfferModal = async () => {
  isLoadingDialogData.value = true;
  try {
    await fetchAvailableCompanies();
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

// Visualizar una oferta

const handleViewOffer = async (offer) => {
  isLoadingDialogData.value = true;
  currentOfferToView.value = { ...offer };
  console.log('el valor de offer es: ', currentOfferToView);
  isViewOfferDialogVisible.value = true;
  isLoadingDialogData.value = false;
};

// Editar una oferta
const handleEditOffer = async (offer) => {
  isLoadingDialogData.value = true;
  try {
    await fetchAvailableCompanies();

    isEditingMode.value = true;
    currentOfferToEdit.value = { ...offer };
    isOfferDialogVisible.value = true;
  } catch (error) {
    console.error("Error loading offer for edit:", error);
    toast.error("Error al cargar la oferta para editar");
  } finally {
    isLoadingDialogData.value = false;
  }
};

// Recalcular una Oferta
const handleRecalculateOffer = async (offer) => {
  const result = await Swal.fire({
    title: "¿Recalcular Estado?",
    text: `Se analizarán las ventas acumuladas de ${offer.company_name} vinculadas a esta oferta para determinar si debe permanecer activa.`,
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "rgb(var(--v-theme-primary))",
    cancelButtonColor: "rgb(var(--v-theme-secondary))",
    confirmButtonText: "Sí, recalcular",
    cancelButtonText: "Cancelar",
    customClass: {
      confirmButton: 'rounded-lg',
      cancelButton: 'rounded-lg'
    }
  });

  if (result.isConfirmed) {
    loadingCompanies.value = true;
    try {
      const response = await axios.post(`/tpv/promotions/company-offer/${offer.id}/recalculate`);
      
      if (response.data.success) {
        const { total_sales, min_required, is_active } = response.data;
        
        await Swal.fire({
          title: "Recálculo Exitoso",
          html: `
            <div class="text-start">
              <p><b>Ventas Acumuladas:</b> ${formatCurrency(total_sales, 'USD')}</p>
              <p><b>Mínimo Requerido:</b> ${formatCurrency(min_required, 'USD')}</p>
              <p><b>Nuevo Estado:</b> <span class="badge ${is_active ? 'badge-success' : 'badge-danger'}">${is_active ? 'ACTIVA' : 'INACTIVA'}</span></p>
            </div>
          `,
          icon: is_active ? "success" : "warning",
          confirmButtonText: "Entendido",
          customClass: {
            confirmButton: 'rounded-lg'
          }
        });
        
        fetchCompaniesOffers();
      } else {
        toast.error(response.data.message || "Error al recalcular");
      }
    } catch (error) {
      console.error("Error recalculating offer:", error);
      toast.error(error.response?.data?.message || "Error al comunicarse con el servidor");
    } finally {
      loadingCompanies.value = false;
    }
  }
};

// Eliminar una Oferta
const handleDeleteOffer = async (offer) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: `Esta acción eliminará la oferta de ${offer.company_name}. Esta acción no se puede deshacer.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/tpv/promotions/company-offer/${offer.id}`);
      toast.success("Oferta eliminada exitosamente");
      fetchCompaniesOffers();
    } catch (error) {
      console.error("Error deleting offer:", error);
      toast.error("Error al eliminar la oferta");
    }
  }
};

// Cerrar modal de creacion o actualizacion
const closeCompaniesOfferModal = () => {
  isOfferDialogVisible.value = false;
  currentOfferToEdit.value = null;
  isEditingMode.value = false;
};

// Cerrar modal de visualizacion
const closeViewOfferModal = () => {
  isViewOfferDialogVisible.value = false;
  currentOfferToView.value = null;
};

// Guardar una oferta
const handleOfferSaved = () => {
  fetchCompaniesOffers(); // Refresh table
  closeCompaniesOfferModal();
};

// Watchers para los filtros de paginacion
watch(
  [
    () => filterSearchQueryCompaniesOffer.value,
    () => filterSearchQueryIdCompaniesOffer.value,
    () => pageCompanies.value,
    () => itemsPerPageCompanies.value,
    () => sortByCompanies.value,
    () => orderByCompanies.value
  ],
  () => {
    fetchCompaniesOffers();
  },
  { immediate: false }
);

onMounted(() => {
  fetchCompaniesOffers();
});
</script>

<template>
  <div>
    <CompanyOfferFilters
      v-model:id-search-query="filterSearchQueryIdCompaniesOffer"
      v-model:search-query="filterSearchQueryCompaniesOffer"
      :add-offer-loading="isLoadingDialogData"
      @clear="handleClearFiltersCompaniesOffer"
      @add-companies="handleAddCompaniesOfferModal"
    />

    <CompanyOfferTable
      :companies="companiesOfferData"
      :loading="loadingCompanies"
      :items-per-page="itemsPerPageCompanies"
      :page="pageCompanies"
      :total-companies="totalCompanies"
      @update:options="updateTableOptionsCompanies"
      @view-offer="handleViewOffer"
      @edit-offer="handleEditOffer"
      @delete-offer="handleDeleteOffer"
      @recalculate-offer="handleRecalculateOffer"
    />

    <CompanyCreateOffer
      v-model="isOfferDialogVisible"
      :loading="isLoadingDialogData"
      :companies-data="availableCompanies"
      :is-editing="isEditingMode"
      :companies-offer-to-edit="currentOfferToEdit"
      @modal-closed="closeCompaniesOfferModal"
      @saved="handleOfferSaved"
    />

    <CompanyViewOffer
      v-model="isViewOfferDialogVisible"
      :offer-data="currentOfferToView"
      :loading="isLoadingDialogData"
      @modal-closed-view="closeViewOfferModal"
    />
  </div>
</template>
