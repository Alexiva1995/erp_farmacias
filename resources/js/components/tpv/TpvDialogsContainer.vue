<script setup>
import PackDetailsModal from "@/components/dialogs/PackDetailsModal.vue";
import RegisterClientModal from "@/components/dialogs/ClientFormDialoge.vue";
import BuysModal from "@/components/dialogs/BuysModal.vue";
import TpvThermalPrintWrapper from "@/components/tpv/TpvThermalPrintWrapper.vue";
import TpvActiveOrdersModal from "@/components/dialogs/TpvActiveOrdersModal.vue";

defineProps({
  showPackDetailsModal: Boolean,
  selectedPack: Object,
  companies: Array,
  showRegisterClientModal: Boolean,
  newClientFormData: Object,
  newClientFormErrors: Object,
  showBuysModal: Boolean,
  orderItems: Array,
  openOrderData: Object,
  isFinishingOrder: Boolean,
  totalOrderAmount: Number,
  totalAmountUsd: Number,
  totalAmountBs: Number,
  totalAmountCop: Number,
  selectedDisplayCurrency: String,
  totalCompanyDiscountAmount: Number,
  selectedDiscountType: String,
  totalDoctorDiscountAmount: Number,
  totalRecipeDiscountAmount: Number,
  totalExpirationDiscountAmount: Number,
  activeDoctorOffers: Array,
  currentPrescriptionDiscountPercentage: Number,
  activeCompanyOffers: Array,
  currentGlobalDiscountDetails: Object,
  isSpecialTaxpayer: Boolean,
  allForeignSalesSpe: Boolean,
  foreignOrdersCount: Number,
  isPrinting: Boolean,
  orderData: Object,
  itemsForTicket: Array,
  TotalToPrint: Number,
  paymentsForPrint: Array,
  changeAmountForPrint: Number,
  changeAmountOriginForPrint: Number,
  creditAmountForPrint: Number,
  creditForPrint: Boolean,
  companyDiscountForPrint: Number,
  discountTypeForPrint: String,
  doctorDiscountForPrint: Number,
  recipeDiscountForPrint: Number,
  speSurchargeAmountPrint: Number,
  showPedidosModal: Boolean,
  pedidosList: Array,
  loadingPedidos: Boolean,
});

defineEmits([
  "update:showPackDetailsModal",
  "update:showRegisterClientModal",
  "handleCloseRegisterModal",
  "handleSaveNewClient",
  "clearFormErrors",
  "update:showBuysModal",
  "closeBuysModal",
  "handleBuysCompletion",
  "printTickeCompletion",
  "printFiscalPNP",
  "finalizeAndCheckPending",
  "update:showPedidosModal",
  "selectPedido",
]);
</script>

<template>
  <div>
    <PackDetailsModal
      :is-dialog-visible="showPackDetailsModal"
      @update:is-dialog-visible="$emit('update:showPackDetailsModal', $event)"
      :pack="selectedPack || null"
    />

    <RegisterClientModal
      :companies="companies || []"
      :modalFormulario="showRegisterClientModal || false"
      titulo="Registrar Nuevo Cliente"
      :formData="newClientFormData || {}"
      :formError="newClientFormErrors || {}"
      @modalClose="$emit('handleCloseRegisterModal')"
      @save="$emit('handleSaveNewClient', $event)"
      @clearErrorForm="$emit('clearFormErrors')"
    />

    <BuysModal
      :is-dialog-visible="showBuysModal"
      @update:is-dialog-visible="$emit('update:showBuysModal', $event)"
      :order-products="orderItems || []"
      :order-data="openOrderData || null"
      :is-external-loading="isFinishingOrder"
      :total-amount="selectedDisplayCurrency === 'BS' ? (totalAmountBs || totalOrderAmount || 0) : (selectedDisplayCurrency === 'COP' ? (totalAmountCop || totalOrderAmount || 0) : (totalAmountUsd || totalOrderAmount || 0))"
      :selected-currency="selectedDisplayCurrency || 'USD'"
      @modal-closed="$emit('closeBuysModal')"
      @purchase-completed="() => {/* La venta ya fue completada, BuysModal muestra el ticket. finish-and-reload limpia el estado al cerrar. */}"
      :company-discount-total="totalCompanyDiscountAmount || 0"
      :selected-discount-type="selectedDiscountType || null"
      :doctor-discount-total="totalDoctorDiscountAmount || 0"
      :recipe-discount-total="totalRecipeDiscountAmount || 0"
      :expiration-discount-total="totalExpirationDiscountAmount || 0"
      :active-doctor-offers="activeDoctorOffers || []"
      :prescription-discount-percentage="currentPrescriptionDiscountPercentage || 0"
      :active-company-offers="activeCompanyOffers || []"
      :global-discount="currentGlobalDiscountDetails || null"
      :is-special-taxpayer="isSpecialTaxpayer || false"
      :all-foreign-sales-spe="allForeignSalesSpe || false"
      :foreign-orders-count="foreignOrdersCount || 0"
      @printTicke-completed="$emit('printTickeCompletion', $event)"
      @print-fiscal="$emit('printFiscalPNP', $event)"
      @finish-and-reload="$emit('finalizeAndCheckPending')"
    />

    <TpvThermalPrintWrapper
      :is-printing="isPrinting"
      :order-data="orderData"
      :items-for-ticket="itemsForTicket || []"
      :total-to-print="TotalToPrint || 0"
      :selected-display-currency="selectedDisplayCurrency || 'USD'"
      :payments-for-print="paymentsForPrint || []"
      :change-amount-for-print="changeAmountForPrint || 0"
      :change-amount-origin-for-print="changeAmountOriginForPrint || 0"
      :credit-amount-for-print="creditAmountForPrint || 0"
      :credit-for-print="creditForPrint || false"
      :company-discount-for-print="companyDiscountForPrint || 0"
      :discount-type-for-print="discountTypeForPrint || null"
      :doctor-discount-for-print="doctorDiscountForPrint || 0"
      :recipe-discount-for-print="recipeDiscountForPrint || 0"
      :is-special-taxpayer="isSpecialTaxpayer || false"
      :spe-surcharge-amount-print="speSurchargeAmountPrint || 0"
    />

    <TpvActiveOrdersModal
      :model-value="showPedidosModal"
      @update:model-value="$emit('update:showPedidosModal', $event)"
      :pedidos-list="pedidosList || []"
      :loading-pedidos="loadingPedidos"
      @select-pedido="$emit('selectPedido', $event)"
    />
  </div>
</template>
