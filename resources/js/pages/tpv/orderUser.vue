<script setup>
import TpvHeaderBar from "@/components/tpv/TpvHeaderBar.vue";
import TpvOrderMainSection from "@/components/tpv/TpvOrderMainSection.vue";
import TpvCatalogSection from "@/components/tpv/TpvCatalogSection.vue";
import TpvDialogsContainer from "@/components/tpv/TpvDialogsContainer.vue";
import { useAuthStore } from "@/stores/auth";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import { useTpvRates } from "@/composables/useTpvRates";
import { useTpvBarcode } from "@/composables/useTpvBarcode";
import { useTpvPromotions } from "@/composables/useTpvPromotions";
import { formatOrderItemForFrontend, getItemPriceByCurrency } from "@/composables/useTpvItemFormatter";
import { useTpvClientManager } from "@/composables/useTpvClientManager";
import { useTpvCalculations } from "@/composables/useTpvCalculations";
import { useTpvOrderManager } from "@/composables/useTpvOrderManager";
import { useTpvCatalog } from "@/composables/useTpvCatalog";
import { useTpvDishes } from "@/composables/useTpvDishes";
import { useTpvPacks } from "@/composables/useTpvPacks";
import { useTpvRestaurantOrders } from "@/composables/useTpvRestaurantOrders";
import { useTpvCart } from "@/composables/useTpvCart";
import { useTpvPrintManager } from "@/composables/useTpvPrintManager";
import { useTpvQuotations } from "@/composables/useTpvQuotations";
import { useTpvState } from "@/composables/useTpvState";
import { useTpvInit } from "@/composables/useTpvInit";
import { useTpvKeyboardShortcuts } from "@/composables/useTpvKeyboardShortcuts";
import { computed, ref } from "vue";

// ─── Stores ───────────────────────────────────────────────────────────────────
const brandingStore = useBrandingStore();
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

// ─── Configuración del TPV (platos, restaurante, deportes) ───────────────────
const {
  enableDishes,
  isRestaurant,
  isSportsRental,
  isSpecialTaxpayer,
  allForeignSalesSpe,
  dishes,
  dishesLoading,
  dishFilterQuery,
  selectedDishCategory,
  dishCategories,
  filteredDishes,
  fetchGeneralSettings: fetchGeneralSettingsComposable,
  fetchDishes,
} = useTpvDishes();

// activeTab controla qué sección está visible para fetchGeneralSettings
const activeTab = ref(0);
const fetchGeneralSettings = () => fetchGeneralSettingsComposable(activeTab);

// ─── Estado centralizado del TPV ──────────────────────────────────────────────
const {
  isSimpleTpv,
  defaultCurrency,
  selectedDisplayCurrency,
  foreignOrdersCount,
  isFinishingOrder,
  isCurrencyChanging,
  activeReservationId,
  showRegisterClientModal,
  selectedClient,
  isLoadingInitialOrder,
  showBuysModal,
  hasOpenOrder,
  openOrderData,
  orderData,
  reservedOrderData,
  pendingOpenOrder,
  pendingQuotationProducts,
  orderItems,
  page,
  itemsPerPage,
  sortBy,
  orderBy,
  filterSearchQuery,
  selectedLaboratory,
  selectedOrigin,
  stockStatusFilter,
  isStrictSearch,
  discount,
  discountMinProducts,
  discountMaxProducts,
  selectedCategory,
  currentGroupId,
  tableOptions,
} = useTpvState({ brandingStore, isRestaurant, isSportsRental });

// ─── Tasas de cambio ──────────────────────────────────────────────────────────
const { exchangeRates, getEffectiveRate, fetchExchangeRates } = useTpvRates(
  brandingStore,
  isRestaurant,
  isSportsRental,
);

// ─── Descuentos y promociones ─────────────────────────────────────────────────
const {
  activeDoctorOffers,
  loadingDoctorOffers,
  activePrescriptionOffers,
  loadingPrescriptionOffers,
  activeCompanyOffers,
  loadingCompanyOffers,
  selectedDoctorOffer,
  prescriptionFile,
  selectedCompany,
  selectedCompanyId,
  selectedDiscountType,
  currentPrescriptionDiscountPercentage,
  currentGlobalDiscountDetails,
  fetchDoctorOffers,
  fetchPrescriptionOffers,
  fetchCompanyOffers,
  validateAndApplyDoctorDiscount,
  validateAndApplyPrescriptionDiscount,
  validateAndApplyCompanyDiscount,
  handlePrescriptionFileSelected,
  handleDoctorDiscountSelected,
  handleCompanyDiscountSelected,
} = useTpvPromotions({ orderItems, selectedClient });

