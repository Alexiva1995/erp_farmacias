<script setup>
import ExpirationOfferFilters from "@/components/ExpirationOfferFilters.vue";
import ExpirationOfferTable from "@/components/ExpirationOfferTable.vue";
import ExpirationCreateOffer from "@/components/dialogs/ExpirationOfferModal.vue";
import axios from "@/plugins/axios";
import Swal from "sweetalert2";
import { toast } from "@/plugins/sweetalert";

// Datos y estado
const availableProductLots = ref([]);
const productExpirationOfferData = ref([]);
const totalExpirations = ref(0);
const loadingExpirations = ref(false);
const pageExpirations = ref(1);
const itemsPerPageExpirations = ref(10);
const sortByExpirations = ref('created_at');
const orderByExpirations = ref('desc');
const filterSearchQueryExpirationsOffer = ref('');
const filterStatusExpirationsOffer = ref('');
const filterMonthsExpirationsOffer = ref('');

// Estados de Modales
const isOfferDialogVisible = ref(false);
const isLoadingDialogData = ref(false);
const currentOfferToEdit = ref(null);
const isEditingMode = ref(false);

// Obtener ofertas
const fetchExpirationOffers = async () => {
  loadingExpirations.value = true;
  
  try {
    const params = {
      q: filterSearchQueryExpirationsOffer.value,
      page: pageExpirations.value,
      itemsPerPage: itemsPerPageExpirations.value,
      sortBy: sortByExpirations.value,
      orderBy: orderByExpirations.value,
      is_active: filterStatusExpirationsOffer.value,
      months: filterMonthsExpirationsOffer.value,
    };

    Object.keys(params).forEach(
      (key) => (params[key] === null || params[key] === "") && delete params[key]
    );

    const response = await axios.get("/tpv/promotions/expiration-offer", { params });
    
    if (response.data.success) {
      productExpirationOfferData.value = response.data.data;
      totalExpirations.value = response.data.total;
    } else {
      throw new Error(response.data.message);
    }
  } catch (error) {
    console.error("Error al obtener ofertas:", error);
    toast.error("Error al obtener la lista de ofertas.");
  } finally {
    loadingExpirations.value = false;
  }
};

// Obtener lotes disponibles para nueva oferta
const fetchAvailableProductLots = async () => {
  isLoadingDialogData.value = true;
  
  try {
    const response = await axios.get("/tpv/promotions/expiration-offer/available-product-lots");
    availableProductLots.value = response.data.data || [];
    //isEditingMode.value = false;
    //currentOfferToEdit.value = null;
    isOfferDialogVisible.value = true;
    
  } catch (error) {
    console.error("Error al obtener lotes:", error);
    toast.error("Error al obtener lotes disponibles.");
  } finally {
    isLoadingDialogData.value = false;
  }
};

// Manejar creación/edición
const handleSaveOffer = async (offerData) => {
  try {
    
    // VERIFICAR QUE TENEMOS EL ID EN MODO EDICIÓN
    if (isEditingMode.value && (!currentOfferToEdit.value || !currentOfferToEdit.value.id)) {
      console.error('ERROR: No hay ID de oferta para editar');
      toast.error('Error: No se puede identificar la oferta a editar');
      return;
    }
    
    const url = isEditingMode.value 
      ? `/tpv/promotions/expiration-offer/${currentOfferToEdit.value.id}`
      : '/tpv/promotions/expiration-offer';
    
    const method = isEditingMode.value ? 'put' : 'post';
    
    const response = await axios[method](url, offerData);
    
    if (response.data.success) {
      toast.success(response.data.message);
      closeExpirationOfferModal();
      fetchExpirationOffers();
    } else {
      throw new Error(response.data.message);
    }
  } catch (error) {
    console.error('Error al guardar oferta:', error);
    console.error('Respuesta del error:', error.response?.data);
    
    const errorMessage = error.response?.data?.message || "Error al guardar la oferta";
    toast.error(errorMessage);
  }
};

// Manejar eliminación
const handleDeleteOffer = async (offerId) => {
  const result = await Swal.fire({
    title: '¿Estás seguro?',
    text: "Esta acción no se puede deshacer",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.delete(`/tpv/promotions/expiration-offer/${offerId}`);
      if (response.data.success) {
        toast.success(response.data.message);
        fetchExpirationOffers();
      }
    } catch (error) {
      console.error("Error al eliminar oferta:", error);
      toast.error("Error al eliminar la oferta");
    }
  }
};

// Editar oferta
const handleEditOffer = (offer) => {
  
  // Asegurarnos de que tenemos el ID
  if (!offer.id) {
    console.error('ERROR: La oferta no tiene ID');
    toast.error('Error: No se puede editar la oferta sin ID');
    return;
  }
  
  currentOfferToEdit.value = {
    id: offer.id,
    months_to_expiration: parseInt(offer.months_to_expiration),
    discount_percentage: parseFloat(offer.discount_percentage),
    is_active: Boolean(offer.is_active),
    product_lots: offer.product_lots || [],
    product_lots_count: offer.product_lots_count || 0
  };
  
  isEditingMode.value = true;
  
  // Cargar lotes disponibles
  fetchAvailableProductLots();
  isOfferDialogVisible.value = true;
};

// Cerrar modal
const closeExpirationOfferModal = () => {
  isOfferDialogVisible.value = false;
  currentOfferToEdit.value = null;
  isEditingMode.value = false;
};

// Actualizar opciones de tabla
const updateTableOptionsExpiration = (options) => {
  pageExpirations.value = options.page;
  itemsPerPageExpirations.value = options.itemsPerPage;
  
  if (options.sortBy && options.sortBy.length > 0) {
    sortByExpirations.value = options.sortBy[0].key;
    orderByExpirations.value = options.sortBy[0].order;
  }
  
  fetchExpirationOffers();
};

// Limpiar filtros
const handleClearFiltersExpirationOffer = () => {
  filterSearchQueryExpirationsOffer.value = "";
  filterStatusExpirationsOffer.value = "";
  filterMonthsExpirationsOffer.value = "";
  fetchExpirationOffers();
};

// Cargar datos iniciales
onMounted(() => {
  fetchExpirationOffers();
});
</script>

<template>
  <div>
    <ExpirationOfferFilters
      v-model:search-query="filterSearchQueryExpirationsOffer"
      v-model:status="filterStatusExpirationsOffer"
      v-model:months="filterMonthsExpirationsOffer"
      :loading="loadingExpirations"
      :add-offer-loading="isLoadingDialogData"
      @clear="handleClearFiltersExpirationOffer"
      @add-expiration-offer="fetchAvailableProductLots"
      @search="fetchExpirationOffers"
    />

    <VCard title="Ofertas por Caducidad">
      <VCardText>
        <ExpirationOfferTable
          :offers="productExpirationOfferData"
          :loading="loadingExpirations"
          :items-per-page="itemsPerPageExpirations"
          :page="pageExpirations"
          :total="totalExpirations"
          @update:options="updateTableOptionsExpiration"
          @edit-offer="handleEditOffer"
          @delete-offer="handleDeleteOffer"
        />
      </VCardText>
    </VCard>

    <ExpirationCreateOffer
      v-model="isOfferDialogVisible"
      :loading="isLoadingDialogData"
      :is-editing="isEditingMode"
      :offer-to-edit="currentOfferToEdit"
      :available-product-lots="availableProductLots"
      @save="handleSaveOffer"
      @modal-closed="closeExpirationOfferModal"
    />
  </div>
</template>
