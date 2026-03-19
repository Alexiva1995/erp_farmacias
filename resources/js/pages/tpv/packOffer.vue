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

// Cambiar estado del pack (Activo/Inactivo)
const handleToggleStatus = async (pack) => {
  try {
    const newStatus = !pack.is_active;
    const response = await updatePack(pack.id, { ...pack, is_active: newStatus });
    
    if (response && response.success) {
      toast.success(`Pack ${newStatus ? 'activado' : 'desactivado'} correctamente`);
      await fetchPacks();
    } else {
      toast.error(response?.message || "Error al cambiar el estado");
    }
  } catch (error) {
    toast.error("Error al comunicarse con el servidor");
  }
};

// Eliminar Pack
const handleDeletePack = async (pack) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: `Esta acción eliminará la oferta de ${pack.name}. Esta acción no se puede deshacer.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "rgb(var(--v-theme-primary))",
    cancelButtonColor: "rgb(var(--v-theme-error))",
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
    customClass: {
      confirmButton: 'rounded-lg',
      cancelButton: 'rounded-lg'
    }
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
    console.log("Guardando pack con datos:", packData);
    
    // Validar que packData tenga los datos necesarios
    if (!packData.name || !packData.name.trim()) {
      toast.error("El nombre del pack es requerido");
      return;
    }
    
    if (!packData.pack_config || Object.keys(packData.pack_config).length === 0) {
      toast.error("Debe agregar al menos un producto al pack");
      return;
    }
    
    if (!packData.total_price || packData.total_price <= 0) {
      toast.error("El precio total del pack debe ser mayor a 0");
      return;
    }
    
    let response;
    if (packData.id) {
      console.log("Actualizando pack:", packData.id);
      response = await updatePack(packData.id, packData);
    } else {
      console.log("Creando nuevo pack");
      response = await createPack(packData);
    }

    console.log("Respuesta del servidor:", response);

    if (response && response.success) {
      toast.success(
        `Pack ${packData.id ? "actualizado" : "creado"} exitosamente`
      );
      await fetchPacks();
      closePackModal();
    } else {
      const errorMessage = response?.message || "Error al guardar el pack";
      if (response?.errors) {
        const errorMessages = Object.values(response.errors).flat().join(", ");
        toast.error(`Error: ${errorMessages}`);
      } else {
        toast.error(errorMessage);
      }
    }
  } catch (error) {
    console.error("Error saving pack:", error);
    const errorMessage = error.response?.data?.message || error.message || "Error al guardar el pack";
    
    if (error.response?.data?.errors) {
      const errorMessages = Object.values(error.response.data.errors).flat().join(", ");
      toast.error(`Error: ${errorMessages}`);
    } else {
      toast.error(errorMessage);
    }
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
  <div class="pa-2">
    <PacksFilters
      v-model:idSearchQuery="filterSearchQueryIdPacks"
      v-model:searchQuery="filterSearchQueryPacks"
      :loading="loadingPack"
      @clear="handleClearFiltersPacks"
      @add-pack="handleAddPackModal"
    />

    <VCard class="elevation-1 rounded-lg border-0 overflow-hidden mt-6">
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
