<script setup>
import MarketOpportunitiesDesktopTable from "@/components/suppliers/market-opportunities/MarketOpportunitiesDesktopTable.vue";
import MarketOpportunitiesFilters from "@/components/suppliers/market-opportunities/MarketOpportunitiesFilters.vue";
import MarketOpportunitiesMobileCards from "@/components/suppliers/market-opportunities/MarketOpportunitiesMobileCards.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const laboratories = ref([]);
const productosSelect = ref([]);
const suppliers = ref([]);
const loading = ref(false);
const exportLoading = ref(false);
const submittingItems = ref({});

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref([{ key: "saving_percentage", order: "desc" }]);
const totalItems = ref(0);
const items = ref([]);

const selectedLaboratory = ref([]);
const selectProducts = ref([]);
const excludeSupplierIds = ref([]);
const withDiscount = ref(false);
const hideRedundant = ref(true);
const hideDuplicates = ref(true);
const isColombia = ref(null);
const tipoFiltracion = ref("combinado");
const lapsoTiempo = ref("3 month");
const stockFilter = ref("all");
const searchQuery = ref("");
const searchQueryDebounced = ref("");

let debounceTimeout = null;
watch(searchQuery, (newValue) => {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    searchQueryDebounced.value = newValue;
  }, 400);
});

const headers = [
  { title: "ID", key: "product_id", sortable: true, width: "80px" },
  { title: "Producto", key: "product_name_inventory", sortable: true, minWidth: "350px" },
  { title: "Histórico", key: "historic_costs", align: "end", sortable: false },
  { title: "OFERTA", key: "unit_cost_usd", align: "end", sortable: true },
  { title: "% Ahorro", key: "saving_percentage", align: "end", sortable: true },
  { title: "vent.", key: "total_sold_completed", align: "end", sortable: true },
  { title: "stock", key: "lote_quantity", align: "end", sortable: true },
  { title: "AO", key: "totalQuantityInAutoOrder", align: "end", sortable: true },
  { title: "Promedio", key: "promedio_calculado", align: "end", sortable: true },
  {
    title: "Añadir",
    key: "actions",
    sortable: false,
    align: "center",
    width: "150px",
  },
];

async function fetchOpportunities() {
  loading.value = true;
  try {
    const params = {
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value[0]?.key,
      orderBy: sortBy.value[0]?.order,
      q: searchQueryDebounced.value,
      laboratoryId: selectedLaboratory.value,
      productId: selectProducts.value,
      excludeSupplierIds: excludeSupplierIds.value,
      withDiscount: withDiscount.value,
      hideRedundant: hideRedundant.value,
      hideDuplicates: hideDuplicates.value,
      is_colombia: isColombia.value,
      tipo_filtracion: tipoFiltracion.value,
      lapso_de_tiempo: lapsoTiempo.value,
      stock: stockFilter.value,
    };

    const response = await axios.get("/market-opportunities", { params });
    items.value = response.data.data;
    totalItems.value = response.data.meta.total;
  } catch (error) {
    console.error("Error al cargar oportunidades:", error);
  } finally {
    loading.value = false;
  }
}

async function fetchInitialData() {
  Promise.all([
    axios.get("/laboratories"),
    axios.get("/suppliers-ia-assistant-report/consult-products"),
    axios.get("/suppliers", { params: { itemsPerPage: 500 } })
  ]).then(([resLabs, resProds, resSups]) => {
    laboratories.value = resLabs.data;
    productosSelect.value = (resProds.data.data || []).map((p) => ({
      name: `${p.id} - ${p.name}`,
      id: p.id,
    }));
    suppliers.value = resSups.data?.data || resSups.data || [];
  }).catch(err => {
    console.error("Error al cargar catálogos iniciales:", err);
  });
}

const handleClearFilters = () => {
  selectedLaboratory.value = [];
  selectProducts.value = [];
  excludeSupplierIds.value = [];
  searchQuery.value = "";
  searchQueryDebounced.value = "";
  hideRedundant.value = true;
  hideDuplicates.value = true;
  isColombia.value = null;
  tipoFiltracion.value = "combinado";
  lapsoTiempo.value = "3 month";
  stockFilter.value = "all";
};

