<script setup>
import InventoryCorrectionModal from "@/components/dialogs/InventoryCorrectionModal.vue";
import LotDistributionModal from "@/components/dialogs/LotDistributionModal.vue";
import InventoryCycleFilters from "@/components/InventoryCycleFilters.vue";
import InvoiceCyclicTable from "@/components/InvoiceCyclicTable.vue";
import ProductCyclicTable from "@/components/ProductCyclicTable.vue";
import SaleCyclicTable from "@/components/SaleCyclicTable.vue";
import { useCyclicTable } from "@/composables/useCyclicTable";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, reactive, ref } from "vue";

const filters = reactive({
  searchQuery: "",
  selectedLaboratory: null,
  discrepancyFilter: null,
  sortBy: undefined,
  orderBy: undefined,
});

const {
  items: productCounts,
  totalItems: totalProductCount,
  loading: productLoading,
  options: productOptions,
  updateTableOptions: updateProductOptions,
  showCorrectionModal: showProductCorrectionModal,
  selectedItemForCorrection: productForCorrection,
  showLotDistributionModal: showProductLotModal,
  itemForLotDistribution: productForLotDistribution,
  targetQuantityForDistribution: productTargetQuantity,
  handleApproveItem: handleApproveProduct,
  handleRejectItem: handleRejectProduct,
  handleCorrectionProcessed: handleProductCorrection,
  handleLotsDistributed: handleProductLots,
} = useCyclicTable("/products", filters);

const {
  items: invoiceCounts,
  totalItems: totalInvoiceCount,
  loading: invoiceLoading,
  options: invoiceOptions,
  updateTableOptions: updateInvoiceOptions,
  showCorrectionModal: showInvoiceCorrectionModal,
  selectedItemForCorrection: invoiceForCorrection,
  showLotDistributionModal: showInvoiceLotModal,
  itemForLotDistribution: invoiceForLotDistribution,
  targetQuantityForDistribution: invoiceTargetQuantity,
  handleApproveItem: handleApproveInvoice,
  handleRejectItem: handleRejectInvoice,
  handleCorrectionProcessed: handleInvoiceCorrection,
  handleLotsDistributed: handleInvoiceLots,
} = useCyclicTable("inventory/count/invoices", filters);

const {
  items: saleCounts,
  totalItems: totalSaleCount,
  loading: saleLoading,
  options: saleOptions,
  updateTableOptions: updateSaleOptions,
  showCorrectionModal: showSaleCorrectionModal,
  selectedItemForCorrection: saleForCorrection,
  showLotDistributionModal: showSaleLotModal,
  itemForLotDistribution: saleForLotDistribution,
  targetQuantityForDistribution: saleTargetQuantity,
  handleApproveItem: handleApproveSale,
  handleRejectItem: handleRejectSale,
  handleCorrectionProcessed: handleSaleCorrection,
  handleLotsDistributed: handleSaleLots,
} = useCyclicTable("inventory/count/sale", filters);

const laboratories = ref([]);
const isLoadingFilters = ref(false);

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const labResponse = await axios.get("/laboratories");
    laboratories.value = labResponse.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

onMounted(() => {
  fetchSelectOptions();
});

const handleClearFilters = () => {
  filters.searchQuery = "";
  filters.selectedLaboratory = null;
  filters.discrepancyFilter = null;
  filters.sortBy = undefined;
  filters.orderBy = undefined;
};

const handleSort = (sortOptions) => {
  filters.sortBy = sortOptions.key;
  filters.orderBy = sortOptions.order;
};

const handleExport = async (format) => {
  toast.info(`Exportando a ${format}...`);
};
</script>

<template>
  <div>
    <InventoryCycleFilters
      v-model:searchQuery="filters.searchQuery"
      v-model:selectedLaboratory="filters.selectedLaboratory"
      v-model:discrepancyFilter="filters.discrepancyFilter"
      :laboratories="laboratories"
      :loading="isLoadingFilters"
      @clear="handleClearFilters"
      @export="handleExport"
      @sort="handleSort"
    />

    <VRow class="mt-4">
      <VCol cols="12">
        <ProductCyclicTable
          :products="productCounts"
          :loading="productLoading"
          :total-product="totalProductCount"
          :items-per-page="productOptions.itemsPerPage"
          :page="productOptions.page"
          @update:options="updateProductOptions"
          @approve-product="handleApproveProduct"
          @reject-product="handleRejectProduct"
        />
      </VCol>

      <VCol cols="12">
        <InvoiceCyclicTable
          :products="invoiceCounts"
          :loading="invoiceLoading"
          :total-product="totalInvoiceCount"
          :items-per-page="invoiceOptions.itemsPerPage"
          :page="invoiceOptions.page"
          @update:options="updateInvoiceOptions"
          @approve-product="handleApproveInvoice"
          @reject-product="handleRejectInvoice"
        />
      </VCol>

      <VCol cols="12">
        <SaleCyclicTable
          :products="saleCounts"
          :loading="saleLoading"
          :total-product="totalSaleCount"
          :items-per-page="saleOptions.itemsPerPage"
          :page="saleOptions.page"
          @update:options="updateSaleOptions"
          @approve-product="handleApproveSale"
          @reject-product="handleRejectSale"
        />
      </VCol>
    </VRow>

    <InventoryCorrectionModal
      v-model="showProductCorrectionModal"
      :product="productForCorrection"
      @correction-processed="handleProductCorrection"
    />
    <LotDistributionModal
      v-model="showProductLotModal"
      :product-name="productForLotDistribution?.name || 'Producto'"
      :lots="productForLotDistribution?.lots || []"
      :target-quantity="productTargetQuantity || 0"
      @save="handleProductLots"
    />

    <InventoryCorrectionModal
      v-model="showInvoiceCorrectionModal"
      :product="invoiceForCorrection"
      @correction-processed="handleInvoiceCorrection"
    />
    <LotDistributionModal
      v-model="showInvoiceLotModal"
      :product-name="invoiceForLotDistribution?.name || 'Producto'"
      :lots="invoiceForLotDistribution?.lots || []"
      :target-quantity="invoiceTargetQuantity || 0"
      @save="handleInvoiceLots"
    />

    <InventoryCorrectionModal
      v-model="showSaleCorrectionModal"
      :product="saleForCorrection"
      @correction-processed="handleSaleCorrection"
    />
    <LotDistributionModal
      v-model="showSaleLotModal"
      :product-name="saleForLotDistribution?.name || 'Producto'"
      :lots="saleForLotDistribution?.lots || []"
      :target-quantity="saleTargetQuantity || 0"
      @save="handleSaleLots"
    />
  </div>
</template>
