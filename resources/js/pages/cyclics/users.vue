<script setup>
import InventoryCountDialog from "@/components/dialogs/InventoryCountDialog.vue";
import InvoiceToCountTable from "@/components/InvoiceToCountTable.vue";
import ProductFilters from "@/components/ProductFilters.vue";
import ProductTable from "@/components/ProductTable.vue";
import { useDataTable } from "@/composables/useDataTable";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, reactive, ref } from "vue";

const filters = reactive({
  q: "",
  laboratoryId: null,
  originId: null,
  hasStock: null,
  startDate: null,
  endDate: null,
});

const {
  items: products,
  totalItems: totalProduct,
  loading: productLoading,
  options: productOptions,
  fetchData: fetchProducts,
  updateTableOptions: updateProductTableOptions,
} = useDataTable("/inventory/products", filters);

const {
  items: invoiceProductsToCount,
  totalItems: totalInvoiceProductsToCount,
  loading: invoiceProductsLoading,
  options: invoiceProductsOptions,
  fetchData: fetchInvoiceProductsToCount,
  updateTableOptions: updateInvoiceProductsTableOptions,
} = useDataTable("/inventory/count/invoice-details-to-count", filters);

const laboratories = ref([]);
const origins = ref([]);
const isLoadingFilters = ref(false);

const isCountDialogVisible = ref(false);
const currentProduct = ref({});
const hasActiveCycle = ref(false);
const countType = ref("product");

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse, cycleResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
      axios.get("/inventory/cycle/active"),
    ]);

    laboratories.value = labResponse.data.data;
    origins.value = originResponse.data;

    if (cycleResponse.data.success) {
      hasActiveCycle.value = cycleResponse.data.has_active_cycle;
      if (!hasActiveCycle.value) {
        toast.warning(
          "No existe un ciclo de inventario activo. Los conteos no podrán ser registrados."
        );
      }
    }
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

onMounted(() => {
  fetchSelectOptions();
  fetchProducts();
  fetchInvoiceProductsToCount();
});

const handleCountProduct = (product, type) => {
  if (!hasActiveCycle.value) {
    toast.error(
      "No se puede realizar el conteo. No existe un ciclo de inventario activo."
    );
    return;
  }

  countType.value = type;
  currentProduct.value = { ...product };
  isCountDialogVisible.value = true;
};

const handleSaveCount = async (countData) => {
  const productId = currentProduct.value.id;
  const endpoint =
    countType.value === "invoice"
      ? `/inventory/count/invoice-count/${productId}`
      : `/inventory/count/${productId}`;

  try {
    const response = await axios.post(endpoint, {
      barcode: countData.barcode,
      counted_quantity: countData.countedQuantity,
      system_quantity: countData.system_quantity,
      discrepancy: countData.discrepancy,
    });

    if (response.data.success) {
      toast.success(response.data.message || "Conteo registrado exitosamente");
      isCountDialogVisible.value = false;

      if (countType.value === "invoice") {
        await fetchInvoiceProductsToCount();
      } else {
        await fetchProducts();
      }
    } else {
      toast.error(response.data.message || "Error al registrar el conteo");
    }
  } catch (error) {
    console.error("Error al registrar el conteo:", error);
    if (error.response?.status === 422) {
      const errors = error.response.data.errors;
      const errorMessages = Object.values(errors).flat().join(", ");
      toast.error(`Errores de validación: ${errorMessages}`);
    } else if (error.response?.status === 400) {
      toast.error(error.response.data.message);
    } else {
      toast.error("Hubo un error al registrar el conteo.");
    }
  }
};

const handleClearFilters = () => {
  filters.q = "";
  filters.laboratoryId = null;
  filters.originId = null;
  filters.hasStock = null;
  filters.startDate = null;
  filters.endDate = null;
};
</script>

<template>
  <div>
    <ProductFilters
      v-model:searchQuery="filters.q"
      v-model:selectedLaboratory="filters.laboratoryId"
      v-model:selectedOrigin="filters.originId"
      v-model:stockStatusFilter="filters.hasStock"
      v-model:startDate="filters.startDate"
      v-model:endDate="filters.endDate"
      :laboratories="laboratories"
      :origins="origins"
      :loading="isLoadingFilters"
      mode="inventory"
      @clear="handleClearFilters"
    />

    <VRow class="mt-4">
      <VCol cols="12">
        <h5 class="text-h5 mb-2">Productos Pendientes de Conteo</h5>
        <ProductTable
          :products="products"
          :loading="productLoading"
          :total-product="totalProduct"
          :items-per-page="productOptions.itemsPerPage"
          :page="productOptions.page"
          mode="inventory"
          @update:options="updateProductTableOptions"
          @count-product="(product) => handleCountProduct(product, 'product')"
        />
      </VCol>

      <VCol cols="12">
        <h5 class="text-h5 mb-2">Productos de Factura por Contar</h5>
        <InvoiceToCountTable
          :products="invoiceProductsToCount"
          :loading="invoiceProductsLoading"
          :total-product="totalInvoiceProductsToCount"
          :items-per-page="invoiceProductsOptions.itemsPerPage"
          :page="invoiceProductsOptions.page"
          mode="inventory"
          @update:options="updateInvoiceProductsTableOptions"
          @count-product="(product) => handleCountProduct(product, 'invoice')"
        />
      </VCol>
    </VRow>

    <InventoryCountDialog
      v-model="isCountDialogVisible"
      :product="currentProduct"
      @save="handleSaveCount"
    />
  </div>
</template>