// ─── Cálculos de totales ──────────────────────────────────────────────────────
const {
  totalEligibleAmount,
  totalCompanyDiscountAmount,
  totalDoctorDiscountAmount,
  totalRecipeDiscountAmount,
  totalExpirationDiscountAmount,
  totalProductsAmount,
  totalIVAAmount,
  totalOrderAmount,
  appliesSpecialTax,
  specialTaxAmount,
  totalOrderAmountWithspecialTaxAmount,
  totalOrderCost,
  totalOrderAmountSinDiscount,
  totalSPESavings,
  totalAmountBs,
  totalAmountUsd,
  totalAmountCop,
  updateOrderTotalsInBackend,
} = useTpvCalculations({
  orderItems,
  selectedDisplayCurrency,
  selectedClient,
  isSpecialTaxpayer,
  selectedDiscountType,
  selectedCompanyId,
  activeCompanyOffers,
  selectedDoctorOffer,
  currentPrescriptionDiscountPercentage,
  getItemPriceByCurrency,
  openOrderData,
  hasOpenOrder,
  isFinishingOrder,
});

// ─── Catálogo de productos ────────────────────────────────────────────────────
const {
  products,
  totalProduct,
  loading,
  laboratories,
  origins,
  categories,
  isLoadingFilters,
  fetchProducts,
  fetchSelectOptions,
  handleClearFilters,
  handleClearSortOrder,
  updateTableOptions,
  handleSort,
  fetchGroupProducts,
  fetchFailuresProducts,
  handleBackFromGroupView,
  handleExternalSort,
} = useTpvCatalog({
  filterSearchQuery,
  selectedLaboratory,
  selectedOrigin,
  selectedCategory,
  stockStatusFilter,
  isStrictSearch,
  sortBy,
  orderBy,
  page,
  itemsPerPage,
  currentGroupId,
  tableOptions,
});

// ─── Carrito / items ──────────────────────────────────────────────────────────
const {
  updateOrderItemQuantity,
  handleSaveOrderItemNote,
  addProductToOrder,
  addDishToOrder,
  handleAddDishToOrder,
} = useTpvCart({
  hasOpenOrder,
  openOrderData,
  orderItems,
  selectedDisplayCurrency,
  brandingStore,
  addOrden: (...args) => addOrden(...args),
  fetchOpenOrder: (...args) => fetchOpenOrder(...args),
  getItemPriceByCurrency,
  formatOrderItemForFrontend,
  selectedDiscountType,
  selectedDoctorOffer,
  prescriptionFile,
  activePrescriptionOffers,
  validateAndApplyDoctorDiscount,
  validateAndApplyPrescriptionDiscount,
});

// ─── Pedidos restaurante / reservaciones ──────────────────────────────────────
const {
  pedidosList,
  loadingPedidos,
  showPedidosModal,
  fetchPedidosList,
  openPedidosModal,
  selectPedido,
  selectReservation,
  handleNoShow,
} = useTpvRestaurantOrders({
  isLoadingInitialOrder,
  hasOpenOrder,
  openOrderData,
  reservedOrderData,
  selectedClient,
  selectedDisplayCurrency,
  defaultCurrency,
  orderItems,
  isSportsRental,
  fetchOpenOrder: (...args) => fetchOpenOrder(...args),
  formatOrderItemForFrontend,
  getEffectiveRate,
});

