<script setup>
import ProductTable from "@/components/ProductTable.vue";
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";

const products = ref([]);
const totalProduct = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};
</script>
<template>
  <div>
    <VRow class='mb-4'>
      <VCol cols="12" sm="12" md="6">
        <QuotationCard />
      </VCol>
    <VCol cols="12" sm="12" md="6">
        <QuotationProducts />
      </VCol>
    </VRow>

    <QuotationTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @edit-product="handleEditProduct"
    />
  </div>
</template>
