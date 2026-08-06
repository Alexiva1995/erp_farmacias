<script setup>
import OpenOrderCard from "@/components/cards/OpenOrderCard.vue";
import OrderClienteCard from "@/components/cards/OrderClienteCard.vue";

defineProps({
  isLoadingInitialOrder: Boolean,
  hasOpenOrder: Boolean,
  isSimpleTpv: Boolean,
  barcodeSearchQuery: String,
  orderItems: Array,
  openOrderData: Object,
  reservedOrderData: Object,
  totalProductsAmount: Number,
  totalIvaAmount: Number,
  totalOrderAmount: Number,
  totalCompanyDiscountAmount: Number,
  totalDoctorDiscountAmount: Number,
  totalRecipeDiscountAmount: Number,
  totalExpirationDiscountAmount: Number,
  selectedClient: Object,
  exchangeRates: Object,
  selectedDisplayCurrency: String,
  selectedDiscountType: String,
  activeDoctorOffers: Array,
  currentPrescriptionDiscountPercentage: Number,
  activeCompanyOffers: Array,
  currentGlobalDiscountDetails: Object,
  isSpecialTaxpayer: Boolean,
  isRestaurant: Boolean,
  isSportsRental: Boolean,
  clientIdentification: String,
});

defineEmits([
  "update:barcodeSearchQuery",
  "update:selectedDiscountType",
  "update:clientIdentification",
  "handleCurrencyChanged",
  "updateOrderItemQuantity",
  "removeOrderItem",
  "cancelarOrder",
  "reserverOrder",
  "openBuysModal",
  "handleAddQuotationProducts",
  "addReserverOrder",
  "handleDoctorDiscountSelected",
  "handlePrescriptionFileSelected",
  "handleCompanyDiscountSelected",
  "handleAddPackToOrder",
  "handleEditCliente",
  "handleSaveOrderItemNote",
  "handleFlashCheckout",
  "verifyClient",
  "handleIdentifyAndStart",
  "openPedidosModal",
  "handleLoadQuotation",
]);
</script>

<template>
  <div>
    <div v-if="isLoadingInitialOrder">
      <p>Cargando sesión de orden...</p>
    </div>

    <div v-else-if="hasOpenOrder">
      <OpenOrderCard
        :search-query="barcodeSearchQuery"
        @update:search-query="$emit('update:barcodeSearchQuery', $event)"
        :order-products="orderItems || []"
        :order="openOrderData || null"
        :order-reserved="reservedOrderData || null"
        :total-products-amount="totalProductsAmount || 0"
        :total-iva-amount="totalIvaAmount || 0"
        :total-order-amount="totalOrderAmount || 0"
        :company-discount-total="totalCompanyDiscountAmount || 0"
        :doctor-discount-total="totalDoctorDiscountAmount || 0"
        :recipe-discount-total="totalRecipeDiscountAmount || 0"
        :expiration-discount-total="totalExpirationDiscountAmount || 0"
        :cliente="selectedClient || null"
        :exchange-rates="exchangeRates"
        :selected-display-currency="selectedDisplayCurrency || 'USD'"
        @currency-changed="$emit('handleCurrencyChanged', $event)"
        @update-quantity="$emit('updateOrderItemQuantity', $event)"
        @remove-item="$emit('removeOrderItem', $event)"
        @cancelar-order="$emit('cancelarOrder')"
        @reserve-order="$emit('reserverOrder')"
        @open-buys-modal="$emit('openBuysModal')"
        @add-quotation-products="$emit('handleAddQuotationProducts', $event)"
        @add-reserved-order="$emit('addReserverOrder')"
        :selected-discount-type="selectedDiscountType"
        @update:selected-discount-type="$emit('update:selectedDiscountType', $event)"
        :active-doctor-offers="activeDoctorOffers || []"
        :prescription-discount-percentage="currentPrescriptionDiscountPercentage || 0"
        :active-company-offers="activeCompanyOffers || []"
        @doctor-discount-selected="$emit('handleDoctorDiscountSelected', $event)"
        @prescription-file-selected="$emit('handlePrescriptionFileSelected', $event)"
        @company-discount-selected="$emit('handleCompanyDiscountSelected', $event)"
        :global-discount="currentGlobalDiscountDetails || null"
        @add-pack="$emit('handleAddPackToOrder', $event)"
        @edit-cliente="$emit('handleEditCliente', $event)"
        @add-note="$emit('handleSaveOrderItemNote', $event)"
        :is-special-taxpayer="isSpecialTaxpayer || false"
        :is-restaurant="isRestaurant"
        :is-sports-rental="isSportsRental"
        @flash-checkout="$emit('handleFlashCheckout', $event)"
      />
    </div>
    <div v-else-if="!isSimpleTpv">
      <OrderClienteCard
        :model-value="clientIdentification"
        @update:model-value="$emit('update:clientIdentification', $event)"
        :buttons-icon-only="true"
        :show-quotation-input="true"
        :show-reserved-button="!isRestaurant"
        @verify-client="$emit('verifyClient', $event)"
        @identify-and-start="$emit('handleIdentifyAndStart', $event)"
        @reserved-order-cliente="$emit('openPedidosModal')"
        @load-quotation="$emit('handleLoadQuotation', $event)"
      />
    </div>
  </div>
</template>
