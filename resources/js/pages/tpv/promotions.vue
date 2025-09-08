<script setup>
import PackTable from "@/components/PackTable.vue";
import PacksFilters from "@/components/PacksFilters.vue";
import PackModal from "@/components/dialogs/PackModal.vue";

const pack = ref([]);
const totalPack = ref(0);
const loadingPack = ref(false);
const pagePack = ref(1);
const itemsPerPagePack = ref(10);
const sortByPack = ref();
const orderByPack = ref();

const addPackModal = ref(false);

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


const handleClearFiltersPacks = () => {
  filterSearchQueryIdPacks.value = "";
  filterSearchQueryPacks.value = "";
  sortByPack.value = undefined;
  sortByPack.value = undefined;
};

const handleAddPackModal = () => {
    addPackModal.value = true;
}

const closePackModal = () => {
  addPackModal.value = false;
};
</script>
<template>
<div>

    <PacksFilters
      v-model:idSearchQuery="filterSearchQueryIdPacks"
      v-model:searchQuery="filterSearchQueryPacks"
      @clear="handleClearFiltersPacks"
      @add-pack="handleAddPackModal"
    ></PacksFilters>

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

    <PackModal
      v-model:is-dialog-visible="addPackModal"
      :pack-data="packData"
      @modal-closed="closePackModal"
    />
</div>
</template>
