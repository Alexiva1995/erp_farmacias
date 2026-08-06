<script setup>
import OrderFilters from "@/components/OrderFilters.vue";
import OrderProductsTable from "@/components/OrderProductsTable.vue";

defineProps({
  filterSearchQuery: String,
  selectedLaboratory: [Number, String],
  selectedOrigin: [Number, String],
  selectedCategory: [Number, String],
  stockStatusFilter: Boolean,
  isStrictSearch: Boolean,
  laboratories: Array,
  origins: Array,
  categories: Array,
  isRestaurant: Boolean,
  isLoadingFilters: Boolean,
  sortBy: String,
  orderBy: String,
  products: Array,
  loading: Boolean,
  totalProduct: Number,
  itemsPerPage: Number,
  page: Number,
  discountMinProducts: Number,
  discountMaxProducts: Number,
  discount: Number,
  orderItems: Array,
  tableOptions: Object,
  exchangeRates: Object,
  selectedDisplayCurrency: String,
});

defineEmits([
  "update:filterSearchQuery",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "update:selectedCategory",
  "update:stockStatusFilter",
  "update:isStrictSearch",
  "handleClearFilters",
  "handleClearSortOrder",
  "handleExternalSort",
  "handleBackFromGroupView",
  "update:itemsPerPage",
  "update:page",
  "updateTableOptions",
  "addProductToOrder",
  "handleAddDishToOrder",
  "fetchGroupProducts",
  "fetchFailuresProducts",
  "handleViewPackDetails",
  "handleAddPackToOrder",
]);
</script>

<template>
  <div>
    <OrderFilters
      :search-query="filterSearchQuery"
      @update:search-query="$emit('update:filterSearchQuery', $event)"
      :selected-laboratory="selectedLaboratory"
      @update:selected-laboratory="$emit('update:selectedLaboratory', $event)"
      :selected-origin="selectedOrigin"
      @update:selected-origin="$emit('update:selectedOrigin', $event)"
      :selected-category="selectedCategory"
      @update:selected-category="$emit('update:selectedCategory', $event)"
      :stock-status-filter="stockStatusFilter"
      @update:stock-status-filter="$emit('update:stockStatusFilter', $event)"
      :is-strict-search="isStrictSearch"
      @update:is-strict-search="$emit('update:isStrictSearch', $event)"
      :laboratories="laboratories || []"
      :origins="origins || []"
      :categories="categories || []"
      :is-restaurant="isRestaurant || false"
      :loading="isLoadingFilters || false"
      :sort-by="sortBy"
      :order-by="orderBy"
      @clear="$emit('handleClearFilters')"
      @clear-sort="$emit('handleClearSortOrder')"
      @sort="$emit('handleExternalSort', $event)"
      @back="$emit('handleBackFromGroupView')"
    />

    <OrderProductsTable
      :products="products || []"
      :loading="loading"
      :total-product="totalProduct || 0"
      :items-per-page="itemsPerPage"
      @update:items-per-page="$emit('update:itemsPerPage', $event)"
      :page="page"
      @update:page="$emit('update:page', $event)"
      :discount-min-products="discountMinProducts || 0"
      :discount-max-products="discountMaxProducts || 0"
      :current-discount="discount || 0"
      :order-items="orderItems || []"
      :options="tableOptions"
      :exchange-rates="exchangeRates"
      :currency="selectedDisplayCurrency"
      @update:options="$emit('updateTableOptions', $event)"
      @add-product="$emit('addProductToOrder', $event)"
      @add-dish="$emit('handleAddDishToOrder', $event)"
      @view-group-products="$emit('fetchGroupProducts', $event)"
      @failures-products="$emit('fetchFailuresProducts', $event)"
      @view-pack-details="$emit('handleViewPackDetails', $event)"
      @add-pack="$emit('handleAddPackToOrder', $event)"
    />
  </div>
</template>