// ─── Gestión de la orden ──────────────────────────────────────────────────────
const {
  addOrden,
  fetchOpenOrder,
  reservedOrderCliente,
  handleCurrencyChanged,
  cancelarOrder,
  reserverOrder,
  removeOrderItem,
  handleBuysCompletion,
  addReserverOrder,
  finalizeAndCheckPending,
} = useTpvOrderManager({
  hasOpenOrder,
  openOrderData,
  reservedOrderData,
  selectedClient,
  selectedDisplayCurrency,
  defaultCurrency,
  orderItems,
  isFinishingOrder,
  isCurrencyChanging,
  showBuysModal,
  totalOrderAmountWithspecialTaxAmount,
  totalOrderAmount,
  appliesSpecialTax,
  specialTaxAmount,
  currentUser,
  isRestaurant,
  isSportsRental,
  fetchPedidosList: () => fetchPedidosList(),
  updateOrderTotalsInBackend: () => updateOrderTotalsInBackend(),
  getItemPriceByCurrency,
  roundUpToNearestHundred,
  selectedDiscountType,
  selectedCompanyId,
  activeCompanyOffers,
  selectedDoctorOffer,
  prescriptionFile,
  activePrescriptionOffers,
  currentPrescriptionDiscountPercentage,
  isLoadingInitialOrder,
  foreignOrdersCount,
  getEffectiveRate,
  formatOrderItemForFrontend,
});

// ─── Cotizaciones ─────────────────────────────────────────────────────────────
const { handleLoadQuotation, handleIdentifyAndStart, handleAddQuotationProducts } =
  useTpvQuotations({
    addOrden,
    selectedClient,
    pendingQuotationProducts,
    verifyClient: (val) => verifyClient(val),
    addProductToOrder,
    fetchProducts,
  });

// ─── Gestión de clientes ──────────────────────────────────────────────────────
const {
  clientIdentification,
  companies,
  newClientFormData,
  newClientFormErrors,
  consultAllcomapanies,
  clearFormErrors,
  handleCloseRegisterModal,
  handleSaveNewClient,
  handleEditCliente,
  verifyClient,
} = useTpvClientManager({
  selectedClient,
  showRegisterClientModal,
  hasOpenOrder,
  openOrderData,
  selectedDisplayCurrency,
  addOrden,
  handleAddQuotationProducts,
  pendingQuotationProducts,
});

// ─── Packs de productos ───────────────────────────────────────────────────────
const {
  showPackDetailsModal,
  selectedPack,
  handleViewPackDetails,
  handleAddPackToOrder,
} = useTpvPacks({
  orderItems,
  loading,
  updateOrderItemQuantity,
  addProductToOrder,
  fetchOpenOrder,
});

// ─── Código de barras ─────────────────────────────────────────────────────────
const { barcodeSearchQuery, barcodeInputTimer } = useTpvBarcode({ addProductToOrder });

// ─── Impresión de tickets ─────────────────────────────────────────────────────
const {
  isPrinting,
  itemsToPrint,
  TotalToPrint,
  speSurchargeAmountPrint,
  paymentsForPrint,
  changeAmountForPrint,
  changeAmountOriginForPrint,
  creditAmountForPrint,
  creditForPrint,
  recipeDiscountForPrint,
  doctorDiscountForPrint,
  companyDiscountForPrint,
  discountTypeForPrint,
  expirationDiscountForPrint,
  speSurchargeAmount,
  itemsForTicket,
  printTickeCompletion,
  printFiscalPNP,
} = useTpvPrintManager({
  orderData,
  isSpecialTaxpayer,
  currentGlobalDiscountDetails,
  selectedDisplayCurrency,
  finalizeAndCheckPending,
  brandingStore,
});

// ─── Control del modal de cobro ───────────────────────────────────────────────
const openBuysModal = () => { showBuysModal.value = true; };
const closeBuysModal = () => { showBuysModal.value = false; };
const handleFlashCheckout = () => { showBuysModal.value = true; };

// ─── Atajos de teclado (F12 = cobrar, Esc = cerrar modal, Alt + letra) ────────
useTpvKeyboardShortcuts({
  openBuysModal,
  closeBuysModal,
  showBuysModal,
  hasOpenOrder,
  showRegisterClientModal,
  cancelarOrder,
  selectedDisplayCurrency,
  handleCurrencyChanged: (currency) => handleCurrencyChanged(currency, isCurrencyChanging),
  isCurrencyChanging,
  onSelectPaymentMethod: (method, currency) => {
    if (showBuysModal.value) {
      // Disparado directamente a la reactividad de Vue
      const eventBus = document.querySelector('.buys-modal-dialog')
      if (eventBus) {
        const btn = eventBus.querySelector(`button[data-shortcut="${method}"]`)
        if (btn) btn.click()
      }
    }
  },
  onCompletePurchase: () => {
    const checkoutBtn = document.querySelector('.checkout-btn')
    if (checkoutBtn && !checkoutBtn.disabled) checkoutBtn.click()
  },
});


