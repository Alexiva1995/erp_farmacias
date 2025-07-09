<script setup>
import SupplierFilters from "@/components/SupplierFilters.vue";
import SupplierTable from "@/components/SupplierTable.vue";
import SupplierEditDialog from "@/components/dialogs/SupplierEditDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const suppliers = ref([]);
const totalSupplier = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();
const searchQuery = ref("");

const currentSupplier = ref({});
const supplierFormErrors = ref({});
const isEditDialogVisible = ref(false);

const fetchSuppliers = async () => {
  loading.value = true;
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
    const response = await axios.get("/suppliers", { params });
    suppliers.value = response.data.data;
    totalSupplier.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los proveedores:", error);
    toast.error("Error al obtener los proveedores.");
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchSuppliers();
});

const handleClearFilters = () => {
  searchQuery.value = "";
  sortBy.value = undefined;
  orderBy.value = undefined;
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const handleAddSupplier = () => {
  currentSupplier.value = {};
  supplierFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleSaveSupplier = async (supplierFormData) => {
    const isNewSupplier = !currentSupplier.value.id;
    const url = isNewSupplier
    ? "/suppliers"
    : `/suppliers/${currentSupplier.value.id}`;

    try {
        if (!isNewSupplier) {
            supplierFormData.append("_method", "PUT");
        }

        await axios.post(url, supplierFormData);

        toast.success(
            `Proveedor ${isNewSupplier ? "creado" : "actualizado"} con éxito`
        );
        isEditDialogVisible.value = false;
        await fetchSuppliers();
    } catch (error) {
        if (error.response && error.response.status === 422) {
            supplierFormErrors.value = error.response.data.errors;
            toast.error("Por favor, corrige los errores en el formulario.");
        } else {
            console.error("Error al guardar/crear el proveedor:", error);
            toast.error("Hubo un error al guardar el proveedor.");
        }
    }
};

const clearFormErrors = () => {
  supplierFormErrors.value = {};
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchSuppliers(), 300);
  },
  { deep: true }
);

watch([searchQuery], () => {
  page.value = 1;
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};
</script>

<template>
  <div>
    <SupplierFilters
      v-model:searchQuery="searchQuery"
      @clear="handleClearFilters"
      @sort="handleSort"
      @add-supplier="handleAddSupplier"
    />

    <SupplierTable
      :suppliers="suppliers"
      :loading="loading"
      :total-supplier="totalSupplier"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
    />

    <SupplierEditDialog
      v-model="isEditDialogVisible"
      :supplier="currentSupplier"
      :errors="supplierFormErrors"
      @save="handleSaveSupplier"
      @clear-errors="clearFormErrors"
    />
  </div>
</template>
