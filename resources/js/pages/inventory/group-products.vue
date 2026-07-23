<script setup>
import AddProductsToGroupDialog from "@/components/dialogs/AddProductsToGroupDialog.vue";
import GroupEditDialog from "@/components/dialogs/GroupEditDialog.vue";
import GroupFilters from "@/components/GroupFilters.vue";
import GroupTable from "@/components/GroupTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { onMounted, onUnmounted, ref, watch } from "vue";

const groups = ref([]);
const totalGroups = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();
const isLoadingFilters = ref(false);
const isStrictSearch = ref(false);
const searchQuery = ref("");

const isAddProductsDialogVisible = ref(false);
const isEditDialogVisible = ref(false);
const currentGroup = ref({});
const groupFormErrors = ref({});

const fetchGroups = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    isStrictSearch: isStrictSearch.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/groups", { params });
    groups.value = response.data.data;
    totalGroups.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los grupos:", error);
    toast.error("Error al obtener los grupos.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery, isStrictSearch],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchGroups(), 300);
  },
  { deep: true }
);

watch([searchQuery], () => {
  page.value = 1;
});

onMounted(() => {
  fetchGroups();
});

onUnmounted(() => clearTimeout(debounceTimer));

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const handleEditGroup = (group) => {
  currentGroup.value = { ...group };
  groupFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleAddProducts = (group) => {
  isAddProductsDialogVisible.value = true;
  currentGroup.value = group;
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
    customClass: {
      confirmButton: 'v-btn v-btn--variant-flat v-theme--default bg-error text-white h-auto py-2 px-6 rounded-lg font-weight-black uppercase',
      cancelButton: 'v-btn v-btn--variant-tonal v-theme--default text-secondary h-auto py-2 px-6 rounded-lg font-weight-black uppercase'
    }
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
  isStrictSearch.value = false;
};

const clearFormErrors = () => {
  groupFormErrors.value = {};
};
</script>

<template>
  <div class="inventory-groups-view pb-12">
    <div class="d-flex flex-column gap-3 mt-1">
      <!-- Filtros Premium Estilo Asistente -->
      <GroupFilters
        v-model:searchQuery="searchQuery"
        v-model:isStrictSearch="isStrictSearch"
        :loading="isLoadingFilters"
        @clear="handleClearFilters"
        @add-group="handleAddGroup"
      />

      <!-- Nuevo Sistema de Acordeones Premium -->
      <GroupTable
        :groups="groups"
        :loading="loading"
        :total-groups="totalGroups"
        :items-per-page="itemsPerPage"
        :page="page"
        @update:options="updateTableOptions"
        @add-products="handleAddProducts"
        @edit-group="handleEditGroup"
        @delete-group="handleDeleteGroup"
        @refresh="fetchGroups"
      />

      <!-- Diálogos -->
      <AddProductsToGroupDialog
        v-model="isAddProductsDialogVisible"
        :selected-group="currentGroup"
      />

      <GroupEditDialog
        v-model="isEditDialogVisible"
        :group="currentGroup"
        :errors="groupFormErrors"
        @save="handleSaveGroup"
        @clear-errors="clearFormErrors"
      />
    </div>
  </div>
</template>

<style scoped>
.inventory-groups-view {
  min-height: 100vh;
  background-color: rgba(var(--v-border-color), 0.02);
}
</style>
