<script setup>
import IndividualOfferFilters from "@/components/IndividualOfferFilters.vue";
import IndividualOfferTable from "@/components/IndividualOfferTable.vue";
import IndividualCreateOffer from "@/components/dialogs/IndividualOfferModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, reactive, ref, watch } from "vue";

// Estados reactivos
const productDataOffer = ref([]);
const discount = ref(0);
const loadingProduct = ref(false);
const pageProduct = ref(1);
const itemsPerPageProduct = ref(10);
const sortByProduct = ref();
const orderByProduct = ref();
const isOfferDialogVisible = ref(false);
const currentOfferToEdit = ref(null);

// Filtros
const filterSearchQueryIdIndivOffer = ref("");
const filterSearchQueryIndivOffer = ref("");

const currentIndvOffer = reactive({
  id: null,
  product_id: null,
  discount_percent: "",
  start_date: "",
  end_date: "",
});

const isEditingMode = ref(false);

const updateTableOptionsOffer = (options) => {
  pageProduct.value = options.page;
  itemsPerPageProduct.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortByProduct.value = options.sortBy[0].key;
    orderByProduct.value = options.sortBy[0].order;
  } else {
    sortByProduct.value = null;
    orderByProduct.value = null;
  }
  actualizarTabla();
};

const formularioError = reactive({
  id: "",
  product_id: "",
  discount_percent: "",
  start_date: "",
  end_date: "",
});

// Editar oferta
const handleEditOffer = (indvOffer) => {
  currentOfferToEdit.value = { ...indvOffer };
  Object.assign(currentIndvOffer, indvOffer);
  formularioError.value = {};
  isOfferDialogVisible.value = true;
  isEditingMode.value = true;
};

// Limpiar filtros
const handleClearFiltersIndivOffer = () => {
  filterSearchQueryIdIndivOffer.value = "";
  filterSearchQueryIndivOffer.value = "";
  sortByProduct.value = undefined;
  orderByProduct.value = undefined;
  actualizarTabla();
};

const handleAddIndividualOfferModal = () => {
  Object.assign(currentIndvOffer, {
    id: null,
    product_id: null,
    discount_percent: "",
    start_date: "",
    end_date: "",
  });
  isEditingMode.value = false;
  currentOfferToEdit.value = null;
  isOfferDialogVisible.value = true;
};

// Manejar eliminación
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
    await axios.delete(`/tpv/promotions/individual/${id}`);
    toast.success("Oferta eliminada correctamente");
    await actualizarTabla();
  } catch (error) {
    toast.error("Error al eliminar la oferta");
    console.error("Error al eliminar:", error);
  }
}

// Cerrar modal
const closeIndividualOfferModal = () => {
  isOfferDialogVisible.value = false;
  limpiarForm();
};

// Limpiar datos
function limpiarForm() {
  Object.assign(currentIndvOffer, {
    id: null,
    product_id: null,
    discount_percent: "",
    start_date: "",
    end_date: "",
  });
  isEditingMode.value = false;
  currentOfferToEdit.value = null;
}

function enviar(payload) {
  if (currentIndvOffer.id == null) {
    crear(payload);
  } else {
    actualizar(payload);
  }
}

// Crear oferta
async function crear(data) {
  try {
    let respuestaApi = await axios.post("/tpv/promotions/individual", data);
    if (respuestaApi.status == 201) {
      toast.success("La oferta se ha guardado correctamente");
      isOfferDialogVisible.value = false;
      await actualizarTabla();
    }
  } catch (error) {
    toast.error(error.response?.data?.message || "Error al crear la oferta");
    console.error("Error al crear:", error);
  }
}

// Actualizacion de la tabla con las ofertas agregadas
async function actualizarTabla() {
  loadingProduct.value = true;
  try {
    const params = {
      page: pageProduct.value,
      per_page: itemsPerPageProduct.value,
      search: filterSearchQueryIndivOffer.value,
      search_id: filterSearchQueryIdIndivOffer.value,
      sort_by: sortByProduct.value,
      order_by: orderByProduct.value
    };

    let responseApi = await axios.get("/tpv/promotions/individual", { params });
    productDataOffer.value = responseApi.data.data;
  } catch (error) {
    toast.error("Error al cargar los datos en la tabla");
    console.error("Error al cargar tabla:", error);
  } finally {
    loadingProduct.value = false;
  }
}

async function actualizar(data) {
  try {
    let respuestaApi = await axios.put(
      `/tpv/promotions/individual/${data.id}`,
      data
    );
    if (respuestaApi.status == 200) {
      toast.success("Se guardaron los cambios correctamente");
      isOfferDialogVisible.value = false;
      await actualizarTabla();
    }
  } catch (error) {
    toast.error(error.response?.data?.message || "Error al actualizar la oferta");
    console.error("Error al actualizar:", error);
  }
}

// Watchers para filtros
watch([filterSearchQueryIdIndivOffer, filterSearchQueryIndivOffer], () => {
  pageProduct.value = 1;
  actualizarTabla();
});

watch([sortByProduct, orderByProduct], () => {
  actualizarTabla();
});

onMounted(async () => {
  await actualizarTabla();
});
</script>

<template>
  <div>
    <IndividualOfferFilters
      v-model:id-search-query="filterSearchQueryIdIndivOffer"
      v-model:search-query="filterSearchQueryIndivOffer"
      @clear="handleClearFiltersIndivOffer"
      @add-product="handleAddIndividualOfferModal"
    />

    <IndividualOfferTable
      :products-offer="productDataOffer.data || []"
      :loading="loadingProduct"
      :total-offer="productDataOffer.total || 0"
      :discount="discount"
      :items-per-page="productDataOffer.per_page || 10"
      :page="productDataOffer.current_page || 1"
      @update:options="updateTableOptionsOffer"
      @edit-offer="handleEditOffer"
      @delete-offer="handleDeleteOffer"
    />

    <IndividualCreateOffer
      v-model="isOfferDialogVisible"
      :form-data="currentIndvOffer"
      :form-errors="formularioError"
      :is-editing="isEditingMode"
      :product-offer-to-edit="currentOfferToEdit"
      @save="enviar"
      @modal-closed="closeIndividualOfferModal"
    />
  </div>
</template>
