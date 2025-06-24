<script setup>
import GroupEditDialog from "@/components/dialogs/GroupEditDialog.vue";
import GroupFilters from "@/components/GroupFilters.vue";
import GroupTable from "@/components/GroupTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, ref, watch } from "vue";

// --- Estado para los Grupos ---
const groups = ref([]);
const totalGroups = ref(0);
const loading = ref(false);

// --- Estado de Paginación y Ordenación ---
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

// --- Estado de los Filtros (simplificado) ---
const searchQuery = ref("");

// --- Estado del Modal de Edición ---
const isEditDialogVisible = ref(false);
const currentGroup = ref({});
const groupFormErrors = ref({});

// --- Lógica de la API ---
const fetchGroups = async () => {
  loading.value = true;
  // Parámetros simplificados para la API de grupos
  const params = {
    q: searchQuery.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    // A futuro, cambiarás "/groups" por tu endpoint real
    const response = await axios.get("/groups", { params });
    groups.value = response.data.data;
    totalGroups.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los grupos:", error);
    toast.error("Error al obtener los grupos.");
    // --- Datos de ejemplo si la API falla ---
    // groups.value = [
    //   { id: 1, name: "Analgésicos" },
    //   { id: 2, name: "Antibióticos" },
    // ];
    // totalGroups.value = 2;
  } finally {
    loading.value = false;
  }
};

// --- Watchers para recargar datos ---
let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchGroups(), 300);
  },
  { deep: true }
);

watch(searchQuery, () => {
  page.value = 1;
});

onMounted(() => {
  fetchGroups();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

// --- Manejadores de Eventos (CRUD) ---
const handleEditGroup = (group) => {
  currentGroup.value = { ...group };
  groupFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleDeleteGroup = async (id) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "¡No podrás revertir la eliminación de este grupo!",
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Eliminar",
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/groups/${id}`);
      toast.success("Grupo eliminado con éxito.");
      fetchGroups();
    } catch (error) {
      console.error(`Error al borrar el grupo ${id}:`, error);
      toast.error("No se pudo eliminar el grupo.");
    }
  }
};

const handleSaveGroup = async (groupFormData) => {
  const isNew = !currentGroup.value.id;
  const url = isNew ? "/groups" : `/groups/${currentGroup.value.id}`;

  const method = isNew ? "post" : "put";

  try {
    await axios[method](url, groupFormData);

    toast.success(`Grupo ${isNew ? "creado" : "actualizado"} con éxito`);
    isEditDialogVisible.value = false;
    await fetchGroups();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      groupFormErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al guardar el grupo:", error);
      toast.error("Hubo un error al guardar el grupo.");
    }
  }
};

const handleAddGroup = () => {
  currentGroup.value = {};
  groupFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleClearFilters = () => {
  searchQuery.value = "";
};

const clearFormErrors = () => {
  groupFormErrors.value = {};
};
</script>

<template>
  <div>
    <GroupFilters
      v-model:searchQuery="searchQuery"
      @clear="handleClearFilters"
      @add-group="handleAddGroup"
    />

    <GroupTable
      :groups="groups"
      :loading="loading"
      :total-groups="totalGroups"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @edit-group="handleEditGroup"
      @delete-group="handleDeleteGroup"
    />

    <GroupEditDialog
      v-model="isEditDialogVisible"
      :group="currentGroup"
      :errors="groupFormErrors"
      @save="handleSaveGroup"
      @clear-errors="clearFormErrors"
    />
  </div>
</template>
