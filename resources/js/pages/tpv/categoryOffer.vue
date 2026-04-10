<script setup>
import CategoryOfferFilters from "@/components/CategoryOfferFilters.vue";
import CategoryOfferTable from "@/components/CategoryOfferTable.vue";
import CategoryCreateOffer from "@/components/dialogs/CategoryOfferModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, reactive, ref, watch } from "vue";

// Estados reactivos
const availableCategories = ref([]);
const discount = ref(0);
const offerData = ref({ data: [], total: 0, per_page: 10, current_page: 1 });
const loadingCategories = ref(false);
const pageCategories = ref(1);
const itemsPerPageCategories = ref(10);
const sortByCategories = ref(null);
const orderByCategories = ref(null);
const formularioError = ref({});

// Filtros
const filterSearchQueryIdCategoriesOffer = ref("");
const filterSearchQueryCategoriesOffer = ref("");
const filterIsActive = ref("");

const categoryOfferData = reactive({
  id: null,
  category_id: null,
  discount_percentage: null,
  start_date: "",
  end_date: "",
  is_active: true,
});

const isOfferDialogVisible = ref(false);
const isLoadingDialogData = ref(false);
const currentOfferToEdit = ref(null);
const isEditingMode = ref(false);

const updateTableOptionsCategories = (options) => {
  // Evitar peticiones si los valores no han cambiado realmente (prevención de bucles)
  const isInitialLoad = offerData.value.data.length === 0;
  
  pageCategories.value = options.page;
  itemsPerPageCategories.value = options.itemsPerPage;
  
  if (options.sortBy && options.sortBy.length > 0) {
    sortByCategories.value = options.sortBy[0].key;
    orderByCategories.value = options.sortBy[0].order;
  } else {
    sortByCategories.value = null;
    orderByCategories.value = null;
  }
  
  actualizarTabla();
};

const handleClearFiltersCategoriesOffer = () => {
  filterSearchQueryIdCategoriesOffer.value = "";
  filterSearchQueryCategoriesOffer.value = "";
  filterIsActive.value = "";
  sortByCategories.value = undefined;
  orderByCategories.value = undefined;
  actualizarTabla();
};

const handleEditOffer = async (catOffer) => {
  isLoadingDialogData.value = true;
  try {
    // Cargar categorías si no están cargadas
    if (availableCategories.value.length === 0) {
      const categoriesResponse = await axios.get("/categories");
      availableCategories.value = categoriesResponse.data;
    }
    
    currentOfferToEdit.value = { ...catOffer };
    Object.assign(categoryOfferData, catOffer);
    
    // Convertir fechas al formato correcto
    if (catOffer.start_date) {
      categoryOfferData.start_date = formatDateForInput(catOffer.start_date);
    }
    if (catOffer.end_date) {
      categoryOfferData.end_date = formatDateForInput(catOffer.end_date);
    }
    
    formularioError.value = {};
    isOfferDialogVisible.value = true;
    isEditingMode.value = true;
  } catch (error) {
    console.error("Error al cargar datos para editar:", error);
    toast.error("Error al cargar los datos para editar");
  } finally {
    isLoadingDialogData.value = false;
  }
};

const formatDateForInput = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

