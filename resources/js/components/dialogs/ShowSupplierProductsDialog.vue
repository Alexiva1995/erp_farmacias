<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  selectedSupplier: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modalValue"]);

const page = ref(1);
const itemsPerPage = ref(10);
const products = ref([]);
const totalProducts = ref(0);
const loading = ref(false);

const closeDialog = () => {
  emit("update:modelValue", false);
};

watch(
  () => props.selectedSupplier,
  (selectedSupplier) => {
    if (selectedSupplier?.id) {
      page.value = 1;
      fetchSupplierProducts(selectedSupplier.id);
    }
  },
  { deep: true, immediate: true },
);

watch([page, itemsPerPage], () => {
  if (props.selectedSupplier?.id) {
    fetchSupplierProducts(props.selectedSupplier.id);
  }
});

const productsHeaders = [
  { title: "ID", key: "id", sortable: false },
  { title: "Nombre", key: "name", sortable: false },
  { title: "Laboratorio", key: "laboratory", sortable: false },
  { title: "Coste (BS)", key: "unit_cost", sortable: false },
  { title: "Coste (Usd)", key: "unit_cost_usd", sortable: false },
];

const formatBs = (amount) => {
  return (
    new Intl.NumberFormat("es-VE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount) + " Bs."
  );
};
const formatUsd = (amount) => {
  return (
    new Intl.NumberFormat("es-VE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount) + " $"
  );
};

const fetchSupplierProducts = async (id) => {
  try {
    loading.value = true;
    const { data } = await axios.get(`/suppliers/${id}/products`, {
      params: {
        page: page.value,
        perPage: itemsPerPage.value,
      },
    });
    products.value = data.data;
    totalProducts.value = data.total;
  } catch (error) {
    console.error(error);
    toast.error("Error al obtener los productos del proveedor.");
  } finally {
    loading.value = false;
  }
};

const formatDate = (dateString) => {
  if (!dateString || dateString === "No se ha establecido conexión") return "N/A";
  try {
    const date = new Date(dateString);
    const year = date.getUTCFullYear();
    const month = (date.getUTCMonth() + 1).toString().padStart(2, "0");
    const day = date.getUTCDate().toString().padStart(2, "0");
    return `${year}-${month}-${day}`;
  } catch (error) {
    return "Fecha inválida";
  }
};

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard class="d-flex flex-column">
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold">Ver Productos</span>

        <VSpacer />

        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VSheet color="#f5f5f5" variant="tonal" rounded="lg" class="pa-4">
        <VRow>
          <VCol cols="6">
            <div class="d-flex align-center gap-4 mb-4">
              <span class="font-weight-medium">Proveedor</span>
              <VChip color="primary" label>{{ selectedSupplier.name }}</VChip>
              <VSpacer />
            </div>
          </VCol>
          <VCol cols="6">
            <div class="d-flex align-center gap-4 mb-4">
              <span class="font-weight-medium">Última actualización</span>
              <VChip color="primary" label>{{ formatDate(selectedSupplier.last_connection) }}</VChip>
              <VSpacer />
            </div>
          </VCol>
        </VRow>

        <VDataTableServer
          :headers="productsHeaders"
          :items="products"
          :loading="loading"
          density="compact"
          class="mt-4 rounded-lg"
          no-data-text="Este proveedor no tiene productos registrados."
          :items-per-page="itemsPerPage"
          :page="page"
          :server-items-length="totalProducts"
          :items-length="totalProducts"
          @update:options="updateTableOptions"
        >
          <template #item.unit_cost="{ item }">
            <span>{{ formatBs(item.unit_cost) }}</span>
          </template>
          <template #item.unit_cost_usd="{ item }">
            <span>{{ formatUsd(item.unit_cost_usd) }}</span>
          </template>
        </VDataTableServer>
      </VSheet>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn color="secondary" variant="outlined" @click="closeDialog" class="flex-grow-1 w-0 mr-4"> Cerrar </VBtn>
        <VBtn color="primary" variant="flat" @click="closeDialog" class="flex-grow-1 w-0"> Aceptar </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
