<script setup>
import GeneralPromotionFilters from "@/components/GeneralPromotionFilters.vue";
import GeneralPromotionTable from "@/components/GeneralPromotionTable.vue";
import GeneralCreateOffer from "@/components/dialogs/GeneralPromotionModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, reactive, ref, watch } from "vue";

// Estados reactivos
const promotionsData = ref([]);
const categories = ref([]);
const loading = ref(false);
const saving = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();
const isOfferDialogVisible = ref(false);
const isEditingMode = ref(false);

const filterSearchQueryId = ref("");
const filterSearchQuery = ref("");

const promoForm = reactive({
  id: null,
  type: "2x1",
  fixed_price: null,
  is_active: true,
  categories: [],
});

const formularioError = reactive({
  id: "",
  type: "",
  fixed_price: "",
  categories: "",
});

const updateTableOptionsOffer = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key;
    orderBy.value = options.sortBy[0].order;
  } else {
    sortBy.value = null;
    orderBy.value = null;
  }
  actualizarTabla();
};

const fetchCategories = async () => {
  try {
    // REQUERIMIENTO: Cargar las categorías de los platos (restaurante)
    const res = await axios.get("/categories", { params: { type: "dishes" } });
    categories.value = res.data;
  } catch (error) {
    console.error("Error al cargar categorías de platos:", error);
    toast.error("Error al cargar las categorías");
  }
};

const handleEditOffer = async (promo) => {
  await fetchCategories();
  Object.assign(promoForm, {
    id: promo.id,
    type: promo.type,
    fixed_price: promo.fixed_price,
    is_active: promo.is_active,
    categories: Array.isArray(promo.categories) ? promo.categories : [],
  });
  Object.keys(formularioError).forEach(key => formularioError[key] = "");
  isOfferDialogVisible.value = true;
  isEditingMode.value = true;
};

const handleClearFilters = () => {
  filterSearchQueryId.value = "";
  filterSearchQuery.value = "";
  sortBy.value = undefined;
  orderBy.value = undefined;
  actualizarTabla();
};

const handleAddPromotionModal = async () => {
  await fetchCategories();
  Object.assign(promoForm, {
    id: null,
    type: "2x1",
    fixed_price: null,
    is_active: true,
    categories: [],
  });
  Object.keys(formularioError).forEach(key => formularioError[key] = "");
  isEditingMode.value = false;
  isOfferDialogVisible.value = true;
};

async function handleDeleteOffer(id) {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "¡No podrás revertir la eliminación de esta Promoción!",
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
    try {
      await axios.delete(`/tpv/promotions/general-promotions/${id}`);
      toast.success("Promoción eliminada correctamente");
      await actualizarTabla();
    } catch (error) {
      toast.error("Error al eliminar la promoción");
      console.error("Error al eliminar:", error);
    }
  }
}

const closePromotionModal = () => {
  isOfferDialogVisible.value = false;
};

function enviar(payload) {
  if (promoForm.id == null) {
    crear(payload);
  } else {
    actualizar(payload);
  }
}

async function crear(data) {
  Object.keys(formularioError).forEach(key => formularioError[key] = "");
  saving.value = true;
  try {
    let res = await axios.post("/tpv/promotions/general-promotions", data);
    if (res.status === 201 || res.status === 200) {
      toast.success("Promoción guardada correctamente");
      isOfferDialogVisible.value = false;
      await actualizarTabla();
    }
  } catch (error) {
    if (error.response?.status === 422) {
      const errors = error.response.data.errors;
      Object.keys(errors).forEach(key => {
        if (formularioError.hasOwnProperty(key)) {
          formularioError[key] = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
        }
      });
      toast.error("Por favor, revisa los errores en el formulario");
    } else {
      toast.error(error.response?.data?.message || "Error al crear la promoción");
    }
  } finally {
    saving.value = false;
  }
}

async function actualizar(data) {
  Object.keys(formularioError).forEach(key => formularioError[key] = "");
  saving.value = true;
  try {
    let res = await axios.put(`/tpv/promotions/general-promotions/${data.id}`, data);
    if (res.status === 200) {
      toast.success("Se guardaron los cambios correctamente");
      isOfferDialogVisible.value = false;
      await actualizarTabla();
    }
  } catch (error) {
    if (error.response?.status === 422) {
      const errors = error.response.data.errors;
      Object.keys(errors).forEach(key => {
        if (formularioError.hasOwnProperty(key)) {
          formularioError[key] = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
        }
      });
      toast.error("Por favor, revisa los errores en el formulario");
    } else {
      toast.error(error.response?.data?.message || "Error al actualizar la promoción");
    }
  } finally {
    saving.value = false;
  }
}

async function actualizarTabla() {
  loading.value = true;
  try {
    const params = {
      page: page.value,
      per_page: itemsPerPage.value,
      search: filterSearchQuery.value,
      search_id: filterSearchQueryId.value,
      sort_by: sortBy.value,
      order_by: orderBy.value
    };

    let res = await axios.get("/tpv/promotions/general-promotions", { params });
    promotionsData.value = res.data.data || res.data;
  } catch (error) {
    toast.error("Error al cargar los datos en la tabla");
    console.error("Error al cargar tabla:", error);
  } finally {
    loading.value = false;
  }
}

watch([filterSearchQueryId, filterSearchQuery], () => {
  page.value = 1;
  actualizarTabla();
});

watch([sortBy, orderBy], () => {
  actualizarTabla();
});

onMounted(async () => {
  await actualizarTabla();
});
</script>

<template>
  <div>
    <GeneralPromotionFilters
      v-model:id-search-query="filterSearchQueryId"
      v-model:search-query="filterSearchQuery"
      @clear="handleClearFilters"
      @add-promotion="handleAddPromotionModal"
    />

    <GeneralPromotionTable
      :promotions="promotionsData.data || promotionsData || []"
      :loading="loading"
      :total-offer="promotionsData.total || (promotionsData ? promotionsData.length : 0)"
      :items-per-page="promotionsData.per_page || 10"
      :page="promotionsData.current_page || 1"
      :categories="categories"
      @update:options="updateTableOptionsOffer"
      @edit-offer="handleEditOffer"
      @delete-offer="handleDeleteOffer"
    />

    <GeneralCreateOffer
      v-model="isOfferDialogVisible"
      :form-data="promoForm"
      :form-errors="formularioError"
      :is-editing="isEditingMode"
      :loading="saving"
      :categories="categories"
      @save="enviar"
      @modal-closed="closePromotionModal"
    />
  </div>
</template>
