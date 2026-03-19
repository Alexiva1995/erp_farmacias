<script setup>
import LotDistributionModal from "@/components/dialogs/LotDistributionModal.vue";
import VerifyCountModal from "@/components/dialogs/VerifyCountModal.vue";
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
  selectedUser: null,
  sortBy: undefined,
  orderBy: undefined,
});

const {
  items: productCounts,
  totalItems: totalProductCount,
  loading: productLoading,
  options: productOptions,
  updateTableOptions: updateProductOptions,
  showVerifyModal: showProductVerifyModal,
  itemForVerification: productForVerification,
  showLotDistributionModal: showProductLotModal,
  itemForLotDistribution: productForLotDistribution,
  targetQuantityForDistribution: productTargetQuantity,
  handleVerifyItem: handleVerifyProduct,
  onVerifyNoDiscrepancy: onProductVerifyNone,
  onVerifyWithDiscrepancy: onProductVerifyDiff,
  handleLotsDistributed: handleProductLots,
} = useCyclicTable("/products", filters);

const {
  items: invoiceCounts,
  totalItems: totalInvoiceCount,
  loading: invoiceLoading,
  options: invoiceOptions,
  updateTableOptions: updateInvoiceOptions,
  showVerifyModal: showInvoiceVerifyModal,
  itemForVerification: invoiceForVerification,
  showLotDistributionModal: showInvoiceLotModal,
  itemForLotDistribution: invoiceForLotDistribution,
  targetQuantityForDistribution: invoiceTargetQuantity,
  handleVerifyItem: handleVerifyInvoice,
  onVerifyNoDiscrepancy: onInvoiceVerifyNone,
  onVerifyWithDiscrepancy: onInvoiceVerifyDiff,
  handleLotsDistributed: handleInvoiceLots,
} = useCyclicTable("inventory/count/invoices", filters);

const {
  items: saleCounts,
  totalItems: totalSaleCount,
  loading: saleLoading,
  options: saleOptions,
  updateTableOptions: updateSaleOptions,
  showVerifyModal: showSaleVerifyModal,
  itemForVerification: saleForVerification,
  showLotDistributionModal: showSaleLotModal,
  itemForLotDistribution: saleForLotDistribution,
  targetQuantityForDistribution: saleTargetQuantity,
  handleVerifyItem: handleVerifySale,
  onVerifyNoDiscrepancy: onSaleVerifyNone,
  onVerifyWithDiscrepancy: onSaleVerifyDiff,
  handleLotsDistributed: handleSaleLots,
} = useCyclicTable("inventory/count/sale", filters);

const laboratories = ref([]);
const users = ref([]);
const isLoadingFilters = ref(false);

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, usersResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/inventory/cycle/users-with-counts")
    ]);
    laboratories.value = labResponse.data || [];

    if (usersResponse.data && Array.isArray(usersResponse.data)) {
      users.value = usersResponse.data.map(user => {
        const firstName = user.employee_name || '';
        const lastName = user.employee_last_name || '';
        
        let displayName = '';
        if (firstName || lastName) {
          displayName = `${firstName} ${lastName}`.trim()
            .split(' ')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
            .join(' ');
        } else {
          displayName = user.username || user.email || `Usuario ${user.id}`;
        }

        return {
          ...user,
          display_name: displayName
        };
      });
    } else {
      users.value = [];
    }
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
    laboratories.value = [];
    users.value = [];
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
  filters.selectedUser = null;
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
      v-model:selectedUser="filters.selectedUser"
      :laboratories="laboratories"
      :users="users"
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
          @verify-product="handleVerifyProduct"
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
          @verify-product="handleVerifyInvoice"
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
          @verify-product="handleVerifySale"
        />
      </VCol>
    </VRow>

    <!-- Modales de Verificación -->
    <VerifyCountModal
      v-model="showProductVerifyModal"
      :count-record="productForVerification"
      @verify-no-discrepancy="onProductVerifyNone"
      @verify-with-discrepancy="onProductVerifyDiff"
    />
    <LotDistributionModal
      v-model="showProductLotModal"
      :product-name="productForLotDistribution?.name || 'Producto'"
      :lots="productForLotDistribution?.lots || []"
      :target-quantity="productTargetQuantity || 0"
      mode="adjustment"
      @save="handleProductLots"
    />

    <VerifyCountModal
      v-model="showInvoiceVerifyModal"
      :count-record="invoiceForVerification"
      @verify-no-discrepancy="onInvoiceVerifyNone"
      @verify-with-discrepancy="onInvoiceVerifyDiff"
    />
    <LotDistributionModal
      v-model="showInvoiceLotModal"
      :product-name="invoiceForLotDistribution?.name || 'Producto'"
      :lots="invoiceForLotDistribution?.lots || []"
      :target-quantity="invoiceTargetQuantity || 0"
      mode="adjustment"
      @save="handleInvoiceLots"
    />

    <VerifyCountModal
      v-model="showSaleVerifyModal"
      :count-record="saleForVerification"
      @verify-no-discrepancy="onSaleVerifyNone"
      @verify-with-discrepancy="onSaleVerifyDiff"
    />
    <LotDistributionModal
      v-model="showSaleLotModal"
      :product-name="saleForLotDistribution?.name || 'Producto'"
      :lots="saleForLotDistribution?.lots || []"
      :target-quantity="saleTargetQuantity || 0"
      mode="adjustment"
      @save="handleSaleLots"
    />
  </div>
</template>
