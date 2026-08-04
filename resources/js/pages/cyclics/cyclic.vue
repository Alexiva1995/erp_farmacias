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

const activeTab = ref("products");

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
const locations = ref([]);
const isLoadingFilters = ref(false);

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, usersResponse, locationResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/inventory/cycle/users-with-counts"),
      axios.get("/locations")
    ]);
    laboratories.value = labResponse.data || [];
    locations.value = locationResponse.data.data || locationResponse.data || [];

    if (usersResponse.data && Array.isArray(usersResponse.data)) {
      users.value = usersResponse.data.map(user => {
        const firstName = (user.employee_name || '').trim().split(' ')[0] || '';
        const lastName = (user.employee_last_name || '').trim().split(' ')[0] || '';
        
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
    locations.value = [];
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
  <div class="inventory-cyclics-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
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

      <v-card variant="outlined" class="rounded-lg bg-surface">
        <v-tabs v-model="activeTab" color="primary" align-tabs="start">
          <v-tab value="products" class="text-none font-weight-medium">
            <v-icon start icon="mdi-package-variant-closed" />
            Conteo por Productos
            <v-chip size="x-small" class="ml-2" color="primary" variant="tonal">
              {{ totalProductCount }}
            </v-chip>
          </v-tab>

          <v-tab value="invoices" class="text-none font-weight-medium">
            <v-icon start icon="mdi-file-document-outline" />
            Conteo por Facturas
            <v-chip size="x-small" class="ml-2" color="info" variant="tonal">
              {{ totalInvoiceCount }}
            </v-chip>
          </v-tab>

          <v-tab value="sales" class="text-none font-weight-medium">
            <v-icon start icon="mdi-cart-outline" />
            Conteo por Ventas
            <v-chip size="x-small" class="ml-2" color="success" variant="tonal">
              {{ totalSaleCount }}
            </v-chip>
          </v-tab>
        </v-tabs>

        <v-divider />

        <v-window v-model="activeTab">
          <v-window-item value="products">
            <ProductCyclicTable
              :products="productCounts"
              :loading="productLoading"
              :total-product="totalProductCount"
              :items-per-page="productOptions.itemsPerPage"
              :page="productOptions.page"
              @update:options="updateProductOptions"
              @verify-product="handleVerifyProduct"
            />
          </v-window-item>

          <v-window-item value="invoices">
            <InvoiceCyclicTable
              :products="invoiceCounts"
              :loading="invoiceLoading"
              :total-product="totalInvoiceCount"
              :items-per-page="invoiceOptions.itemsPerPage"
              :page="invoiceOptions.page"
              @update:options="updateInvoiceOptions"
              @verify-product="handleVerifyInvoice"
            />
          </v-window-item>

          <v-window-item value="sales">
            <SaleCyclicTable
              :products="saleCounts"
              :loading="saleLoading"
              :total-product="totalSaleCount"
              :items-per-page="saleOptions.itemsPerPage"
              :page="saleOptions.page"
              @update:options="updateSaleOptions"
              @verify-product="handleVerifySale"
            />
          </v-window-item>
        </v-window>
      </v-card>
    </div>

    <!-- Modales de Verificación y Lotes -->
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
      :locations="locations"
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
      :locations="locations"
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
      :locations="locations"
      mode="adjustment"
      @save="handleSaleLots"
    />
  </div>
</template>