// ─── Inicialización ───────────────────────────────────────────────────────────
useTpvInit({
  authStore,
  brandingStore,
  fetchGeneralSettings,
  sortBy,
  orderBy,
  tableOptions,
  selectedDisplayCurrency,
  defaultCurrency,
  fetchOpenOrder,
  hasOpenOrder,
  addOrden,
  selectedClient,
  fetchCompanyOffers,
  selectedDiscountType,
  selectedCompany,
  isLoadingInitialOrder,
  fetchSelectOptions,
  fetchProducts,
  consultAllcomapanies,
  fetchDoctorOffers,
  fetchPrescriptionOffers,
  barcodeInputTimer,
});
</script>

<template>
  <div>
    <TpvHeaderBar
      :is-currency-changing="isCurrencyChanging"
      :is-loading-initial-order="isLoadingInitialOrder"
      :is-restaurant="isRestaurant"
      :is-sports-rental="isSportsRental"
      :pedidos-list="pedidosList || []"
      @select-pedido="selectPedido"
      @select-reservation="selectReservation"
      @handle-no-show="handleNoShow"
    />

    <TpvOrderMainSection
      :is-loading-initial-order="isLoadingInitialOrder"
      :has-open-order="hasOpenOrder"
      :is-simple-tpv="isSimpleTpv"
      v-model:barcode-search-query="barcodeSearchQuery"
      :order-items="orderItems || []"
      :open-order-data="openOrderData"
      :reserved-order-data="reservedOrderData"
      :total-products-amount="totalProductsAmount || 0"
      :total-iva-amount="totalIVAAmount || 0"
      :total-order-amount="totalOrderAmount || 0"
      :total-company-discount-amount="totalCompanyDiscountAmount || 0"
      :total-doctor-discount-amount="totalDoctorDiscountAmount || 0"
      :total-recipe-discount-amount="totalRecipeDiscountAmount || 0"
      :total-expiration-discount-amount="totalExpirationDiscountAmount || 0"
      :selected-client="selectedClient"
      :exchange-rates="exchangeRates"
      :selected-display-currency="selectedDisplayCurrency || 'USD'"
      v-model:selected-discount-type="selectedDiscountType"
      :active-doctor-offers="activeDoctorOffers || []"
      :current-prescription-discount-percentage="currentPrescriptionDiscountPercentage || 0"
      :active-company-offers="activeCompanyOffers || []"
      :current-global-discount-details="currentGlobalDiscountDetails"
      :is-special-taxpayer="isSpecialTaxpayer || false"
      :is-restaurant="isRestaurant"
      :is-sports-rental="isSportsRental"
      v-model:client-identification="clientIdentification"
      @handle-currency-changed="handleCurrencyChanged"
      @update-order-item-quantity="updateOrderItemQuantity"
      @remove-order-item="removeOrderItem"
      @cancelar-order="cancelarOrder"
      @reserver-order="reserverOrder"
      @open-buys-modal="openBuysModal"
      @handle-add-quotation-products="handleAddQuotationProducts"
      @add-reserver-order="addReserverOrder"
      @handle-doctor-discount-selected="handleDoctorDiscountSelected"
      @handle-prescription-file-selected="handlePrescriptionFileSelected"
      @handle-company-discount-selected="handleCompanyDiscountSelected"
      @handle-add-pack-to-order="handleAddPackToOrder"
      @handle-edit-cliente="handleEditCliente"
      @handle-save-order-item-note="handleSaveOrderItemNote"
      @handle-flash-checkout="handleFlashCheckout"
      @verify-client="verifyClient"
      @handle-identify-and-start="handleIdentifyAndStart"
      @open-pedidos-modal="openPedidosModal"
      @handle-load-quotation="handleLoadQuotation"
    />

    <TpvCatalogSection
      v-model:filter-search-query="filterSearchQuery"
      v-model:selected-laboratory="selectedLaboratory"
      v-model:selected-origin="selectedOrigin"
      v-model:selected-category="selectedCategory"
      v-model:stock-status-filter="stockStatusFilter"
      v-model:is-strict-search="isStrictSearch"
      :laboratories="laboratories || []"
      :origins="origins || []"
      :categories="categories || []"
      :is-restaurant="isRestaurant || false"
      :is-loading-filters="isLoadingFilters || false"
      :sort-by="sortBy"
      :order-by="orderBy"
      @handle-clear-filters="handleClearFilters"
      @handle-clear-sort-order="handleClearSortOrder"
      @handle-external-sort="handleExternalSort"
      @handle-back-from-group-view="handleBackFromGroupView"
      :products="products || []"
      :loading="loading"
      :total-product="totalProduct || 0"
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      :discount-min-products="discountMinProducts || 0"
      :discount-max-products="discountMaxProducts || 0"
      :discount="discount || 0"
      :order-items="orderItems || []"
      :table-options="tableOptions"
      :exchange-rates="exchangeRates"
      :selected-display-currency="selectedDisplayCurrency"
      @update-table-options="updateTableOptions"
      @add-product-to-order="addProductToOrder"
      @handle-add-dish-to-order="handleAddDishToOrder"
      @fetch-group-products="fetchGroupProducts"
      @fetch-failures-products="fetchFailuresProducts"
      @handle-view-pack-details="handleViewPackDetails"
      @handle-add-pack-to-order="handleAddPackToOrder"
    />

    <TpvDialogsContainer
      v-model:showPackDetailsModal="showPackDetailsModal"
      :selectedPack="selectedPack"
      :companies="companies"
      v-model:showRegisterClientModal="showRegisterClientModal"
      :newClientFormData="newClientFormData"
      :newClientFormErrors="newClientFormErrors"
      @handleCloseRegisterModal="handleCloseRegisterModal"
      @handleSaveNewClient="handleSaveNewClient"
      @clearFormErrors="clearFormErrors"
      v-model:showBuysModal="showBuysModal"
      :orderItems="orderItems"
      :openOrderData="openOrderData"
      :isFinishingOrder="isFinishingOrder"
      :totalOrderAmount="totalOrderAmount"
      :totalAmountUsd="totalAmountUsd"
      :selectedDisplayCurrency="selectedDisplayCurrency"
      @closeBuysModal="closeBuysModal"
      @handleBuysCompletion="handleBuysCompletion"
      :totalCompanyDiscountAmount="totalCompanyDiscountAmount"
      :selectedDiscountType="selectedDiscountType"
      :totalDoctorDiscountAmount="totalDoctorDiscountAmount"
      :totalRecipeDiscountAmount="totalRecipeDiscountAmount"
      :totalExpirationDiscountAmount="totalExpirationDiscountAmount"
      :activeDoctorOffers="activeDoctorOffers"
      :currentPrescriptionDiscountPercentage="currentPrescriptionDiscountPercentage"
      :activeCompanyOffers="activeCompanyOffers"
      :currentGlobalDiscountDetails="currentGlobalDiscountDetails"
      :isSpecialTaxpayer="isSpecialTaxpayer"
      :allForeignSalesSpe="allForeignSalesSpe"
      :foreignOrdersCount="foreignOrdersCount"
      @printTickeCompletion="printTickeCompletion"
      @printFiscalPNP="printFiscalPNP"
      @finalizeAndCheckPending="finalizeAndCheckPending"
      :isPrinting="isPrinting"
      :orderData="orderData"
      :itemsForTicket="itemsForTicket"
      :TotalToPrint="TotalToPrint"
      :paymentsForPrint="paymentsForPrint"
      :changeAmountForPrint="changeAmountForPrint"
      :changeAmountOriginForPrint="changeAmountOriginForPrint"
      :creditAmountForPrint="creditAmountForPrint"
      :creditForPrint="creditForPrint"
      :companyDiscountForPrint="companyDiscountForPrint"
      :discountTypeForPrint="discountTypeForPrint"
      :doctorDiscountForPrint="doctorDiscountForPrint"
      :recipeDiscountForPrint="recipeDiscountForPrint"
      :speSurchargeAmountPrint="speSurchargeAmountPrint"
      v-model:showPedidosModal="showPedidosModal"
      :pedidosList="pedidosList"
      :loadingPedidos="loadingPedidos"
      @selectPedido="selectPedido"
    />
  </div>
</template>

<style scoped>
/* Tarjetas de platos en el menú del restaurante */
.dish-card {
  transition: transform 0.18s ease, box-shadow 0.18s ease;
  cursor: pointer;
}
.dish-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15) !important;
}
.dish-card--inactive {
  opacity: 0.55;
  filter: grayscale(40%);
}
</style>
