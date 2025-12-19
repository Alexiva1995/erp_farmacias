<script setup>
import PackTable from "@/components/PackTable.vue";
import PacksFilters from "@/components/PacksFilters.vue";
import PackDetailsModal from "@/components/dialogs/PackDetailsModal.vue";
import PackModal from "@/components/dialogs/PackModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref } from "vue";

// Estados reactivos
const packs = ref([]);
const totalPacks = ref(0);
const loadingPack = ref(false);
const pagePack = ref(1);
const itemsPerPagePack = ref(10);
const sortByPack = ref("id");
const orderByPack = ref("desc");
const filterSearchQueryPacks = ref("");
const filterSearchQueryIdPacks = ref("");

const addPackModal = ref(false);
const viewPackModal = ref(false);
const packData = ref(null);
const selectedPack = ref(null);

// Funciones API
const fetchPacks = async () => {
  loadingPack.value = true;
  try {
    const params = {
      page: pagePack.value,
      per_page: itemsPerPagePack.value,
      sort_by: sortByPack.value,
      order: orderByPack.value,
      search: filterSearchQueryPacks.value,
      search_id: filterSearchQueryIdPacks.value,
    };

    // Eliminar parámetros vacíos
    Object.keys(params).forEach((key) => {
      if (
        params[key] === "" ||
        params[key] === null ||
        params[key] === undefined
      ) {
        delete params[key];
      }
    });

    const response = await axios.get("/tpv/promotions/product-packs", {
      params,
    });

    if (response.data.success) {
      packs.value = response.data.data;
      totalPacks.value = response.data.total;
    } else {
      console.error("Error obteniendo los packs:", response.data.message);
      toast.error("Error al cargar los packs", "error");
    }
  } catch (error) {
    console.error("Error obteniendo los packs:", error);
    toast.error("Error al cargar los packs", "error");
  } finally {
    loadingPack.value = false;
  }
};

// Creacion de un nuevo pack
const createPack = async (packData) => {
  try {
    const response = await axios.post(
      "/tpv/promotions/product-packs",
      packData
    );
    return response.data;
  } catch (error) {
    console.error("Error al crear el pack:", error);
    throw error;
  }
};

// Actualizacion de un pack
const updatePack = async (id, packData) => {
  try {
    const response = await axios.put(
      `/tpv/promotions/product-packs/${id}`,
      packData
    );
    return response.data;
  } catch (error) {
    console.error("Error actualizando el pack:", error);
    throw error;
  }
};

// Eliminar un pack
const deletePack = async (id) => {
  try {
    const response = await axios.delete(
      `/tpv/promotions/product-packs/${id}`,
      id
    );
    return response.data;
  } catch (error) {
    console.error("Error eliminando pack:", error);
    throw error;
  }
};

// Actualizar opciones de paginacion de la tabla
const updateTableOptionsPack = (options) => {
  pagePack.value = options.page;
  itemsPerPagePack.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortByPack.value = options.sortBy[0].key;
    orderByPack.value = options.sortBy[0].order;
  } else {
    sortByPack.value = null;
    orderByPack.value = null;
  }
  fetchPacks();
};

// Limpiar filtros
const handleClearFiltersPacks = () => {
  filterSearchQueryIdPacks.value = "";
  filterSearchQueryPacks.value = "";
  sortByPack.value = "id";
  orderByPack.value = "desc";
  pagePack.value = 1;
  fetchPacks();
};

// Cambiar estados al crear un pack
const handleAddPackModal = () => {
  packData.value = null;
  addPackModal.value = true;
};

// Cambiar estados al visualizar un pack
const handleViewPack = (pack) => {
  selectedPack.value = pack;
  viewPackModal.value = true;
};

// Cambiar estados al actualizar un pack
const handleEditPack = (pack) => {
  packData.value = pack;
  addPackModal.value = true;
};

// Eliminar Pack
const handleDeletePack = async (pack) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: `Esta acción eliminará la oferta de ${pack.name}. Esta acción no se puede deshacer.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
  });

  if (result.isConfirmed) {
    try {
      const response = await deletePack(pack.id);
      if (response.success) {
        toast.success("Pack eliminado exitosamente");
        fetchPacks();
      } else {
        toast.error(response.message, "error");
      }
    } catch (error) {
      toast.error("Error al eliminar el pack", "error");
    }
  }
};

// Guardar un pack
const handlePackSaved = async (packData) => {
  try {
    let response;
    if (packData.id) {
      response = await updatePack(packData.id, packData);
    } else {
      response = await createPack(packData);
    }

    if (response.success) {
      toast.success(
        `Pack ${packData.id ? "actualizado" : "creado"} exitosamente`
      );
      fetchPacks();
      closePackModal();
    } else {
      if (response.errors) {
        const errorMessages = Object.values(response.errors).flat().join(", ");
        toast.error(`Error: ${errorMessages}`, "error");
      } else {
        toast.error(response.message, "error");
      }
      throw new Error(response.message);
    }
  } catch (error) {
    console.error("Error saving pack:", error);
    throw error;
  }
};

const closePackModal = () => {
  addPackModal.value = false;
  packData.value = null;
};

// Función para cerrar el modal de detalles
const closeViewPackModal = () => {
  viewPackModal.value = false;
  selectedPack.value = null;
};

watch([filterSearchQueryIdPacks, filterSearchQueryPacks], () => {
  pagePack.value = 1;
  fetchPacks();
});
// Cargar packs al montar el componente
onMounted(() => {
  fetchPacks();
});
</script>

<template>
  <div>
    <PacksFilters
      v-model:idSearchQuery="filterSearchQueryIdPacks"
      v-model:searchQuery="filterSearchQueryPacks"
      :loading="loadingPack"
      @clear="handleClearFiltersPacks"
      @add-pack="handleAddPackModal"
    />

    <VCard title="Packs de Productos">
      <div class="mb-2"></div>
      <PackTable
        :packs="packs"
        :loading="loadingPack"
        :total-packs="totalPacks"
        :items-per-page="itemsPerPagePack"
        :page="pagePack"
        @update:options="updateTableOptionsPack"
        @edit-pack="handleEditPack"
        @delete-pack="handleDeletePack"
        @view-pack="handleViewPack"
        @toggle-status="handleToggleStatus"
      />
    </VCard>

    <PackModal
      v-model:is-dialog-visible="addPackModal"
      :pack-data="packData"
      @modal-closed="closePackModal"
      @pack-saved="handlePackSaved"
    />

    <PackDetailsModal
      v-model:is-dialog-visible="viewPackModal"
      :pack="selectedPack"
    />
  </div>
</template>
