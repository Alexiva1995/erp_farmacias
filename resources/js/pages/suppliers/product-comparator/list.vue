<script setup>
import ApplyDiscountDialog from "@/components/dialogs/ApplyDiscountDialog.vue";
import DeleteOldProductsDialog from "@/components/dialogs/DeleteOldProductsDialog.vue";
import GeneratePublicLinkDialog from "@/components/dialogs/GeneratePublicLinkDialog.vue";
import ShowImportProductsFileDialog from "@/components/dialogs/ShowImportProductsFileDialog.vue";
import ShowSupplierProductsDialog from "@/components/dialogs/ShowSupplierProductsDialog.vue";
import ComparatorCatalogFiltersDialog from "@/components/dialogs/ComparatorCatalogFiltersDialog.vue";
import ProductComparisionProductsTable from "@/components/ProductComparisionProductsTable.vue";
import ProductComparisionTable from "@/components/ProductComparisionTable.vue";
import ProductsWithoutSupplierComparatorTable from "@/components/ProductsWithoutSupplierComparatorTable.vue";
import { useProductComparator } from "./useProductComparator";

const {
  supplierConnections,
  suppliers,
  origins,
  laboratories,
  groups,
  products,
  loadingSuppliers,
  loadingProducts,
  quantityErrors,
  supplierOption,
  selectedSupplier,
  selectedOrigin,
  filterSearchQuery,
  isStrictSearch,
  searchedSupplier,
  searchedLaboratory,
  enableDiscounts,
  isShowSupplierProductsDialogActive,
  isShowImportFileDialogActive,
  isApplyDiscountDialogActive,
  supplierForDiscount,
  isGeneratePublicLinkDialogActive,
  supplierForPublicLink,
  isCatalogFiltersDialogVisible,
  checkingApiSupplierId,
  tab,
  page,
  itemsPerPage,
  totalSupplierConnections,
  productsPage,
  productsItemPerPage,
  productsTotal,
  sortOptions,
  enableUsdAmountCol,
  enableDiscountCol,
  isDeleteDialogVisible,
  listProductsWithoutSupplier,
  totalProductsWithoutSupplier,
  loadingProductsWithoutSupplier,
  pageProductsWithoutSupplier,
  itemsPerPageProductsWithoutSupplier,
  selectedProductFromTop,
  searchQueryRight,
  con_descuento,
  selectedLaboratory,
  selectedGroup,
  needsLaboratory,
  needsGroup,
  tipo_de_filtracion,
  lapso_de_tiempo,
  stock,
  needsHasStock,
  needsIsColombian,
  needsIsNovaventa,
  handleSelectProductFromTop,
  handleShowDiscountDialog,
  handleDeleteOldProducts,
  handleUpdateAllApi,
  handleApplyDiscount,
  handleRefreshAll,
  fetchSupplierConnections,
  updateTableOptions,
  updateProductsTableOptions,
  handleShowProducts,
  handleCheckSupplierApi,
  handleClearProductsFilters,
  handleAddItemToAutoOrder,
  handleShowImportProductsDialog,
  handleHideImportProductsDialog,
  handleDeleteSupplierProducts,
  handleToggleOrder,
  handleSaveAnalysis,
  updateProductsWithoutSupplierOptions,
  handleOpenPublicLink,
} = useProductComparator();
</script>