const handleAddUnits = async (item) => {
  if (!item.quantity_to_add || item.quantity_to_add <= 0) {
    toast.error("Ingresa una cantidad válida.");
    return;
  }

  submittingItems.value[item.id] = true;
  try {
    const data = {
      product_id: item.product_id,
      quantity: item.quantity_to_add,
      supplier_id: item.supplier_id,
      product_supplier_id: item.id,
      unit_cost: item.unit_cost_usd
    };

    const response = await axios.post("/suppliers-ia-order-assistant/add-to-order", data);
    
    toast.success(response.data.message || "Producto añadido a la orden.");
    
    items.value = items.value.filter(i => i.id !== item.id);
    totalItems.value -= 1;
  } catch (error) {
    console.error("Error al añadir a la orden:", error);
    toast.error(error.response?.data?.message || "Error al procesar el pedido.");
  } finally {
    delete submittingItems.value[item.id];
  }
};

const exportExcel = async () => {
  exportLoading.value = true;
  try {
    const params = {
      q: searchQueryDebounced.value,
      laboratoryId: selectedLaboratory.value,
      productId: selectProducts.value,
      excludeSupplierIds: excludeSupplierIds.value,
      withDiscount: withDiscount.value,
      hideRedundant: hideRedundant.value,
      hideDuplicates: hideDuplicates.value,
      is_colombia: isColombia.value,
      tipo_filtracion: tipoFiltracion.value,
      lapso_de_tiempo: lapsoTiempo.value,
      stock: stockFilter.value,
    };

    const response = await axios.get("/market-opportunities/export", {
      params,
      responseType: "blob",
    });

    const blob = new Blob([response.data], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `oportunidades_mercado_${new Date().toISOString().slice(0, 10)}.xlsx`;
    link.click();
    URL.revokeObjectURL(link.href);

    toast.success("Exportación completada con éxito.");
  } catch (error) {
    console.error("Error al exportar:", error);
    toast.error("Ocurrió un error al exportar el archivo.");
  } finally {
    exportLoading.value = false;
  }
};

watch(
  [page, itemsPerPage, sortBy, selectedLaboratory, selectProducts, excludeSupplierIds, searchQueryDebounced, withDiscount, hideRedundant, hideDuplicates, isColombia, tipoFiltracion, lapsoTiempo, stockFilter],
  () => {
    fetchOpportunities();
  },
  { deep: true },
);

onMounted(() => {
  fetchInitialData();
  fetchOpportunities();
});
</script>

<template>
  <div class="market-opportunities-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <!-- Subcomponente de Filtros -->
      <MarketOpportunitiesFilters
        v-model:search-query="searchQuery"
        v-model:selected-laboratory="selectedLaboratory"
        v-model:select-products="selectProducts"
        v-model:exclude-supplier-ids="excludeSupplierIds"
        v-model:tipo-filtracion="tipoFiltracion"
        v-model:lapso-tiempo="lapsoTiempo"
        v-model:stock-filter="stockFilter"
        v-model:with-discount="withDiscount"
        v-model:hide-redundant="hideRedundant"
        v-model:hide-duplicates="hideDuplicates"
        :laboratories="laboratories"
        :productos-select="productosSelect"
        :suppliers="suppliers"
        :export-loading="exportLoading"
        @clear="handleClearFilters"
        @export="exportExcel"
      />

      <!-- Contenedor Principal de la Tabla / Tarjetas -->
      <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface mt-2">
        <!-- Vista Desktop -->
        <div class="d-none d-md-block">
          <MarketOpportunitiesDesktopTable
            v-model:items-per-page="itemsPerPage"
            v-model:page="page"
            v-model:sort-by="sortBy"
            :headers="headers"
            :items="items"
            :total-items="totalItems"
            :loading="loading"
            :submitting-items="submittingItems"
            @fetch="fetchOpportunities"
            @add-units="handleAddUnits"
          />
        </div>

        <!-- Vista Móvil -->
        <div class="d-md-none">
          <MarketOpportunitiesMobileCards
            v-model:page="page"
            :loading="loading"
            :items="items"
            :total-items="totalItems"
            :items-per-page="itemsPerPage"
            :submitting-items="submittingItems"
            @add-units="handleAddUnits"
          />
        </div>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
.market-opportunities-view {
  background-color: rgb(var(--v-theme-background));
  min-block-size: 100vh;
}
</style>