const handleAddCategoriesOfferModal = async () => {
  isLoadingDialogData.value = true;
  try {
    const categoriesResponse = await axios.get("/categories");
    availableCategories.value = categoriesResponse.data;

    // Resetear formulario
    Object.assign(categoryOfferData, {
      id: null,
      category_id: null,
      discount_percentage: null,
      start_date: "",
      end_date: "",
      is_active: true,
    });
    
    // Resetear errores
    formularioError.value = {};

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

async function handleDeleteOffer(payload) {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "¡No podrás revertir la eliminación de esta Oferta!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, ¡Eliminar!",
    cancelButtonText: "No, ¡Cancelar!",
    buttonsStyling: false,
    customClass: {
      confirmButton:
        "v-btn v-btn--elevated v-theme--light bg-error v-btn--density-default v-btn--size-default v-btn--variant-elevated",
      cancelButton:
        "v-btn v-theme--light text-secondary v-btn--density-default v-btn--size-default v-btn--variant-outlined mx-2",
    },
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    await eliminar(payload);
  }
}

async function eliminar(id) {
  try {
    await axios.delete(`/tpv/promotions/category/${id}`);
    toast.success("Oferta eliminada correctamente");
    await actualizarTabla();
  } catch (error) {
    toast.error("Error al eliminar la oferta");
    console.error("Error al eliminar:", error);
  }
}

function enviar(payload) {
  if (categoryOfferData.id == null) {
    crear(payload);
  } else {
    actualizar(payload);
  }
}

async function actualizar(data) {
  formularioError.value = {};
  try {
    let respuestaApi = await axios.put(
      `/tpv/promotions/category/${data.id}`,
      data
    );
    if (respuestaApi.status == 200) {
      toast.success("Se guardaron los cambios correctamente");
      isOfferDialogVisible.value = false;
      await actualizarTabla();
    }
  } catch (error) {
    if (error.response?.status === 422) {
      formularioError.value = error.response.data.errors;
      toast.error("Por favor, revisa los errores en el formulario");
    } else {
      const errorMessage = error.response?.data?.message || "Error al actualizar la oferta";
      toast.error(errorMessage);
    }
    console.error("Error al actualizar:", error);
  }
}

async function crear(data) {
  formularioError.value = {};
  try {
    let respuestaApi = await axios.post("/tpv/promotions/category", data);
    if (respuestaApi.status == 201) {
      toast.success("La oferta se ha guardado correctamente");
      isOfferDialogVisible.value = false;
      await actualizarTabla();
    }
  } catch (error) {
    if (error.response?.status === 422) {
      formularioError.value = error.response.data.errors;
      toast.error("Por favor, revisa los errores en el formulario");
    } else {
      const errorMessage = error.response?.data?.message || "Error al crear la oferta";
      toast.error(errorMessage);
    }
    console.error("Error al crear:", error);
  }
}

const closeCategoriesOfferModal = () => {
  isOfferDialogVisible.value = false;
  limpiarForm();
};

function limpiarForm() {
  Object.assign(categoryOfferData, {
    id: null,
    category_id: null,
    discount_percentage: null,
    start_date: "",
    end_date: "",
    is_active: true,
  });
  isEditingMode.value = false;
  isLoadingDialogData.value = false;
  currentOfferToEdit.value = null;
}

async function actualizarTabla() {
  loadingCategories.value = true;
  try {
    const params = {
      page: pageCategories.value,
      per_page: itemsPerPageCategories.value,
      search: filterSearchQueryCategoriesOffer.value,
      search_id: filterSearchQueryIdCategoriesOffer.value,
      is_active: filterIsActive.value,
      sort_by: sortByCategories.value,
      order_by: orderByCategories.value
    };

    let responseApi = await axios.get("/tpv/promotions/category", { params });
    offerData.value = responseApi.data.data;
  } catch (error) {
    toast.error("Error al cargar los datos en la tabla");
    console.error("Error al cargar tabla:", error);
  } finally {
    loadingCategories.value = false;
  }
}

// Watchers para filtros
watch([
  filterSearchQueryIdCategoriesOffer, 
  filterSearchQueryCategoriesOffer,
  filterIsActive
], () => {
  pageCategories.value = 1;
  actualizarTabla();
});

onMounted(async () => {
  // La carga inicial la dispara automáticamente la tabla mediante @update:options
});
</script>

<template>
  <div>
    <CategoryOfferFilters
      v-model:id-search-query="filterSearchQueryIdCategoriesOffer"
      v-model:search-query="filterSearchQueryCategoriesOffer"
      v-model:is-active="filterIsActive"
      :add-offer-loading="isLoadingDialogData"
      @clear="handleClearFiltersCategoriesOffer"
      @add-categories="handleAddCategoriesOfferModal"
    />

    <CategoryOfferTable
      :categories-offer="offerData.data || []"
      :loading="loadingCategories"
      :total-offer="offerData.total || 0"
      :discount="discount"
      :items-per-page="offerData.per_page || 10"
      :page="offerData.current_page || 1"
      @update:options="updateTableOptionsCategories"
      @edit-offer="handleEditOffer"
      @delete-offer="handleDeleteOffer"
    />

    <CategoryCreateOffer
      v-model="isOfferDialogVisible"
      :form-data="categoryOfferData"
      :loading="isLoadingDialogData"
      :categories-data="availableCategories"
      :form-errors="formularioError"
      :is-editing="isEditingMode"
      :category-offer-to-edit="currentOfferToEdit"
      @save="enviar"
      @modal-closed="closeCategoriesOfferModal"
    />
  </div>
</template>