<template>
  <div class="product-comparator-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <ShowSupplierProductsDialog
        v-model="isShowSupplierProductsDialogActive"
        :selectedSupplier="supplierOption"
      />
      <ShowImportProductsFileDialog
        v-model="isShowImportFileDialogActive"
        :selectedSupplier="supplierOption"
        @close-dialog="handleHideImportProductsDialog"
        @refresh-products="handleRefreshAll"
      />
      <ApplyDiscountDialog
        v-model:isDialogVisible="isApplyDiscountDialogActive"
        :selected-supplier="supplierForDiscount"
        @submit="handleApplyDiscount"
      />
      <GeneratePublicLinkDialog
        v-model:isDialogVisible="isGeneratePublicLinkDialogActive"
        :selected-supplier="supplierForPublicLink"
        @refresh="fetchSupplierConnections"
      />
      <DeleteOldProductsDialog
        v-model:isDialogVisible="isDeleteDialogVisible"
        @submit="handleDeleteOldProducts"
      />

      <!-- Tabs Container -->
      <VCard class="rounded-lg border shadow-sm overflow-hidden">
        <VCardText class="pa-0">
          <VTabs v-model="tab" color="primary" grow>
            <VTab value="suppliers">
              <VIcon icon="tabler-truck-delivery" class="me-2" />
              Proveedores
            </VTab>
            <VTab value="products">
              <VIcon icon="tabler-package" class="me-2" />
              Productos
            </VTab>
          </VTabs>
        </VCardText>
      </VCard>

      <ComparatorCatalogFiltersDialog
        v-if="tab === 'products'"
        v-model:is-dialog-visible="isCatalogFiltersDialogVisible"
        v-model:selected-laboratory="selectedLaboratory"
        v-model:selected-group="selectedGroup"
        v-model:selected-origin="selectedOrigin"
        v-model:selected-supplier="searchedSupplier"
        v-model:enable-discounts="enableDiscounts"
        v-model:enable-usd-amount-col="enableUsdAmountCol"
        v-model:enable-discount-col="enableDiscountCol"
        v-model:is-strict-search="isStrictSearch"
        :laboratories="laboratories"
        :groups="groups"
        :origins="origins"
        :suppliers="suppliers"
        @clear="handleClearProductsFilters"
        @update-all-api="handleUpdateAllApi"
      />

      <VTabsWindow v-model="tab">
        <VTabsWindowItem value="suppliers">
          <ProductComparisionTable
            :supplierConnections="supplierConnections"
            :loading="loadingSuppliers"
            :total-supplierConnections="totalSupplierConnections"
            :items-per-page="itemsPerPage"
            :page="page"
            :checking-api-id="checkingApiSupplierId"
            :search-query="selectedSupplier"
            @update:search-query="selectedSupplier = $event"
            @update:options="updateTableOptions"
            @show-products="handleShowProducts"
            @update-products="handleCheckSupplierApi"
            @load-products="handleShowImportProductsDialog"
            @delete-products="handleDeleteSupplierProducts"
            @open-discount-dialog="handleShowDiscountDialog"
            @open-public-link="handleOpenPublicLink"
          />
        </VTabsWindowItem>

        <VTabsWindowItem value="products">
          <VRow class="match-height">
            <!-- COLUMNA IZQUIERDA: Catálogo de Proveedores -->
            <VCol cols="12" md="6">
              <ProductComparisionProductsTable
                :products="products"
                :loading="loadingProducts"
                :total-products="productsTotal"
                :items-per-page="productsItemPerPage"
                :page="productsPage"
                :quantity-errors="quantityErrors"
                :enable-usd-amount-col="enableUsdAmountCol"
                :enable-discount-col="enableDiscountCol"
                :search-query="filterSearchQuery"
                @update:search-query="filterSearchQuery = $event"
                :is-strict-search="isStrictSearch"
                @update:is-strict-search="isStrictSearch = $event"
                :selected-product="selectedProductFromTop"
                v-model:sortBy="sortOptions"
                @update:options="updateProductsTableOptions"
                @send-product="handleAddItemToAutoOrder"
                :laboratories="laboratories"
                v-model:selected-laboratory="searchedLaboratory"
                :groups="groups"
                :origins="origins"
                :suppliers="suppliers"
                v-model:selected-supplier="searchedSupplier"
                v-model:enable-discounts="enableDiscounts"
                @sync-apis="handleUpdateAllApi"
              />
            </VCol>

            <!-- COLUMNA DERECHA: Productos sin proveedor -->
            <VCol cols="12" md="6">
              <ProductsWithoutSupplierComparatorTable
                v-model="selectedProductFromTop"
                :products="listProductsWithoutSupplier"
                :loading="loadingProductsWithoutSupplier"
                :total-products="totalProductsWithoutSupplier"
                :items-per-page="itemsPerPageProductsWithoutSupplier"
                :page="pageProductsWithoutSupplier"
                :search-query="searchQueryRight"
                @update:search-query="searchQueryRight = $event"
                @update:options="updateProductsWithoutSupplierOptions"
                @select-product="handleSelectProductFromTop"
                @delete="handleToggleOrder"
                @save-analysis="handleSaveAnalysis"
                :laboratories="laboratories"
                v-model:selected-laboratory="needsLaboratory"
                :groups="groups"
                v-model:selected-group="needsGroup"
                v-model:tipo_de_filtracion="tipo_de_filtracion"
                v-model:lapso_de_tiempo="lapso_de_tiempo"
                v-model:stock="stock"
                v-model:select-con-descuento="con_descuento"
                v-model:has-stock="needsHasStock"
                v-model:is-colombian="needsIsColombian"
                v-model:is-novaventa="needsIsNovaventa"
                @delete-old="isDeleteDialogVisible = true"
              />
            </VCol>
          </VRow>
        </VTabsWindowItem>
      </VTabsWindow>
    </div>
  </div>
</template>

<style scoped>
.product-comparator-view {
  background-color: #f8fafc;
  min-block-size: 100vh;
}
</style>
