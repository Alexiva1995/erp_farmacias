<script setup>
import PackTable from "@/components/PackTable.vue";

const pack = ref([]);
const totalPack = ref(0);
const loadingPack = ref(false);
const pagePack = ref(1);
const itemsPerPagePack = ref(10);
const sortByPack = ref();
const orderByPack = ref();


const updateTableOptionsPack = (options) => {
  pagePack.value = options.page;
  itemsPerPagePack.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortByPack.value = options.sortBy[0].key;
    orderByPack.value = options.sortBy[0].order;
  } else {
    sortByPack.value = null;
    orderByPack.value = null;
  }
};

</script>
<template>
<div>
  <VCard title="Pack">
      <div class="mb-2"></div>
      <PackTable
        :packs="packs"
        :loading="loadingPack"
        :total-Packs="totalPacks"
        :items-per-page="itemsPerPagePack"
        :page="pagePack"
        @update:options="updateTableOptionsPack"
      />
    </VCard>
</div>
</template>
